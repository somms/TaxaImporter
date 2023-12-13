<?php

namespace Somms\BV2Observation\Pipeline;

use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Source\DataSourceService;

class PipelineService
{
    private $configService;
    private $datasourceService;

    public function __construct(ConfigService $configService, DataSourceService $dataSourceService)
    {
        $this->configService = $configService;
        $this->datasourceService = $dataSourceService;
    }

    public function getPipeline(string $pipelineName): Pipeline
    {
        // Lógica para obtener dependencias y construir una instancia de Pipeline
        $pipelineConfig = $this->configService->loadPipelineConfig($pipelineName);
        if($pipelineConfig['default']['input']['type'] == 'datasource'){
            $datasource = $this->datasourceService->getDataSource($pipelineConfig['default']['input']['name']);
        }

        return new Pipeline(
            $pipelineName,
            $pipelineConfig['default']['remote']['processor'],
            $pipelineConfig['default']['remote']['options'] ?? [],
            $datasource,
            $pipelineConfig['default']['input']['parser'],
            $pipelineConfig['default']['output_ok']['path'],
            $pipelineConfig['default']['output_errors']['path']
        );
    }
}