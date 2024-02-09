<?php

namespace Somms\BV2Observation\Provider\GNames;

use Somms\BV2Observation\Parser\SpeciesParser;
use Somms\BV2Observation\Provider\GBIF\GBIFSpecies;

class GNSpeciesParser extends SpeciesParser
{

    protected function parseInput()
    {
        // De base nos quedamos con el nombre canónico
        $taxonName = $this->input['matchedCanonicalFull'];
        $authorName = trim(substr($this->input['matchedName'], strlen($taxonName)));

        // Ahora le ponemos el epíteto en función de su grado
        $rangos = explode('|', $this->input['classificationRanks']);

        if(array_search('species', $rangos) === false){
            if(array_search('genus', $rangos)){
                $taxonName .= " indet.";
            }
            else{
                $taxonName .= " spec.";
            }
        }
        $species = new GNSpecies($taxonName, '', $authorName);
        $species->setRemoteId($this->input['matchedNameID']);
        return $species;
    }
}