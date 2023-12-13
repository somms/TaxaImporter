<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 12/11/2018
 * Time: 18:22
 */

namespace Somms\BV2Observation\Parser;


abstract class Parser implements IParser {

  protected $input;
  protected $providerParser;

  protected function preprocessInput($inputString){
    return $inputString;
  }

  public function setInput($input){
    $this->input = $this->preprocessInput($input);
  }


} 