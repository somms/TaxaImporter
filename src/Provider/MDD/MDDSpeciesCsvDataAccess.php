<?php

namespace Somms\BV2Observation\Provider\MDD;

use Somms\BV2Observation\Source\DataSourceInterface;

class MDDSpeciesCsvDataAccess implements MDDSpeciesDataAccessInterface
{
    private DataSourceInterface $mainDs;
    private ?DataSourceInterface $synDs;

    /** @var array<string,array>|null */
    private ?array $mainBySciName = null;

    public function __construct(DataSourceInterface $mainDs, ?DataSourceInterface $synDs = null)
    {
        $this->mainDs = $mainDs;
        $this->synDs  = $synDs;
    }

    private function ensureMainLoaded(): void
    {
        if ($this->mainBySciName !== null) {
            return;
        }

        $this->mainBySciName = [];

        foreach ($this->mainDs->getInputCollection() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = $row['sciName'] ?? null;
            if ($key) {
                $this->mainBySciName[$key] = $row;
            }
        }
    }

    public function findBySciName(string $normalizedSciName): ?array
    {
        $this->ensureMainLoaded();
        return $this->mainBySciName[$normalizedSciName] ?? null;
    }

    public function hasGenus(string $genus): bool
    {
        $this->ensureMainLoaded();
        foreach ($this->mainBySciName as $row) {
            if (($row['genus'] ?? null) === $genus) {
                return true;
            }
        }
        return false;
    }

    public function hasHigherTaxon(string $taxonName): bool
    {
        $this->ensureMainLoaded();
        $higherCols = [
            'subclass','infraclass','magnorder','superorder','order',
            'suborder','infraorder','parvorder','superfamily','family',
            'subfamily','tribe','subtribe',
        ];

        foreach ($this->mainBySciName as $row) {
            foreach ($higherCols as $col) {
                if (($row[$col] ?? null) === $taxonName) {
                    return true;
                }
            }
        }

        return false;
    }

    public function findAcceptedBySynonym(string $originalCombination): ?array
    {
        if (!$this->synDs) {
            return null;
        }

        foreach ($this->synDs->getInputCollection() as $synRow) {
            if (!is_array($synRow)) {
                continue;
            }
            if (($synRow['MDD_original_combination'] ?? null) === $originalCombination) {
                $id = $synRow['MDD_species_id'] ?? null;
                if (!$id) {
                    return null;
                }

                $this->ensureMainLoaded();
                // buscar en main por id
                foreach ($this->mainBySciName as $row) {
                    if (($row['id'] ?? null) == $id) {
                        return $row;
                    }
                }
                return null;
            }
        }

        return null;
    }

    public function getAllSpecies(): \Iterator
    {
        // Podrías devolver getInputCollection() directamente,
        // o el array cacheado si ya está cargado.
        $this->ensureMainLoaded();
        return new \ArrayIterator($this->mainBySciName);
    }

    public function getAllSynonyms(): \Iterator
    {
        if (!$this->synDs) {
            return new \ArrayIterator([]);
        }
        return $this->synDs->getInputCollection();
    }
}
