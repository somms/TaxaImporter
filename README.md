# TaxaImporter

   A tool for converting taxonomies from one source to another, and verifying it against taxonomical checklists accepted by Observation.org

USAGE:
   Importer.php <OPTIONS> <COMMAND> ... <skip>

OPTIONS:
   -h, --help                              Display this help screen and exit immediately.

   --no-colors                             Do not use any colors in output. Useful when piping output to other tools or files.

   --loglevel <level>                      Minimum level of messages to display. Default is info. Valid levels are: debug, info,
                                           notice, success, warning, error, critical, alert, emergency.
                                           
COMMANDS:
   This tool accepts a command as first parameter as outlined below:

   clean-species <OPTIONS> [<origin>] [<target>] [<species>] [<output>] [<output-error>]

     Verify species and gets a clean file and a error file


     -p <pipeline_name>, --pipeline         Pipeline filename, in the pipeline config folder. Use this option to load the set of
     <pipeline_name>                        options from the yml file. If this option is not provided, the rest of arguments are
                                            required

     -s <skip>, --skip <skip>               Number of rows to jump over


     <origin>                               Source platform from species: BV, BV-Flora, Gorosti
     <target>                               Platform to verify species with: POWO, GBIF or Observation
     <species>                              File with species names
     <output>                               Output file with species names
     <output-error>                         Output file for error records

   gbif-import <origin> <species> <output> <output-error>

     Import species to Observation from GBIF using its species name

     <origin>                               Source platform from species: BV, BV-Flora, Gorosti
     <species>                              File with species names
     <output>                               Output file with imported species
     <output-error>                         Output file for error records
