<?php

namespace Somms\BV2Observation\Service;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class ConfigService
{
    private $configFolder;
    private $configContent = array();

    /**
     *
     * @param string $configFolder
     */
    #[Inject]
    public function __construct(string $configFolder)
    {
        $this->configFolder = $configFolder;
    }

    public function loadConfig($configType, $configName)
    {

        return !isset($this->configContent[$configType][$configName]) ?
            $this->loadConfigFromFile($configType, $configName) :
            $this->configContent[$configType][$configName];
    }
    public function loadPipelineConfig($pipelineName)
    {
        return $this->loadConfig("pipelines", $pipelineName);

    }

    public function loadDatasourceConfig($datasourceName){
        return $this->loadConfig('datasources', $datasourceName);
    }

    protected function loadConfigFromFile($configType, $configName){
        $filePath = $this->configFolder . "/$configType/$configName.yml";

        if (file_exists($filePath)) {
            return Yaml::parseFile($filePath);
        }

        throw new InvalidArgumentException("Configuración de $configType para '$configName' no encontrada.");
    }
}