<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Provider\MycoBank;

use Somms\BV2Observation\Data\RemoteSpecies;
use Somms\BV2Observation\Source\Database\DatabaseDataSource;

final class MycoBankSpecies extends RemoteSpecies
{
    protected function queryRemoteSource(string $speciesName, string $author = null, $options = []): mixed
    {
        $datasource = $options['datasource'] ?? null;
        if (!$datasource instanceof DatabaseDataSource) {
            throw new \InvalidArgumentException(
                'MycoBank requiere un datasource de base de datos PostgreSQL.'
            );
        }

        $repository = new MycoBankSpeciesDbDataAccess(
            $datasource->getPdo(),
            $datasource->getTableName()
        );
        $speciesName = trim($speciesName);

        $row = $repository->findResolvedByName($speciesName);
        if ($row !== null) {
            return $this->remoteResult($row);
        }

        if (preg_match('/^([A-Z][a-z-]+)\s+(?:spec\.|sp\.)$/', $speciesName, $matches)
            && $repository->hasTaxon($matches[1])) {
            return [
                'taxon_name' => $speciesName,
                'mycobank_number' => $speciesName,
                'authors_abbreviated' => '',
                'synonym' => false,
            ];
        }

        if (preg_match('/^(.+)\s+indet\.$/i', $speciesName, $matches)
            && $repository->hasTaxon(trim($matches[1]))) {
            return [
                'taxon_name' => $speciesName,
                'mycobank_number' => $speciesName,
                'authors_abbreviated' => '',
                'synonym' => false,
            ];
        }

        return false;
    }

    protected function validMatch(): bool
    {
        return $this->remoteId !== '';
    }

    private function remoteResult(array $row): array
    {
        return [
            'taxon_name' => (string) ($row['taxon_name'] ?? ''),
            'mycobank_number' => (string) ($row['mycobank_number'] ?? ''),
            'authors_abbreviated' => (string) ($row['authors_abbreviated'] ?? ''),
            'synonym' => (bool) ($row['synonym'] ?? false),
        ];
    }
}
