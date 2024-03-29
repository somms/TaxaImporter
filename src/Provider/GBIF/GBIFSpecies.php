<?php

namespace Somms\BV2Observation\Provider\GBIF;

use duzun\hQuery;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Provider\Observation\ObservationWebSpeciesParser;

class GBIFSpecies extends \Somms\BV2Observation\Data\RemoteSpecies
{

    protected $synonymValues = [
        'SYNONYM',
        'HETEROTYPIC_SYNONYM',
        'HOMOTYPIC_SYNONYM',
        'INTERMEDIATE_RANK_SYNONYM',
        'MISAPPLIED',
        'PROPARTE_SYNONYM'
    ];

    public function queryRemoteSource($speciesName, $author = null, $options = []): mixed
    {
        $authorSearch = $options['author_search'] ?? false;

        // By default it searches in the GBIF taxonomy backbone
        $datasetKey = $options['datasetKey'] ?? 'd7dddbf4-2cf0-4f39-9b2a-bb099caae36c';


        $GBIFscientificName = str_replace([' indet.', ' spec.'], '', $this->scientificName);
        $searchString = $GBIFscientificName;
        if ($author != null && $authorSearch){
            $searchString .= ' ' . $author;
        }

        $URL = "https://api.gbif.org/v1/species/search?q=" . urlencode($searchString) . "&datasetKey=$datasetKey";
        $doc = hQuery::fromUrl($URL, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);

        if ($doc->text() != '') {
            $data = json_decode($doc, true);
            if ($data && isset($data['results']) && count($data['results']) > 0) {
                foreach ($data['results'] as $result) {
                    $synonymData = false;
                    // Verificamos si la especie está aceptada
                    if ($result['taxonomicStatus'] != 'ACCEPTED' || $result['origin'] != 'SOURCE' ||
                        (isset($result['canonicalName']) && $result['canonicalName'] != $GBIFscientificName)

                    ) {

                        if (in_array($result['taxonomicStatus'], $this->synonymValues) &&
                            ($result['key'] == ($result['nubKey'] ?? '')) && $result['canonicalName'] == $GBIFscientificName
                        ) {
                            // Esto es un sinónimo y tendríamos que traernos el sinónimo
                            $synonymData = hQuery::fromUrl(
                                'https://api.gbif.org/v1/species/' . $result['acceptedKey'],
                                ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']
                            );
                            if ($synonymData->text() != '') {
                                $result = json_decode($synonymData, true);
                                $result['synonym'] = true;
                            }
                        } else {
                            continue;
                        }
                    }
                    return $result;
                }
            }


        }
        return false;
    }

    protected function validMatch() : bool
    {
        $result = false;

        if ($this->scientificName == $this->remoteScientificName) {
            $result = true;
        } else {
            $stripedOriginals = str_replace([' indet.', ' spec.'], '', [$this->scientificName, $this->remoteScientificName]);
            if ($stripedOriginals[0] == $stripedOriginals[1]) {
                $result = true;
            }
        }

        return $result;

    }
}