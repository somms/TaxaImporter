<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 13/11/2018
 * Time: 10:47
 */

namespace Somms\BV2Observation\Processor;


use Somms\BV2Observation\Parser\IParser;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Parser\Parser;
use Somms\BV2Observation\Source\DataSourceInterface;

abstract class Processor {

  /**
   * @var Parser
   */
  protected $inputParser;

  /**
   * @var \Iterator
   */
  protected $unparsedCollection;

  protected $parsedItems;

  protected array $options = [];

    function __construct(IParser $inputParser, $options = []){
    $this->inputParser = $inputParser;
    $this->parsedItems = array();
    $this->options = $options;
  }

  function flushSpeciesCache(){
    $this->parsedItems = array();
  }

    public function process($offset = 0)
    {
        $collection = $this->inputSource->getInputCollection();
        $this->processCollection($collection, $offset, $this->options);
    }

  public function processCollection( \Iterator $unparsedCollection, $offset = 0, $options = []){
    $this->unparsedCollection = $unparsedCollection;
    $total = iterator_count($this->unparsedCollection)-1;
    $this->unparsedCollection->rewind();
    $currentIndex = 0;

    foreach($this->unparsedCollection as $inputRow){
      $currentIndex++;
      if($currentIndex < $offset){
          continue;
      }
      echo "$currentIndex/$total ";
      $rawItemName = $this->getItemName($inputRow);
      if(PHP_SAPI == 'cli')
      {
        echo 'Procesando item: ' . $rawItemName . "\n";
      }

      if(!$rawItemName){
          $this->discardedOutput($inputRow);
          continue;
      }
      if(!array_key_exists($rawItemName, $this->parsedItems)){
        $item = $this->preProcessRow($rawItemName, $inputRow);
        if($result = $this->processItem($inputRow, $item, $options))
        {
            $inputRow = array_merge($inputRow, $result);
            $this->okOutput($inputRow);
        }
        else{
            $this->errorOutput($inputRow);
        }
          $this->parsedItems[$rawItemName] = $result;
      }else{
          if($result = $this->parsedItems[$rawItemName]){
              $inputRow = array_merge($inputRow, $result);
              $this->okOutput($inputRow);
          }
          else{
              $this->errorOutput($inputRow);
          }
      }
    }

    return $this->parsedItems;
  }

  abstract function okOutput($inputRow);

  abstract function errorOutput($inputRow);

  abstract function discardedOutput($inputRow);

  /**
   * @param $rawImName
   * @param $inputRow
   *
   * @return mixed
   */
  abstract protected function preProcessRow($rawItemName, $inputRow);

  /**
   * @param array $inputRow
   *
   * @return string
   */
  protected abstract function getItemName($inputRow);

  protected abstract function processItem(&$inputRow, &$item, $options = []);


} 