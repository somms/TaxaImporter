<?php

namespace Somms\BV2Observation\DataOutput;

use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Pipeline\Pipeline;
use Somms\BV2Observation\Processor\Processor;

class PipelineDataOutput implements DataOutputInterface
{
    protected Processor $processor;

    public function __construct(Processor $processor){
        $this->processor = $processor;
    }
    public function put($inputRow)
    {
        return $this->processor->processRow($inputRow, $this->processor->options);
    }
}