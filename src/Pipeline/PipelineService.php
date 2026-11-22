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

        if (isset($pipelineConfig['default']['remote']['options']['datasource'])) {
            $datasourceOption = $pipelineConfig['default']['remote']['options']['datasource'];

            // Mantener compatibilidad: si es un string, se resuelve a un único DataSourceInterface
            if (!is_array($datasourceOption)) {
                $pipelineConfig['default']['remote']['options']['datasource'] =
                    $this->datasourceService->getDataSource($datasourceOption);
            } else {
                // Nuevo comportamiento: permitir múltiples datasources nombrados
                //   datasource:
                //     main: some_datasource_name
                //     synonyms: other_datasource_name
                $resolved = [];
                foreach ($datasourceOption as $name => $dsName) {
                    // Permitimos dejar ya inyectados objetos DataSourceInterface en configuración avanzada
                    if ($dsName instanceof \Somms\BV2Observation\Source\DataSourceInterface) {
                        $resolved[$name] = $dsName;
                    } else {
                        $resolved[$name] = $this->datasourceService->getDataSource($dsName);
                    }
                }
                $pipelineConfig['default']['remote']['options']['datasource'] = $resolved;
            }
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