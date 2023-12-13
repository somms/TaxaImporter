<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 11/11/2018
 * Time: 11:43
 */

namespace Somms\BV2Observation\Provider\Forum4Images\CSV;


use Somms\BV2Observation\Source\CSV\CSVSource;

class LocationCSVSource extends CSVSource{

  const DEFAULT_DELIMITER = ';';

  function __construct($fileName, $delimiter = self::DEFAULT_DELIMITER, $enclosure = CSVSource::DEFAULT_ENCLOSURE, $escape = CSVSource::DEFAULT_ESCAPE){
    parent::__construct($fileName, $delimiter, $enclosure, $escape);
    $this->CSVCollection->useFirstRowAsHeader();
  }

    public function getItemName($item)
    {
        $result = false;
        // Aquí se selecciona la columna con la especie
        if(count($item)){
            $result = $item['imagen_localidad_plus'];
        }

        return $result;
    }
}