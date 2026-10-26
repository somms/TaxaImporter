#!/usr/bin/env php
<?php

/**
 * Compare WFO validation backends on a line-delimited taxon list.
 *
 * Usage:
 *   php bin/wfo-validate-taxa.php tests/fixtures/wfo_andalusia_taxa.txt
 *   php bin/wfo-validate-taxa.php tests/fixtures/wfo_andalusia_taxa.txt --backend=rest|gn|both
 */

use Somms\BV2Observation\Parser\GNames\GNParser\LocaBinGNSpeciesParser;
use Somms\BV2Observation\Provider\GNames\GnVerifierCli;
use Somms\BV2Observation\Provider\GNames\GNWFOSpecies;
use Somms\BV2Observation\Provider\GNames\GNSpeciesParser;
use Somms\BV2Observation\Provider\WFO\WFOSpecies;
use Somms\BV2Observation\Provider\WFO\WFOSpeciesParser;
use Somms\BV2Observation\Util\WfoTaxonListReader;

require dirname(__DIR__) . '/vendor/autoload.php';

$path = $argv[1] ?? WfoTaxonListReader::defaultFixturePath();
$backend = 'both';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backend=')) {
        $backend = substr($arg, strlen('--backend='));
    }
}

$taxa = WfoTaxonListReader::read($path);
if ($taxa === []) {
    fwrite(STDERR, "No taxa in {$path}\n");
    exit(1);
}

$restOk = 0;
$gnOk = 0;
$restParser = new WFOSpeciesParser();
$gnParser = new GNSpeciesParser();
$restOptions = [
    'fuzzy_names' => 2,
    'fuzzy_authors' => 1,
    'accept_single_candidate' => true,
    'rate_limit_seconds' => 0.05,
];

foreach ($taxa as $name) {
    if ($backend === 'rest' || $backend === 'both') {
        $remote = new WFOSpecies($name, '', '');
        if ($remote->populateFromRemote($restParser, $restOptions)) {
            $restOk++;
        }
    }

    if ($backend === 'gn' || $backend === 'both') {
        $nameParser = new LocaBinGNSpeciesParser();
        $nameParser->setInput($name);
        $parsed = $nameParser->getSpecies();
        $remote = new GNWFOSpecies($parsed->getScientificName(), '', $parsed->getAuthor());
        if ($remote->populateFromRemote($gnParser, ['source' => GnVerifierCli::DATASOURCE_WFO])) {
            $gnOk++;
        }
    }
}

$total = count($taxa);
if ($backend === 'rest' || $backend === 'both') {
    printf("WFO REST:      %d/%d (%.1f%%)\n", $restOk, $total, 100 * $restOk / $total);
}
if ($backend === 'gn' || $backend === 'both') {
    printf("GNVerifier 196: %d/%d (%.1f%%)\n", $gnOk, $total, 100 * $gnOk / $total);
}
