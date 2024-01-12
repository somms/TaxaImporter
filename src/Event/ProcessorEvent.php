<?php

namespace Somms\BV2Observation\Event;

use Somms\BV2Observation\Processor\Processor;
use Symfony\Contracts\EventDispatcher\Event;

class ProcessorEvent extends Event
{
    public const TYPE_OK = 'processor.ok';
    public const TYPE_ERROR = 'processor.error';
    public const TYPE_DISMISSED = 'processor.dismissed';
    private array $data;
    private Processor $processor;

    public function __construct(array $data, Processor $sourceProcessor) {
        $this->data = $data;
        $this->processor = $sourceProcessor;
    }

    public function getData(): array {
        return $this->data;
    }

    /**
     * @return Processor
     */
    public function getProcessor(): Processor
    {
        return $this->processor;
    }
}