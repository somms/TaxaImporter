<?php

namespace Somms\BV2Observation\Provider\GNames;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GNSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor
{
    function __construct(ISpeciesParser $inputSpeciesParser, DataSourceInterface $inputSource, EventDispatcherInterface $eventDispatcher, $options = [])
    {
        parent::__construct($inputSpeciesParser, new GNSpeciesParser(), $inputSource, $eventDispatcher, $options);

    }
    function getRemoteSpecies(Species $species)
    {
        return new GNSpecies($species->getScientificName(),$species->getCommonName(), $species->getAuthor());

    }

    function getRemoteRow(Species $species)
    {
        $result = [];
        $result['gn_species_id'] = $species->getRemoteId();
        $result['gn_species_name'] = $species->getRemoteScientificName();
        $result['output_species_name_author'] = trim($species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor());
        return $result;
    }
}