<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 27/10/2018
 * Time: 20:43
 */

namespace Somms\BV2Observation\Parser;


use Somms\BV2Observation\Data\Species;

abstract class SpeciesParser implements ISpeciesParser {

  protected $input;
  protected $configOptions = array();

  function __construct(array $configOptions = []){
      $this->configOptions = $configOptions;
  }

  public function preprocessInput($input){
    return $input;
  }

  public function setInput($input){
    $this->input = $this->preprocessInput($input);
  }

  /**
   * @return Species
   */
  abstract protected function parseInput();

  /**
   * Returns a Species from the inputString
   * @return null|Species
   */
  public function getSpecies(){
    $result = null;
    if($this->input != null){
      $result = $this->parseInput();
    }

    return $result;
  }

} 