<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 14/11/2018
 * Time: 12:58
 */

namespace Somms\BV2Observation\Parser;


use Somms\BV2Observation\Data\Observation;

interface IObservationParser {

  /**
   * @return Observation
   */
  public function getObservation();
} 