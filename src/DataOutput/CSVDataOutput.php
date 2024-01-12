<?php

namespace Somms\BV2Observation\DataOutput;

use Somms\BV2Observation\DataOutput\DataOutputInterface;

class CSVDataOutput implements DataOutputInterface
{
    /**
     * @var \SplFileObject
     */
    protected $csvFile;

    const DEFAULT_DELIMITER = ',';


    public function __construct(string $csvPath, string $mode)
    {
        $this->csvFile = new \SplFileObject($csvPath, $mode);
    }

    public function put($inputRow)
    {
        $this->csvFile->fputcsv($inputRow, self::DEFAULT_DELIMITER);
    }
}