<?php

namespace Somms\BV2Observation\Provider\Observation;

use duzun\hQuery;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Processor\SpeciesProcessor;
use Somms\BV2Observation\Provider\Forum4Images\CSV\Species4ImagesCSVSource;
use Somms\BV2Observation\Provider\Forum4Images\SpeciesStringParser;
use Somms\BV2Observation\Provider\GBIF\GBIFSpeciesParser;
use Somms\BV2Observation\Source\DataSourceInterface;

class GBIFSpeciesImportProcessor extends ObservationSpeciesProcessor
{
    protected string $cookie_json_jar;

    function __construct(ISpeciesParser $inputSpeciesParser, DataSourceInterface $inputSource, $eventDispatcher, $options = [])
    {
        $this->cookie_json_jar = $options['cookie_jar'] ?? '';
        parent::__construct($inputSpeciesParser, $inputSource, $eventDispatcher, $options);

    }

    function getRemoteRow(Species $species)
    {
        $result = [];
        $result['obs_species_id'] = $species->getRemoteId();
        $result['obs_species_name'] = $species->getRemoteScientificName();
        return $result;
    }


    /**
     * Hace la importación de la especie usando el formulario de importación desde
     * GBIF.
     * @param Species $species
     * @return bool|Species
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRemoteSpecies(Species $species) : bool|Species
    {
      $result = false;

      // Preparamos las cookies
      $cookies= [];
      $tmpCookies = json_decode($this->cookie_json_jar);
      foreach ($tmpCookies as $tmpCookie)
      {
          $cookies[$tmpCookie->name] = $tmpCookie->value;
      }

    $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray(
        $cookies,
        'observation.org'
    );
        // URL del formulario
        $url = 'https://old.observation.org/add_species.php';
        // Datos a enviar mediante POST
        $postData = [
            'species_name' =>
                str_replace([' spec.', ' indet.'], '', $species->getScientificName()),
        ];

        $client = new Client([
            'cookies' => true,
        ]);

        // Crear un objeto hQuery
        $response = $client->request( 'POST', $url, [
            RequestOptions::COOKIES => $cookieJar,
            RequestOptions::FORM_PARAMS => $postData,
        ]);

        $result = $response->getBody();
        $doc = hQuery::fromHTML($result->getContents());
        $result = $doc->find('#bd #content .content')->text();

        if( str_contains($result, 'ERROR')){
            $result = false;
        }

      return parent::getRemoteSpecies($species);
    }
}