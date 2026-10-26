<?php

namespace Somms\BV2Observation\Parser\GNames\GNVerifier;

use Somms\BV2Observation\Provider\GNames\GnVerifierCli;

/**
 * Local gnverifier binary access. Delegates to {@see GnVerifierCli}.
 */
final class LocalBinGNSpeciesVerifier
{
    public function __construct(private ?GnVerifierCli $cli = null)
    {
    }

    /**
     * @return array<string, mixed>|null First verifier result row (compact JSON), or null.
     */
    public function verifyCompact(string $input, string $dataSourceId = GnVerifierCli::DATASOURCE_GBIF): ?array
    {
        return ($this->cli ?? new GnVerifierCli())->verifyCompact($input, $dataSourceId);
    }
}
