<?php

namespace Somms\BV2Observation\Source\Null;

use Somms\BV2Observation\Source\DataSourceInterface;

class NullDataSource implements DataSourceInterface
{

    /**
     * @inheritDoc
     */
    public function getInputCollection()
    {
        return new NullCollection();
    }

    public function getItemName($item)
    {
        return '';
    }
}