<?php

namespace Somms\BV2Observation\Provider\IUCN;

use Somms\BV2Observation\Data\RemoteSpecies;
use GuzzleHttp\Client;

class IUCNSpecies extends RemoteSpecies
{
    protected Client $http;
    protected IUCNSearchScrapper $scraper;
    protected string $apiBase = 'https://api.iucnredlist.org/api/v4/';
    protected ?string $apiToken = null;

    public function __construct(string $scientificName, string $commonName, string $author = '', array $options = [])
    {
        parent::__construct($scientificName, $commonName, $author);

        $this->http = new Client([
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'TaxaImporter/1.0 (+https://github.com/somms/TaxaImporter)'
            ]
        ]);

        // Initialize scraper with FlareSolverr and Playwright configuration if provided
        $flareSolverrUrl = $options['flaresolverr_url'] ?? null;
        $useFlareSolverr = $options['use_flaresolverr'] ?? false; // Disabled by default, prefer Playwright
        $playwrightUrl = $options['playwright_url'] ?? null;
        $usePlaywright = $options['use_playwright'] ?? true; // Enabled by default

        $this->scraper = new IUCNSearchScrapper(
            null,
            $flareSolverrUrl,
            $useFlareSolverr,
            $playwrightUrl,
            $usePlaywright
        );

        // API token can be set via environment variable or via setApiToken method
        $this->apiToken = getenv('IUCN_API_TOKEN') ?: null;
    }

    /**
     * Set the API token for IUCN Red List API
     */
    public function setApiToken(string $token): void
    {
        $this->apiToken = $token;
    }

    /**
     * Queries the IUCN Red List using API first, then scraper as fallback.
     *
     * Workflow:
     * 1. Try API v4 directly by species name (without author)
     * 2. If API fails, use scraper to search (handles synonyms)
     * 3. If scraper finds a taxonid, query API for full details
     * 4. Return combined data
     */
    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        // Step 1: Try API v4 directly by species name (most reliable)
        if ($this->apiToken) {
            $apiData = $this->queryApiBySpeciesName($speciesName);
            if ($apiData) {
                error_log("IUCN: Found via API v4 direct lookup: $speciesName");
                return $apiData;
            }
        }

        // Step 2: Fallback to scraper (handles synonyms but requires Cloudflare bypass)
        $query = trim($speciesName . ' ' . $author);
        $searchResults = $this->scraper->search($query);

        if (empty($searchResults)) {
            error_log("IUCN: No results found for: $query");
            return false;
        }

        // Get the first (best) match
        $bestMatch = $searchResults[0];

        // Step 3: If we have a taxonid, query the API for full details
        if (!empty($bestMatch['taxonid']) && $this->apiToken) {
            $apiData = $this->queryApiByTaxonId($bestMatch['taxonid']);

            if ($apiData) {
                // Merge scraper data with API data
                $apiData['scraped_label'] = $bestMatch['label'] ?? '';
                $apiData['scraped_link'] = $bestMatch['link'] ?? '';

                // Mark if this was a synonym search
                if (isset($bestMatch['scientific_name']) && stripos($bestMatch['scientific_name'], $speciesName) === false) {
                    $apiData['synonym'] = true;
                }

                return $apiData;
            }
        }

        // Step 4: Fallback to scraper data only
        // Transform scraper result to expected format
        return [
            'scientific_name' => $bestMatch['scientific_name'] ?? $bestMatch['label'] ?? '',
            'taxonid' => $bestMatch['taxonid'] ?? null,
            'category' => $bestMatch['category'] ?? null,
            'main_common_name' => $bestMatch['main_common_name'] ?? $bestMatch['common_name'] ?? null,
            'authority' => $bestMatch['authority'] ?? '',
            'link' => $bestMatch['link'] ?? '',
            'synonym' => isset($bestMatch['scientific_name']) && stripos($bestMatch['scientific_name'], $speciesName) === false
        ];
    }

    /**
     * Query IUCN API v4 by species name to get species details
     * This uses the /species/{name} endpoint which accepts exact scientific names
     */
    protected function queryApiBySpeciesName(string $speciesName): ?array
    {
        if (!$this->apiToken) {
            return null;
        }

        try {
            // API v4 endpoint: /species/{name}
            // Note: This requires exact scientific name match (no author)
            $url = $this->apiBase . 'species/' . rawurlencode($speciesName);
            $resp = $this->http->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken
                ],
                'http_errors' => false // Don't throw on 404
            ]);

            if ($resp->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode($resp->getBody()->getContents(), true);

            // API v4 returns data in 'result' array
            if (isset($data['result']) && is_array($data['result']) && !empty($data['result'])) {
                return $data['result'][0]; // Return first result
            }

            return null;
        } catch (\Throwable $e) {
            error_log("IUCN API v4 species lookup failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Query IUCN API v4 by taxon ID to get full species details
     */
    protected function queryApiByTaxonId(int $taxonid): ?array
    {
        if (!$this->apiToken) {
            return null;
        }

        try {
            // API v4 endpoint: /taxonredirect/{taxonid}
            $url = $this->apiBase . 'taxonredirect/' . $taxonid;
            $resp = $this->http->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiToken
                ]
            ]);

            $data = json_decode($resp->getBody()->getContents(), true);

            // API v4 returns data directly in the response
            if (isset($data) && is_array($data)) {
                return $data;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validates if the remote match is acceptable
     */
    protected function validMatch(): bool
    {
        // Strip common suffixes for comparison
        $stripPatterns = [' indet.', ' spec.', ' sp.', ' spp.'];

        $cleanOriginal = str_replace($stripPatterns, '', $this->scientificName);
        $cleanRemote = str_replace($stripPatterns, '', $this->remoteScientificName);

        // Exact match
        if ($cleanOriginal === $cleanRemote) {
            return true;
        }

        // Case-insensitive match
        if (strcasecmp($cleanOriginal, $cleanRemote) === 0) {
            return true;
        }

        return false;
    }
}
