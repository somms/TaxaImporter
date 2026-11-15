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
     * @return array<string, mixed>|null Best result row, or null when no match.
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
        $results = $payload['results'] ?? null;
        if (!is_array($results) || $results === []) {
            return null;
        }

        return $this->selectBestResult($results);
    }

    /**
     * Prefer a placed name (with classificationRanks) over bare-name stubs.
     *
     * GNVerifier / WFO often returns the bare-name record first; that row lacks
     * classificationRanks and would make GNSpeciesParser append " spec." wrongly.
     *
     * @param list<mixed> $results
     *
     * @return array<string, mixed>|null
     */
    public function selectBestResult(array $results): ?array
    {
        $candidates = [];
        foreach ($results as $row) {
            if (is_array($row)) {
                $candidates[] = $row;
            }
        }
        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $row) {
            $recordId = (string) ($row['recordId'] ?? '');
            $hasRanks = $this->hasClassificationRanks($row);
            $isBare = str_starts_with($recordId, 'bare-name-');
            if ($hasRanks && !$isBare) {
                return $row;
            }
        }

        foreach ($candidates as $row) {
            if ($this->hasClassificationRanks($row)) {
                return $row;
            }
        }

        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hasClassificationRanks(array $row): bool
    {
        return isset($row['classificationRanks'])
            && trim((string) $row['classificationRanks']) !== '';
    }
}
