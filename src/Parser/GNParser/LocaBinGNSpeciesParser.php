<?php

namespace Somms\BV2Observation\Parser\GNParser;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\SpeciesParser;

class LocaBinGNSpeciesParser extends GNSpeciesParser
{
    const LOCAL_PATH = 'vendor/bin/gnparser';

    protected function getJSON()
    {
        $escapedInput = escapeshellarg($this->input);
        $command = self::LOCAL_PATH . ' ' . $escapedInput . ' -f compact';
        $output = array();
        $retval = null;
        return exec($command, $output, $retval);

    }
}