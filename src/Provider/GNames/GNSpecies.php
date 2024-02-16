<?php

namespace Somms\BV2Observation\Provider\GNames;

use Somms\BV2Observation\Data\RemoteSpecies;

class GNSpecies extends RemoteSpecies
{
    const LOCAL_PATH = 'vendor/bin/gnverifier';

    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {

        $input = trim($speciesName . ' ' . $author);
        $escapedInput = escapeshellarg($input);
        $source = $options['source'] ?? '11';
        $source = escapeshellarg($source);
        $command = self::LOCAL_PATH . " -q -M -s $source -f compact $escapedInput";
        $output = array();
        $retval = null;
        $response = exec($command, $output, $retval);
        // Decodificar la respuesta JSON
        $resultados = json_decode($response, true);
        $resultados = $resultados[0] ?? $resultados;
        if(!isset($resultados) || !isset($resultados['results'])){
            return false;
        }
        if($resultados['results'][0]['isSynonym']){
            $currentName = $resultados['results'][0]['currentName'];
            return $this->queryRemoteSource($currentName, '', $options);
        }
        return $resultados['results'][0];

    }
}