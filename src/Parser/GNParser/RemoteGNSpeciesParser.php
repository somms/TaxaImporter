<?php

namespace Somms\BV2Observation\Parser\GNParser;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\SpeciesParser;

class RemoteGNSpeciesParser extends GNSpeciesParser
{
    const GNPARSER_URL = 'https://parser.globalnames.org/?format=json&with_details=on&names=';

    protected function getJSON()
    {
        $url = self::GNPARSER_URL . urlencode($this->input);
        $client = new Client();
        // Realizar la solicitud a la API de GNParser

        return $client->request('GET', $url)->getBody();
    }
}