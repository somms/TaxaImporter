<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 14/11/2018
 * Time: 17:05
 */

namespace Somms\BV2Observation\Provider\Forum4Images;


use Somms\BV2Observation\Data\Observation;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Processor\ObservationProcessor;
use Somms\BV2Observation\Provider\Forum4Images\CSV\ObservationCSVSource;

class GorostiCSVObservationProcessor extends ObservationProcessor {

  /**
   * @var ObservationCSVSource
   */
  protected $inputSource;
  /**
   * @var \SplFileObject
   */
  protected $okOutput;
  /**
   * @var \SplFileObject
   */
  protected $errorOutput;

  /**
   * @param $inputFilePath
   * @param $okOutputFilePath
   * @param $errorOutputFilePath
   */
  function __construct($inputFilePath, $outputFolder){
    $this->inputSource = new ObservationCSVSource($inputFilePath, ObservationCSVSource::DEFAULT_DELIMITER);
    $basename = basename($inputFilePath, '.csv');
    $okOutputFilePath = $outputFolder . '/' . $basename . '_ok.csv';

    $errorOutputFilePath = $outputFolder . '/' . $basename . '_error.csv';

    $this->okOutput = new \SplFileObject($okOutputFilePath,'w');
    $this->errorOutput = new \SplFileObject($errorOutputFilePath,'w');

    parent::__construct(new ObservationRowParser());

  }

  /**
   * @param array $inputRow
   *
   * @return string
   */
  protected function getItemName($inputRow) {
      return $this->inputSource::getItemName($inputRow);
  }

  /**
   * @param $inputRow
   * @param $item Observation
   */
  protected function processItem(&$inputRow, &$item, $options = []) {
    // TODO: Implement postProcessSpecies() method.
    if($item){
      if(PHP_SAPI == 'cli')
      {
        echo 'Procesada observacion: ' . $item->getCollectionNumber() . "\n";
      }

      $this->okOutput->fputcsv($item->toArray(), ObservationCSVSource::DEFAULT_DELIMITER);
    }
    else{
      if(PHP_SAPI == 'cli')
      {
        echo "Localidad no encontrada\n";
      }
      $this->errorOutput->fputcsv($inputRow, ObservationCSVSource::DEFAULT_DELIMITER);
    }
  }

  public function processCollection(\Iterator $unparsedCollection, $offset = 0, ISpeciesParser $speciesParser){
    $this->unparsedCollection = $unparsedCollection;
    $total = iterator_count($this->unparsedCollection)-1;
    $this->unparsedCollection->rewind();
    $currentIndex = 0;

    /**
     * @var $bufferedObservation Observation
     */
    $bufferedObservation = null;
    $bufferedRow = null;

    foreach($this->unparsedCollection as $inputRow){
      $currentIndex++;
      $rawItemName = $this->getItemName($inputRow);

      if($rawItemName){

        if(PHP_SAPI == 'cli')
        {

          echo "$currentIndex/$total ";
          echo '<- ' . $rawItemName . "\n";
        }
        /**
         * @var $item Observation
         */
        $item = $this->preProcessRow($rawItemName, $inputRow);
        if($bufferedObservation==null){
          $bufferedObservation = $item;
          $bufferedRow = $inputRow;
        }
        else{
          if($item->getCollectionNumber() == $bufferedObservation->getCollectionNumber()){
            $imageURLs = $bufferedObservation->getImageURL();
            $imageURLs[] = $item->getImageURL()[0];
            $bufferedObservation->setImageURL($imageURLs);
            $parser = new ObservationRowParser();
            $stripedRemarks = $parser->prepareRemarks($inputRow, true);

            $bufferedObservation->setRemarks( $bufferedObservation->getRemarks() . $stripedRemarks);
            $bufferedRow = $inputRow;
          }
          else{
            $this->processItem($bufferedRow, $bufferedObservation);
            $bufferedRow = $inputRow;
            $bufferedObservation = $item;
          }
        }
      }

    }

    if($bufferedObservation != null){
      $this->processItem($bufferedRow, $bufferedObservation);
    }

    return $this->parsedItems;
  }
}