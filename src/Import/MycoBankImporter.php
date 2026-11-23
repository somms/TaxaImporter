<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Import;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Importa un CSV ya convertido desde MBList.xlsx a PostgreSQL
 * mediante COPY directo (FORMAT csv), sin fichero TSV intermedio.
 */
final class MycoBankImporter
{
    public const DEFAULT_CSV_NAME = 'MBList.csv';

    /** Orden de columnas del CSV de MycoBank → columnas de la tabla. */
    private const HEADER_MAP = [
        'ID' => 'id',
        'Taxon name' => 'taxon_name',
        'Authors' => 'authors',
        'Authors (abbreviated)' => 'authors_abbreviated',
        'Rank' => 'rank',
        'Year of effective publication' => 'publication_year',
        'Name status' => 'name_status',
        'MycoBank #' => 'mycobank_number',
        'Hyperlink to MB' => 'hyperlink',
        'Classification' => 'classification',
        'Current MycoBank #' => 'current_mycobank_number',
        'Current name' => 'current_name',
        'Synonymy' => 'synonymy',
    ];

    private const COLUMNS = [
        'id',
        'taxon_name',
        'authors',
        'authors_abbreviated',
        'rank',
        'publication_year',
        'name_status',
        'mycobank_number',
        'hyperlink',
        'classification',
        'current_mycobank_number',
        'current_name',
        'synonymy',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName,
        private readonly string $downloadDirectory,
        private readonly string $dsn,
        private readonly string $username,
        private readonly string $password,
    ) {
        $this->assertIdentifier($this->tableName);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function resolveCsvPath(?string $source = null): string
    {
        $this->ensureDirectory($this->downloadDirectory);

        if ($source === null || $source === '') {
            $source = $this->downloadDirectory . DIRECTORY_SEPARATOR . self::DEFAULT_CSV_NAME;
        }

        if (!is_file($source)) {
            throw new RuntimeException(
                "No existe el CSV de MycoBank: {$source}\n" .
                "Convierte MBList.xlsx a CSV (UTF-8) con LibreOffice o Excel y colócalo ahí.\n" .
                "Ejemplo LibreOffice:\n" .
                "  soffice --headless --convert-to csv --outdir data/downloaded/MycoBank MBList.xlsx"
            );
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            throw new RuntimeException(
                "El módulo de entrada espera un CSV ya procesado (.csv). Recibido: {$source}"
            );
        }

        return (string) realpath($source);
    }

    public function import(string $csvFile): int
    {
        $csvFile = $this->resolveCsvPath($csvFile);
        $delimiter = $this->detectDelimiter($csvFile);
        $this->assertCsvHeader($csvFile, $delimiter);

        $this->ensureTargetTable();
        $stagingTable = $this->stagingTableName();
        $this->createStagingTable($stagingTable);

        try {
            echo "Cargando CSV en staging con COPY (delimitador '" . ($delimiter === "\t" ? '\t' : $delimiter) . "')...\n";
            $this->copyCsvIntoTable($stagingTable, $csvFile, $delimiter);
            $this->normalizeStaging($stagingTable);
            $rowCount = (int) $this->pdo->query(
                sprintf('SELECT COUNT(*) FROM %s', $this->quoteIdentifier($stagingTable))
            )->fetchColumn();
            $this->validateStaging($stagingTable, $rowCount);
            $this->replaceTarget($stagingTable);
            $this->ensureIndexes();
            $this->pdo->exec(sprintf('ANALYZE %s', $this->quoteIdentifier($this->tableName)));
            RunnerImportDatabase::grantRunnerReadOnly($this->pdo, $this->tableName);

            return $rowCount;
        } finally {
            $this->pdo->exec(sprintf('DROP TABLE IF EXISTS %s', $this->quoteIdentifier($stagingTable)));
        }
    }

    private function copyCsvIntoTable(string $tableName, string $csvFile, string $delimiter): void
    {
        $copySql = $this->buildCopySql($tableName, $delimiter);
        $params = $this->parsePdoDsn($this->dsn);
        $dbname = $params['dbname'] ?? '';
        if ($dbname === '') {
            throw new RuntimeException('El DSN no incluye dbname.');
        }

        $container = getenv('MYCOBANK_PG_CONTAINER');
        if (is_string($container) && $container !== '') {
            $this->runCopyCommand(
                [
                    'docker',
                    'exec',
                    '-i',
                    '-e',
                    'PGPASSWORD=' . $this->password,
                    $container,
                    'psql',
                    '-h',
                    '127.0.0.1',
                    '-U',
                    $this->username,
                    '-d',
                    $dbname,
                    '-v',
                    'ON_ERROR_STOP=1',
                    '-c',
                    $copySql,
                ],
                $csvFile,
                'docker exec/psql'
            );

            return;
        }

        $host = $params['host'] ?? 'localhost';
        $port = $params['port'] ?? '5432';
        $this->runCopyCommand(
            [
                'psql',
                '-h',
                $host,
                '-p',
                (string) $port,
                '-U',
                $this->username,
                '-d',
                $dbname,
                '-v',
                'ON_ERROR_STOP=1',
                '-c',
                $copySql,
            ],
            $csvFile,
            'psql',
            ['PGPASSWORD' => $this->password]
        );
    }

    private function buildCopySql(string $tableName, string $delimiter): string
    {
        $columnList = implode(', ', array_map($this->quoteIdentifier(...), self::COLUMNS));
        $delimiterSql = $delimiter === "'" ? "''" : $delimiter;

        return sprintf(
            "COPY %s (%s) FROM STDIN WITH (FORMAT csv, HEADER true, DELIMITER '%s', QUOTE '\"', ESCAPE '\"', NULL '')",
            $this->quoteIdentifier($tableName),
            $columnList,
            $delimiterSql
        );
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $extraEnv
     */
    private function runCopyCommand(array $command, string $csvFile, string $label, array $extraEnv = []): void
    {
        $descriptors = [
            0 => ['file', $csvFile, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = $extraEnv === [] ? null : array_merge($_ENV, $extraEnv);
        $process = proc_open($command, $descriptors, $pipes, null, $env, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException("No se pudo lanzar {$label} para COPY.");
        }

        try {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        } catch (Throwable $exception) {
            proc_terminate($process);
            throw $exception;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr !== '' && $stderr !== false ? $stderr : ($stdout ?: 'COPY CSV falló.')));
        }

        if (is_string($stdout) && $stdout !== '') {
            echo trim($stdout) . "\n";
        }
    }

    private function resolvePostgresContainer(string $port): string
    {
        $configured = getenv('MYCOBANK_PG_CONTAINER');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (!preg_match('/^[0-9]+$/', $port)) {
            throw new RuntimeException("Puerto PostgreSQL no válido: {$port}");
        }

        $output = [];
        $exitCode = 0;
        exec(
            'docker ps --filter publish=' . $port . " --format '{{.Names}}'",
            $output,
            $exitCode
        );
        $output = array_values(array_filter(array_map('trim', $output)));
        if ($exitCode !== 0 || $output === []) {
            throw new RuntimeException(
                "No se encontró un contenedor Docker publicando el puerto {$port}. " .
                'Define MYCOBANK_PG_CONTAINER o asegúrate de que PostgreSQL corre en Docker.'
            );
        }

        return $output[0];
    }

    /**
     * @return array<string, string>
     */
    private function parsePdoDsn(string $dsn): array
    {
        if (!str_starts_with($dsn, 'pgsql:')) {
            throw new RuntimeException("DSN no soportado para COPY CSV: {$dsn}");
        }

        $params = [];
        foreach (explode(';', substr($dsn, strlen('pgsql:'))) as $part) {
            if ($part === '' || !str_contains($part, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $part, 2);
            $params[$key] = $value;
        }

        return $params;
    }

    private function detectDelimiter(string $csvFile): string
    {
        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException("No se puede abrir el CSV: {$csvFile}");
        }
        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('El CSV de MycoBank está vacío.');
            }
            $firstLine = $this->stripBom($firstLine);
            $comma = substr_count($firstLine, ',');
            $semicolon = substr_count($firstLine, ';');
            $tab = substr_count($firstLine, "\t");

            if ($tab >= $comma && $tab >= $semicolon && $tab > 0) {
                return "\t";
            }
            if ($semicolon > $comma) {
                return ';';
            }

            return ',';
        } finally {
            fclose($handle);
        }
    }

