#!/usr/bin/env php
<?php

/**
 * Compare WFO validation backends (match rate + wall-clock time).
 *
 * Usage:
 *   php bin/wfo-validate-taxa.php tests/fixtures/wfo_andalusia_taxa.txt
 *   php bin/wfo-validate-taxa.php tests/fixtures/wfo_andalusia_taxa.txt --backend=rest|gn|both
 *   php bin/wfo-validate-taxa.php … --rate-limit=0.05
 *   php bin/wfo-validate-taxa.php … --summary=data/output/wfo_benchmark_summary.json
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
$rateLimit = (float) (getenv('TAXAIMPORTER_WFO_RATE_LIMIT') ?: '0');
$summaryPath = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backend=')) {
        $backend = substr($arg, strlen('--backend='));
    }
    if (str_starts_with($arg, '--rate-limit=')) {
        $rateLimit = (float) substr($arg, strlen('--rate-limit='));
    }
    if (str_starts_with($arg, '--summary=')) {
        $summaryPath = substr($arg, strlen('--summary='));
    }
}

$taxa = WfoTaxonListReader::read($path);
if ($taxa === []) {
    fwrite(STDERR, "No taxa in {$path}\n");
    exit(1);
}

$total = count($taxa);
$summary = [
    'taxa_file' => $path,
    'taxa_count' => $total,
    'rate_limit_seconds' => $rateLimit,
    'started_at' => date('c'),
    'backends' => [],
];

printf("Taxa: %d | rate_limit_seconds=%.3f | backend=%s\n\n", $total, $rateLimit, $backend);

if ($backend === 'rest' || $backend === 'both') {
    $summary['backends']['rest'] = runRestBackend($taxa, $rateLimit);
}

if ($backend === 'gn' || $backend === 'both') {
    $summary['backends']['gn'] = runGnBackend($taxa);
}

$summary['finished_at'] = date('c');

if ($summaryPath !== null) {
    $dir = dirname($summaryPath);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
    file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "\nSummary JSON: {$summaryPath}\n";
}

/**
 * @param list<string> $taxa
 * @return array<string, mixed>
 */
function runRestBackend(array $taxa, float $rateLimit): array
{
    $parser = new WFOSpeciesParser();
    $options = [
        'fuzzy_names' => 2,
        'fuzzy_authors' => 1,
        'accept_single_candidate' => true,
        'rate_limit_seconds' => $rateLimit,
    ];
    $ok = 0;
    $withSpecOrIndet = 0;
    $t0 = microtime(true);

    foreach ($taxa as $name) {
        $remote = new WFOSpecies($name, '', '');
        if ($remote->populateFromRemote($parser, $options)) {
            $ok++;
            $out = $remote->getRemoteScientificName();
            if (str_contains($out, ' spec.') || str_contains($out, ' indet.')) {
                $withSpecOrIndet++;
            }
        }
    }

    $elapsed = microtime(true) - $t0;
    $stats = formatBackendStats('WFO REST', $ok, count($taxa), $elapsed, $withSpecOrIndet);
    printBackendStats($stats);

    return $stats;
}

/**
 * @param list<string> $taxa
 * @return array<string, mixed>
 */
function runGnBackend(array $taxa): array
{
    $parser = new GNSpeciesParser();
    $ok = 0;
    $withSpecOrIndet = 0;
    $t0 = microtime(true);

    foreach ($taxa as $name) {
        $nameParser = new LocaBinGNSpeciesParser();
        $nameParser->setInput($name);
        $parsed = $nameParser->getSpecies();
        $remote = new GNWFOSpecies($parsed->getScientificName(), '', $parsed->getAuthor());
        if ($remote->populateFromRemote($parser, ['source' => GnVerifierCli::DATASOURCE_WFO])) {
            $ok++;
            $out = $remote->getRemoteScientificName();
            if (str_contains($out, ' spec.') || str_contains($out, ' indet.')) {
                $withSpecOrIndet++;
            }
        }
    }

    $elapsed = microtime(true) - $t0;
    $stats = formatBackendStats('GNVerifier 196', $ok, count($taxa), $elapsed, $withSpecOrIndet);
    printBackendStats($stats);

    return $stats;
}

/**
 * @return array<string, mixed>
 */
function formatBackendStats(string $label, int $ok, int $total, float $elapsed, int $withSpecOrIndet): array
{
    $avgMs = $total > 0 ? ($elapsed * 1000) / $total : 0.0;

    return [
        'label' => $label,
        'matched' => $ok,
        'total' => $total,
        'match_rate_pct' => $total > 0 ? round(100 * $ok / $total, 2) : 0.0,
        'elapsed_seconds' => round($elapsed, 3),
        'avg_ms_per_taxon' => round($avgMs, 2),
        'names_with_spec_or_indet_suffix' => $withSpecOrIndet,
    ];
}

/**
 * @param array<string, mixed> $stats
 */
function printBackendStats(array $stats): void
{
    printf(
        "%s: %d/%d (%.1f%%) | %.1fs total | %.1f ms/taxon | suffix spec/indet: %d\n",
        $stats['label'],
        $stats['matched'],
        $stats['total'],
        $stats['match_rate_pct'],
        $stats['elapsed_seconds'],
        $stats['avg_ms_per_taxon'],
        $stats['names_with_spec_or_indet_suffix'],
    );
}
