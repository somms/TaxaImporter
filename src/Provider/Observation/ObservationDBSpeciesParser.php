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

class ObservationDBSpeciesParser extends SpeciesParser {

  /**
   * @return RemoteSpecies
   */
  protected function parseInput() {

    $species = new ObservationSpecies($this->input['name_scientific'],$this->input['name_common'] ?? '', $this->input['author'] ?? '');
    $species->setRemoteId($this->input['id']);
    return $species;
  }
}