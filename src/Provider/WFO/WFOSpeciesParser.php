<?php

namespace Somms\BV2Observation\Provider\WFO;

use Somms\BV2Observation\Parser\GNames\GNParser\LocaBinGNSpeciesParser;
use Somms\BV2Observation\Parser\AbstractSpeciesParser;
use Somms\BV2Observation\Data\Species;

class WFOSpeciesParser extends AbstractSpeciesParser
{
    protected function parseInput()
    {
        $fullName = trim((string) ($this->input['full_name_plain'] ?? ''));
        if ($fullName === '') {
            return new Species('', '', '');
        }

        $nameParser = new LocaBinGNSpeciesParser();
        $nameParser->setInput($fullName);
        $species = $nameParser->getSpecies();

        return new WfoParserResult(
            $species->getScientificName(),
            $species->getCommonName(),
            $species->getAuthor(),
            (string) ($this->input['wfo_id'] ?? ''),
        );
    }
}

/**
 * Parser output carrying the WFO identifier into {@see \Somms\BV2Observation\Data\RemoteSpecies::populateFromRemote()}.
 */
final class WfoParserResult extends Species
{
    public function __construct(
        string $scientificName,
        string $commonName,
        string $author,
        private readonly string $remoteId = '',
    ) {
        parent::__construct($scientificName, $commonName, $author);
    }

    public function getRemoteId(): string
    {
        return $this->remoteId;
    }
}
