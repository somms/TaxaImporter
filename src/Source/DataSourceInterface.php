<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 12:48
 */

namespace Somms\BV2Observation\Source;


use Iterator;

interface DataSourceInterface {

  /**
   *
   * return InputCollection
   */
  public function getInputCollection();

  public function getItemName($item);


}