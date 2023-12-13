<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 11/11/2018
 * Time: 13:31
 */

namespace Somms\BV2Observation\Parser;


use Somms\BV2Observation\Data\Species;

interface ISpeciesParser extends IParser{

  /**
   * @return Species
   */
  public function getSpecies();
} 