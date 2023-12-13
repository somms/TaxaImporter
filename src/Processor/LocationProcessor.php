<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 12/11/2018
 * Time: 17:58
 */

namespace Somms\BV2Observation\Processor;


use Somms\BV2Observation\Parser\ILocationParser;
use Somms\BV2Observation\Parser\LocationParser;

abstract class LocationProcessor extends Processor{

  /**
   * @var LocationParser
   */
  protected $inputParser;

  /**
   * @param ILocationParser $locationParser
   */
  function __construct(ILocationParser $locationParser) {
    parent::__construct($locationParser);
  }

  /**
   * @param $rawItemName string
   * @param $inputRow array
   *
   * @return mixed|null|\Somms\BV2Observation\Data\Location
   */
  protected function preProcessRow($rawItemName, $inputRow){
    $this->inputParser->setInput($rawItemName);
    return $this->inputParser->getLocation();
  }

} 