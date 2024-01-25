<?php

namespace Somms\BV2Observation\Source\Null;

use PDO;
use Somms\BV2Observation\Source\Database\DatabaseDataSource;
use Somms\BV2Observation\Source\DataSourceInterface;

class NullDataSource implements DataSourceInterface
{

    public function __construct()
    {

    }

    /**
     * @inheritDoc
     */
    public function getInputCollection()
    {
        return new NullCollection();
    }

    public function getItemName($item)
    {
        $result = false;
        // Aquí se selecciona la columna con la especie
        if(count($item)){
            $result = trim($item['output_species_name_author']);
        }

        return $result;
    }
}