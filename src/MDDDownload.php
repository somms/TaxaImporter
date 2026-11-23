<?php

// Script para:
// 1) Descargar la última versión de MDD.zip
// 2) Descomprimirla en ./data/downloaded/MDD
// 3) Crear/actualizar tablas mdd_species y mdd_synonyms según cabeceras CSV
// 4) Borrar contenido previo y recargar datos desde los CSV

// Requisitos:
// - Extensiones: yaml, pdo_pgsql, zip
// - Configuración en:
//     ../config/datasources/mdd_species_database.yml
//     ../config/datasources/mdd_synonyms_database.yml

if (PHP_SAPI !== 'cli') {
    die("Este script debe ejecutarse desde la línea de comandos.\n");
}

require_once __DIR__ . '/../vendor/autoload.php';

use Somms\BV2Observation\Import\RunnerImportDatabase;

echo "=== Descarga e importación MDD ===\n";

// -----------------------------------------------------------------------------
// 1. Descarga de la MDD.zip
// -----------------------------------------------------------------------------

$defaultUrl = 'https://github.com/mammaldiversity/mammaldiversity.github.io/raw/refs/heads/master/assets/data/MDD.zip';
$url = $argv[1] ?? $defaultUrl;

echo "Descargando MDD desde:\n  $url\n";

$zipContent = @file_get_contents($url);
if ($zipContent === false) {
    die("Error descargando el archivo ZIP de MDD.\n");
}

$zipFile = tempnam(sys_get_temp_dir(), 'MDD_') . '.zip';
if (file_put_contents($zipFile, $zipContent) === false) {
    die("Error guardando el archivo ZIP temporal.\n");
}

echo "ZIP descargado en: $zipFile\n";

// -----------------------------------------------------------------------------
// 2. Descomprimir en ./data/downloaded/MDD
// -----------------------------------------------------------------------------

$baseDir    = realpath(__DIR__ . '/..'); // proyecto
$targetDir  = $baseDir . '/data/downloaded/MDD';

if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
    die("No se pudo crear el directorio destino: $targetDir\n");
}

echo "Extrayendo ZIP en: $targetDir\n";

$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    die("No se pudo abrir el ZIP con ZipArchive.\n");
}

if (!$zip->extractTo($targetDir)) {
    $zip->close();
    die("Error extrayendo el ZIP en $targetDir\n");
}
$zip->close();

echo "Extracción completada.\n";

// -----------------------------------------------------------------------------
// 3. Localizar los CSV de datos
//     - Especies:     MDD_v*species.csv
//     - Sinónimos:    Species_Syn_v*.csv
// -----------------------------------------------------------------------------

function findSingleCsv(string $dir, string $pattern): string
{
    $files = glob($dir . '/' . $pattern);
    if (!$files) {
        die("No se ha encontrado ningún CSV con el patrón $pattern en $dir\n");
    }
    // Si hay varios, tomamos el "mayor" por orden alfabético (suele ser la última versión)
    sort($files, SORT_STRING);
    $file = end($files);
    echo "  Encontrado: $file\n";
    return $file;
}

echo "Buscando CSV de especies...\n";
$speciesCsv = findSingleCsv($targetDir, 'MDD_v*species.csv');

echo "Buscando CSV de sinónimos...\n";
$synCsv = findSingleCsv($targetDir, 'Species_Syn_v*.csv');

// -----------------------------------------------------------------------------
// 4. Leer configuración de los datasources YAML
// -----------------------------------------------------------------------------

$speciesConfigFile = $baseDir . '/config/datasources/mdd_species_database.yml';
$synConfigFile     = $baseDir . '/config/datasources/mdd_synonyms_database.yml';

echo "Leyendo configuración BD especies: $speciesConfigFile\n";
try {
    $speciesDbConfig = RunnerImportDatabase::loadBlock($speciesConfigFile, 'mdd_species_database');
} catch (Throwable $e) {
    die($e->getMessage() . "\n");
}

echo "Leyendo configuración BD sinónimos: $synConfigFile\n";
try {
    $synDbConfig = RunnerImportDatabase::loadBlock($synConfigFile, 'mdd_synonyms_database');
} catch (Throwable $e) {
    die($e->getMessage() . "\n");
}

// -----------------------------------------------------------------------------
// 5. Conectar a PostgreSQL
// -----------------------------------------------------------------------------

echo "Conectando a la base de datos...\n";
try {
    $pdo = RunnerImportDatabase::connect($speciesDbConfig);
} catch (PDOException $e) {
    die("Error conectando a la base de datos: " . $e->getMessage() . "\n");
}

