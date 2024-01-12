<?php

namespace Somms\BV2Observation\Pipeline;

use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\DataOutput\DataOutputService;
use Somms\BV2Observation\Event\DataOutputEventSubscriber;
use Somms\BV2Observation\Parser\IParser;
use Somms\BV2Observation\Parser\IProviderParser;
use Somms\BV2Observation\Processor\Processor;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Source\DataSourceInterface;
use Somms\BV2Observation\Source\DataSourceService;

class Pipeline
{
    protected string $pipelineName;
    protected string $remoteProcessorName;
    protected array $remoteProcessorOptions;
    protected DataSourceInterface $inputDatasource;
    protected string $inputParser;
    protected array $outputOkConfig;
    protected array $outputErrorConfig;

    public function __construct(string $pipelineName, string $remoteProcessorName, array $remoteProcessorOptions, DataSourceInterface $inputDatasource, string $inputParserName, array $outputOkConfig, array $outputErrorConfig)
    {
        $this->pipelineName = $pipelineName;
        $this->remoteProcessorName = $remoteProcessorName;
        $this->remoteProcessorOptions = $remoteProcessorOptions;
        $this->inputDatasource = $inputDatasource;
        $this->inputParser = $inputParserName;
        $this->outputOkConfig = $outputOkConfig;
        $this->outputErrorConfig = $outputErrorConfig;
    }

    /**
     * @return string
     */
    public function getRemoteProcessorName()
    {
        return $this->remoteProcessorName;
    }

    /**
     * @return array
     */
    public function getRemoteProcessorOptions(): array
    {
        return $this->remoteProcessorOptions;
    }

    /**
     * @return IProviderParser
     */
    public function getRemoteParser()
    {
        return $this->remoteParser;
    }

    /**
     * @return mixed
     */
    public function getInputDatasource()
    {
        return $this->inputDatasource;
    }

    /**
     * @return mixed
     */
    public function getInputParserName()
    {
        return $this->inputParser;
    }

    /**
     * @return array
     */
    public function getOutputOkConfig() : array
    {
        return $this->outputOkConfig;
    }

    /**
     * @return array
     */
    public function getOutputErrorConfig() : array
    {
        return $this->outputErrorConfig;
    }



}