<?php

namespace Somms\BV2Observation\Util;

use Somms\BV2Observation\Parser\GNames\GNParser\LocaBinGNSpeciesParser;
use Somms\BV2Observation\Provider\GNames\GnVerifierCli;
use Somms\BV2Observation\Provider\GNames\GNSpeciesParser;
use Somms\BV2Observation\Provider\WFO\WfoMatchClient;
use Somms\BV2Observation\Provider\WFO\WFOSpecies;
use Somms\BV2Observation\Provider\WFO\WFOSpeciesParser;

final class WfoDualMatchReport
{
    /**
     * @param list<string> $taxa
     * @param resource     $csvStream
     */
    public static function writeCsv(
        array $taxa,
        $csvStream,
        array $restOptions = [],
        float $rateLimitSeconds = 0.05,
    ): void {
        fputcsv($csvStream, [
            'input_name',
            'gn_matched',
            'gn_matched_name',
            'gn_matched_name_id',
            'rest_matched',
            'rest_wfo_id',
            'rest_full_name_plain',
            'status',
            'notes',
        ]);

        $gnVerifier = new GnVerifierCli();
        $gnParser = new GNSpeciesParser();
        $restParser = new WFOSpeciesParser();
        $restOptions = array_merge([
            'fuzzy_names' => 2,
            'fuzzy_authors' => 1,
            'accept_single_candidate' => true,
            'rate_limit_seconds' => $rateLimitSeconds,
        ], $restOptions);

        foreach ($taxa as $inputName) {
            $gnMatched = false;
            $gnName = '';
            $gnId = '';

            $nameParser = new LocaBinGNSpeciesParser();
            $nameParser->setInput($inputName);
            $parsed = $nameParser->getSpecies();
            $gnInput = trim($parsed->getScientificName() . ' ' . $parsed->getAuthor());
            $gnRow = $gnVerifier->verifyCompact($gnInput, GnVerifierCli::DATASOURCE_WFO);
            if ($gnRow !== null) {
                $gnParser->setInput($gnRow);
                $gnSpecies = $gnParser->getSpecies();
                $gnMatched = true;
                $gnName = trim($gnSpecies->getScientificName() . ' ' . $gnSpecies->getAuthor());
                $gnId = (string) ($gnRow['matchedNameID'] ?? '');
                if ($gnId === '' && method_exists($gnSpecies, 'getRemoteId')) {
                    $gnId = $gnSpecies->getRemoteId();
                }
            }

            $restMatched = false;
            $restWfoId = '';
            $restPlain = '';
            $remote = new WFOSpecies($inputName, '', '');
            if ($remote->populateFromRemote($restParser, $restOptions)) {
                $restMatched = true;
                $restWfoId = $remote->getRemoteId();
                $restPlain = trim($remote->getRemoteScientificName() . ' ' . $remote->getRemoteAuthor());
            }

            $status = self::classifyStatus($gnMatched, $restMatched, $gnId, $restWfoId, $gnName, $restPlain);
            $notes = self::buildNotes($gnMatched, $restMatched, $gnId, $restWfoId);

            fputcsv($csvStream, [
                $inputName,
                $gnMatched ? '1' : '0',
                $gnName,
                $gnId,
                $restMatched ? '1' : '0',
                $restWfoId,
                $restPlain,
                $status,
                $notes,
            ]);
        }
    }

    private static function classifyStatus(
        bool $gnMatched,
        bool $restMatched,
        string $gnId,
        string $restWfoId,
        string $gnName,
        string $restPlain,
    ): string {
        if (!$gnMatched && !$restMatched) {
            return 'neither';
        }
        if ($gnMatched && !$restMatched) {
            return 'gn_only';
        }
        if (!$gnMatched && $restMatched) {
            return 'rest_only';
        }
        if ($gnId !== '' && $restWfoId !== '' && $gnId === $restWfoId) {
            return 'agree_id';
        }
        if (self::normalizeName($gnName) === self::normalizeName($restPlain)) {
            return 'agree_name';
        }

        return 'disagree';
    }

    private static function buildNotes(
        bool $gnMatched,
        bool $restMatched,
        string $gnId,
        string $restWfoId,
    ): string {
        if ($gnMatched && $restMatched && $gnId !== '' && $restWfoId !== '' && $gnId !== $restWfoId) {
            return 'different_wfo_ids';
        }

        return '';
    }

    private static function normalizeName(string $name): string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
        $name = str_replace([' indet.', ' spec.'], '', $name);

        return $name;
    }
}
