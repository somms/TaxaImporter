<?php

namespace Somms\BV2Observation\Source\Database;

use PDO;
use Somms\BV2Observation\Source\DataSourceInterface;

class DatabaseDataSource implements DataSourceInterface
{
    private $pdo;
    private $tableName;
    private string $speciesNameField;
    private string $authorField;
    private string $idField;

    public function __construct(PDO $pdo, $tableName, $speciesNameField, $authorField = '', $idField = '')
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->speciesNameField = $speciesNameField;
        $this->authorField = $authorField;
        $this->idField = $idField;
    }

    /**
     * @inheritDoc
     */
    public function getInputCollection()
    {
        // Realiza una consulta para obtener datos desde la base de datos
        $statement = $this->pdo->query("SELECT * FROM {$this->tableName}");
        return new DatabaseCollection($statement);
    }

    public function searchSpecies($speciesName, $author = '', $id = -1): DatabaseCollection
    {
        if(str_contains($speciesName, ' '))
        {
            // Si el nombre de especie contiene espacio es que viene listo para
            // consultar directamente
            $speciesName = $this->pdo->quote($speciesName);
            $whereClause = "{$this->speciesNameField} LIKE $speciesName";
        }
        else{
            // Si no tiene espacio haye que incluir la búsqueda de indet. y spec.
            $whereClause = " {$this->speciesNameField} LIKE
             ANY(ARRAY[". $this->pdo->quote($speciesName) . "," .
                $this->pdo->quote($speciesName . ' indet.')
                . ", " .
                $this->pdo->quote($speciesName . ' spec.')
                . "])";
        }
        $author = $author!='' ? $this->pdo->quote($author) : '';

        $sqlSentence = "SELECT * FROM {$this->tableName} WHERE $whereClause";
        $sqlSentence .= ($author != '' ? " AND {$this->authorField} LIKE $author" : '' );
        $sqlSentence .= ($id != -1 ? " AND {$this->idField} LIKE $id" : '' );
        $statement = $this->pdo->query($sqlSentence);
        return new DatabaseCollection($statement);
    }

    public function getItemName($item)
    {
        $result = false;
        // Aquí se selecciona la columna con la especie
        if(count($item)){
            $result = trim($item[$this->speciesNameField] . ' ' . ($this->authorField != '' ? $item[$this->authorField] : '' )) ;
        }

        return $result;
    }
}