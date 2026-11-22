<?php

namespace Somms\BV2Observation\Provider\IUCN;

use GuzzleHttp\Client;
use duzun\hQuery;

class IUCNSearchScrapper
{
    protected Client $http;
    protected string $baseSearchUrl = 'https://www.iucnredlist.org/search?searchType=species&query=';
    protected string $flareSolverrUrl = 'http://localhost:8191/v1';
    protected bool $useFlareSolverr = true;
    protected ?PlaywrightClient $playwright = null;
    protected bool $usePlaywright = false;

    public function __construct(?Client $client = null, ?string $flareSolverrUrl = null, bool $useFlareSolverr = true, ?string $playwrightUrl = null, bool $usePlaywright = false)
    {
        $this->http = $client ?? new Client([
            'timeout' => 60, // Increased timeout for FlareSolverr
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Accept-Encoding' => 'gzip, deflate, br'
            ],
            'verify' => false,
            'allow_redirects' => true
        ]);

        if ($flareSolverrUrl !== null) {
            $this->flareSolverrUrl = $flareSolverrUrl;
        }
        $this->useFlareSolverr = $useFlareSolverr;

        // Initialize Playwright client if enabled
        $this->usePlaywright = $usePlaywright;
        if ($usePlaywright) {
            $this->playwright = new PlaywrightClient($playwrightUrl, true);
        }
    }

    /**
     * Search the IUCN Red List frontend (HTML) for species candidates.
     * This handles both direct matches and synonyms.
     */
    public function search(string $query): array
    {
        // Try the internal API first (faster and more reliable)
        try {
            $results = $this->searchViaInternalApi($query);
            if (!empty($results)) {
                return $results;
            }
        } catch (\Throwable $e) {
            error_log("IUCN Internal API search failed: " . $e->getMessage());
        }

        // Fallback to HTML scraping if API fails
        $url = $this->baseSearchUrl . rawurlencode($query);

        try {
            // Priority: Playwright > FlareSolverr > Direct HTTP
            if ($this->usePlaywright && $this->playwright) {
                error_log("IUCN: Using Playwright to fetch $url");
                $html = $this->playwright->fetch($url, [
                    'waitForSelector' => '#redlist-js',
                    'waitTimeout' => 10000
                ]);
            } elseif ($this->useFlareSolverr) {
                error_log("IUCN: Using FlareSolverr to fetch $url");
                $html = $this->fetchViaFlareSolverr($url);
            } else {
                error_log("IUCN: Using direct HTTP to fetch $url");
                $response = $this->http->get($url);
                $html = (string) $response->getBody();
            }
        } catch (\Throwable $e) {
            // Log error if needed
            error_log("IUCN Search error: " . $e->getMessage());
            return [];
        }

        return $this->parseHtmlResults($html, $query);
    }

    /**
     * Search using IUCN's internal API endpoint
     *
     * Note: The IUCN website uses a React SPA that loads data dynamically.
     * We need to find the actual API endpoint they use, or use a headless browser.
     * For now, this method is disabled and we rely on the official API v4.
     */
    protected function searchViaInternalApi(string $query): array
    {
        // The internal API endpoints are not publicly documented and may require
        // authentication or use different formats. Disabling for now.
        //
        // The best approach is to:
        // 1. Use the official API v4 (already implemented in IUCNSpecies)
        // 2. Or use a headless browser like Playwright to wait for JS to load

        error_log("IUCN: Internal API search is disabled. Using official API v4 instead.");
        return [];
    }

    /**
     * Parse results from the internal API
     */
    protected function parseApiResults(array $results, string $originalQuery): array
    {
        $parsed = [];

        foreach ($results as $result) {
            if (!isset($result['scientific_name'])) {
                continue;
            }

            $parsed[] = [
                'scientific_name' => $result['scientific_name'] ?? '',
                'taxonid' => $result['taxonid'] ?? '',
                'authority' => $result['authority'] ?? '',
                'category' => $result['category'] ?? '',
                'main_common_name' => $result['main_common_name'] ?? '',
                'is_synonym' => false, // API v3 returns accepted names
                'match_type' => 'api'
            ];
        }

        return $parsed;
    }

    /**
     * Fetch URL via FlareSolverr to bypass Cloudflare protection
     *
     * Note: IUCN website uses a SPA (Single Page Application) that loads search results
     * dynamically via JavaScript. FlareSolverr needs to wait for the content to load.
     */
    protected function fetchViaFlareSolverr(string $url): string
    {
        $payload = [
            'cmd' => 'request.get',
            'url' => $url,
            'maxTimeout' => 60000,
            // Wait for the dynamic content to load
            'postData' => null,
            'returnOnlyCookies' => false
        ];

        $response = $this->http->post($this->flareSolverrUrl, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        $result = json_decode((string) $response->getBody(), true);

        if (!isset($result['solution']['response'])) {
            throw new \RuntimeException('FlareSolverr did not return a valid response');
        }

        return $result['solution']['response'];
    }

    /**
     * Parse IUCN search HTML using hQuery
     * The IUCN website structure typically shows:
     * - Species cards with scientific name, common name, category, and taxonid
     * - Synonym information if the search term is a synonym
     */
    protected function parseHtmlResults(string $html, string $originalQuery): array
    {
        // Debug: Save HTML to file for inspection
        $debugFile = sys_get_temp_dir() . '/iucn_search_debug_' . time() . '.html';
        file_put_contents($debugFile, $html);
        error_log("IUCN HTML saved to: $debugFile");

        $doc = hQuery::fromHTML($html);

        if (!$doc) {
            error_log("IUCN: Failed to parse HTML with hQuery");
            return [];
        }

        $results = [];

        // Look for species result cards - IUCN uses various selectors
        // Try multiple selectors to be robust
        $cards = $doc->find('.species-result, .result-item, article.species, div[data-taxonid]');

        error_log("IUCN: Found " . ($cards ? $cards->length : 0) . " cards with initial selectors");

        if ($cards && $cards->length > 0) {
            foreach ($cards as $card) {
                $result = $this->parseSpeciesCard($card);
                if ($result) {
                    $results[] = $result;
                }
            }
        }

        // Fallback: look for species links if no cards found
        if (empty($results)) {
            error_log("IUCN: No cards found, trying species links fallback");
            $results = $this->parseSpeciesLinks($doc);
            error_log("IUCN: Found " . count($results) . " results from links");
        }

        // Sort results: exact matches first, then by relevance
        usort($results, function($a, $b) use ($originalQuery) {
            $aName = $a['scientific_name'] ?? $a['label'];
            $bName = $b['scientific_name'] ?? $b['label'];

            // Exact match gets priority
            $aExact = strcasecmp($aName, $originalQuery) === 0 ? 1 : 0;
            $bExact = strcasecmp($bName, $originalQuery) === 0 ? 1 : 0;

            if ($aExact !== $bExact) {
                return $bExact - $aExact;
            }

            // Then by starts with
            $aStarts = stripos($aName, $originalQuery) === 0 ? 1 : 0;
            $bStarts = stripos($bName, $originalQuery) === 0 ? 1 : 0;

            return $bStarts - $aStarts;
        });

        return $results;
    }

    /**
     * Parse a species card element
     */
    protected function parseSpeciesCard($card): ?array
    {
        $scientificName = '';
        $commonName = '';
        $category = '';
        $taxonid = null;
        $link = '';

        // Try to extract taxonid from data attribute
        $taxonid = $card->attr('data-taxonid');
        if ($taxonid) {
            $taxonid = (int)$taxonid;
        }

        // Find scientific name (usually in a heading or strong tag)
        $nameEl = $card->find('h3, h4, .scientific-name, strong.species-name, a.species-link')->first();
        if ($nameEl) {
            $scientificName = trim($nameEl->text());
            $href = $nameEl->attr('href');
            if ($href) {
                $link = $this->normalizeUrl($href);
                if (!$taxonid) {
                    $taxonid = $this->extractTaxonId($link);
                }
            }
        }

        // Find common name
        $commonEl = $card->find('.common-name, .vernacular-name, span.common')->first();
        if ($commonEl) {
            $commonName = trim($commonEl->text());
        }

        // Find Red List category
        $categoryEl = $card->find('.category, .rl-category, span.status, .assessment-status')->first();
        if ($categoryEl) {
            $category = trim($categoryEl->text());
        }

        // Extract authority (author)
        $authority = '';
        $authorEl = $card->find('.authority, .author, .species-authority')->first();
        if ($authorEl) {
            $authority = trim($authorEl->text());
        }

        // If we still don't have a link, look for any species link
        if (!$link) {
            $linkEl = $card->find('a[href*="/species/"], a[href*="/taxon/"]')->first();
            if ($linkEl) {
                $link = $this->normalizeUrl($linkEl->attr('href'));
                if (!$taxonid) {
                    $taxonid = $this->extractTaxonId($link);
                }
            }
        }

        // Must have at least scientific name and taxonid
        if (!$scientificName || !$taxonid) {
            return null;
        }

        return [
            'scientific_name' => $scientificName,
            'label' => $scientificName,
            'common_name' => $commonName,
            'category' => $category,
            'authority' => $authority,
            'taxonid' => $taxonid,
            'link' => $link
        ];
    }

    /**
     * Fallback: parse species links from the page
     */
    protected function parseSpeciesLinks($doc): array
    {
        $results = [];
        $links = $doc->find('a[href*="/species/"], a[href*="/taxon/"]');

        $seen = [];

        if (!$links) {
            return $results;
        }

        foreach ($links as $a) {
            $href = $a->attr('href');
            if (!$href) continue;

            $link = $this->normalizeUrl($href);
            $taxonid = $this->extractTaxonId($link);

            if (!$taxonid || isset($seen[$taxonid])) {
                continue;
            }

            $seen[$taxonid] = true;

            $label = trim($a->text());
            if (!$label || strlen($label) < 3) {
                continue;
            }

            // Try to find category near the link
            $category = '';
            $parent = $a->parent();
            if ($parent) {
                $catEl = $parent->find('.category, .rl-category, span.status')->first();
                if ($catEl) {
                    $category = trim($catEl->text());
                }
            }

            $results[] = [
                'scientific_name' => $label,
                'label' => $label,
                'common_name' => '',
                'category' => $category,
                'authority' => '',
                'taxonid' => $taxonid,
                'link' => $link
            ];
        }

        return $results;
    }

    /**
     * Normalize URL to absolute
     */
    protected function normalizeUrl(string $href): string
    {
        if (strpos($href, 'http') === 0) {
            return $href;
        }

        if (strpos($href, '/') === 0) {
            return 'https://www.iucnredlist.org' . $href;
        }

        return 'https://www.iucnredlist.org/' . $href;
    }

    /**
     * Extract taxonid from URL patterns
     */
    protected function extractTaxonId(string $url): ?int
    {
        if (preg_match('#/species/(\d+)#', $url, $m)) {
            return (int)$m[1];
        }
        if (preg_match('#/taxon/(\d+)#', $url, $m)) {
            return (int)$m[1];
        }

        // Query param fallback
        $parts = parse_url($url);
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (!empty($qs['taxon'])) {
                return (int)$qs['taxon'];
            }
            if (!empty($qs['taxonid'])) {
                return (int)$qs['taxonid'];
            }
        }
        return null;
    }
}
