<?php

namespace Somms\BV2Observation\Parser\GNames\GNVerifier;

use Somms\BV2Observation\Parser\GNames\GNSpeciesParser;

class LocaBinGNSpeciesVerifier extends GNSpeciesParser
{
    const LOCAL_PATH = 'vendor/bin/gnverifier';

    protected function getJSON()
    {
        $escapedInput = escapeshellarg($this->input);
        $command = self::LOCAL_PATH . '-q -M -s 11 -f compact '. $escapedInput ;
        $output = array();
        $retval = null;
        return exec($command, $output, $retval);

    }
}