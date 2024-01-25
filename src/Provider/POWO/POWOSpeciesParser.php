<?php

namespace Somms\BV2Observation\Provider\POWO;

use Somms\BV2Observation\Parser\SpeciesParser;
use Somms\PHPKew\POWO\Enums\Name;

class POWOSpeciesParser extends SpeciesParser
{

    protected function parseInput()
    {
        $taxonName = $this->input[Name::FULL_NAME];
        $authorName = $this->input[Name::AUTHOR] ?? '';
        $rank = $this->input['rank'] ?? '';
        // TODO: Añadir "indet." y "spec." si esta en un rango superior
        if($rank != 'Genus' && !str_contains($taxonName, ' ')){
            $taxonName .= ' indet.';
        }
        elseif ($rank == 'Genus'){
            $taxonName .= ' spec.';
        }

        $species = new POWOSpecies($taxonName, '', $authorName);
        $species->setRemoteId($this->input['fqId']); // Debería ser esto originId?
        return $species;
    }
}