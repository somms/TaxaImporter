<?php

namespace Somms\BV2Observation\Source;

use InvalidArgumentException;
use PDO;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Source\CSV\CSVSource;
use Somms\BV2Observation\Source\Database\DatabaseDataSource;
use Somms\BV2Observation\Source\Null\NullDataSource;

class DataSourceService
{
    private $configService;
    private $datasourceCache;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function getDataSource($datasourceName)
    {
        if(!isset($this->datasourceCache[$datasourceName])) {


            $config = $this->configService->loadDatasourceConfig($datasourceName)[$datasourceName];
            $sourceType = $config['type'];

            switch ($sourceType) {
                case 'csv':
                    $datasource =  new CSVSource(
                        $config['path'], $config['key_fieldname'],
                        $config['author_fieldname'] ?? '',
                        $config['id_fieldname'] ?? '',
                        $config['delimiter']
                    );
                    break;
                case 'database':
                    $datasource = new DatabaseDataSource(
                        new PDO($config['dsn'], $config['username'], $config['password']),
                        $config['table_name'],
                        $config['key_fieldname'],
                        $config['author_fieldname'] ?? '',
                        $config['id_fieldname'] ?? ''
                    );
                    break;
                // Puedes agregar más casos según tus necesidades
                case 'null':
                    $datasource = new NullDataSource();
                    break;
                default:
                    throw new InvalidArgumentException('Tipo de fuente de datos no válido');
            }
            $this->datasourceCache[$datasourceName] = $datasource;
        }
        else{
            $datasource = $this->datasourceCache[$datasourceName];
        }
        return $datasource;
    }
}