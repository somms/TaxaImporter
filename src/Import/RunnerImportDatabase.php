<?php

declare(strict_types=1);

namespace Somms\BV2Observation\Import;

use PDO;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Database connection for Taxa Runner admin import scripts (Obs / MycoBank / MDD).
 * Overrides YAML DSN with TAXAIMPORTER_IMPORT_* env when set (stack uses obs-db service).
 */
final class RunnerImportDatabase
{
    /**
     * @return array{dsn: string, username: string, password: string, table_name: string, ...}
     */
    public static function loadBlock(string $configFile, string $blockKey): array
    {
        if (!is_file($configFile)) {
            throw new RuntimeException("No existe la configuración {$configFile}.");
        }

        $parsed = Yaml::parseFile($configFile);
        if (!is_array($parsed)) {
            throw new RuntimeException("YAML inválido: {$configFile}");
        }

        $block = $parsed[$blockKey] ?? null;
        if (!is_array($block)) {
            throw new RuntimeException("Falta el bloque {$blockKey} en {$configFile}.");
        }

        foreach (['dsn', 'username', 'password', 'table_name'] as $required) {
            if (!array_key_exists($required, $block)) {
                throw new RuntimeException("Falta '{$required}' en {$configFile} ({$blockKey}).");
            }
        }

        $dsn = getenv('TAXAIMPORTER_IMPORT_DSN');
        if (is_string($dsn) && $dsn !== '') {
            $block['dsn'] = $dsn;
        }

        $user = getenv('TAXAIMPORTER_IMPORT_USER');
        if (is_string($user) && $user !== '') {
            $block['username'] = $user;
        }

        $password = getenv('TAXAIMPORTER_IMPORT_PASSWORD');
        if (is_string($password) && $password !== '') {
            $block['password'] = $password;
        }

        $table = getenv('TAXAIMPORTER_IMPORT_TABLE');
        if (is_string($table) && $table !== '') {
            $block['table_name'] = $table;
        }

        return $block;
    }

    public static function connect(array $block): PDO
    {
        return new PDO(
            (string) $block['dsn'],
            (string) $block['username'],
            (string) $block['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    public static function grantRunnerReadOnly(PDO $pdo, string $tableName): void
    {
        $role = getenv('TAXAIMPORTER_RUNNER_RO_ROLE');
        if (!is_string($role) || $role === '') {
            $role = 'runner_ro';
        }
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $role) || !preg_match('/^[a-z_][a-z0-9_]*$/i', $tableName)) {
            return;
        }

        $quoted = '"' . str_replace('"', '""', $tableName) . '"';
        $pdo->exec(sprintf('GRANT SELECT ON %s TO %s', $quoted, $role));
    }
}