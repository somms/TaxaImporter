<?php

namespace Somms\BV2Observation\Provider\GNames;

use Somms\BV2Observation\Parser\AbstractSpeciesParser;

class GNSpeciesParser extends AbstractSpeciesParser
{
    protected function parseInput()
    {
        // De base nos quedamos con el nombre canónico
        $taxonName = (string) ($this->input['matchedCanonicalFull'] ?? '');
        $matchedName = (string) ($this->input['matchedName'] ?? $taxonName);
        $authorName = trim(substr($matchedName, strlen($taxonName)));

        $ranksRaw = trim((string) ($this->input['classificationRanks'] ?? ''));
        $rangos = $ranksRaw !== '' ? explode('|', $ranksRaw) : [];

        $isSpeciesLevel = in_array('species', $rangos, true)
            || in_array('subspecies', $rangos, true)
            || in_array('variety', $rangos, true)
            || in_array('form', $rangos, true);

        // Fallback when WFO/GNames omit ranks (e.g. bare-name stub still selected).
        $cardinality = (int) ($this->input['matchedCardinality'] ?? 0);
        if (!$isSpeciesLevel && $rangos === []) {
            $isSpeciesLevel = $cardinality >= 2;
        }

        if (!$isSpeciesLevel) {
            if (in_array('genus', $rangos, true) || $cardinality === 1) {
                $taxonName .= ' indet.';
            } else {
                $taxonName .= ' spec.';
            }
        }

        $species = new GNSpecies($taxonName, '', $authorName);
        $species->setRemoteId((string) ($this->input['matchedNameID'] ?? ''));

        return $species;
    }
}
