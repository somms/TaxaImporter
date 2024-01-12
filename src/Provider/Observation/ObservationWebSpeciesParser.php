<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 11/11/2018
 * Time: 16:47
 */

namespace Somms\BV2Observation\Provider\Observation;


use Somms\BV2Observation\Data\RemoteSpecies;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\SpeciesParser;

class ObservationWebSpeciesParser extends SpeciesParser {

  /**
   * @return RemoteSpecies
   */
  protected function parseInput() {

    //Eliminamos los "|";
    preg_match('/^((.*)\s-\s)?([^\|]+)\|(\d+)\|.*/', $this->input, $outputArray);

    $scientificString = $outputArray[3];
    $commonString = $outputArray[2];
    $species = new ObservationSpecies($scientificString, $commonString);
    $species->setRemoteId($outputArray[4]);
    return $species;
  }
}