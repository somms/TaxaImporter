<?php

namespace Somms\BV2Observation\Tests\GNames;

use PHPUnit\Framework\TestCase;
use Somms\BV2Observation\Provider\GNames\GnVerifierCli;
use Somms\BV2Observation\Provider\GNames\GNSpeciesParser;

final class GnVerifierResultSelectionTest extends TestCase
{
    public function testSelectBestResultSkipsBareNameWithoutRanks(): void
    {
        $payload = json_decode(
            file_get_contents(dirname(__DIR__) . '/fixtures/gnverifier_eryngium_tenue_wfo.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $cli = new GnVerifierCli();
        $best = $cli->selectBestResult($payload['results']);

        $this->assertNotNull($best);
        $this->assertSame('wfo-1200009730-2025-12', $best['recordId']);
        $this->assertStringContainsString('species', $best['classificationRanks']);
    }

    public function testParserDoesNotAppendSpecWhenRanksContainSpecies(): void
    {
        $payload = json_decode(
            file_get_contents(dirname(__DIR__) . '/fixtures/gnverifier_eryngium_tenue_wfo.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $cli = new GnVerifierCli();
        $best = $cli->selectBestResult($payload['results']);

        $parser = new GNSpeciesParser();
        $parser->setInput($best);
        $species = $parser->getSpecies();

        $this->assertSame('Eryngium tenue', $species->getScientificName());
        $this->assertStringNotContainsString('spec.', $species->getScientificName());
    }

    public function testParserUsesCardinalityWhenRanksMissing(): void
    {
        $parser = new GNSpeciesParser();
        $parser->setInput([
            'matchedCanonicalFull' => 'Eryngium tenue',
            'matchedName' => 'Eryngium tenue Lam.',
            'matchedNameID' => 'id-1',
            'matchedCardinality' => 2,
        ]);
        $species = $parser->getSpecies();

        $this->assertSame('Eryngium tenue', $species->getScientificName());
    }

    public function testParserAppendsIndetForGenusCardinality(): void
    {
        $parser = new GNSpeciesParser();
        $parser->setInput([
            'matchedCanonicalFull' => 'Quercus',
            'matchedName' => 'Quercus L.',
            'matchedNameID' => 'id-2',
            'matchedCardinality' => 1,
        ]);
        $species = $parser->getSpecies();

        $this->assertSame('Quercus indet.', $species->getScientificName());
    }
}