$speciesTable = $speciesDbConfig['table_name'];   // mdd_species
$synTable     = $synDbConfig['table_name'];       // mdd_synonyms

// -----------------------------------------------------------------------------
// 6. Funciones auxiliares para sincronizar tablas con cabeceras CSV
// -----------------------------------------------------------------------------

/**
 * Lee la cabecera de un CSV (primera fila) y devuelve array de nombres de columna.
 */
function readCsvHeader(string $csvFile): array
{
    $fh = fopen($csvFile, 'r');
    if ($fh === false) {
        die("No se pudo abrir el CSV: $csvFile\n");
    }

    // Asumimos separador por defecto de MDD: coma
    $header = fgetcsv($fh, 0, ',', '"');
    fclose($fh);

    if ($header === false) {
        die("No se pudo leer la cabecera del CSV: $csvFile\n");
    }

    // Limpiar espacios alrededor
    $header = array_map(static fn($h) => trim((string)$h), $header);

    return $header;
}

/**
 * Crea la tabla si no existe, o añade las columnas que falten respecto a la cabecera CSV.
 * Todas las columnas se crean como TEXT para simplificar.
 *
 * IMPORTANTE: Se asume que los nombres de columna del CSV son válidos como identificadores
 * de PostgreSQL (sin espacios, normalmente snake_case, p. ej. 'sciName', 'MDD_original_combination').
 * Se usan comillas dobles para preservar mayúsculas/minúsculas y guiones bajos.
 */
function ensureTableMatchesCsv(PDO $pdo, string $tableName, array $columns): void
{
    echo "Asegurando estructura de tabla: $tableName\n";

    // 1) ¿Existe la tabla?
    $sql = "SELECT to_regclass(:table_name) AS exists_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table_name' => $tableName]);
    $existsName = $stmt->fetchColumn();

    if ($existsName === null) {
        // La tabla no existe: crearla con todas las columnas como TEXT
        $colsSql = [];
        foreach ($columns as $col) {
            if ($col === '') {
                continue;
            }
            // "NombreColumna" text
            $colsSql[] = '"' . $col . '" TEXT';
        }
        if (empty($colsSql)) {
            die("La cabecera CSV para la tabla $tableName está vacía.\n");
        }

        $createSql = sprintf(
            'CREATE TABLE "%s" (%s)',
            $tableName,
            implode(",\n  ", $colsSql)
        );

        echo "  Creando tabla $tableName...\n";
        $pdo->exec($createSql);
        echo "  Tabla $tableName creada.\n";
    } else {
        // La tabla existe: comprobar columnas y añadir las que falten
        $sqlCols = "
            SELECT column_name
            FROM information_schema.columns
            WHERE table_name = :table
              AND table_schema = 'public'
        ";
        $stmt = $pdo->prepare($sqlCols);
        $stmt->execute([':table' => $tableName]);
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $existingSet = array_flip($existing);

        foreach ($columns as $col) {
            if ($col === '') {
                continue;
            }
            if (!isset($existingSet[$col])) {
                $alterSql = sprintf(
                    'ALTER TABLE "%s" ADD COLUMN "%s" TEXT',
                    $tableName,
                    $col
                );
                echo "  Añadiendo columna '$col' a $tableName\n";
                $pdo->exec($alterSql);
            }
        }
    }
}

/**
 * Vacía la tabla y carga el CSV completo.
 * Inserta dinámicamente basándose en las columnas de cabecera.
 */
