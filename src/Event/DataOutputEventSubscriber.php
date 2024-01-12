<?php

namespace Somms\BV2Observation\Event;

use Somms\BV2Observation\DataOutput\DataOutputInterface;
use Somms\BV2Observation\Processor\Processor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DataOutputEventSubscriber implements EventSubscriberInterface
{
    private DataOutputInterface $okDataOutput;
    private DataOutputInterface $errorDataOutput;
    private DataOutputInterface $discardDataOutput;
    private Processor $processor;

    public function __construct(Processor $processor,  DataOutputInterface $okDataOutput, DataOutputInterface $errorDataOutput, DataOutputInterface $discardDataOutput) {
        $this->processor = $processor;
        $this->okDataOutput = $okDataOutput;
        $this->errorDataOutput = $errorDataOutput;
        $this->discardDataOutput = $discardDataOutput;
        
    }

    public function onDataOk(ProcessorEvent $event) {
        if($this->processor === $event->getProcessor()){
            $data = $event->getData();
            $this->okDataOutput->put($data);
        }
    }

    public function onDataError(ProcessorEvent $event) {

        if($this->processor === $event->getProcessor()) {
            $data = $event->getData();
            $this->errorDataOutput->put($data);
        }
    }

    public function onDataDiscard(ProcessorEvent $event) {

        if($this->processor === $event->getProcessor()) {
            $data = $event->getData();
            $this->discardDataOutput->put($data);
        }
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            ProcessorEvent::TYPE_OK => 'onDataOk',
            ProcessorEvent::TYPE_ERROR => 'onDataError',
            ProcessorEvent::TYPE_DISMISSED => 'onDataDiscard'
        ];
    }
}