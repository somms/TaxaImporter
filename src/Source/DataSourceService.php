<?php

namespace Somms\BV2Observation\Source;

use InvalidArgumentException;
use PDO;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Source\CSV\CSVSource;
use Somms\BV2Observation\Source\Database\DatabaseDataSource;

class DataSourceService
{
    private $configService;
    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function getDataSource($datasourceName)
    {
        $config = $this->configService->loadDatasourceConfig($datasourceName)[$datasourceName];
        $sourceType = $config['type'];

        switch ($sourceType) {
            case 'csv':
                return new CSVSource(
                    $config['path'], $config['key_fieldname'], $config['delimiter']
                );
            case 'database':
                return new DatabaseDataSource(
                    new PDO($config['dsn'], $config['username'], $config['password']),
                    $config['table_name'],
                    $config['key_fieldname']
                );
            // Puedes agregar más casos según tus necesidades
            default:
                throw new InvalidArgumentException('Tipo de fuente de datos no válido');
        }
    }
}