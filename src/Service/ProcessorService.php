<?php

namespace Somms\BV2Observation\Service;

use Somms\BV2Observation\Pipeline\Pipeline;
use Somms\BV2Observation\Pipeline\PipelineService;

class ProcessorService
{
    protected $pipelineService;
    public function __construct(PipelineService $pipelineService){
        $this->pipelineService = $pipelineService;
    }
    public function getProcessor(string $pipelineName){
        $pipeline = $this->pipelineService->getPipeline($pipelineName);
        $baseNamespace = "Somms\BV2Observation\\Provider\\";
        $processorClassName = $baseNamespace . $pipeline->getRemoteProcessorName();
        $speciesParserClassName = $baseNamespace . $pipeline->getInputParserName();
        $processor = new $processorClassName(
            new $speciesParserClassName(),
            $pipeline->getInputDatasource(),
            $pipeline->getOutputOk(),
            $pipeline->getOutputError(),
            $pipeline->getRemoteProcessorOptions()
        );
        return $processor;
    }
}