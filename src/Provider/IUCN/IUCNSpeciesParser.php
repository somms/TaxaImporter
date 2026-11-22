<?php

namespace Somms\BV2Observation\Provider\IUCN;

use Somms\BV2Observation\Parser\AbstractSpeciesParser;

class IUCNSpeciesParser extends AbstractSpeciesParser
{
    /**
     * Expected input is one element from IUCNSpecies::queryRemoteSource
     * Should contain keys like:
     *   - scientific_name (or scientificname from API)
     *   - category
     *   - taxonid
     *   - authority (author)
     *   - main_common_name
     */
    protected function parseInput()
    {
        $input = $this->input;

        // Extract scientific name - API uses 'scientific_name' or 'scientificname'
        $scientificName = $input['scientific_name']
            ?? $input['scientificname']
            ?? $input['label']
            ?? '';

        // Extract author/authority
        $author = $input['authority']
            ?? $input['infra_authority']
            ?? '';

        // Extract common name
        $commonName = $input['main_common_name']
            ?? $input['common_name']
            ?? '';

        // Build IUCNSpecies entity (extends RemoteSpecies)
        $species = new IUCNSpecies($scientificName, $commonName, $author);

        // Set remote ID (taxonid)
        if (!empty($input['taxonid'])) {
            $species->setRemoteId((string)$input['taxonid']);
        }

        // Set remote scientific name (same as scientific name for IUCN)
        $species->setRemoteScientificName($scientificName);

        // Set remote author
        $species->setRemoteAuthor($author);

        // Store Red List category in extra data
        if (!empty($input['category'])) {
            $species->extra['iucn_category'] = $input['category'];
        }

        // Store assessment information if available
        if (!empty($input['assessment_date'])) {
            $species->extra['assessment_date'] = $input['assessment_date'];
        }

        // Store population trend if available
        if (!empty($input['population_trend'])) {
            $species->extra['population_trend'] = $input['population_trend'];
        }

        // Store taxonomic information
        if (!empty($input['kingdom'])) {
            $species->extra['kingdom'] = $input['kingdom'];
        }
        if (!empty($input['phylum'])) {
            $species->extra['phylum'] = $input['phylum'];
        }
        if (!empty($input['class'])) {
            $species->extra['class'] = $input['class'];
        }
        if (!empty($input['order'])) {
            $species->extra['order'] = $input['order'];
        }
        if (!empty($input['family'])) {
            $species->extra['family'] = $input['family'];
        }
        if (!empty($input['genus'])) {
            $species->extra['genus'] = $input['genus'];
        }

        // Store link to IUCN page
        if (!empty($input['link'])) {
            $species->extra['iucn_link'] = $input['link'];
        }

        return $species;
    }
}
