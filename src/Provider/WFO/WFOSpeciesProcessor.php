<?php

namespace Somms\BV2Observation\Provider\WFO;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WFOSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor
{
    public function __construct(
        ISpeciesParser $inputSpeciesParser,
        DataSourceInterface $inputSource,
        EventDispatcherInterface $eventDispatcher,
        $options = [],
    ) {
        parent::__construct($inputSpeciesParser, new WFOSpeciesParser(), $inputSource, $eventDispatcher, $options);
    }

    function getRemoteSpecies(Species $species)
    {
        return new WFOSpecies($species->getScientificName(), $species->getCommonName(), $species->getAuthor());
    }

    function getRemoteRow(Species $species)
    {
        $result = [];
        $result['wfo_id'] = $species->getRemoteId();
        $result['wfo_species_name'] = $species->getRemoteScientificName();
        $result['wfo_species_author'] = $species->getRemoteAuthor();
        $result['output_species_name_author'] = trim($species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor());

        return $result;
    }
}
