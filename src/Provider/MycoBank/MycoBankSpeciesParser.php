<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Provider\MycoBank;

use Somms\BV2Observation\Parser\AbstractSpeciesParser;

final class MycoBankSpeciesParser extends AbstractSpeciesParser
{
    public function preprocessInput($input)
    {
        return $input;
    }

    protected function parseInput(): ?MycoBankSpecies
    {
        if (!is_array($this->input) || !isset($this->input['taxon_name'])) {
            return null;
        }

        $scientificName = (string) $this->input['taxon_name'];
        $author = (string) ($this->input['authors_abbreviated'] ?? '');
        $species = new MycoBankSpecies($scientificName, '', $author);
        $species->setRemoteId((string) ($this->input['mycobank_number'] ?? ''));
        $species->setRemoteScientificName($scientificName);
        $species->setRemoteAuthor($author);

        return $species;
    }
}
