<?php

namespace Somms\BV2Observation\Tests\WFO;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Somms\BV2Observation\Provider\WFO\WfoMatchClient;

final class WfoMatchClientTest extends TestCase
{
    public function testExtractMatchReturnsNullWhenErrorFlagSet(): void
    {
        $this->assertNull(WfoMatchClient::extractMatch(['error' => true, 'match' => ['wfo_id' => 'wfo-1']]));
    }

    public function testMatchBuildsQueryAndParsesJson(): void
    {
        $json = json_encode([
            'inputString' => 'Quercus ilex L.',
            'match' => [
                'wfo_id' => 'wfo-0000123456-2023-04',
                'full_name_plain' => 'Quercus ilex L.',
            ],
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([new Response(200, [], $json)]);
        $client = new WfoMatchClient(new Client(['handler' => HandlerStack::create($mock)]));

        $result = $client->match('Quercus ilex L.', [
            'fuzzy_names' => 2,
            'accept_single_candidate' => true,
        ]);

        $this->assertIsArray($result);
        $match = WfoMatchClient::extractMatch($result);
        $this->assertSame('wfo-0000123456-2023-04', $match['wfo_id']);
    }
}
