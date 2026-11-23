<?php

namespace Somms\BV2Observation\Provider\MDD;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MDDSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor
{
    public function __construct(
        ISpeciesParser $inputSpeciesParser,
        DataSourceInterface $inputSource,
        EventDispatcherInterface $eventDispatcher,
        $options = []
    ) {
        parent::__construct(
            $inputSpeciesParser,
            new MDDSpeciesParser(),
            $inputSource,
            $eventDispatcher,
            $options
        );
    }

    public function getRemoteSpecies(Species $species)
    {
        // For MDD, we just pass the scientific name and author
        return new MDDSpecies(
            $species->getScientificName(),
            $species->getCommonName(),
            $species->getAuthor()
        );
    }

    public function getRemoteRow(Species $species)
    {
        $result = [];
        $result['mdd_species_id']   = $species->getRemoteId();
        $result['mdd_species_name'] = $species->getRemoteScientificName();
        $result['mdd_author']       = $species->getRemoteAuthor();
        $result['output_species_name_author'] = trim(
            $species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor()
        );

        return $result;
    }
}
