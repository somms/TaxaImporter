<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 14/11/2018
 * Time: 13:11
 */

namespace Somms\BV2Observation\Processor;


use Somms\BV2Observation\Parser\IObservationParser;
use Somms\BV2Observation\Parser\ObservationParser;

abstract class ObservationProcessor extends Processor{

  /**
   * @var ObservationParser
   */
  protected $inputParser;


  function __construct(IObservationParser $observationParser){
    parent::__construct($observationParser);
  }

  /**
   * @param $rawImName
   * @param $inputRow
   *
   * @return mixed
   */
  protected function preProcessRow($rawItemName, $inputRow) {
    $this->inputParser->setInput($inputRow);
    return $this->inputParser->getObservation();
  }



}