<?php

namespace Somms\BV2Observation\Tests\WFO;

use PHPUnit\Framework\TestCase;
use Somms\BV2Observation\Provider\WFO\WfoHttpConfig;

final class WfoHttpConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TAXAIMPORTER_WFO_INSECURE_SSL');
        putenv('TAXAIMPORTER_WFO_CA_BUNDLE');
    }

    public function testInsecureSslDisablesVerification(): void
    {
        putenv('TAXAIMPORTER_WFO_INSECURE_SSL=1');
        $this->assertFalse(WfoHttpConfig::tlsVerifyOption());
    }

    public function testCaBundleEnvOverridesDefault(): void
    {
        $path = '/etc/ssl/certs/ca-certificates.crt';
        if (!is_readable($path)) {
            $this->markTestSkipped('No system CA bundle at ' . $path);
        }
        putenv('TAXAIMPORTER_WFO_CA_BUNDLE=' . $path);
        $this->assertSame($path, WfoHttpConfig::tlsVerifyOption());
    }
}
