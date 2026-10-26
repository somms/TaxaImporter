<?php

namespace Somms\BV2Observation\Tests\WFO;

use PHPUnit\Framework\TestCase;
use Somms\BV2Observation\Parser\GNames\GNParser\LocaBinGNSpeciesParser;
use Somms\BV2Observation\Provider\GNames\GnVerifierCli;
use Somms\BV2Observation\Provider\GNames\GNWFOSpecies;
use Somms\BV2Observation\Provider\WFO\WFOSpecies;
use Somms\BV2Observation\Provider\WFO\WFOSpeciesParser;
use Somms\BV2Observation\Tests\Support\WfoTaxonListReader;

/**
 * Live checks against WFO Match API and GNVerifier (data source 196).
 *
 * Run: TAXAIMPORTER_LIVE_WFO=1 vendor/bin/phpunit --group live
 *
 * @group live
 */
final class WfoLiveValidationTest extends TestCase
{
    private const MIN_EXPECTED_MATCH_RATE = 0.85;

    protected function setUp(): void
    {
        if (!getenv('TAXAIMPORTER_LIVE_WFO')) {
            $this->markTestSkipped('Set TAXAIMPORTER_LIVE_WFO=1 to run live WFO validation.');
        }
    }

    /**
     * @return list<string>
     */
    private function loadTaxa(): array
    {
        $taxa = WfoTaxonListReader::read(WfoTaxonListReader::defaultFixturePath());
        $max = (int) (getenv('TAXAIMPORTER_WFO_MAX_TAXA') ?: 0);
        if ($max > 0) {
            $taxa = array_slice($taxa, 0, $max);
        }

        return $taxa;
    }

    public function testWfoRestMatchRateOnAndalusiaFixture(): void
    {
        $taxa = $this->loadTaxa();
        $this->assertNotEmpty($taxa, 'Fixture missing: ' . WfoTaxonListReader::defaultFixturePath());

        $parser = new WFOSpeciesParser();
        $options = [
            'fuzzy_names' => 2,
            'fuzzy_authors' => 1,
            'accept_single_candidate' => true,
            'rate_limit_seconds' => 0.05,
        ];
        $matched = 0;

        foreach ($taxa as $name) {
            $remote = new WFOSpecies($name, '', '');
            if ($remote->populateFromRemote($parser, $options)) {
                $matched++;
            }
        }

        $rate = $matched / count($taxa);
        $this->assertGreaterThanOrEqual(
            self::MIN_EXPECTED_MATCH_RATE,
            $rate,
            sprintf('WFO REST match rate %.1f%% (%d/%d) below threshold', $rate * 100, $matched, count($taxa)),
        );
    }

    public function testGnVerifierWfoDataSourceMatchRateOnAndalusiaFixture(): void
    {
        if (!is_executable('vendor/bin/gnverifier')) {
            $this->markTestSkipped('gnverifier binary not installed (composer install).');
        }

        $taxa = $this->loadTaxa();
        $this->assertNotEmpty($taxa);

        $parser = new \Somms\BV2Observation\Provider\GNames\GNSpeciesParser();
        $matched = 0;

        foreach ($taxa as $name) {
            $nameParser = new LocaBinGNSpeciesParser();
            $nameParser->setInput($name);
            $parsed = $nameParser->getSpecies();

            $remote = new GNWFOSpecies($parsed->getScientificName(), '', $parsed->getAuthor());
            if ($remote->populateFromRemote($parser, ['source' => GnVerifierCli::DATASOURCE_WFO])) {
                $matched++;
            }
        }

        $rate = $matched / count($taxa);
        $this->assertGreaterThanOrEqual(
            self::MIN_EXPECTED_MATCH_RATE,
            $rate,
            sprintf('GNVerifier WFO match rate %.1f%% (%d/%d) below threshold', $rate * 100, $matched, count($taxa)),
        );
    }
}
