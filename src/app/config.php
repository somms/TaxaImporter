<?php

use Somms\BV2Observation\DataOutput\DataOutputService;
use Somms\BV2Observation\DataOutput\IDataOutputService;
use Somms\BV2Observation\Event\DataOutputEventSubscriber;
use Somms\BV2Observation\Importer;
use Somms\BV2Observation\Pipeline\PipelineManager;
use Somms\BV2Observation\Pipeline\PipelineService;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Service\IProcessorService;
use Somms\BV2Observation\Service\ProcessorService;
use Somms\BV2Observation\Source\DataSourceService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function DI\create;

return [
    Importer::class => DI\autowire()->constructor(
        DI\get(ProcessorService::class),
        DI\get(PipelineManager::class)
    ),

    ProcessorService::class => DI\autowire(),

    DataOutputService::class => DI\autowire(DataOutputService::class),

    PipelineService::class => DI\autowire()->constructor(
        DI\get(ConfigService::class),
        DI\get(DataSourceService::class),
        DI\get(DataOutputService::class)
    ),
    PipelineManager::class => DI\autowire(),
    ConfigService::class => create()->constructor(DI\get('config.general_path')),
    'config.general_path' => __DIR__ . '/../../config/',
    EventDispatcherInterface::class => DI\get(\Symfony\Component\EventDispatcher\EventDispatcher::class),
];
