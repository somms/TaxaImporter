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
      $cookies_json = '[{"domain":"observation.org","hostOnly":true,"httpOnly":false,"name":"PHPSESSID","path":"/","sameSite":"unspecified","secure":false,"session":true,"storeId":"0","value":"1d8df6ac09447227d2f487b05ed5a2d3"},{"domain":".observation.org","hostOnly":false,"httpOnly":false,"name":"__utmc","path":"/","sameSite":"unspecified","secure":false,"session":true,"storeId":"0","value":"220592455"},{"domain":"observation.org","expirationDate":1906977201,"hostOnly":true,"httpOnly":false,"name":"cookielaw_accepted","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"1"},{"domain":".observation.org","expirationDate":1699607885.149821,"hostOnly":false,"httpOnly":false,"name":"_ga_2EFJWZ44T4","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"GS1.1.1665047804.6.1.1665047885.0.0.0"},{"domain":".observation.org","expirationDate":1706879325,"hostOnly":false,"httpOnly":false,"name":"leaflet_basemap_details","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"Google sat"},{"domain":"observation.org","expirationDate":1729697290.154656,"hostOnly":true,"httpOnly":false,"name":"csrftoken","path":"/","sameSite":"no_restriction","secure":true,"session":false,"storeId":"0","value":"RxXnQffq5iOip9usYdZxKsl4eGcO25Gy"},{"domain":"observation.org","expirationDate":1729780709.443483,"hostOnly":true,"httpOnly":true,"name":"sessionid","path":"/","sameSite":"lax","secure":false,"session":false,"storeId":"0","value":"beils4mkc03ken5hmpngqnucizka4800"},{"domain":".observation.org","expirationDate":1729501724,"hostOnly":false,"httpOnly":false,"name":"leaflet_basemap","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"OSM human"},{"domain":".observation.org","expirationDate":1700810559.493133,"hostOnly":false,"httpOnly":true,"name":"lang","path":"/","sameSite":"lax","secure":true,"session":false,"storeId":"0","value":"es"},{"domain":".observation.org","expirationDate":1700747542.998559,"hostOnly":false,"httpOnly":true,"name":"token","path":"/","sameSite":"lax","secure":true,"session":false,"storeId":"0","value":"98500%2F9f656ff0e93c38b55c89747654bc8a05"},{"domain":".observation.org","expirationDate":1698334091,"hostOnly":false,"httpOnly":false,"name":"_gid","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"GA1.2.583519524.1697437393"},{"domain":"observation.org","expirationDate":1729607658.204463,"hostOnly":true,"httpOnly":false,"name":"django_language","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"es"},{"domain":".observation.org","expirationDate":1732717007.451761,"hostOnly":false,"httpOnly":false,"name":"_ga_E82Y78YY1E","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"GS1.1.1698155543.14.1.1698157007.0.0.0"},{"domain":".observation.org","expirationDate":1732717007.456646,"hostOnly":false,"httpOnly":false,"name":"_ga_M6M73VLLPN","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"GS1.1.1698155544.12.1.1698157007.0.0.0"},{"domain":".observation.org","expirationDate":1732807691.429791,"hostOnly":false,"httpOnly":false,"name":"_ga","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"GA1.2.1056511422.1477480351"},{"domain":".observation.org","expirationDate":1698247751,"hostOnly":false,"httpOnly":false,"name":"_gat","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"1"}]';
      if($this->cookie_json_jar != ''){
          $cookies_json = $this->cookie_json_jar;
      }
      $tmpCookies = json_decode($cookies_json);
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