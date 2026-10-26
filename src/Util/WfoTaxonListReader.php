<?php

namespace Somms\BV2Observation\Util;

final class WfoTaxonListReader
{
    public static function defaultFixturePath(): string
    {
        return dirname(__DIR__, 2) . '/tests/fixtures/wfo_andalusia_taxa.txt';
    }

    /**
     * @return list<string>
     */
    public static function read(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $names = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $names[] = $line;
        }

        return $names;
    }
}
