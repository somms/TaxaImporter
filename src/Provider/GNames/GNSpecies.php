<?php

namespace Somms\BV2Observation\Provider\GNames;

use Somms\BV2Observation\Data\RemoteSpecies;

class GNSpecies extends RemoteSpecies
{
    private static ?GnVerifierCli $verifier = null;

    public static function setVerifier(?GnVerifierCli $verifier): void
    {
        self::$verifier = $verifier;
    }

    protected function queryRemoteSource(string $speciesName, string $author, $options = []): mixed
    {
        $input = trim($speciesName . ' ' . $author);
        $source = (string) ($options['source'] ?? GnVerifierCli::DATASOURCE_GBIF);
        $verifier = self::$verifier ?? new GnVerifierCli();

        $result = $verifier->verifyCompact($input, $source);
        if ($result === null) {
            return false;
        }

        if (($result['isSynonym'] ?? false) && $speciesName !== ($result['currentName'] ?? '')) {
            return $this->queryRemoteSource((string) $result['currentName'], '', $options);
        }

        return $result;
    }
}