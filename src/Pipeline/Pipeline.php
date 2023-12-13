<?php

namespace Somms\BV2Observation\Pipeline;

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
    protected string $outputOk;
    protected string $outputError;

    public function __construct(string $pipelineName, string $remoteProcessorName, array $remoteProcessorOptions, DataSourceInterface $inputDatasource, string $inputParserName, string $outputOk, string $outputError)
    {
        $this->pipelineName = $pipelineName;
        $this->remoteProcessorName = $remoteProcessorName;
        $this->remoteProcessorOptions = $remoteProcessorOptions;
        $this->inputDatasource = $inputDatasource;
        $this->inputParser = $inputParserName;
        $this->outputOk = $outputOk;
        $this->outputError = $outputError;
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
     * @return mixed
     */
    public function getOutputOk()
    {
        return $this->outputOk;
    }

    /**
     * @return mixed
     */
    public function getOutputError()
    {
        return $this->outputError;
    }



}