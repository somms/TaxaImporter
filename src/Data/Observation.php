<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 11/11/2018
 * Time: 17:24
 */

namespace Somms\BV2Observation\Data;


class Observation {

  protected $date;
  protected $time;
  protected $lat;
  protected $lon;
  protected $accuracy;
  protected $species;
  protected $number;
  protected $countMethod;
  protected $gender;
  protected $certain;
  protected $escape;
  protected $method;
  protected $appearance;
  protected $activity;
  protected $remarks;
  protected $collectionNumber;
  protected $hideUntil;
  protected $obsercureLocation;
  protected $areaID;

  /**
   * @var array
   */
  protected $imageURL;


  /**
   * @return mixed
   */
  public function getAccuracy() {
    return $this->accuracy;
  }

  /**
   * @param mixed $accuracy
   *
   * @return Observation
   */
  public function setAccuracy($accuracy) {
    $this->accuracy = $accuracy;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getActivity() {
    return $this->activity;
  }

  /**
   * @param mixed $activity
   *
   * @return Observation
   */
  public function setActivity($activity) {
    $this->activity = $activity;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getAppearance() {
    return $this->appearance;
  }

  /**
   * @param mixed $appearance
   *
   * @return Observation
   */
  public function setAppearance($appearance) {
    $this->appearance = $appearance;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getAreaID() {
    return $this->areaID;
  }

  /**
   * @param mixed $areaID
   *
   * @return Observation
   */
  public function setAreaID($areaID) {
    $this->areaID = $areaID;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getCertain() {
    return $this->certain;
  }

  /**
   * @param mixed $certain
   *
   * @return Observation
   */
  public function setCertain($certain) {
    $this->certain = $certain;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getCollectionNumber() {
    return $this->collectionNumber;
  }

  /**
   * @param mixed $collectionNumber
   *
   * @return Observation
   */
  public function setCollectionNumber($collectionNumber) {
    $this->collectionNumber = $collectionNumber;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getCountMethod() {
    return $this->countMethod;
  }

  /**
   * @param mixed $countMethod
   *
   * @return Observation
   */
  public function setCountMethod($countMethod) {
    $this->countMethod = $countMethod;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getDate() {
    return $this->date;
  }

  /**
   * @param mixed $date
   *
   * @return Observation
   */
  public function setDate($date) {
    $this->date = $date;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getEscape() {
    return $this->escape;
  }

  /**
   * @param mixed $escape
   *
   * @return Observation
   */
  public function setEscape($escape) {
    $this->escape = $escape;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getGender() {
    return $this->gender;
  }

  /**
   * @param mixed $gender
   *
   * @return Observation
   */
  public function setGender($gender) {
    $this->gender = $gender;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getHideUntil() {
    return $this->hideUntil;
  }

  /**
   * @param mixed $hideUntil
   *
   * @return Observation
   */
  public function setHideUntil($hideUntil) {
    $this->hideUntil = $hideUntil;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getImageURL() {
    return $this->imageURL;
  }

  /**
   * @param mixed $imageURL
   *
   * @return Observation
   */
  public function setImageURL($imageURL) {
    $this->imageURL = $imageURL;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getLat() {
    return $this->lat;
  }

  /**
   * @param mixed $lat
   *
   * @return Observation
   */
  public function setLat($lat) {
    $this->lat = $lat;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getLon() {
    return $this->lon;
  }

  /**
   * @param mixed $lon
   *
   * @return Observation
   */
  public function setLon($lon) {
    $this->lon = $lon;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   * @param mixed $method
   *
   * @return Observation
   */
  public function setMethod($method) {
    $this->method = $method;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getNumber() {
    return $this->number;
  }

  /**
   * @param mixed $number
   *
   * @return Observation
   */
  public function setNumber($number) {
    $this->number = $number;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getObsercureLocation() {
    return $this->obsercureLocation;
  }

  /**
   * @param mixed $obsercureLocation
   *
   * @return Observation
   */
  public function setObsercureLocation($obsercureLocation) {
    $this->obsercureLocation = $obsercureLocation;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getRemarks() {
    return $this->remarks;
  }

  /**
   * @param mixed $remarks
   *
   * @return Observation
   */
  public function setRemarks($remarks) {
    $this->remarks = $remarks;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getSpecies() {
    return $this->species;
  }

  /**
   * @param mixed $species
   *
   * @return Observation
   */
  public function setSpecies($species) {
    $this->species = $species;

    return $this;
  }

  /**
   * @return mixed
   */
  public function getTime() {
    return $this->time;
  }

  /**
   * @param mixed $time
   *
   * @return Observation
   */
  public function setTime($time) {
    $this->time = $time;

    return $this;
  }

  public function toArray(){
    $result=[
      "ID" => $this->getCollectionNumber(),
      "Date" => $this->getDate(),
      "Time (hh:mm)" => $this->getTime(),
      "Lat/x" => $this->getLat(),
      "lng/y" => $this->getLon(),
      "accuracy" => $this->getAccuracy(),
      "Species (Latin)" => $this->getSpecies(),
      "Number" => $this->getNumber(),
      "Count method" => $this->getCountMethod(),
      "Gender" => $this->getGender(),
      "Certain" => $this->getCertain(),
      "Escape" => $this->getEscape(),
      "Method" => $this->getMethod(),
      "Appearance" => $this->getAppearance(),
      "Activity" => $this->getActivity(),
      "Remarks" => $this->getRemarks(),
      "Collection number" => $this->getCollectionNumber(),
      "Hide until" => $this->getHideUntil(),
      "Obscure site location" => $this->getObsercureLocation(),
      "Image URL" => json_encode($this->imageURL)
    ];
    return $result;
  }
} 