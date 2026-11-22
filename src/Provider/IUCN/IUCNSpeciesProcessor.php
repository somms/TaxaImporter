<?php

namespace Somms\BV2Observation\Provider\IUCN;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class IUCNSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor
{
    /**
     * Constructor following the same pattern as GBIFSpeciesProcessor
     */
    public function __construct(
        ISpeciesParser $inputSpeciesParser,
        DataSourceInterface $inputSource,
        EventDispatcherInterface $eventDispatcher,
        $options = []
    ) {
        parent::__construct(
            $inputSpeciesParser,
            new IUCNSpeciesParser(),
            $inputSource,
            $eventDispatcher,
            $options
        );
    }

    /**
     * Creates an IUCNSpecies instance to query the remote source
     * This follows the same pattern as GBIFSpeciesProcessor::getRemoteSpecies
     */
    public function getRemoteSpecies(Species $species)
    {
        $iucnSpecies = new IUCNSpecies(
            $species->getScientificName(),
            $species->getCommonName(),
            $species->getAuthor(),
            $this->options // Pass all options including FlareSolverr config
        );

        // Set API token from options if available
        if (!empty($this->options['api_token'])) {
            $iucnSpecies->setApiToken($this->options['api_token']);
        }

        return $iucnSpecies;
    }

    /**
     * Returns the result row to be incorporated into the output
     * This follows the same pattern as GBIFSpeciesProcessor::getRemoteRow
     */
    public function getRemoteRow(Species $species)
    {
        $result = [];
        $result['iucn_species_id'] = $species->getRemoteId();
        $result['iucn_species_name'] = $species->getRemoteScientificName();
        $result['iucn_category'] = $species->extra['iucn_category'] ?? null;
        $result['output_species_name_author'] = trim(
            $species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor()
        );

        // Include additional IUCN-specific data if available
        if (!empty($species->extra['assessment_date'])) {
            $result['iucn_assessment_date'] = $species->extra['assessment_date'];
        }

        if (!empty($species->extra['population_trend'])) {
            $result['iucn_population_trend'] = $species->extra['population_trend'];
        }

        if (!empty($species->extra['iucn_link'])) {
            $result['iucn_link'] = $species->extra['iucn_link'];
        }

        return $result;
    }
}
