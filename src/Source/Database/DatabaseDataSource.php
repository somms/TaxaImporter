<?php

namespace Somms\BV2Observation\Source\Database;

use PDO;
use Somms\BV2Observation\Source\DataSourceInterface;

class DatabaseDataSource implements DataSourceInterface
{
    private $pdo;
    private $tableName;

    public function __construct(PDO $pdo, $tableName, $speciesNameField)
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
        $this->speciesNameField = $speciesNameField;
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

    public function getItemName($item)
    {
        $result = false;
        // Aquí se selecciona la columna con la especie
        if(count($item)){
            $result = $item[$this->speciesNameField];
        }

        return $result;
    }
}