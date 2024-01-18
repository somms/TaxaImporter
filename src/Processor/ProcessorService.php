<?php

namespace Somms\BV2Observation\Processor;

use Somms\BV2Observation\DataOutput\IDataOutputService;
use Somms\BV2Observation\Event\DataOutputEventSubscriber;
use Somms\BV2Observation\Pipeline\Pipeline;
use Somms\BV2Observation\Pipeline\PipelineService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProcessorService implements IProcessorService
{
    private EventDispatcherInterface $eventDispatcher;
    private array $processorCache = array();

    public function __construct(EventDispatcherInterface $eventDispatcher){
        $this->eventDispatcher = $eventDispatcher;
    }
    public function getProcessor(Pipeline $pipeline){

        if (isset($this->processorCache[spl_object_id($pipeline)]) &&
            $this->processorCache[spl_object_id($pipeline)] != null
        ){
            return $this->processorCache[spl_object_id($pipeline)];
        }

        $baseNamespace = "Somms\BV2Observation\\Provider\\";
        $processorClassName = $baseNamespace . $pipeline->getRemoteProcessorName();
        $speciesParserClassName = $baseNamespace . $pipeline->getInputParserName();

        $processor = new $processorClassName(
            new $speciesParserClassName(),
            $pipeline->getInputDatasource(),
            $this->eventDispatcher,
            $pipeline->getRemoteProcessorOptions()
        );

        $this->processorCache[spl_object_id($pipeline)] = $processor;

        return $processor;
    }
}