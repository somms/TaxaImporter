<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 14/11/2018
 * Time: 13:20
 */

namespace Somms\BV2Observation\Provider\Forum4Images;


use Somms\BV2Observation\Data\Observation;
use Somms\BV2Observation\Parser\ObservationParser;

class ObservationRowParser extends ObservationParser{

  const VALUE_UNKNOWN = 'unknown';
  /**
   * @return Observation
   */
  protected function parseInputRow($subimage = false) {


    $observation = new Observation();
    $observation->setDate($this->prepareFecha($this->input['image_fecha']));
    $observation->setAccuracy('Area');
    $observation->setActivity(self::VALUE_UNKNOWN);
    $observation->setAppearance(self::VALUE_UNKNOWN);
    $observation->setAreaID(self::VALUE_UNKNOWN);
    $observation->setCertain('yes');
    $observation->setCollectionNumber($this->calculateCollectionNumber()); // Esto debe calcularse en tiempo de ejecución
    $observation->setCountMethod('seen not counted');
    $observation->setEscape('no');
    $observation->setGender(self::VALUE_UNKNOWN);
    $observation->setHideUntil('1900-01-01');
    $observation->setImageURL(array($this->prepareImageURL($this->input['image_media_file'])));
    $observation->setLat($this->prepareCoordinate($this->input['localidad_lat']));
    $observation->setLon($this->prepareCoordinate($this->input['localidad_lon']));
    $observation->setMethod(self::VALUE_UNKNOWN);
    $observation->setNumber('1');
    $observation->setObsercureLocation('no');
    $observation->setRemarks($this->prepareRemarks($this->input));
    $observation->setTime(self::VALUE_UNKNOWN);
    $observation->setSpecies($this->prepareSpecies($this->input['cat_name']));

    return $observation;

  }

  /**
   * Computes an unique ID for the observation
   * @return string
   */
  private function calculateCollectionNumber(){
    $result = $this->input['cat_id'] . '-' . $this->input['user_id'] . '-' .
              $this->input['image_fecha'] . '-' .
              $this->input['localidad_lat'] . $this->input['localidad_lon'];
    return $result;
  }

  public function prepareSpecies($speciesName){

    $speciesParser = new SpeciesStringParser();
    $speciesParser->setInput($speciesName);
    $species = $speciesParser->getSpecies();
    return $species->getScientificName();
  }

  public function prepareCoordinate($coordinate){
    $coordinate = str_replace(',', '.', $coordinate);
    return $coordinate;
  }
  public function prepareImageURL($url){
    if(!filter_var($url, FILTER_VALIDATE_URL)){
      $url = 'http://www.guiavisual-gorosti.org/galeria/data/media/' . $this->input['cat_id'] . '/' . $url;
    }
    return $url;
  }

  protected function prepareFecha($fecha){
    $date = \DateTime::createFromFormat('d/m/Y', $fecha);
    return $date->format('Y-m-d');
  }

  public function prepareRemarks($row, $subImage = false){

    $remarks = '';
    if(!$subImage) {
      $remarks = "Observación importada de la Guia Visual de Gorosti<br/>" . PHP_EOL;
      $remarks .= "Información original:<br/>" . PHP_EOL;
      $remarks .= "Especie: " . $row['cat_name'] . '<br/>' . PHP_EOL;
      $remarks .= "Usuario: " . $row['user_name'] . '<br/>' . PHP_EOL;
      $remarks .= "Provincia: " . $row['image_provincia'] . '<br/>' . PHP_EOL;
      $remarks .= "Localidad: " . $row['image_localidad'] . '<br/>' . PHP_EOL;
      $remarks .= "Paraje: " . $row['image_paraje'] . '<br/>' . PHP_EOL;
      $remarks .= "UTM: " . $row['image_xutm'] . ' ' . $row['image_yutm'] . '<br/>' . PHP_EOL;
    }
    $remarks .= "Imagen Id:" . $row["image_id"] . '<br/>' . PHP_EOL;
    $remarks .= "Nombre de la imagen: " . $row['image_name'] . '<br/>' . PHP_EOL;
    $remarks .= "Palabras clave: " . $row['image_keywords'] . '<br/>' . PHP_EOL;
    $remarks .= "Descripción: " . $row['image_description'] . '<br/>' . PHP_EOL;
    $remarks .= "" . '<br/>';

    return $remarks;

  }


}