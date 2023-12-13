<?php

namespace Somms\BV2Observation\Provider\Observation;

use duzun\hQuery;
use Somms\BV2Observation\Data\RemoteSpecies;
use Somms\BV2Observation\Data\Species;

class ObservationSpecies extends RemoteSpecies
{

    /*
 * @var boolean
 */
    protected bool $inObservation;


    public function getInObservationOrg(): bool
    {
        if ($this->inObservation === null) {

            $this->inObservation = false;

            $URL = "https://old.observation.org/soort/autocomplete_name?q=" . urlencode($this->scientificName);
            $doc = hQuery::fromUrl($URL, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);

            if ($doc->text() != '') {
                $observationParser = new ObservationSpeciesParser();
                $speciesNames = explode("\n", $doc->text());
                foreach ($speciesNames as $speciesName) {
                    $observationParser->setInput($speciesName);
                    $observationSpecies = $observationParser->getSpecies();
                    $this->inObservation = ($observationSpecies->getScientificName() == $this->getScientificName());
                    if ($this->inObservation) {
                        $this->setObservationId($observationSpecies->getObservationId());
                        break;
                    }
                }

            }

        }

        return $this->inObservation;
    }

    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        $URL = "https://old.observation.org/soort/autocomplete_name?q=" . urlencode($this->scientificName);
        $doc = hQuery::fromUrl($URL, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
        if ($doc->text() != '') {
            $observationParser = new ObservationSpeciesParser();
            $speciesNames = explode("\n", $doc->text());
            foreach ($speciesNames as $speciesName) {
                $observationParser->setInput($speciesName);
                $observationSpecies = $observationParser->getSpecies();
                $this->inRemote = ($observationSpecies->getScientificName() == $this->getScientificName());
                if ($this->inRemote) {
                    $this->setRemoteId($observationSpecies->getRemoteId());
                    break;
                }
            }

        }
        return false;
    }
}