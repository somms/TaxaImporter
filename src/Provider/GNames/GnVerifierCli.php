<?php

namespace Somms\BV2Observation\Provider\GNames;

/**
 * Thin wrapper around the gnverifier CLI (compact JSON output).
 */
final class GnVerifierCli
{
    public const DATASOURCE_GBIF = '11';
    public const DATASOURCE_WFO = '196';

    public function __construct(
        private string $binaryPath = 'vendor/bin/gnverifier',
    ) {
    }

    /**
     * @return array<string, mixed>|null First result row, or null when no match.
     */
    public function verifyCompact(string $input, string $dataSourceId = self::DATASOURCE_GBIF): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        $escapedInput = escapeshellarg($input);
        $source = escapeshellarg($dataSourceId);
        $command = "{$this->binaryPath} -q -M -s {$source} -f compact {$escapedInput}";

        $output = [];
        $retval = null;
        $response = exec($command, $output, $retval);
        if ($retval !== 0 || $response === false || $response === '') {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        $payload = $decoded[0] ?? $decoded;
        if (!isset($payload['results'][0]) || !is_array($payload['results'][0])) {
            return null;
        }

        return $payload['results'][0];
    }
}
