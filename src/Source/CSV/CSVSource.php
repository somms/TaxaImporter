<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 10/11/2018
 * Time: 14:31
 */

namespace Somms\BV2Observation\Source\CSV;


use ogrrd\CsvIterator\CsvIterator;
use Somms\BV2Observation\Source\DataSourceInterface;

class CSVSource implements DataSourceInterface{

  protected $fileName;
  protected $CSVCollection;

  protected $keyFieldname;

  const       DEFAULT_DELIMITER   =   ',';
  const       DEFAULT_ENCLOSURE   =   '"';
  const       DEFAULT_ESCAPE      =   '\\';

  function __construct($fileName, $keyFieldname, $delimiter = self::DEFAULT_DELIMITER, $enclosure = self::DEFAULT_ENCLOSURE, $escape = self::DEFAULT_ESCAPE){
    $this->fileName = $fileName;
    $this->keyFieldname = $keyFieldname;
    $this->CSVCollection = new CSVCollection($fileName, $delimiter, $enclosure, $escape);
    $this->CSVCollection->useFirstRowAsHeader();
  }

  /**
   *
   * return Iterator
   */
  public function getInputCollection() {
    return $this->CSVCollection;
  }

    public function getItemName($item){
        $result = false;
        // Aquí se selecciona la columna con la especie
        if(count($item)){
            $result = $item[$this->keyFieldname];
        }

        return $result;
    }
}