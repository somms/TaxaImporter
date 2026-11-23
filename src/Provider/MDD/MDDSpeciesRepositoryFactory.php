<?php

namespace Somms\BV2Observation\Provider\MDD;

use Somms\BV2Observation\Source\DataSourceInterface;
use Somms\BV2Observation\Source\Database\DatabaseDataSource;

class MDDSpeciesRepositoryFactory
{
    /**
     * Crea un MDDSpeciesDataAccessInterface a partir de la opción 'datasource'
     * que ya ha resuelto PipelineService.
     *
     * @param mixed $datasource Puede ser:
     *                          - DataSourceInterface
     *                          - array ['main' => DataSourceInterface, 'synonyms' => DataSourceInterface]
     *                          - MDDSpeciesDataAccessInterface (ya construido)
     */
    public static function fromDatasourceOption(mixed $datasource): MDDSpeciesDataAccessInterface
    {
        // Si ya nos dan un repositorio, lo usamos tal cual (flexible para el futuro)
        if ($datasource instanceof MDDSpeciesDataAccessInterface) {
            return $datasource;
        }

        $mainDs = null;
        $synDs  = null;

        if ($datasource instanceof DataSourceInterface) {
            $mainDs = $datasource;
        } elseif (is_array($datasource)) {
            $mainDs = $datasource['main']     ?? null;
            $synDs  = $datasource['synonyms'] ?? null;
        }

        if (!$mainDs instanceof DataSourceInterface) {
            throw new \InvalidArgumentException('MDDSpeciesRepositoryFactory: datasource principal inválido');
        }

        // Si ambos datasources son de tipo DatabaseDataSource, usamos acceso optimizado a BD
        if ($mainDs instanceof DatabaseDataSource && $synDs instanceof DatabaseDataSource) {
            $pdo          = $mainDs->getPdo();           // asumimos misma conexión
            $speciesTable = $mainDs->getTableName();
            $synTable     = $synDs->getTableName();

            return new MDDSpeciesDbDataAccess($pdo, $speciesTable, $synTable);
        }

        // Fallback genérico: versión en memoria (CSV/iterador), funciona con CSV o BD
        return new MDDSpeciesCsvDataAccess($mainDs, $synDs);
    }
}