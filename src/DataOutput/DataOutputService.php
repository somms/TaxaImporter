<?php

namespace Somms\BV2Observation\DataOutput;

use Composer\XdebugHandler\Process;
use Somms\BV2Observation\Pipeline\PipelineService;
use Somms\BV2Observation\Processor\Processor;
use Somms\BV2Observation\Processor\IProcessorService;
use Somms\BV2Observation\Processor\ProcessorService;
use splitbrain\phpcli\Exception;

class DataOutputService implements IDataOutputService
{
    private $dataOutputCache;

    public function __construct(){
    }

    /**
     * @param array $options
     * @return DataOutputInterface
     *
     * Returns a DataOutputInterfaced based in the options array.
     *
     * Options array can have two fields:
     *  - type: csv o pipeline
     *  - name: filename including path or pipeline name
     */
    public function getDataOutput(array $options, Processor $processor = null) : DataOutputInterface{
        $key = serialize($options);
        if(isset($this->dataOutputCache[$key]) && $this->dataOutputCache[$key] != null){
            return $this->dataOutputCache[$key];
        }

        if( !isset($options['type']) || !in_array($options['type'], ['csv', 'pipeline']) || !isset($options['path'])){
            throw new \Exception('The received options for a DataOutput are not well formatted. ' . serialize($options));
        }
        $result = null;
        switch ($options['type']) {
            case 'csv':
                $result = new CSVDataOutput($options['path'], 'a');
                break;
            case 'pipeline':
                $result = new PipelineDataOutput($processor);
        }

        $this->dataOutputCache[$key] = $result;

        return $result;
    }
}