<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 12:55
 */

namespace Somms\BV2Observation\Provider\Observation;


use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;

class ObservationSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor{

    function __construct(ISpeciesParser $inputSpeciesParser, DataSourceInterface $inputSource, $okOutputFilePath, $errorOutputFilePath, $options = [])
    {
        parent::__construct($inputSpeciesParser, new ObservationSpeciesParser(), $inputSource, $okOutputFilePath, $errorOutputFilePath, $options);

    }
    function getRemoteSpecies(Species $species)
    {
        return new ObservationSpecies($species->getScientificName(),$species->getCommonName(), $species->getAuthor());

    }
    function getRemoteRow(Species $species)
    {
        $result = [];
        $result['obs_species_id'] = $species->getRemoteId();
        $result['obs_species_name'] = $species->getRemoteScientificName();
        return $result;
    }
}