<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 13/11/2018
 * Time: 14:00
 */

namespace Somms\BV2Observation\Provider\Forum4Images;


use Somms\BV2Observation\Data\Location;
use Somms\BV2Observation\Parser\LocationParser;

class LocationStringParser extends LocationParser{

  /**
   * @return Location
   */
  protected function parseInputString() {
    $result = Location::getFromString($this->input);
    return $result;
  }
}