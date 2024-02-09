<?php

namespace Somms\BV2Observation\Parser\GNames\GNParser;

use Somms\BV2Observation\Parser\GNames\GNSpeciesParser;

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