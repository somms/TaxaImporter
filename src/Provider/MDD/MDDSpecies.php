<?php

namespace Somms\BV2Observation\Provider\MDD;

use Somms\BV2Observation\Data\RemoteSpecies;

class MDDSpecies extends RemoteSpecies
{

    protected function queryRemoteSource(string $speciesName, string $author = null, $options = []): mixed
    {
        $datasource = $options['datasource'] ?? null;
        if (!$datasource) {
            return false;
        }

        // Si ya te pasan un repositorio directamente, úsalo tal cual
        if ($datasource instanceof MDDSpeciesDataAccessInterface) {
            $repo = $datasource;
        } else {
            // Caso genérico: DataSourceInterface o array de ellos -> factoría
            $repo = MDDSpeciesRepositoryFactory::fromDatasourceOption($datasource);
        }

        $result = $this->resolveWithRepository($repo, $speciesName, $author ?? '');

        return $result ?? false;
    }

    /**
     * For MDD, if we found a row (either primary or via synonym),
     * we treat it as a valid match.
     */
    protected function validMatch(): bool
    {
        return $this->remoteId !== '';
    }

    private function resolveWithRepository(
        MDDSpeciesDataAccessInterface $repo,
        string $speciesName,
        string $author
    ): ?array {
        $speciesName = trim($speciesName);
        $parts       = preg_split('/\s+/', $speciesName);
        $normalized  = str_replace(' ', '_', $speciesName);

        // 1) match directo
        if ($row = $repo->findBySciName($normalized)) {
            $sciName = str_replace('_', ' ', $row['sciName'] ?? $normalized);

            return [
                'sciName'                => $sciName,
                'id'                     => $row['id'] ?? null,
                'authoritySpeciesAuthor' => $row['authoritySpeciesAuthor'] ?? '',
                'synonym'                => false,
            ];
        }

        // 2) Genus spec. / Genus sp.
        if (preg_match('/^([A-Z][a-z]+)\s+(spec\.|sp\.)$/', $speciesName, $m)) {
            $genusOnly = $m[1];
            if ($repo->hasGenus($genusOnly)) {
                return [
                    'sciName'                => $speciesName,
                    'id'                     => $speciesName,
                    'authoritySpeciesAuthor' => '',
                    'synonym'                => false,
                ];
            }
        }

        // 3) Taxon indet.
        if (preg_match('/^(.+)\s+indet\.$/i', $speciesName, $m)) {
            $taxonName = trim($m[1]);
            if ($repo->hasHigherTaxon($taxonName)) {
                return [
                    'sciName'                => $speciesName,
                    'id'                     => $speciesName,
                    'authoritySpeciesAuthor' => '',
                    'synonym'                => false,
                ];
            }
        }

        // 4) Sinónimo -> especie aceptada
        $spaceName   = str_replace('_', ' ', $normalized);
        $acceptedRow = $repo->findAcceptedBySynonym($spaceName);

        if ($acceptedRow) {
            $sciName = $acceptedRow['sciName'] ?? ($acceptedRow['MDD_species'] ?? '');
            $sciName = str_replace('_', ' ', $sciName);

            return [
                'sciName'                => $sciName,
                'id'                     => $acceptedRow['id'] ?? null,
                'authoritySpeciesAuthor' => $acceptedRow['authoritySpeciesAuthor'] ?? '',
                'synonym'                => true,
            ];
        }

        // Si quieres, aquí podrías seguir con otras heurísticas usando getAllSpecies(), etc.
        return null;
    }
}
