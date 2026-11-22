<?php

namespace Somms\BV2Observation\Provider\GBIF;

use Somms\BV2Observation\Parser\AbstractSpeciesParser;

class GBIFSpeciesParser extends AbstractSpeciesParser
{

    protected function parseInput()
    {
        // De base nos quedamos con el nombre canónico
        $taxonName = $this->input['canonicalName'] ?? end($this->input['higherClassificationMap']);

        // Ahora le ponemos el epíteto en función de su grado

        if(!isset($this->input['species'])){
            if(!isset($this->input['genus'])){
                $taxonName .= " indet.";
            }
            else{
                $taxonName .= " spec.";
            }
        }
        $species = new GBIFSpecies($taxonName, '', $this->input['authorship']);
        $species->setRemoteId($this->input['key']);
        return $species;
    }
}