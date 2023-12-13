<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 27/10/2018
 * Time: 20:52
 */

namespace Somms\BV2Observation\Data;


use duzun\hQuery;
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Provider\Observation\ObservationSpeciesParser;

class Species
{

    /*
     * @var string
     */
    protected string $scientificName;

    /*
     * @var string
     */
    protected string $commonName;

    protected string $author;

    function __construct(string $scientificName, string $commonName, string $author = '')
    {
        $this->scientificName = $scientificName;
        $this->commonName = $commonName;
        $this->author = $author;
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

} 