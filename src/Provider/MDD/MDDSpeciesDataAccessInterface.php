<?php

namespace Somms\BV2Observation\Provider\MDD;

interface MDDSpeciesDataAccessInterface
{
    public function findBySciName(string $normalizedSciName): ?array;

    public function hasGenus(string $genus): bool;

    public function hasHigherTaxon(string $taxonName): bool;

    public function findAcceptedBySynonym(string $originalCombination): ?array;

    /**
     * Fallback para casos en los que la lógica necesite iterar todo
     * (por ejemplo, alguna heurística compleja que no quieras pasar a SQL).
     */
    public function getAllSpecies(): \Iterator;

    public function getAllSynonyms(): \Iterator;
}
