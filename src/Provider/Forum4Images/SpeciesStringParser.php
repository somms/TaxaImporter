<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 27/10/2018
 * Time: 20:18
 */

  namespace Somms\BV2Observation\Provider\Forum4Images;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\SpeciesParser;

class SpeciesStringParser extends SpeciesParser {


  /**
   * @return Species
   */
  protected function parseInput() {

    $scientificString = '';
    $commonString = '';

    if(substr_count($this->input, '/')>0){
      preg_match('/(.*)\/(.*)/', $this->input, $outputArray);

      $scientificString = $outputArray[2];
      $commonString = $outputArray[1];
    }
    else{
      $scientificString = $this->input;
    }

    $scientificString = str_replace(' sp.', ' spec.', $scientificString);

    return new Species($scientificString, $commonString);

  }
}