<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Provider\MycoBank;

use PDO;
use RuntimeException;

final class MycoBankSpeciesDbDataAccess
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'mycobank_species'
    ) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->tableName)) {
            throw new RuntimeException("Nombre de tabla MycoBank no válido: {$this->tableName}");
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function findResolvedByName(string $scientificName): ?array
    {
        $table = $this->quotedTable();
        $statement = $this->pdo->prepare(<<<SQL
            SELECT *
            FROM {$table}
            WHERE "taxon_name" = :name
            ORDER BY
                CASE
                    WHEN "mycobank_number" IS NOT DISTINCT FROM "current_mycobank_number" THEN 0
                    ELSE 1
                END,
                "id"
            LIMIT 1
            SQL);
        $statement->execute([':name' => trim($scientificName)]);
        $source = $statement->fetch(PDO::FETCH_ASSOC);
        if ($source === false) {
            return null;
        }

        $currentNumber = $source['current_mycobank_number'] ?? null;
        $sourceNumber = $source['mycobank_number'] ?? null;
        $isSynonym = $currentNumber !== null
            && $currentNumber !== ''
            && (string) $currentNumber !== (string) $sourceNumber;

        if ($isSynonym) {
            $accepted = $this->findCurrentByNumber((string) $currentNumber);
            if ($accepted !== null) {
                $accepted['synonym'] = true;
                $accepted['matched_name'] = $source['taxon_name'];

                return $accepted;
            }
        }

        $source['synonym'] = $isSynonym;
        $source['matched_name'] = $source['taxon_name'];
        if ($isSynonym && !empty($source['current_name'])) {
            $source['taxon_name'] = $source['current_name'];
            $source['mycobank_number'] = $currentNumber;
            $source['authors_abbreviated'] = '';
        }

        return $source;
    }

    public function hasTaxon(string $taxonName): bool
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT 1 FROM %s WHERE "taxon_name" = :name LIMIT 1',
            $this->quotedTable()
        ));
        $statement->execute([':name' => trim($taxonName)]);

        return (bool) $statement->fetchColumn();
    }

    private function findCurrentByNumber(string $mycoBankNumber): ?array
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE "mycobank_number" = :number ORDER BY "id" LIMIT 1',
            $this->quotedTable()
        ));
        $statement->execute([':number' => $mycoBankNumber]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function quotedTable(): string
    {
        return '"' . $this->tableName . '"';
    }
}