function loadCsvIntoTable(PDO $pdo, string $tableName, string $csvFile, array $columns): void
{
    echo "Vaciando tabla $tableName...\n";
    $pdo->exec(sprintf('TRUNCATE TABLE "%s"', $tableName));

    echo "Cargando datos desde $csvFile...\n";

    $colListSql = implode(', ', array_map(static fn($c) => '"' . $c . '"', $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));

    $insertSql = sprintf(
        'INSERT INTO "%s" (%s) VALUES (%s)',
        $tableName,
        $colListSql,
        $placeholders
    );

    $stmt = $pdo->prepare($insertSql);

    $fh = fopen($csvFile, 'r');
    if ($fh === false) {
        die("No se pudo abrir el CSV para lectura: $csvFile\n");
    }

    // Saltar cabecera
    $header = fgetcsv($fh, 0, ',', '"');

    $rowCount = 0;
    $pdo->beginTransaction();
    try {
        while (($row = fgetcsv($fh, 0, ',', '"')) !== false) {
            // Ajustar número de columnas si hiciera falta
            if (count($row) < count($columns)) {
                $row = array_pad($row, count($columns), null);
            } elseif (count($row) > count($columns)) {
                $row = array_slice($row, 0, count($columns));
            }

            $stmt->execute($row);
            $rowCount++;

            // Commit intermedio para CSVs muy grandes (por ejemplo cada 5000 filas)
            if ($rowCount % 5000 === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
                echo "  $rowCount filas insertadas...\n";
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fclose($fh);
        die("Error insertando datos en $tableName: " . $e->getMessage() . "\n");
    }

    fclose($fh);
    echo "Carga completada para $tableName: $rowCount filas.\n";
}

/**
 * Crea índices recomendados para la tabla mdd_species.
 */
function ensureMddSpeciesIndexes(PDO $pdo, string $tableName): void
{
    echo "Creando índices para tabla de especies ($tableName)...\n";

    $higherCols = [
        'subclass',
        'infraclass',
        'magnorder',
        'superorder',
        '"order"',      // palabra reservada, la citamos tal cual
        'suborder',
        'infraorder',
        'parvorder',
        'superfamily',
        'family',
        'subfamily',
        'tribe',
        'subtribe',
    ];

    $sqls = [
        // Índice sobre la PK / ID
        sprintf('CREATE INDEX IF NOT EXISTS idx_%s_id ON "%s" ("id")', $tableName, $tableName),

        // Búsquedas directas por sciName
        sprintf('CREATE INDEX IF NOT EXISTS idx_%s_sciname ON "%s" ("sciName")', $tableName, $tableName),

        // Búsquedas por género ("Genus spec.")
        sprintf('CREATE INDEX IF NOT EXISTS idx_%s_genus ON "%s" ("genus")', $tableName, $tableName),
    ];

    // Índices sobre columnas de taxones superiores usadas en hasHigherTaxon()
    foreach ($higherCols as $col) {
        // $col ya viene con comillas en el caso de "order"
        $colForIndex = str_replace('"', '', $col); // solo para el nombre del índice
        $sqls[] = sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_%s ON "%s" (%s)',
            $tableName,
            $colForIndex,
            $tableName,
            $col
        );
    }

    foreach ($sqls as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // No paramos el proceso por errores de índice; solo avisamos.
            echo "  Aviso al crear índice: " . $e->getMessage() . "\n";
        }
    }
}

/**
 * Crea índices recomendados para la tabla mdd_synonyms.
 */
function ensureMddSynonymIndexes(PDO $pdo, string $tableName): void
{
    echo "Creando índices para tabla de sinónimos ($tableName)...\n";

    $sqls = [
        // Búsqueda por combinación original (MDD_original_combination)
        sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_original_combination ON "%s" ("MDD_original_combination")',
            $tableName,
            $tableName
        ),

        // Vínculo hacia la especie aceptada (MDD_species_id)
        sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_species_id ON "%s" ("MDD_species_id")',
            $tableName,
            $tableName
        ),
    ];

    foreach ($sqls as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            echo "  Aviso al crear índice: " . $e->getMessage() . "\n";
        }
    }
}

// -----------------------------------------------------------------------------
// 7. Procesar tabla principal de especies
// -----------------------------------------------------------------------------

echo "=== Procesando tabla de especies ($speciesTable) ===\n";
$speciesHeader = readCsvHeader($speciesCsv);
ensureTableMatchesCsv($pdo, $speciesTable, $speciesHeader);
loadCsvIntoTable($pdo, $speciesTable, $speciesCsv, $speciesHeader);
ensureMddSpeciesIndexes($pdo, $speciesTable);

// -----------------------------------------------------------------------------
// 8. Procesar tabla de sinónimos
// -----------------------------------------------------------------------------

echo "=== Procesando tabla de sinónimos ($synTable) ===\n";
$synHeader = readCsvHeader($synCsv);
ensureTableMatchesCsv($pdo, $synTable, $synHeader);
loadCsvIntoTable($pdo, $synTable, $synCsv, $synHeader);

// Crear índices para mdd_synonyms
ensureMddSynonymIndexes($pdo, $synTable);

RunnerImportDatabase::grantRunnerReadOnly($pdo, $speciesTable);
RunnerImportDatabase::grantRunnerReadOnly($pdo, $synTable);

echo "=== Proceso MDD completado con éxito. ===\n";
