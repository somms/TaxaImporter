<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 27/10/2018
 * Time: 20:52
 */

namespace Somms\BV2Observation\Data;


use duzun\hQuery;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Provider\Observation\ObservationWebSpeciesParser;

class Species
{
    protected string $originId;

    /*
     * @var string
     */
    protected string $scientificName;

    /*
     * @var string
     */
    protected string $commonName;

    protected string $author;

    function __construct(string $scientificName, string $commonName, string $author = '', string $originId = '')
    {
        $this->scientificName = $scientificName;
        $this->commonName = $commonName;
        $this->author = $author;
        $this->originId = $originId;
    }

    /**
     * @return string
     */
    public function getCommonName(): string
    {
        return $this->commonName;
    }

    /**
     * @return string
     */
    public function getScientificName(): string
    {
        return $this->scientificName;
    }


    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getOriginId(): string
    {
        return $this->originId;
    }

    public function setOriginId(string $originId): void
    {
        $this->originId = $originId;
    }

} 