    private function assertCsvHeader(string $csvFile, string $delimiter): void
    {
        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException("No se puede abrir el CSV: {$csvFile}");
        }
        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('El CSV de MycoBank está vacío.');
            }
            $header = str_getcsv($this->stripBom($firstLine), $delimiter, '"', '\\');
            $header = array_map(static fn($value) => trim((string) $value), $header);
            foreach (array_keys(self::HEADER_MAP) as $sourceHeader) {
                if (!in_array($sourceHeader, $header, true)) {
                    throw new RuntimeException("Falta la columna obligatoria '{$sourceHeader}' en el CSV de MycoBank.");
                }
            }

            $expected = array_keys(self::HEADER_MAP);
            if ($header !== $expected) {
                // Permite el mismo conjunto aunque el orden coincida con HEADER_MAP.
                if (array_values(array_intersect($header, $expected)) !== $expected) {
                    throw new RuntimeException(
                        'El orden/conjunto de columnas del CSV no coincide con la exportación de MycoBank.'
                    );
                }
                foreach ($expected as $index => $name) {
                    if (($header[$index] ?? null) !== $name) {
                        throw new RuntimeException(
                            "La columna {$index} debería ser '{$name}' y es '" . ($header[$index] ?? '') . "'."
                        );
                    }
                }
            }
        } finally {
            fclose($handle);
        }
    }

    private function normalizeStaging(string $stagingTable): void
    {
        $table = $this->quoteIdentifier($stagingTable);

        foreach (['id', 'mycobank_number', 'current_mycobank_number', 'hyperlink', 'current_name', 'taxon_name'] as $column) {
            $quoted = $this->quoteIdentifier($column);
            $this->pdo->exec(<<<SQL
                UPDATE {$table}
                SET {$quoted} = NULL
                WHERE {$quoted} IS NOT NULL
                  AND (
                    {$quoted} = ''
                    OR {$quoted} LIKE '#%'
                    OR {$quoted} LIKE '=%'
                  )
                SQL);
        }

        $this->pdo->exec(<<<SQL
            UPDATE {$table}
            SET "mycobank_number" = NULL
            WHERE "mycobank_number" IS NOT NULL
              AND "mycobank_number" !~ '^[0-9]+$'
            SQL);
        $this->pdo->exec(<<<SQL
            UPDATE {$table}
            SET "current_mycobank_number" = NULL
            WHERE "current_mycobank_number" IS NOT NULL
              AND "current_mycobank_number" !~ '^[0-9]+$'
            SQL);

        $this->pdo->exec(<<<SQL
            UPDATE {$table}
            SET "hyperlink" = 'https://www.mycobank.org/page/Name details page/' || "id"
            WHERE "id" IS NOT NULL
              AND "id" ~ '^[0-9]+$'
              AND "hyperlink" IS NULL
            SQL);

        $this->pdo->exec(<<<SQL
            UPDATE {$table} AS source
            SET "current_mycobank_number" = substring(source."synonymy" from 'Current name:.*?\\[MB#([0-9]+)\\]')
            WHERE source."current_mycobank_number" IS NULL
              AND source."synonymy" ~ 'Current name:.*?\\[MB#[0-9]+\\]'
            SQL);

        $this->pdo->exec(<<<SQL
            UPDATE {$table} AS source
            SET "current_name" = accepted."taxon_name"
            FROM (
                SELECT "mycobank_number", MIN("taxon_name") AS "taxon_name"
                FROM {$table}
                WHERE "mycobank_number" IS NOT NULL
                  AND "mycobank_number" ~ '^[0-9]+$'
                GROUP BY "mycobank_number"
            ) AS accepted
            WHERE source."current_name" IS NULL
              AND source."current_mycobank_number" = accepted."mycobank_number"
            SQL);
    }

    private function ensureTargetTable(): void
    {
        $table = $this->quoteIdentifier($this->tableName);
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS {$table} (
                "id" BIGINT PRIMARY KEY,
                "taxon_name" TEXT NOT NULL,
                "authors" TEXT,
                "authors_abbreviated" TEXT,
                "rank" TEXT,
                "publication_year" TEXT,
                "name_status" TEXT,
                "mycobank_number" BIGINT,
                "hyperlink" TEXT,
                "classification" TEXT,
                "current_mycobank_number" BIGINT,
                "current_name" TEXT,
                "synonymy" TEXT
            )
            SQL);

        $statement = $this->pdo->prepare(
            "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :table"
        );
        $statement->execute([':table' => $this->tableName]);
        $existingColumns = $statement->fetchAll(PDO::FETCH_COLUMN);
        $missingColumns = array_diff(self::COLUMNS, $existingColumns);
        if ($missingColumns !== []) {
            throw new RuntimeException(
                "La tabla {$this->tableName} no tiene las columnas requeridas: " . implode(', ', $missingColumns)
            );
        }
    }

    private function createStagingTable(string $stagingTable): void
    {
        $table = $this->quoteIdentifier($stagingTable);
        // Todo TEXT: el CSV puede traer errores de fórmula de Excel/LibreOffice (#¿NOMBRE?).
        $this->pdo->exec(<<<SQL
            CREATE UNLOGGED TABLE {$table} (
                "id" TEXT,
                "taxon_name" TEXT,
                "authors" TEXT,
                "authors_abbreviated" TEXT,
                "rank" TEXT,
                "publication_year" TEXT,
                "name_status" TEXT,
                "mycobank_number" TEXT,
                "hyperlink" TEXT,
                "classification" TEXT,
                "current_mycobank_number" TEXT,
                "current_name" TEXT,
                "synonymy" TEXT
            )
            SQL);
    }

    private function validateStaging(string $stagingTable, int $rowCount): void
    {
        if ($rowCount === 0) {
            throw new RuntimeException('El CSV no contiene registros de MycoBank.');
        }

        $table = $this->quoteIdentifier($stagingTable);
        $invalid = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM {$table}
             WHERE id IS NULL OR id !~ '^[0-9]+$'
                OR taxon_name IS NULL OR taxon_name = ''"
        )->fetchColumn();
        if ($invalid > 0) {
            throw new RuntimeException("Hay {$invalid} filas sin ID numérico o nombre científico.");
        }

        $duplicates = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM (SELECT id FROM {$table} GROUP BY id HAVING COUNT(*) > 1) duplicated"
        )->fetchColumn();
        if ($duplicates > 0) {
            throw new RuntimeException("Hay {$duplicates} identificadores ID duplicados.");
        }
    }

    private function replaceTarget(string $stagingTable): void
    {
        $target = $this->quoteIdentifier($this->tableName);
        $staging = $this->quoteIdentifier($stagingTable);

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("LOCK TABLE {$target} IN ACCESS EXCLUSIVE MODE");
            $this->pdo->exec("TRUNCATE TABLE {$target}");
            $this->pdo->exec(<<<SQL
                INSERT INTO {$target} (
                    "id", "taxon_name", "authors", "authors_abbreviated", "rank",
                    "publication_year", "name_status", "mycobank_number", "hyperlink",
                    "classification", "current_mycobank_number", "current_name", "synonymy"
                )
                SELECT
                    "id"::bigint,
                    "taxon_name",
                    "authors",
                    "authors_abbreviated",
                    "rank",
                    "publication_year",
                    "name_status",
                    NULLIF("mycobank_number", '')::bigint,
                    "hyperlink",
                    "classification",
                    NULLIF("current_mycobank_number", '')::bigint,
                    "current_name",
                    "synonymy"
                FROM {$staging}
                SQL);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function ensureIndexes(): void
    {
        $table = $this->quoteIdentifier($this->tableName);
        $indexPrefix = substr($this->tableName, 0, 40);
        $indexes = [
            "CREATE INDEX IF NOT EXISTS \"idx_{$indexPrefix}_taxon_name\" ON {$table} (\"taxon_name\")",
            "CREATE INDEX IF NOT EXISTS \"idx_{$indexPrefix}_mycobank_number\" ON {$table} (\"mycobank_number\")",
            "CREATE INDEX IF NOT EXISTS \"idx_{$indexPrefix}_current_number\" ON {$table} (\"current_mycobank_number\")",
            "CREATE INDEX IF NOT EXISTS \"idx_{$indexPrefix}_current_name\" ON {$table} (\"current_name\")",
            "CREATE INDEX IF NOT EXISTS \"idx_{$indexPrefix}_rank\" ON {$table} (\"rank\")",
        ];
        foreach ($indexes as $sql) {
            $this->pdo->exec($sql);
        }
    }

    private function stagingTableName(): string
    {
        return substr($this->tableName, 0, 42) . '_staging_' . bin2hex(random_bytes(6));
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new RuntimeException("Identificador PostgreSQL no válido: {$identifier}");
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertIdentifier($identifier);

        return '"' . $identifier . '"';
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio {$directory}.");
        }
    }

    private function stripBom(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            return substr($line, 3);
        }

        return $line;
    }
}
