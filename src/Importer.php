#!/usr/bin/php
<?php
/**
 * User: Julio. Somms Multimedia Solutions SL
 * Date: 27/10/2018
 * Time: 20:41
 */

namespace Somms\BV2Observation;
require __DIR__ . '/../vendor/autoload.php';


use DI\Attribute\Inject;
use DI\Container;
use Somms\BV2Observation\Pipeline\PipelineManager;
use Somms\BV2Observation\Processor\ProcessorService;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Exception;
use splitbrain\phpcli\Options;

class Importer extends CLI
{
    private $processorService;
    private PipelineManager $pipelineManager;

    public function __construct(ProcessorService $processorService, PipelineManager $pipelineManager, $autocatch = true, )
    {
        $this->processorService = $processorService;
        $this->pipelineManager = $pipelineManager;
        parent::__construct($autocatch);
    }

    public static function getServiceContainer() : Container{
        static $serviceContainer;
        if(!$serviceContainer){
            $serviceContainer = require __DIR__ . '/app/bootstrap.php';
        }

        return $serviceContainer;
    }

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     *
     * @return void
     *
     * @throws Exception
     */
    protected function setup(Options $options)
    {

        $options->setHelp('A tool for converting taxonomies from one source to another, and verifying it against checklists accepted by Observation.org');

        $options->registerCommand('clean-species', 'Process species from a source');
        $options->registerOption('pipeline', 'Pipeline filename, in the pipeline config folder. Use this option to load the set of options from the yml file.', 'p', 'pipeline_name', 'clean-species');
        $options->registerOption('skip', 'Number of rows to jump over', 's', 'skip', 'clean-species' );

    }

    /**
     * Main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     *
     * @return void
     *
     * @throws Exception
     */
    protected function main(Options $options)
    {
        if ($options->getOpt('version')) {
            $this->info('2.0.0');
        }

        switch ($options->getCmd()) {
            case 'clean-species':
                $this->cleanSpecies($options);
                break;
            default:
                $this->error('No known command was called, we show the default help instead:');
                echo $options->help();
                exit;
        }
    }


    /**
     * @param $options Options
     */
    #[Inject]
    protected function cleanSpecies($options)
    {

        $args = $options->getArgs();
        if($pipelineName = $options->getOpt('pipeline')){
            // Cargamos los valores desde el fichero de configuración
            $pipeline = $this->pipelineManager->getPipeline($pipelineName);
            $processor = $this->processorService->getProcessor($pipeline);
        }else{
            $this->error('Unable to create pipeline');
            exit;
        }

        $offset = $options->getOpt('skip') ?? 0;

        $processor->process($offset);

    }
}
$serviceContainer = Importer::getServiceContainer();

// execute it
$cli = $serviceContainer->get(Importer::class);
$cli->run();