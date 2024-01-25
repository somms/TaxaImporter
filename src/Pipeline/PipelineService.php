<?php

namespace Somms\BV2Observation\Pipeline;

use Somms\BV2Observation\DataOutput\DataOutputService;
use Somms\BV2Observation\DataOutput\IDataOutputService;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Source\DataSourceService;

class PipelineService
{
    private $configService;
    private $datasourceService;
    private IDataOutputService $dataOutputService;

    public function __construct(ConfigService $configService, DataSourceService $dataSourceService, IDataOutputService $dataOutputService)
    {
        $this->configService = $configService;
        $this->datasourceService = $dataSourceService;
        $this->dataOutputService = $dataOutputService;
    }

    public function buildPipeline(string $pipelineName, bool $isChild = false): Pipeline
    {
        // Lógica para obtener dependencias y construir una instancia de Pipeline
        $pipelineConfig = $this->configService->loadPipelineConfig($pipelineName);
        if($pipelineConfig['default']['input']['type'] == 'datasource' && !$isChild){
            $inputDatasource = $this->datasourceService->getDataSource($pipelineConfig['default']['input']['name']);
        }
        elseif($isChild || $pipelineConfig['default']['input']['type'] === null){
            $inputDatasource = $this->datasourceService->getDataSource('null');
        }

        if(isset($pipelineConfig['default']['remote']['options']['datasource'])){
            $pipelineConfig['default']['remote']['options']['datasource'] = $this->datasourceService->getDataSource($pipelineConfig['default']['remote']['options']['datasource']);
        }

        $okOutput = $pipelineConfig['default']['output_ok'];
        $errorOutput = $pipelineConfig['default']['output_errors'];


        return new Pipeline(
            $pipelineName,
            $pipelineConfig['default']['remote']['processor'],
            $pipelineConfig['default']['remote']['options'] ?? [],
            $inputDatasource,
            $pipelineConfig['default']['input']['parser'],
            $okOutput,
            $errorOutput
        );
    }
}