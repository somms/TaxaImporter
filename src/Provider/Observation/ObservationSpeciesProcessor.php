<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 12:55
 */

namespace Somms\BV2Observation\Provider\Observation;


use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ObservationSpeciesProcessor extends \Somms\BV2Observation\Processor\SpeciesProcessor{

    function __construct(ISpeciesParser $inputSpeciesParser, DataSourceInterface $inputSource, EventDispatcherInterface $eventDispatcher,  $options = [])
    {
        if (isset($options['datasource'])){
            parent::__construct($inputSpeciesParser, new ObservationDBSpeciesParser(), $inputSource, $eventDispatcher,  $options);

        }
        else{
            parent::__construct($inputSpeciesParser, new ObservationWebSpeciesParser(), $inputSource, $eventDispatcher,  $options);


        }
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
        $result['output_species_name_author'] = trim($species->getRemoteScientificName() . ' ' . $species->getRemoteAuthor());

        return $result;
    }
}