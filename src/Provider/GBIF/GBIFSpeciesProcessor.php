<?php

namespace Somms\BV2Observation\Provider\GBIF;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Parser\SpeciesParser;
use Somms\BV2Observation\Provider\Forum4Images\CSV\Species4ImagesCSVSource;
use Somms\BV2Observation\Provider\Forum4Images\SpeciesStringParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GBIFSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor
{
    function __construct(ISpeciesParser $inputSpeciesParser, DataSourceInterface $inputSource, EventDispatcherInterface $eventDispatcher, $options = [])
    {
        parent::__construct($inputSpeciesParser, new GBIFSpeciesParser(), $inputSource, $eventDispatcher, $options);

    }
    function getRemoteSpecies(Species $species)
    {
        return new GBIFSpecies($species->getScientificName(),$species->getCommonName(), $species->getAuthor());

    }

    function getRemoteRow(Species $species)
    {
        $result = [];
        $result['gbif_species_id'] = $species->getRemoteId();
        $result['gbif_species_name'] = $species->getRemoteScientificName();
        $result['gbif_species_name_author'] = $species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor();
        return $result;
    }
}