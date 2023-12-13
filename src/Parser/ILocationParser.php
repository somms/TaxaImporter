<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 12/11/2018
 * Time: 18:13
 */

namespace Somms\BV2Observation\Parser;


use Somms\BV2Observation\Data\Location;

interface ILocationParser extends IParser {


  /**
   * @return Location
   */
  public function getLocation();
} 