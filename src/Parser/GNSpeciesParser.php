<?php

namespace Somms\BV2Observation\Parser;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\SpeciesParser;

class GNSpeciesParser extends SpeciesParser
{
    const GNPARSER_URL = 'https://parser.globalnames.org/?format=json&with_details=on&names=';

    /**
     * @inheritDoc
     */
    protected function parseInput()
    {
        $url = self::GNPARSER_URL . urlencode($this->input);
        $species = null;
        $scientificName = "";
        $authorship = "";

        // Crear una instancia de Guzzle Client
        $client = new Client();
        try {
            // Realizar la solicitud a la API de GNParser
            $response = $client->request('GET', $url);

            // Decodificar la respuesta JSON
            $resultados = json_decode($response->getBody(), true);
            if(!isset($resultados[0]) || !$resultados[0]['parsed']){
                echo 'No se ha encontrado nombre científico para ' . $this->input;
                return new Species($this->input, '', '');
            }
            $resultados = $resultados[0];
            if(isset($resultados['canonical']['full'])){
                $scientificName = $resultados['canonical']['full'];
            }
            else{
                echo 'No se ha encontrado nombre científico para ' . $this->input;
                return new Species($this->input, '', '');
            }

            if(isset($resultados['authorship']['normalized'])){
                $authorship = $resultados['authorship']['normalized'];
            }
            $species = new Species($scientificName, '', $authorship);

        } catch (Exception $e) {
            // Manejar errores
            echo 'Error al realizar la solicitud: ' . $e->getMessage();
        } catch (GuzzleException $e) {
            // Manejar errores
            echo 'Error al realizar la solicitud: ' . $e->getMessage();
        }

        return $species;
    }
}