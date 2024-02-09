<?php

namespace Somms\BV2Observation\Parser\GNames\GNParser;

use GuzzleHttp\Client;
use Somms\BV2Observation\Parser\GNames\GNSpeciesParser;

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