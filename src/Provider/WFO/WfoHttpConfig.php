<?php

namespace Somms\BV2Observation\Provider\WFO;

/**
 * Guzzle client options for WFO HTTP APIs.
 *
 * Environment:
 * - TAXAIMPORTER_WFO_INSECURE_SSL=1 — disable TLS verification (dev/WSL only).
 * - TAXAIMPORTER_WFO_CA_BUNDLE=/path/to/cacert.pem — explicit CA bundle (preferred on WSL).
 */
final class WfoHttpConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function guzzleClientOptions(): array
    {
        return self::guzzleClientOptionsForVerify(self::tlsVerifyOption());
    }

    /**
     * @return array<string, mixed>
     */
    public static function guzzleClientOptionsForVerify(bool|string $verify): array
    {
        return [
            'timeout' => 30,
            'connect_timeout' => 15,
            'verify' => $verify,
            'headers' => ['Accept' => 'application/json'],
        ];
    }

    public static function sslFallbackEnabled(): bool
    {
        return filter_var(getenv('TAXAIMPORTER_WFO_SSL_FALLBACK') ?: '1', FILTER_VALIDATE_BOOL);
    }

    public static function isTlsError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'ssl')
            || str_contains($message, 'certificate')
            || str_contains($message, 'curl error 60')
            || str_contains($message, 'unable to get local issuer');
    }

    /**
     * @return bool|string Guzzle `verify` option.
     */
    public static function tlsVerifyOption(): bool|string
    {
        if (filter_var(getenv('TAXAIMPORTER_WFO_INSECURE_SSL') ?: '0', FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $bundle = getenv('TAXAIMPORTER_WFO_CA_BUNDLE');
        if (is_string($bundle) && $bundle !== '' && is_readable($bundle)) {
            return $bundle;
        }

        foreach (self::defaultCaBundlePaths() as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function defaultCaBundlePaths(): array
    {
        return [
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/ca-bundle.pem',
            '/usr/share/ssl/certs/ca-bundle.crt',
        ];
    }
}
