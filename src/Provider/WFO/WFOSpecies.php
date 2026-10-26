<?php

namespace Somms\BV2Observation\Provider\WFO;

use Somms\BV2Observation\Data\RemoteSpecies;

class WFOSpecies extends RemoteSpecies
{
    private static int $nextAllowedRequestTime = 0;

    private static ?WfoMatchClient $sharedClient = null;

    public static function setMatchClient(?WfoMatchClient $client): void
    {
        self::$sharedClient = $client;
    }

    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        $input = trim($speciesName . ' ' . $author);
        $client = self::$sharedClient ?? new WfoMatchClient();

        $this->throttle($options);

        $response = $client->match($input, $this->normalizeMatchOptions($options));
        if ($response === null) {
            return false;
        }

        $match = WfoMatchClient::extractMatch($response);
        if ($match === null) {
            return false;
        }

        $match['synonym'] = $this->looksLikeSynonym($input, $match);
        $match['_wfo_response'] = $response;

        return $match;
    }

    protected function validMatch(): bool
    {
        return $this->remoteId !== '';
    }

    /**
     * @param array<string, mixed> $options
     */
    private function normalizeMatchOptions(array $options): array
    {
        $defaults = [
            'fuzzy_names' => 2,
            'fuzzy_authors' => 1,
            'accept_single_candidate' => true,
            'check_homonyms' => false,
            'check_rank' => false,
        ];

        return array_merge($defaults, array_intersect_key($options, $defaults));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function throttle(array $options): void
    {
        $delay = isset($options['rate_limit_seconds']) ? (float) $options['rate_limit_seconds'] : 0.0;
        if ($delay <= 0) {
            return;
        }

        $now = microtime(true);
        if (self::$nextAllowedRequestTime > $now) {
            usleep((int) ((self::$nextAllowedRequestTime - $now) * 1_000_000));
        }
        self::$nextAllowedRequestTime = microtime(true) + $delay;
    }

    /**
     * @param array<string, mixed> $match
     */
    private function looksLikeSynonym(string $input, array $match): bool
    {
        $matchedPlain = strtolower(trim((string) ($match['full_name_plain'] ?? '')));
        $inputNorm = strtolower(preg_replace('/\s+/', ' ', $input) ?? $input);

        return $matchedPlain !== '' && $matchedPlain !== $inputNorm;
    }
}
