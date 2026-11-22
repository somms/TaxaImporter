<?php

namespace Somms\BV2Observation\Provider\IUCN;

use GuzzleHttp\Client;

/**
 * Simple client to interact with Playwright Server via WebSocket/HTTP
 * This allows us to fetch JavaScript-rendered pages
 *
 * The Playwright server exposes a CDP (Chrome DevTools Protocol) endpoint
 * that we can use to control the browser and fetch rendered HTML.
 */
class PlaywrightClient
{
    protected Client $http;
    protected string $playwrightUrl;
    protected bool $enabled;

    public function __construct(?string $playwrightUrl = null, bool $enabled = true)
    {
        $this->playwrightUrl = $playwrightUrl ?? 'http://localhost:3000';
        $this->enabled = $enabled;

        $this->http = new Client([
            'timeout' => 60,
            'verify' => false,
            'http_errors' => false
        ]);
    }

    /**
     * Fetch a URL using Playwright Server (waits for JavaScript to load)
     *
     * This connects to the Playwright HTTP server (playwright-server.js)
     * which uses Playwright to render JavaScript-heavy pages.
     *
     * @param string $url The URL to fetch
     * @param array $options Additional options (waitForSelector, waitTimeout)
     * @return string The rendered HTML
     * @throws \RuntimeException If Playwright is not available or fails
     */
    public function fetch(string $url, array $options = []): string
    {
        if (!$this->enabled) {
            throw new \RuntimeException('Playwright client is disabled');
        }

        // Check if server is running
        if (!$this->isServerRunning()) {
            throw new \RuntimeException(
                'Playwright server is not running on ' . $this->playwrightUrl . "\n" .
                'Start it with: node bin/playwright-server.js'
            );
        }

        $waitForSelector = $options['waitForSelector'] ?? '#redlist-js';
        $waitTimeout = $options['waitTimeout'] ?? 10000;

        try {
            error_log("Playwright: Fetching $url");

            // Send request to our Playwright HTTP server
            $response = $this->http->post($this->playwrightUrl . '/render', [
                'json' => [
                    'url' => $url,
                    'waitForSelector' => $waitForSelector,
                    'waitTimeout' => $waitTimeout
                ],
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 60
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Playwright server returned status: ' . $response->getStatusCode());
            }

            $result = json_decode((string) $response->getBody(), true);

            if (isset($result['success']) && $result['success'] === false) {
                throw new \RuntimeException('Playwright error: ' . ($result['error'] ?? 'Unknown error'));
            }

            if (isset($result['html'])) {
                error_log("Playwright: Successfully fetched " . strlen($result['html']) . " bytes");
                return $result['html'];
            }

            throw new \RuntimeException('Unexpected response from Playwright server');
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new \RuntimeException('Failed to communicate with Playwright server: ' . $e->getMessage());
        }
    }

    /**
     * Check if Playwright server is running
     */
    protected function isServerRunning(): bool
    {
        try {
            $response = $this->http->get($this->playwrightUrl . '/health', [
                'timeout' => 2
            ]);

            if ($response->getStatusCode() === 200) {
                $result = json_decode((string) $response->getBody(), true);
                return isset($result['status']) && $result['status'] === 'ok';
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
