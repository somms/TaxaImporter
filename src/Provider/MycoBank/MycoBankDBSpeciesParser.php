<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Provider\MycoBank;

/**
 * Parser de filas completas de mycobank_species usadas como input de pipeline.
 */
final class MycoBankDBSpeciesParser extends MycoBankSpeciesParser
{
    /**
     * Conserva la fila completa de la base de datos (no solo el nombre).
     */
    public function setInput($input): void
    {
        $this->input = $input;
    }
}
