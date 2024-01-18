<?php

namespace Somms\BV2Observation\Source\Null;

class NullCollection implements \Iterator
{

    public function current(): mixed
    {
        return null;
    }

    public function next(): void
    {
    }

    public function key(): mixed
    {
        return 0;
    }

    public function valid(): bool
    {
        return false;
    }

    public function rewind(): void
    {
    }
}