<?php

namespace Somms\BV2Observation\Provider\WFO;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/**
 * Client for https://list.worldfloraonline.org/matching_rest.php
 */
final class WfoMatchClient
{
    public const DEFAULT_BASE_URL = 'https://list.worldfloraonline.org/matching_rest.php';

    public function __construct(
        private ?ClientInterface $http = null,
        private string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
    }

    /**
     * @param array{
     *   fuzzy_names?: int,
     *   fuzzy_authors?: int,
     *   check_homonyms?: bool,
     *   check_rank?: bool,
     *   accept_single_candidate?: bool
     * } $options
     *
     * @return array<string, mixed>|null Decoded JSON body, or null on transport/parse failure.
     */
    public function match(string $inputString, array $options = []): ?array
    {
        $inputString = trim($inputString);
        if ($inputString === '') {
            return null;
        }

        $query = ['input_string' => $inputString];

        if (isset($options['fuzzy_names']) && (int) $options['fuzzy_names'] > 0) {
            $query['fuzzy_names'] = (int) $options['fuzzy_names'];
        }
        if (isset($options['fuzzy_authors']) && (int) $options['fuzzy_authors'] > 0) {
            $query['fuzzy_authors'] = (int) $options['fuzzy_authors'];
        }
        foreach (['check_homonyms', 'check_rank', 'accept_single_candidate'] as $flag) {
            if (!empty($options[$flag])) {
                $query[$flag] = 'true';
            }
        }

        if ($this->http !== null) {
            return $this->requestWithClient($this->http, $query);
        }

        $verify = WfoHttpConfig::tlsVerifyOption();
        try {
            return $this->requestWithClient(
                new Client(WfoHttpConfig::guzzleClientOptionsForVerify($verify)),
                $query,
            );
        } catch (\Throwable $e) {
            if (
                WfoHttpConfig::sslFallbackEnabled()
                && $verify !== false
                && WfoHttpConfig::isTlsError($e)
            ) {
                try {
                    return $this->requestWithClient(
                        new Client(WfoHttpConfig::guzzleClientOptionsForVerify(false)),
                        $query,
                    );
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }
    }

    /**
     * @param array<string, string|int> $query
     *
     * @return array<string, mixed>|null
     */
    private function requestWithClient(ClientInterface $client, array $query): ?array
    {
        try {
            $response = $client->request('GET', $this->baseUrl, ['query' => $query]);
            $body = (string) $response->getBody();
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            // Re-throw TLS errors so match() can attempt SSL fallback; swallow timeouts/etc.
            if (WfoHttpConfig::isTlsError($e)) {
                throw $e;
            }

            return null;
        }
    }

    /**
     * @param array<string, mixed> $response Full taxonNameMatch-style payload from {@see match()}.
     *
     * @return array<string, mixed>|null The unambiguous match object, if any.
     */
    public static function extractMatch(array $response): ?array
    {
        if (!empty($response['error'])) {
            return null;
        }
        if (!isset($response['match']) || !is_array($response['match'])) {
            return null;
        }

        return $response['match'];
    }
}
