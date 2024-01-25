<?php

namespace Somms\BV2Observation\Pipeline;

use Somms\BV2Observation\DataOutput\DataOutputService;
use Somms\BV2Observation\DataOutput\IDataOutputService;
use Somms\BV2Observation\Event\DataOutputEventSubscriber;
use Somms\BV2Observation\Processor\Processor;
use Somms\BV2Observation\Processor\ProcessorService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class PipelineManager
{
    private IDataOutputService $dataOutputService;
    private PipelineService $pipelineService;
    private static array $pipelineCache = array();
    private EventDispatcherInterface $eventDispatcher;
    private ProcessorService $processorService;

    function __construct(DataOutputService $dataOutputService, PipelineService $pipelineService, ProcessorService $processorService, EventDispatcherInterface $eventDispatcher){
        $this->dataOutputService = $dataOutputService;
        $this->pipelineService = $pipelineService;
        $this->eventDispatcher = $eventDispatcher;
        $this->processorService = $processorService;
    }

    /**
     * @param $pipelineName string The data pipeline name to be loaded
     * @param $isChild bool If this data pipeline is a child of another one
     * @return Pipeline
     * @throws \Exception
     */
    public function getPipeline( string $pipelineName, bool $isChild = false): Pipeline{
        // Si está en la caché lo devolvemos
        if(isset(self::$pipelineCache[$pipelineName]) && self::$pipelineCache[$pipelineName]!= null){
            return self::$pipelineCache[$pipelineName];
        }

        $pipeline = $this->pipelineService->buildPipeline($pipelineName, $isChild);
        self::$pipelineCache[$pipelineName . $isChild] = $pipeline;

        $processor = $this->processorService->getProcessor($pipeline);

        // Esto lo hacemos aquí y no en el dataOutputService para evitar referencias
        // circulares de dependencias con el PipelineService y ProcessorService
        // Almacena el "Processor" que necesita el DataOutput de errores (si es que tiene)
        $errorProcessor = $this->getDataOutputProcessor($pipeline->getOutputErrorConfig());

        // Almacena el "Processor" que necesita el DataOutput ok (si es que tiene)
        $okProcessor = $this->getDataOutputProcessor($pipeline->getOutputOkConfig());

        // Instanciar y registrar el DataOutputEventSubscriber
        $dataoutputEventSubscriber = new DataOutputEventSubscriber(
            $processor,
            $this->dataOutputService->getDataOutput($pipeline->getOutputOkConfig(), $okProcessor),
            $this->dataOutputService->getDataOutput($pipeline->getOutputErrorConfig(), $errorProcessor),
            $this->dataOutputService->getDataOutput($pipeline->getOutputErrorConfig(), $errorProcessor)
        );

        $this->eventDispatcher->addSubscriber($dataoutputEventSubscriber);
        return $pipeline;
    }

    private function getDataOutputProcessor($dataOutputConfig): Processor|null{
        if($dataOutputConfig['type'] != 'pipeline'){
            return null;
        }
        $pipeline = $this->getPipeline($dataOutputConfig['path'], true);
        return $this->processorService->getProcessor($pipeline);

    }

}