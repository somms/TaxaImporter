<?php

use Somms\BV2Observation\Importer;
use Somms\BV2Observation\Pipeline\PipelineService;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Service\ProcessorService;
use Somms\BV2Observation\Source\DataSourceService;
use function DI\create;

return [
    Importer::class => DI\autowire()->constructor(DI\get(ProcessorService::class), DI\get(PipelineService::class)),
    ProcessorService::class => DI\autowire(),
    PipelineService::class => DI\autowire()->constructor(DI\get(ConfigService::class), DI\get(DataSourceService::class)),
    ConfigService::class => create()->constructor(DI\get('config.general_path')),
    'config.general_path' => __DIR__ . '/../../config/',
    DataSourceService::class => DI\autowire(),

];