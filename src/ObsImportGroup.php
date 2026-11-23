#!/usr/bin/env php
<?php

declare(strict_types=1);

use Somms\BV2Observation\Import\ObservationSpeciesImporter;
use Somms\BV2Observation\Import\RunnerImportDatabase;

require_once __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    die("Este script debe ejecutarse desde la línea de comandos.\n");
}

$baseDirectory = dirname(__DIR__);
$downloadDirectory = $baseDirectory . '/data/downloaded/Observation.org';
$configFile = $baseDirectory . '/config/datasources/obs_species_database.yml';

/**
 * Uso:
 *   php src/ObsImportGroup.php <speciesgroup_id> <archivo.csv|archivo.sqlite>
 *   php src/ObsImportGroup.php <archivo.sqlite>          # detecta el grupo
 *   php src/ObsImportGroup.php 11                       # interactivo / busca ficheros del grupo
 */
try {
    $config = RunnerImportDatabase::loadBlock($configFile, 'obs_species_database');

    [$sourceFile, $speciesGroupId] = resolveInput($argv, $downloadDirectory);

    echo "=== Importación Observation.org ===\n";
    echo "Archivo: {$sourceFile}\n";
    if ($speciesGroupId !== null) {
        $label = ObservationSpeciesImporter::SPECIES_GROUPS[$speciesGroupId] ?? 'desconocido';
        echo "Grupo: [{$speciesGroupId}] {$label}\n";
    }

    $pdo = RunnerImportDatabase::connect($config);
    $importer = new ObservationSpeciesImporter($pdo, (string) $config['table_name']);
    $count = $importer->import($sourceFile, $speciesGroupId);
    echo "Importación completada: {$count} registros.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error: {$exception->getMessage()}\n");
    exit(1);
}

/**
 * @param list<string> $argv
 * @return array{0:string,1:?int}
 */
function resolveInput(array $argv, string $downloadDirectory): array
{
    $arg1 = $argv[1] ?? null;
    $arg2 = $argv[2] ?? null;

    if ($arg1 !== null && is_file($arg1)) {
        $groupId = null;
        if ($arg2 !== null && ctype_digit((string) $arg2)) {
            $groupId = (int) $arg2;
        }
        return [realpath($arg1), $groupId];
    }

    if ($arg1 !== null && ctype_digit((string) $arg1)) {
        $groupId = (int) $arg1;
        if (!isset(ObservationSpeciesImporter::SPECIES_GROUPS[$groupId])) {
            throw new RuntimeException("speciesgroup_id no válido: {$groupId}");
        }
        if ($arg2 !== null) {
            if (!is_file($arg2)) {
                throw new RuntimeException("No existe el fichero: {$arg2}");
            }
            return [realpath($arg2), $groupId];
        }
        $file = findSourceForGroup($downloadDirectory, $groupId);
        return [$file, $groupId];
    }

    if ($arg1 === null) {
        throw new RuntimeException(
            "Uso:\n" .
            "  php src/ObsImportGroup.php <archivo.sqlite>\n" .
            "  php src/ObsImportGroup.php <speciesgroup_id> <archivo.csv|sqlite>\n" .
            "  php src/ObsImportGroup.php <speciesgroup_id>"
        );
    }

    throw new RuntimeException("No se reconoce el argumento: {$arg1}");
}

function findSourceForGroup(string $downloadDirectory, int $groupId): string
{
    if (!is_dir($downloadDirectory)) {
        throw new RuntimeException("No existe el directorio {$downloadDirectory}");
    }

    $patterns = [
        11 => ['*Setas*.sqlite', '*mushrooms*.sqlite', '*Setas*.csv'],
        12 => ['*Musgos*.sqlite', '*Líquenes*.sqlite', '*liquenes*.sqlite', '*mosses*.sqlite', '*Musgos*.csv'],
    ];
    $candidates = [];
    foreach ($patterns[$groupId] ?? ['*.sqlite', '*.csv'] as $pattern) {
        foreach (glob($downloadDirectory . '/' . $pattern) ?: [] as $file) {
            $candidates[] = $file;
        }
    }
    $candidates = array_values(array_unique($candidates));
    if ($candidates === []) {
        // Fallback: cualquier sqlite/csv del directorio
        $candidates = array_merge(
            glob($downloadDirectory . '/*.sqlite') ?: [],
            glob($downloadDirectory . '/*.csv') ?: []
        );
    }
    if ($candidates === []) {
        throw new RuntimeException("No hay ficheros CSV/SQLite en {$downloadDirectory}");
    }

    usort($candidates, static fn($a, $b) => filemtime($b) <=> filemtime($a));
    $chosen = $candidates[0];
    echo "Fichero detectado: " . basename($chosen) . "\n";
    return realpath($chosen);
}
