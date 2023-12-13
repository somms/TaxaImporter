<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 12/11/2018
 * Time: 18:19
 */

namespace Somms\BV2Observation\Parser;


use Somms\BV2Observation\Data\Location;

abstract class LocationParser extends Parser implements ILocationParser {

  /**
   * @return Location
   */
  abstract protected function parseInputString();

  /**
   * Returns a Species from the inputString
   * @return null|Location
   */
  public function getLocation(){
    $result = null;
    if($this->input != null){
      $result = $this->parseInputString();
    }

    return $result;
  }

} 