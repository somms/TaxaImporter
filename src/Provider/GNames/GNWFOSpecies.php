<?php

namespace Somms\BV2Observation\Provider\GNames;

class GNWFOSpecies extends GNSpecies
{
    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        if (!isset($options['source'])) {
            $options['source'] = GnVerifierCli::DATASOURCE_WFO;
        }

        return parent::queryRemoteSource($speciesName, $author, $options);
    }
}
