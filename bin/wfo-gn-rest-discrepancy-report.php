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
WfoDualMatchReport::writeCsv($taxa, $fp, [], $rateLimit);
fclose($fp);

echo "Wrote " . count($taxa) . " rows to {$outPath}\n";
