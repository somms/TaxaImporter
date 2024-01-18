<?php

namespace Somms\BV2Observation\Processor;

use Somms\BV2Observation\Pipeline\Pipeline;

interface IProcessorService
{
    public function getProcessor(Pipeline $pipeline);
}