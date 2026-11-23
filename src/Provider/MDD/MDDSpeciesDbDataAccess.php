<?php

namespace Somms\BV2Observation\Provider\MDD;

use PDO;

class MDDSpeciesDbDataAccess implements MDDSpeciesDataAccessInterface
{
    private PDO $pdo;
    private string $speciesTable;
    private string $synonymTable;

    public function __construct(PDO $pdo, string $speciesTable = 'mdd_species', string $synonymTable = 'mdd_synonyms')
    {
        $this->pdo          = $pdo;
        $this->speciesTable = $speciesTable;
        $this->synonymTable = $synonymTable;
    }

    public function findBySciName(string $normalizedSciName): ?array
    {
        $sql = sprintf('SELECT * FROM "%s" WHERE "sciName" = :sci LIMIT 1', $this->speciesTable);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sci' => $normalizedSciName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function hasGenus(string $genus): bool
    {
        $sql = sprintf('SELECT 1 FROM "%s" WHERE "genus" = :genus LIMIT 1', $this->speciesTable);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':genus' => $genus]);

        return (bool) $stmt->fetchColumn();
    }

    public function hasHigherTaxon(string $taxonName): bool
    {
        $higherCols = [
            'subclass','infraclass','magnorder','superorder','order',
            'suborder','infraorder','parvorder','superfamily','family',
            'subfamily','tribe','subtribe',
        ];

        $conditions = [];
        foreach ($higherCols as $col) {
            $conditions[] = sprintf('"%s" = :taxon', $col);
        }

        $sql = sprintf(
            'SELECT 1 FROM "%s" WHERE %s LIMIT 1',
            $this->speciesTable,
            implode(' OR ', $conditions)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':taxon' => $taxonName]);

        return (bool) $stmt->fetchColumn();
    }

    public function findAcceptedBySynonym(string $originalCombination): ?array
    {
        $sqlSyn = sprintf(
            'SELECT "MDD_species_id"
             FROM "%s"
             WHERE "MDD_original_combination" = :comb
             LIMIT 1',
            $this->synonymTable
        );

        $stmtSyn = $this->pdo->prepare($sqlSyn);
        $stmtSyn->execute([':comb' => $originalCombination]);
        $synRow = $stmtSyn->fetch(PDO::FETCH_ASSOC);

        if ($synRow === false) {
            return null;
        }

        $id = $synRow['MDD_species_id'] ?? null;
        if (!$id) {
            return null;
        }

        $sqlSp = sprintf('SELECT * FROM "%s" WHERE "id" = :id LIMIT 1', $this->speciesTable);
        $stmtSp = $this->pdo->prepare($sqlSp);
        $stmtSp->execute([':id' => $id]);
        $row = $stmtSp->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getAllSpecies(): \Iterator
    {
        $sql = sprintf('SELECT * FROM "%s"', $this->speciesTable);
        $stmt = $this->pdo->query($sql);
        return new \ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAllSynonyms(): \Iterator
    {
        $sql = sprintf('SELECT * FROM "%s"', $this->synonymTable);
        $stmt = $this->pdo->query($sql);
        return new \ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}