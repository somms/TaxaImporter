<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 14/11/2018
 * Time: 13:02
 */

namespace Somms\BV2Observation\Parser;


use Somms\BV2Observation\Data\Observation;

abstract class ObservationParser extends Parser implements IObservationParser {

  /**
   * @return Observation
   */
  abstract protected function parseInputRow();

  /**
   * @return Observation
   */
  public function getObservation() {
    $result = null;
    if($this->input != null){
      $result = $this->parseInputRow();
    }

    return $result;
  }
}