<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 18:30
 */

namespace Somms\BV2Observation\Processor;


use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Parser\SpeciesParser;
use Somms\BV2Observation\Provider\Forum4Images\CSV\Species4ImagesCSVSource;
use Somms\BV2Observation\Provider\POWO\POWOSpeciesProcessor;
use Somms\BV2Observation\Provider\POWO\POWOSpeciesParser;
use Somms\BV2Observation\Source\CSV\CSVSource;
use Somms\BV2Observation\Source\DataSourceInterface;

abstract class SpeciesProcessor extends Processor
{

    /**
     * @var SpeciesParser
     */
    protected $inputParser;
    /**
     * @var DataSourceInterface
     */
    protected $inputSource;
    /**
     * @var \SplFileObject
     */
    protected $okOutput;
    /**
     * @var \SplFileObject
     */
    protected $errorOutput;

    /**
     * @var ISpeciesParser
     */
    protected $sourceSpeciesParser;

    function __construct(ISpeciesParser $inputSpeciesParser, ISpeciesParser $sourceSpeciesParser, DataSourceInterface $inputSource, $okOutputFilePath, $errorOutputFilePath, $options = [])
    {
        $this->inputSource = $inputSource;
        $this->okOutput = new \SplFileObject($okOutputFilePath,'a');
        $this->errorOutput = new \SplFileObject($errorOutputFilePath,'a');
        $this->sourceSpeciesParser = $sourceSpeciesParser;
        parent::__construct($inputSpeciesParser, $options);

    }

    function errorOutput($inputRow)
    {
        $this->errorOutput->fputcsv($inputRow, CSVSource::DEFAULT_DELIMITER);
    }

    function discardedOutput($inputRow)
    {
        echo 'Entrada descartada, porque no cumple criterios de especie';
    }

    function okOutput($inputRow)
    {
        $this->okOutput->fputcsv($inputRow, CSVSource::DEFAULT_DELIMITER);
    }

    /**
     * @param $rawItemName
     * @param $inputRow
     *
     * @return mixed|null|Species
     */
    protected function preProcessRow($rawItemName, $inputRow)
    {
        $this->inputParser->setInput($rawItemName);
        return $this->inputParser->getSpecies();
    }

    protected function getItemName($inputRow)
    {
        $baseName = $this->inputSource->getItemName($inputRow);
        return $this->inputParser->preprocessInput($baseName);
    }

    abstract function getRemoteSpecies(Species $species);

    abstract function getRemoteRow(Species $species);

    /**
     * @param $inputRow
     * @param Species $species
     * @param array $options Set of options to send to the processor
     * @return mixed
     */
    protected function processItem(&$inputRow, &$species, $options = [])
    {
        $result = false;
        $species = $this->getRemoteSpecies($species);
        if($species->populateFromRemote($this->sourceSpeciesParser, $options)){
            if(PHP_SAPI == 'cli')
            {
                echo 'Especie encontrada: ' . $species->getRemoteScientificName() . "\n";
            }
            $result = $this->getRemoteRow($species);
        }
        else{
            if(PHP_SAPI == 'cli')
            {
                echo 'Especie no encontrada: ' . $species->getScientificName() . "\n";
            }
        }

        return $result;
    }
}