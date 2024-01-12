<?php

namespace Somms\BV2Observation\Provider\POWO;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Parser\SpeciesParser;
use Somms\BV2Observation\Provider\Forum4Images\CSV\Species4ImagesCSVSource;
use Somms\BV2Observation\Provider\Forum4Images\SpeciesStringParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class POWOSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor
{
    function __construct(ISpeciesParser $inputSpeciesParser, DataSourceInterface $inputSource, EventDispatcherInterface $eventDispatcher, $options = [])
    {
        parent::__construct($inputSpeciesParser, new POWOSpeciesParser(), $inputSource, $eventDispatcher, $options);

    }



    function getRemoteSpecies(Species $species)
    {
        return new POWOSpecies($species->getScientificName(), $species->getCommonName(), $species->getAuthor());
    }

    function getRemoteRow(Species $species)
    {
        $result = [];
        $result['powo_species_id'] = $species->getRemoteId();
        $result['powo_species_name'] = $species->getRemoteScientificName();
        $result['powo_species_author'] = $species->getRemoteAuthor();
        return $result;
    }
}