<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Import;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Importa listados de especies de Observation.org (CSV o SQLite)
 * a la tabla local obs_species, reemplazando solo el grupo indicado.
 */
final class ObservationSpeciesImporter
{
    public const SPECIES_GROUPS = [
        1 => 'Aves',
        2 => 'Mamíferos',
        3 => 'Reptiles y Anfibios',
        4 => 'Mariposas',
        5 => 'Libélulas (Odonata)',
        6 => 'Insectos (otras familias)',
        7 => 'Moluscos (Mollusca)',
        8 => 'Polillas',
        9 => 'Peces',
        10 => 'Plantas',
        11 => 'Setas',
        12 => 'Musgos y Líquenes',
        13 => 'Artrópodos (otras familias)',
        14 => 'Ortópteros (Orthoptera)',
        15 => 'Hemípteros (Hemiptera)',
        16 => 'Escarabajos (Coleoptera)',
        17 => 'Himenópteros (Hymenoptera)',
        18 => 'Dípteros (Diptera)',
        19 => 'Algas',
        20 => 'Otros invertebrados',
        30 => 'Molestias',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName,
    ) {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function import(string $sourceFile, ?int $speciesGroupId = null): int
    {
        if (!is_file($sourceFile) || !is_readable($sourceFile)) {
            throw new RuntimeException("No se puede leer el fichero: {$sourceFile}");
        }

        $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));
        return match ($extension) {
            'sqlite', 'db', 'sqlite3' => $this->importSqlite($sourceFile, $speciesGroupId),
            'csv' => $this->importCsv($sourceFile, $speciesGroupId),
            default => throw new RuntimeException(
                "Formato no soportado: .{$extension}. Use CSV o SQLite de Observation.org."
            ),
        };
    }

    public function importSqlite(string $sqliteFile, ?int $speciesGroupId = null): int
    {
        $sqlite = new PDO('sqlite:' . $sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->assertSqliteSchema($sqlite);
        $detectedGroupId = $this->detectSqliteGroupId($sqlite);
        $speciesGroupId = $speciesGroupId ?? $detectedGroupId;
        if ($speciesGroupId === null) {
            throw new RuntimeException('No se pudo determinar speciesgroup_id desde el SQLite.');
        }
        if ($detectedGroupId !== null && $detectedGroupId !== $speciesGroupId) {
            throw new RuntimeException(
                "El SQLite pertenece al grupo {$detectedGroupId}, pero se solicitó {$speciesGroupId}."
            );
        }

        $groupName = self::SPECIES_GROUPS[$speciesGroupId]
            ?? $this->detectSqliteGroupName($sqlite)
            ?? (string) $speciesGroupId;

        $familyById = [];
        foreach ($sqlite->query('SELECT id, scientific_name FROM family') as $familyRow) {
            $familyById[(string) $familyRow['id']] = $this->nullableString($familyRow['scientific_name'] ?? null);
        }

        $statement = $sqlite->prepare(<<<SQL
            SELECT
                s.id,
                s.name,
                s.scientific_name,
                s.type,
                s.parent,
                s.euring,
                s.authority,
                s.rank,
                st.name AS type_name
            FROM species s
            LEFT JOIN species_type st ON st.id = s.type
            WHERE s."group" = :group_id
            ORDER BY s.id
            SQL);
        $statement->execute([':group_id' => $speciesGroupId]);

        $rows = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $rankKey = isset($row['rank']) && $row['rank'] !== '' ? (string) $row['rank'] : null;
            $rows[] = [
                'id' => (int) $row['id'],
                'name_common' => $this->nullableString($row['name'] ?? null),
                'name_scientific' => $this->nullableString($row['scientific_name'] ?? null),
                'freq' => null,
                'speciesgroup_id' => $speciesGroupId,
                'SPECIESGROUP' => $groupName,
                'type_name' => $this->nullableString($row['type_name'] ?? null),
                'euring' => (int) ($row['euring'] ?? 0),
                'family' => $rankKey !== null ? ($familyById[$rankKey] ?? null) : null,
                'AUTHOR' => $this->nullableString($row['authority'] ?? null),
                'status' => null,
                'refer_to' => (int) ($row['parent'] ?? 0),
            ];
        }

        return $this->replaceGroupRows($speciesGroupId, $rows);
    }

    public function importCsv(string $csvFile, ?int $speciesGroupId = null): int
    {
        if ($speciesGroupId === null) {
            throw new RuntimeException('Para CSV es obligatorio indicar speciesgroup_id.');
        }

        $handle = fopen($csvFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException("No se puede abrir el CSV: {$csvFile}");
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new RuntimeException('El CSV está vacío.');
            }
            $firstLine = $this->stripBom($firstLine);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $header = array_map(
                static fn($value) => trim((string) $value),
                str_getcsv($firstLine, $delimiter, '"', '\\')
            );

            $columnMap = [
                'id' => array_search('id', $header, true),
                'name' => array_search('name', $header, true),
                'scientific_name' => array_search('scientific name', $header, true),
                'type' => array_search('type', $header, true),
                'parent_species_id' => array_search('parent species id', $header, true),
                'family' => array_search('family', $header, true),
                'group' => array_search('group', $header, true),
                'authority' => array_search('authority', $header, true),
                'rarity' => array_search('rarity', $header, true),
                'status' => array_search('status', $header, true),
                'euring' => array_search('euring', $header, true),
            ];

            if ($columnMap['id'] === false || $columnMap['scientific_name'] === false) {
                throw new RuntimeException('El CSV no tiene las columnas id / scientific name.');
            }

            $groupName = self::SPECIES_GROUPS[$speciesGroupId] ?? (string) $speciesGroupId;
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }
                $csvGroup = $columnMap['group'] !== false
                    ? $this->nullableString($row[$columnMap['group']] ?? null)
                    : null;
                $rows[] = [
                    'id' => (int) ($row[$columnMap['id']] ?? 0),
                    'name_common' => $columnMap['name'] !== false
                        ? $this->nullableString($row[$columnMap['name']] ?? null)
                        : null,
                    'name_scientific' => $this->nullableString($row[$columnMap['scientific_name']] ?? null),
                    'freq' => ($columnMap['rarity'] !== false && ($row[$columnMap['rarity']] ?? '') !== '')
                        ? (int) $row[$columnMap['rarity']]
                        : null,
                    'speciesgroup_id' => $speciesGroupId,
                    'SPECIESGROUP' => $csvGroup ?? $groupName,
                    'type_name' => $columnMap['type'] !== false
                        ? $this->nullableString($row[$columnMap['type']] ?? null)
                        : null,
                    'euring' => ($columnMap['euring'] !== false && ($row[$columnMap['euring']] ?? '') !== '')
                        ? (int) $row[$columnMap['euring']]
                        : 0,
                    'family' => $columnMap['family'] !== false
                        ? $this->nullableString($row[$columnMap['family']] ?? null)
                        : null,
                    'AUTHOR' => $columnMap['authority'] !== false
                        ? $this->nullableString($row[$columnMap['authority']] ?? null)
                        : null,
                    'status' => $columnMap['status'] !== false
                        ? $this->nullableString($row[$columnMap['status']] ?? null)
                        : null,
                    'refer_to' => ($columnMap['parent_species_id'] !== false && ($row[$columnMap['parent_species_id']] ?? '') !== '')
                        ? (int) $row[$columnMap['parent_species_id']]
                        : 0,
                ];
            }
        } finally {
            fclose($handle);
        }

        return $this->replaceGroupRows($speciesGroupId, $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function replaceGroupRows(int $speciesGroupId, array $rows): int
    {
        if ($rows === []) {
            throw new RuntimeException("No hay filas para importar en el grupo {$speciesGroupId}.");
        }

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare(
                "DELETE FROM {$this->tableName} WHERE speciesgroup_id = :speciesgroup_id"
            );
            $delete->execute([':speciesgroup_id' => $speciesGroupId]);
            $deleted = $delete->rowCount();
            echo "Eliminadas {$deleted} filas previas del grupo {$speciesGroupId}.\n";

            $inserted = $this->insertRowsBatched($rows);
            $this->pdo->commit();
            return $inserted;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertRowsBatched(array $rows, int $batchSize = 500): int
    {
        $columns = [
            'id', 'name_common', 'name_scientific', 'protection_level', 'freq', 'speciesgroup_id', 'SPECIESGROUP',
            'type', 'type_name', 'euring', 'pons', 'SEARCHKEY', 'family', 'AUTHOR', 'checklist', 'status',
            'refer_to', 'plantlistid', 'name_order',
        ];
        $columnSql = implode(', ', $columns);
        $inserted = 0;

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            $placeholders = [];
            $params = [];
            foreach ($chunk as $i => $row) {
                $prefix = "p{$i}_";
                $placeholders[] = '(' . implode(', ', array_map(
                    static fn(string $column): string => ':' . $prefix . $column,
                    $columns
                )) . ')';
                $params[$prefix . 'id'] = $row['id'];
                $params[$prefix . 'name_common'] = $row['name_common'];
                $params[$prefix . 'name_scientific'] = $row['name_scientific'];
                $params[$prefix . 'protection_level'] = null;
                $params[$prefix . 'freq'] = $row['freq'];
                $params[$prefix . 'speciesgroup_id'] = $row['speciesgroup_id'];
                $params[$prefix . 'SPECIESGROUP'] = $row['SPECIESGROUP'];
                $params[$prefix . 'type'] = null;
                $params[$prefix . 'type_name'] = $row['type_name'];
                $params[$prefix . 'euring'] = $row['euring'];
                $params[$prefix . 'pons'] = 0;
                $params[$prefix . 'SEARCHKEY'] = null;
                $params[$prefix . 'family'] = $row['family'];
                $params[$prefix . 'AUTHOR'] = $row['AUTHOR'];
                $params[$prefix . 'checklist'] = null;
                $params[$prefix . 'status'] = $row['status'];
                $params[$prefix . 'refer_to'] = $row['refer_to'];
                $params[$prefix . 'plantlistid'] = null;
                $params[$prefix . 'name_order'] = null;
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $this->tableName,
                $columnSql,
                implode(', ', $placeholders)
            );
            $this->pdo->prepare($sql)->execute($params);
            $inserted += count($chunk);
            echo "  {$inserted} filas insertadas...\n";
        }

        return $inserted;
    }

    private function assertSqliteSchema(PDO $sqlite): void
    {
        $tables = $sqlite->query(
            "SELECT name FROM sqlite_master WHERE type='table'"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach (['species', 'species_group', 'species_type', 'family'] as $required) {
            if (!in_array($required, $tables, true)) {
                throw new RuntimeException("El SQLite no contiene la tabla requerida '{$required}'.");
            }
        }
    }

    private function detectSqliteGroupId(PDO $sqlite): ?int
    {
        $value = $sqlite->query('SELECT id FROM species_group LIMIT 1')->fetchColumn();
        if ($value === false || $value === null || $value === '') {
            $value = $sqlite->query('SELECT "group" FROM species LIMIT 1')->fetchColumn();
        }
        return ($value === false || $value === null || $value === '') ? null : (int) $value;
    }

    private function detectSqliteGroupName(PDO $sqlite): ?string
    {
        $value = $sqlite->query('SELECT name FROM species_group LIMIT 1')->fetchColumn();
        return ($value === false || $value === null || $value === '') ? null : (string) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @param list<null|string> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }
}
