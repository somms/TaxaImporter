<?php

namespace Somms\BV2Observation\Provider\MDD;

use PDO;
use PDOStatement;
use Somms\BV2Observation\Source\DataSourceInterface;

/**
 * DataSource especializado para MDD cuando los datos están en base de datos.
 *
 * - Tabla principal de especies (ej. "mdd_species")
 * - Tabla de sinónimos (ej. "mdd_synonyms")
 *
 * Implementa DataSourceInterface para mantener compatibilidad,
 * pero además expone métodos específicos para búsquedas rápidas
 * aprovechando índices de la BD.
 */
class MDDSpeciesDatabaseDataSource implements DataSourceInterface
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

    /**
     * Implementación mínima para cumplir DataSourceInterface.
     * Devuelve todas las filas de la tabla de especies.
     *
     * MDDSpecies NO usará esto cuando el datasource sea de este tipo,
     * pero lo implementamos por compatibilidad.
     */
    public function getInputCollection()
    {
        $sql = sprintf('SELECT * FROM "%s"', $this->speciesTable);
        $stmt = $this->pdo->query($sql);

        // Puedes reutilizar tu DatabaseCollection si quieres;
        // aquí lo dejamos como un Iterator "crudo".
        return new \ArrayIterator($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * También por compatibilidad con DataSourceInterface.
     * Para MDD no es crítico, pero devolvemos "sciName + author" si existen.
     */
    public function getItemName($item)
    {
        if (!is_array($item) || empty($item)) {
            return false;
        }

        $sciName = $item['sciName'] ?? '';
        $author  = $item['authoritySpeciesAuthor'] ?? '';

        return trim($sciName . ' ' . $author);
    }

    /**
     * Busca una especie en la tabla principal por sciName normalizado (con guion bajo).
     *
     * @param string $normalizedSciName p.ej. "Myotis_myotis"
     * @return array|null               Fila completa de la tabla mdd_species o null si no se encuentra
     */
    public function findSpeciesBySciName(string $normalizedSciName): ?array
    {
        $sql = sprintf(
            'SELECT * FROM "%s" WHERE "sciName" = :sci LIMIT 1',
            $this->speciesTable
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sci' => $normalizedSciName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Comprueba si existe al menos una especie de un género dado (para casos "Genus spec.", etc.).
     */
    public function hasGenus(string $genus): bool
    {
        $sql = sprintf(
            'SELECT 1 FROM "%s" WHERE "genus" = :genus LIMIT 1',
            $this->speciesTable
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':genus' => $genus]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Comprueba si existe algún taxón superior igual a $taxonName en las columnas jerárquicas de MDD.
     * Se usa para los casos "Taxon indet."
     */
    public function hasHigherTaxon(string $taxonName): bool
    {
        $higherCols = [
            'subclass',
            'infraclass',
            'magnorder',
            'superorder',
            'order',
            'suborder',
            'infraorder',
            'parvorder',
            'superfamily',
            'family',
            'subfamily',
            'tribe',
            'subtribe',
        ];

        // Construimos un WHERE con OR en las columnas relevantes
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

    /**
     * Dado un nombre original de combinación (MDD_original_combination),
     * devuelve la fila aceptada de la tabla mdd_species.
     *
     * Equivalente a:
     *  1) Buscar en mdd_synonyms por MDD_original_combination
     *  2) Usar MDD_species_id para traer la especie aceptada de mdd_species
     *
     * @param string $originalCombination p.ej. "Myotis myotis"
     * @return array|null                 Fila de mdd_species o null
     */
    public function findAcceptedBySynonym(string $originalCombination): ?array
    {
        // 1) Buscar el sinónimo en la tabla de sinónimos
        $sqlSyn = sprintf(
            'SELECT "MDD_species_id", "MDD_species"
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

        $targetId = $synRow['MDD_species_id'] ?? null;
        if ($targetId === null || $targetId === '') {
            return null;
        }

        // 2) Buscar la especie aceptada en la tabla principal de especies
        $sqlSp = sprintf(
            'SELECT *
             FROM "%s"
             WHERE "id" = :id
             LIMIT 1',
            $this->speciesTable
        );

        $stmtSp = $this->pdo->prepare($sqlSp);
        $stmtSp->execute([':id' => $targetId]);
        $row = $stmtSp->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}
