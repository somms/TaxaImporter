<?php

namespace Somms\BV2Observation\DataOutput;

use Somms\BV2Observation\Processor\Processor;

interface IDataOutputService
{
    public function getDataOutput(array $options, Processor $processor = null ) : DataOutputInterface;
}