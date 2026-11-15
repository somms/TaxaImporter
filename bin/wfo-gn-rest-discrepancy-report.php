#!/usr/bin/env php
<?php

/**
 * CSV comparing GNVerifier (WFO DS 196) vs WFO REST match for each input name.
 *
 * Usage:
 *   php bin/wfo-gn-rest-discrepancy-report.php [taxa-file] [output.csv]
 */

use Somms\BV2Observation\Util\WfoDualMatchReport;
use Somms\BV2Observation\Util\WfoTaxonListReader;

require dirname(__DIR__) . '/vendor/autoload.php';

$taxaPath = $argv[1] ?? WfoTaxonListReader::defaultFixturePath();
$outPath = $argv[2] ?? dirname(__DIR__) . '/data/output/wfo_gn_rest_discrepancies.csv';

$taxa = WfoTaxonListReader::read($taxaPath);
if ($taxa === []) {
    fwrite(STDERR, "No taxa in {$taxaPath}\n");
    exit(1);
}

$outDir = dirname($outPath);
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$fp = fopen($outPath, 'w');
if ($fp === false) {
    fwrite(STDERR, "Cannot write {$outPath}\n");
    exit(1);
}

$rateLimit = (float) (getenv('TAXAIMPORTER_WFO_RATE_LIMIT') ?: '0.05');
$t0 = microtime(true);
WfoDualMatchReport::writeCsv($taxa, $fp, [], $rateLimit);
fclose($fp);
$elapsed = microtime(true) - $t0;

$counts = [
    'agree_id' => 0,
    'agree_name' => 0,
    'disagree' => 0,
    'gn_only' => 0,
    'rest_only' => 0,
    'neither' => 0,
    'gn_spec_or_indet' => 0,
];
$fh = fopen($outPath, 'r');
if ($fh !== false) {
    $header = fgetcsv($fh);
    $statusIdx = $header !== false ? array_search('status', $header, true) : false;
    $gnNameIdx = $header !== false ? array_search('gn_matched_name', $header, true) : false;
    while (($row = fgetcsv($fh)) !== false) {
        if ($statusIdx !== false && isset($row[$statusIdx], $counts[$row[$statusIdx]])) {
            $counts[$row[$statusIdx]]++;
        }
        if ($gnNameIdx !== false && isset($row[$gnNameIdx])) {
            $n = $row[$gnNameIdx];
            if (str_contains($n, ' spec.') || str_contains($n, ' indet.')) {
                $counts['gn_spec_or_indet']++;
            }
        }
    }
    fclose($fh);
}

printf("Wrote %d rows to %s\n", count($taxa), $outPath);
printf("Elapsed: %.1fs (%.1f ms/taxon, rate_limit=%.3fs)\n", $elapsed, 1000 * $elapsed / max(1, count($taxa)), $rateLimit);
printf(
    "Status: agree_id=%d agree_name=%d disagree=%d gn_only=%d rest_only=%d neither=%d | gn suffix spec/indet=%d\n",
    $counts['agree_id'],
    $counts['agree_name'],
    $counts['disagree'],
    $counts['gn_only'],
    $counts['rest_only'],
    $counts['neither'],
    $counts['gn_spec_or_indet'],
);
