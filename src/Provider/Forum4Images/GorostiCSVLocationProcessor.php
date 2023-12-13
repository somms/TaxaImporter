<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 12:55
 */

namespace Somms\BV2Observation\Provider\Forum4Images;


use Somms\BV2Observation\Data\Location;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Provider\Forum4Images\CSV\LocationCSVSource;
use Somms\BV2Observation\Provider\Forum4Images\CSV\Species4ImagesCSVSource;
use Somms\BV2Observation\Source\CSV\CSVSource;
use Somms\BV2Observation\Source\DataSourceInterface;

class GorostiCSVLocationProcessor extends \Somms\BV2Observation\Processor\LocationProcessor{

  /**
   * @var LocationCSVSource
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
    $this->inputSource = new LocationCSVSource($inputFilePath, LocationCSVSource::DEFAULT_DELIMITER);
    $basename = basename($inputFilePath, '.csv');
    $okOutputFilePath = $outputFolder . '/' . $basename . '_ok.csv';

    $errorOutputFilePath = $outputFolder . '/' . $basename . '_error.csv';

    $this->okOutput = new \SplFileObject($okOutputFilePath,'w');
    $this->errorOutput = new \SplFileObject($errorOutputFilePath,'w');

    parent::__construct(new LocationStringParser());


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
   * @param $item Location
   */
  protected function processItem(&$inputRow, &$item, $options = []) {
    // TODO: Implement postProcessSpecies() method.
    if($item){
      if(PHP_SAPI == 'cli')
      {
        echo 'Localidad encontrada: ' . $item->getName() . "\n";
      }

      $inputRow['lat'] = $item->getCenterCoordinates()->getLatitude();
      $inputRow['lon'] = $item->getCenterCoordinates()->getLongitude();
      $inputRow['OSM_name'] = $item->getName();

      $this->okOutput->fputcsv($inputRow, LocationCSVSource::DEFAULT_DELIMITER);
    }
    else{
      if(PHP_SAPI == 'cli')
      {
        echo "Localidad no encontrada\n";
      }
      $this->errorOutput->fputcsv($inputRow, LocationCSVSource::DEFAULT_DELIMITER);
    }
  }

  protected function preProcessRow($rawItemName, $inputRow){
    // Intentamos traerlo de normal
    $result = parent::preProcessRow($rawItemName, $inputRow);
    if($result == null){
      // Intentamos repetir el proceso con distintas opciones
      // TODO: preparar las alternativas al sistema inicial
      $tests = $this->getNameTests($inputRow);
      $i=0;
      while($result == null && count($tests) > $i){
        $result = parent::preProcessRow($tests[$i], $inputRow);
        $i++;
      }
      if($result == null){
        echo '';
      }

    }

    return $result;
  }

  /**
   * @param $inputRow
   *
   * @return array
   */
  protected function getNameTests($inputRow){
    $result = [
      $inputRow['image_localidad'] . ',' . $inputRow['image_provincia'],
      $inputRow['image_localidad'],
      $inputRow['image_paraje'] . ',' . $inputRow['image_provincia'],
    ];
    return $result;
  }
}