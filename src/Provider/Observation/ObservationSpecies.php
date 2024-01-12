<?php

namespace Somms\BV2Observation\Provider\Observation;

use duzun\hQuery;
use Somms\BV2Observation\Data\RemoteSpecies;
use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Source\Database\DatabaseDataSource;

class ObservationSpecies extends RemoteSpecies
{

    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        if (isset($options['datasource'])){
            $result =  $this->queryDatasource($speciesName, $author, $options);
        }
        else{
            $result = $this->queryWebsite($speciesName, $author, $options);
        }

        if ($result) {
            foreach ($result as $speciesArray) {
                return $speciesArray;
//                $this->inRemote = (
//                    $observationSpecies->getScientificName() == $this->getScientificName() ||
//                    $observationSpecies->getScientificName() == $this->getScientificName() . ' indet.' ||
//                    $observationSpecies->getScientificName() == $this->getScientificName() . ' spec.'
//                );
//                if ($this->inRemote) {
//                    return $speciesName;
//                    break;
//                }
            }

        }
        return false;
    }

    private function queryDatasource(string $speciesName, string $author, $options = []){
        /**
         * @var $datasource DatabaseDataSource
         */
        $datasource = $options['datasource'];
        if(isset($options['author_search']) && $options['author_search'] = 'false'){
            $author = '';
        }
        $result = $datasource->searchSpecies($speciesName, $author);
        $species = [];
/*        $observationParser = new ObservationDBSpeciesParser();
        foreach ($result as $rawItem){
            $observationParser->setInput($rawItem);
            $species[] = $observationParser->getSpecies();
        }*/
        return $result;

    }

    private function queryWebsite(string $speciesName, string $author, $options = []){
        $URL = "https://old.observation.org/soort/autocomplete_name?q=" . urlencode($this->scientificName);
        if(isset($options['species_group'])){
            $URL .= '&q=' . $options['species_group'];
        }
        $doc = hQuery::fromUrl($URL, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
        if ($doc->text() != '') {
            $observationParser = new ObservationWebSpeciesParser();
            $rawResults = explode("\n", $doc->text());
           /* $results = [];
            foreach ($rawResults as $rawResult){
                $observationParser->setInput($rawResult);
                $results[] = $observationParser->getSpecies();
            }*/
            return $rawResults;

        }
        return false;
    }

    /**
     * Compares local and remote Species to check that there is a match, or we just got a false possitive
     * @return bool
     */
    protected function validMatch() : bool{
       return $this->getScientificName() == $this->getRemoteScientificName() ||
                    $this->getRemoteScientificName() == $this->getScientificName() . ' indet.' ||
                    $this->getRemoteScientificName() == $this->getScientificName() . ' spec.';

    }
}