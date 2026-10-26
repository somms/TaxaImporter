<?php

namespace Somms\BV2Observation\Provider\GNames;

use Somms\BV2Observation\Data\Species;

class GNWFOSpeciesProcessor extends GNSpeciesProcessor
{
    function getRemoteSpecies(Species $species)
    {
        return new GNWFOSpecies($species->getScientificName(), $species->getCommonName(), $species->getAuthor());
    }

    function getRemoteRow(Species $species)
    {
        $result = parent::getRemoteRow($species);
        $result['wfo_id'] = $species->getRemoteId();
        $result['wfo_data_source'] = GnVerifierCli::DATASOURCE_WFO;

        return $result;
    }
}
