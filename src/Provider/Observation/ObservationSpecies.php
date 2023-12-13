<?php

namespace Somms\BV2Observation\Provider\Observation;

use duzun\hQuery;
use Somms\BV2Observation\Data\RemoteSpecies;
use Somms\BV2Observation\Data\Species;

class ObservationSpecies extends RemoteSpecies
{

    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        $URL = "https://old.observation.org/soort/autocomplete_name?q=" . urlencode($this->scientificName);
        if(isset($options['species_group'])){
            $URL .= '&q=' . $options['species_group'];
        }
        $doc = hQuery::fromUrl($URL, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
        if ($doc->text() != '') {
            $observationParser = new ObservationSpeciesParser();
            $speciesNames = explode("\n", $doc->text());
            foreach ($speciesNames as $speciesName) {
                $observationParser->setInput($speciesName);
                $observationSpecies = $observationParser->getSpecies();
                $this->inRemote = (
                    $observationSpecies->getScientificName() == $this->getScientificName() ||
                    $observationSpecies->getScientificName() == $this->getScientificName() . ' indet.' ||
                    $observationSpecies->getScientificName() == $this->getScientificName() . ' spec.'
                );
                if ($this->inRemote) {
                    return $speciesName;
                    break;
                }
            }

        }
        return false;
    }
}