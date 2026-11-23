#!/usr/bin/env php
<?php

declare(strict_types=1);

use Somms\BV2Observation\Import\MycoBankImporter;
use Somms\BV2Observation\Import\RunnerImportDatabase;

require_once __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    die("Este script debe ejecutarse desde la línea de comandos.\n");
}

$baseDirectory = dirname(__DIR__);
$configFile = $baseDirectory . '/config/datasources/mycobank_database.yml';
$source = $argv[1] ?? null;

try {
    $databaseConfig = RunnerImportDatabase::loadBlock($configFile, 'mycobank_database');

    echo "=== Importación de MycoBank ===\n";
    $pdo = RunnerImportDatabase::connect($databaseConfig);

    $importer = new MycoBankImporter(
        $pdo,
        (string) $databaseConfig['table_name'],
        $baseDirectory . '/data/downloaded/MycoBank',
        (string) $databaseConfig['dsn'],
        (string) $databaseConfig['username'],
        (string) $databaseConfig['password'],
    );

    $csvFile = $importer->resolveCsvPath($source);
    echo "Procesando CSV: {$csvFile}\n";
    $rowCount = $importer->import($csvFile);
    echo "Importación completada: {$rowCount} registros cargados.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error importando MycoBank: {$exception->getMessage()}\n");
    exit(1);
}
