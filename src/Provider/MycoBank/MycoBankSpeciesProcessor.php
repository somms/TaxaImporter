<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Provider\MycoBank;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Processor\SpeciesProcessor;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class MycoBankSpeciesProcessor extends SpeciesProcessor
{
    public function __construct(
        ISpeciesParser $inputSpeciesParser,
        DataSourceInterface $inputSource,
        EventDispatcherInterface $eventDispatcher,
        $options = []
    ) {
        parent::__construct(
            $inputSpeciesParser,
            new MycoBankSpeciesParser(),
            $inputSource,
            $eventDispatcher,
            $options
        );
    }

    public function getRemoteSpecies(Species $species): MycoBankSpecies
    {
        return new MycoBankSpecies(
            $species->getScientificName(),
            $species->getCommonName(),
            $species->getAuthor()
        );
    }

    public function getRemoteRow(Species $species): array
    {
        return [
            'mycobank_number' => $species->getRemoteId(),
            'mycobank_name' => $species->getRemoteScientificName(),
            'mycobank_author' => $species->getRemoteAuthor(),
            'output_species_name_author' => trim(
                $species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor()
            ),
        ];
    }
}
