<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 12/11/2018
 * Time: 17:58
 */

namespace Somms\BV2Observation\Data;


use Academe\Proj4Php\Mgrs\LatLong;
use Academe\Proj4Php\Mgrs\Square;
use SNAGPhpNominatim\NominatimApi;

class Location {

  /**
   * @var LatLong
   */
  private $centerCoordinates;
  /**
   * @var Square
   */
  private $square;

  /**
   * @var string
   */
  private $name;

  function __construct(LatLong $center, Square $square = null, $name = null){
    $this->centerCoordinates = $center;
    $this->square = $square;
    $this->name = $name;
  }

  /**
   * @return LatLong
   */
  public function getCenterCoordinates() {
    return $this->centerCoordinates;
  }

  /**
   * @return Square
   */
  public function getSquare() {
    return $this->square;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  public static function getFromString($inputString){
    $nominatim = new NominatimApi('info@observado.es');
    $nominatim->setLimit(1);
    $result = $nominatim->request(urlencode($inputString));
    if($result && !is_array($result)){
      $result = array($result);
    }
    if(isset($result[0])){
      $lat = $result[0]->getLat();
      $lon = $result[0]->getLon();
      $square = $result[0]->getBoundingBox();
      $square = new Square( new LatLong( $square[0], $square[2]), new LatLong($square[1], $square[3]));
      $name = $result[0]->getDisplayName();
      $name = iconv('ISO-8859-1', 'UTF-8', $name);

      return new Location(new LatLong($lat, $lon), $square, $name);
    }
  }
} 