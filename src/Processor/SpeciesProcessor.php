<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 18:30
 */

namespace Somms\BV2Observation\Processor;


use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Event\ProcessorEvent;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Parser\SpeciesParser;
use Somms\BV2Observation\Provider\Forum4Images\CSV\Species4ImagesCSVSource;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @var ISpeciesParser
     */
    protected $sourceSpeciesParser;
    protected $eventDispatcher;

    function __construct(ISpeciesParser $inputSpeciesParser, ISpeciesParser $sourceSpeciesParser, DataSourceInterface $inputSource, EventDispatcherInterface $eventDispatcher, $options = [])
    {
        $this->inputSource = $inputSource;
        $this->eventDispatcher = $eventDispatcher;
        $this->sourceSpeciesParser = $sourceSpeciesParser;
        parent::__construct($inputSpeciesParser, $options);

    }

    function errorOutput($inputRow)
    {
        $event = new ProcessorEvent($inputRow, $this);
        $this->eventDispatcher->dispatch($event, ProcessorEvent::TYPE_ERROR);
    }

    function discardedOutput($inputRow)
    {
        echo "\n". 'Entrada descartada, porque no cumple criterios de especie.' . "\n";
        $inputRow['discarded'] = 1;
        $event = new ProcessorEvent($inputRow, $this);
        $this->eventDispatcher->dispatch($event, ProcessorEvent::TYPE_DISMISSED);

    }

    function okOutput($inputRow)
    {
        $event = new ProcessorEvent($inputRow, $this);
        $this->eventDispatcher->dispatch($event, ProcessorEvent::TYPE_OK);
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
        if($species != null && $species->populateFromRemote($this->sourceSpeciesParser, $options)){
            if(PHP_SAPI == 'cli')
            {
                echo " -> \033[32m" . $species->getRemoteScientificName() . "\033[0m\n";
            }
            $result = $this->getRemoteRow($species);
        }
        else{
            if(PHP_SAPI == 'cli')
            {
                echo " -> \033[31m" . $species->getScientificName() . "\033[0m\n";
            }
        }

        return $result;
    }
}