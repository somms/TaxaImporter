<?php

namespace Somms\BV2Observation\Parser\GNParser;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\SpeciesParser;

abstract class GNSpeciesParser extends SpeciesParser
{
    /**
     * @inheritDoc
     */
    protected function parseInput()
    {
        $species = null;
        $authorship = "";

        try {
            // Realizar la solicitud a la API de GNParser
            $response = $this->getJSON();

            // Decodificar la respuesta JSON
            $resultados = json_decode($response, true);
            $resultados = $resultados[0] ?? $resultados;
            if(!isset($resultados) || !$resultados['parsed']){
                echo "\n No se ha encontrado nombre científico para " . $this->input;
                return new Species($this->input, '', '');
            }
            ;
            if(isset($resultados['canonical']['full'])){
                $scientificName = $resultados['canonical']['full'];
            }
            else{
                echo "\n No se ha encontrado nombre científico para " . $this->input;
                return new Species($this->input, '', '');
            }

            if(isset($resultados['authorship']['verbatim'])){
                $authorship = $resultados['authorship']['verbatim'];
            }
            $species = new Species($scientificName, '', $authorship);

        } catch (Exception $e) {
            // Manejar errores
            echo "\n".'Error al realizar la solicitud: ' . $e->getMessage();
        } catch (GuzzleException $e) {
            // Manejar errores
            echo "\n".'Error al realizar la solicitud: ' . $e->getMessage();
        }

        return $species;
    }

    abstract protected function getJSON();
}