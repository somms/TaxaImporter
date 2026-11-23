<?php

namespace Somms\BV2Observation\Provider\MDD;

use Somms\BV2Observation\Parser\AbstractSpeciesParser;

class MDDSpeciesParser extends AbstractSpeciesParser
{
    /**
     * Preprocesses the input from MDD database.
     * Expected format: "Genus_species Author" or array with 'sciName' and 'authoritySpeciesAuthor'
     */
    public function preprocessInput($input)
    {
        // Si ya es un array (viene de queryRemoteSource), lo dejamos tal cual
        if (is_array($input)) {
            return $input;
        }

        // Si es un string, lo procesamos
        if (is_string($input)) {
            // Reemplazar guiones bajos por espacios
            $input = str_replace('_', ' ', $input);
        }

        return $input;
    }

    /**
     * Parses input from MDD database.
     * Can receive:
     *   1. Array from MDDSpecies::queryRemoteSource with keys: sciName, id, authoritySpeciesAuthor, synonym
     *   2. String from database like "Genus_species Author"
     */
    protected function parseInput()
    {
        $input = $this->input;

        // Caso 1: Ya viene como array procesado (desde queryRemoteSource)
        if (is_array($input) && isset($input['sciName'])) {
            return $this->parseFromArray($input);
        }

        // Caso 2: String crudo de la base de datos
        if (is_string($input)) {
            return $this->parseFromString($input);
        }

        // Caso 3: Array de base de datos con campos crudos
        if (is_array($input)) {
            return $this->parseFromDatabaseRow($input);
        }

        return null;
    }

    /**
     * Parse from already processed array (from queryRemoteSource)
     */
    private function parseFromArray(array $input): MDDSpecies
    {
        $scientificName = $input['sciName'] ?? '';
        $authorName     = $input['authoritySpeciesAuthor'] ?? '';

        $species = new MDDSpecies($scientificName, '', $authorName);

        if (!empty($input['id'])) {
            $species->setRemoteId((string)$input['id']);
        }

        $species->setRemoteScientificName($scientificName);
        $species->setRemoteAuthor($authorName);

        return $species;
    }

    /**
     * Parse from raw string like "Ornithorhynchus anatinus G. K. Shaw"
     */
    private function parseFromString(string $input): MDDSpecies
    {
        $input = trim($input);

        // Separar nombre científico y autor
        // El nombre científico son las primeras 1-2 palabras (Genus o Genus species)
        $parts = preg_split('/\s+/', $input);

        if (count($parts) < 1) {
            return new MDDSpecies('', '', '');
        }

        $scientificName = '';
        $authorName = '';

        // Detectar patrones comunes
        if (count($parts) >= 2) {
            // Si la segunda palabra empieza con mayúscula, probablemente es el autor
            if (preg_match('/^[A-Z]/', $parts[1])) {
                // Solo género
                $scientificName = $parts[0];
                $authorName = implode(' ', array_slice($parts, 1));
            } else {
                // Género + especie
                $scientificName = $parts[0] . ' ' . $parts[1];
                if (count($parts) > 2) {
                    $authorName = implode(' ', array_slice($parts, 2));
                }
            }
        } else {
            // Solo una palabra, es el género
            $scientificName = $parts[0];
        }

        // Normalizar patrones especiales
        if (preg_match('/^([A-Z][a-z]+)\s+(spec\.|sp\.)$/i', $scientificName)) {
            // Ya está en formato correcto
        } elseif (preg_match('/^(.+)\s+indet\.$/i', $scientificName)) {
            // Ya está en formato correcto
        }

        $species = new MDDSpecies($scientificName, '', $authorName);

        return $species;
    }

    /**
     * Parse from database row with fields like 'sciName', 'authoritySpeciesAuthor', 'id'
     */
    private function parseFromDatabaseRow(array $input): MDDSpecies
    {
        $scientificName = $input['sciName'] ?? ($input['MDD_species'] ?? '');
        $scientificName = str_replace('_', ' ', $scientificName);

        $authorName = $input['authoritySpeciesAuthor'] ?? '';

        $species = new MDDSpecies($scientificName, '', $authorName);

        if (!empty($input['id'])) {
            $species->setRemoteId((string)$input['id']);
        }

        return $species;
    }
}