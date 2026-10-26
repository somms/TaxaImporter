<?php

namespace Somms\BV2Observation\Tests\Support;

use Somms\BV2Observation\Util\WfoTaxonListReader as CoreReader;

/** @deprecated Use Somms\BV2Observation\Util\WfoTaxonListReader */
final class WfoTaxonListReader
{
    public static function defaultFixturePath(): string
    {
        return CoreReader::defaultFixturePath();
    }

    public static function read(string $path): array
    {
        return CoreReader::read($path);
    }
}
