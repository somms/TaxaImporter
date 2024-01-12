<?php

namespace Somms\BV2Observation\Service;

use Somms\BV2Observation\Pipeline\Pipeline;

interface IProcessorService
{
    public function getProcessor(Pipeline $pipeline);
}