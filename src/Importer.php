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
use Somms\BV2Observation\Parser\ISpeciesParser;
use Somms\BV2Observation\Pipeline\PipelineManager;
use Somms\BV2Observation\Pipeline\PipelineService;
use Somms\BV2Observation\Provider\Forum4Images\BV\FloraSpeciesParser;
use Somms\BV2Observation\Provider\Forum4Images\GorostiCSVLocationProcessor;
use Somms\BV2Observation\Provider\Forum4Images\GorostiCSVObservationProcessor;
use Somms\BV2Observation\Provider\Forum4Images\SpeciesStringParser;
use Somms\BV2Observation\Provider\GBIF\GBIFSpeciesProcessor;
use Somms\BV2Observation\Provider\Observation\ObservationSpeciesProcessor;
use Somms\BV2Observation\Provider\Observation\GBIFSpeciesImportProcessor;
use Somms\BV2Observation\Provider\POWO\POWOSpeciesProcessor;
use Somms\BV2Observation\Service\ConfigService;
use Somms\BV2Observation\Service\ProcessorService;
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

        $options->registerCommand('clean-species', 'Verify species and gets a clean file and a error file');
        $options->registerArgument('origin', 'Source platform from species: BV, BV-Flora, Gorosti', false, 'clean-species');
        $options->registerArgument('target', 'Platform to verify species: POWO, GBIF or Observation', false, 'clean-species');
        $options->registerArgument('species', 'File with species', false, 'clean-species');
        $options->registerArgument('output', 'Output file with species', false, 'clean-species');
        $options->registerArgument('output-error', 'Output file for error records', false, 'clean-species');
        $options->registerOption('pipeline', 'Pipeline filename, in the pipeline config folder. Use this option to load the set of options from the yml file. If this option is not provided, the rest of arguments are required', 'p', 'pipeline_name', 'clean-species');
        $options->registerOption('skip', 'Number of rows to jump over', 's', 'skip', 'clean-species' );

        $options->registerCommand('obs-files', 'Convert files to Observation spreadsheet');
        $options->registerArgument('observations', 'File with observations', true, 'obs-files');
        $options->registerArgument('output', 'Name of the output file', true, 'obs-files');

        $options->registerCommand('clean-locations', 'Verify locations and gets a clean file and a error file');
        $options->registerArgument('locations', 'File with locations', true, 'clean-locations');
        $options->registerArgument('output', 'Output folder', true, 'clean-locations');

        $options->registerCommand('gbif-import', 'Import species from GBIF using its species name');
        $options->registerArgument('origin', 'Source platform from species: BV, BV-Flora, Gorosti', true, 'gbif-import');
        $options->registerArgument('species', 'File with species', true, 'gbif-import');
        $options->registerArgument('output', 'Output file with species', true, 'gbif-import');
        $options->registerArgument('output-error', 'Output file for error records', true, 'gbif-import');
        $options->registerArgument('skip', 'Number of rows to jump' );

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
            $this->info('1.0.0');
        }

        switch ($options->getCmd()) {
            case 'clean-species':
                $this->cleanSpecies($options);
                break;
            case 'obs-files':
                $this->obsFiles($options);
                break;
            case 'clean-locations':
                $this->cleanLocations($options);
                break;
            case 'gbif-import':
                $this->importGBIFSpecies($options);
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
    protected function cleanLocations($options)
    {
        $args = $options->getArgs();
        if (!file_exists($args[0]) || !is_file($args[0])) {
            $this->error('The location source file does not exist or is not a file' . $args[0]);
            exit;
        }

        $processor = new GorostiCSVLocationProcessor($args[0], $args[1]);

        $processor->process();
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
        }
        else{
            if (!in_array($args[1], ['GBIF', 'POWO', 'Observation'])) {
                $this->error('The target platform should be GBIF or Observation: ' . $args[0]);
                exit;
            }
            if (!in_array($args[0], ['BV', 'BV-Flora', 'Gorosti'])) {
                $this->error('The target platform should be GBIF or Observation: ' . $args[0]);
                exit;
            }
            if (!file_exists($args[2]) || !is_file($args[2])) {
                $this->error('The species source file does not exist or is not a file: ' . $args[2]);
                exit;
            }

            // Configuramos la fuente de los datos, que en este caso es siempre una exportación
            // en CSV de la tabla de especies (categorías) de 4Images
            $args[5] ??= 0;
            $inputSource = new Species4ImagesCSVSource($args[2], Species4ImagesCSVSource::DEFAULT_DELIMITER);

            // Configuramos el parser de nombres de especies de la fuente de datos,
            // en función del segundo parámetro
            $speciesParser = $this->getInputParser($args[0]);

            switch ($args[1]) {
                case 'GBIF':
                    $processor = new GBIFSpeciesProcessor($speciesParser, $inputSource, $args[2], $args[3], $args[4]);
                    break;
                case 'POWO':
                    $processor = new POWOSpeciesProcessor($speciesParser, $inputSource, $args[2] , $args[3], $args[4] );
                    break;
                case 'Observation':
                    $processor = new ObservationSpeciesProcessor( $speciesParser, $inputSource, $args[2], $args[3], $args[4]);
            }
        }

        $offset = $options->getOpt('skip') ?? 0;

        $processor->process($offset);

    }

    protected function importGBIFSpecies($options)
    {
        $args = $options->getArgs();
        $inputSpeciesParser = $this->getInputParser($args[0]);
        $processor = new GBIFSpeciesImportProcessor($inputSpeciesParser, $args[1], $args[2], $args[3]);
        $offset = $args[3] ?? 0;
        $processor->process($offset);
    }

    protected function getInputParser($argValue): ISpeciesParser
    {
        switch($argValue){
            case 'BV-Flora':
                $speciesParser = new FloraSpeciesParser();
                break;
            default:
                $speciesParser = new SpeciesStringParser(); // Este es el genérico
        }

        return $speciesParser;
    }

    /**
     * @param $options Options
     */
    protected function obsFiles($options)
    {
        $args = $options->getArgs();
        if (!file_exists($args[0]) || !is_file($args[0])) {
            $this->error('The observations source file does not exsits or is not a file' . $args[0]);
            exit;
        }

        $processor = new GorostiCSVObservationProcessor($args[0], $args[1]);
        $processor->process();
    }
}
$serviceContainer = Importer::getServiceContainer();

// execute it
$cli = $serviceContainer->get(Importer::class);
$cli->run();