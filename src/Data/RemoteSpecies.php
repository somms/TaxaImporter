<?php

namespace Somms\BV2Observation\Data;

use Somms\BV2Observation\Data\Species;
use Somms\BV2Observation\Parser\ISpeciesParser;

abstract class RemoteSpecies extends Species
{
    protected $inRemote;

    protected string $remoteId;

    protected string $remoteScientificName;
    protected string $remoteAuthor;


    public function populateFromRemote(ISpeciesParser $remoteParser, $options = []) : bool{
        if($this->inRemote === NULL){
            $this->inRemote = false;
            $result = $this->queryRemoteSource($this->scientificName, $this->author, $options);
            if($result){
                $remoteParser->setInput($result);
                $remoteSpecies = $remoteParser->getSpecies();
                $this->remoteId = $remoteSpecies->getRemoteId();
                $this->remoteScientificName = $remoteSpecies->getScientificName();
                $this->remoteAuthor = $remoteSpecies->getAuthor();
                $this->inRemote = ($result['synonym'] ?? false) || $this->validMatch();
            }
        }

        return $this->inRemote;
    }

    protected abstract function queryRemoteSource(string $speciesName, string $author, $options = []): mixed;

    /**
     * @return mixed
     */
    public function getInRemote()
    {
        return $this->inRemote;
    }

    /**
     * @param mixed $inRemote
     */
    public function setInRemote($inRemote): void
    {
        $this->inRemote = $inRemote;
    }

    /**
     * @return string
     */
    public function getRemoteId(): string
    {
        return $this->remoteId;
    }

    /**
     * @param string $remoteId
     */
    public function setRemoteId(string $remoteId): void
    {
        $this->remoteId = $remoteId;
    }

    /**
     * @return string
     */
    public function getRemoteScientificName(): string
    {
        return $this->remoteScientificName;
    }

    /**
     * @param string $remoteScientificName
     */
    public function setRemoteScientificName(string $remoteScientificName): void
    {
        $this->remoteScientificName = $remoteScientificName;
    }

    /**
     * @return string
     */
    public function getRemoteAuthor(): string
    {
        return $this->remoteAuthor;
    }

    /**
     * @param string $remoteAuthor
     */
    public function setRemoteAuthor(string $remoteAuthor): void
    {
        $this->remoteAuthor = $remoteAuthor;
    }

    protected function validMatch():bool{
        return true;
    }
}