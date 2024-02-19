# TaxaImporter

   A tool for converting taxonomies from one source to another, and verifying it against taxonomical checklists. Currently, it supports Observation.org, Plants Of the World Online (POWO), GBIF.org and more than 100 other checklists available with GNVerifier.  

When faced with the challenge of importing biodiversity data from one external source to another, the first obstacle to overcome is always being able to locate and resolve differences in taxonomic criteria. This task can be really complex when you have a high number of entries, or the database does not have an optimal structure.

TaxaImporter is a tool that allows you to automate a large part of the process, giving you enough flexibility to adapt to a large number of situations. 

## Index

<!-- TOC -->
* [TaxaImporter](#taxaimporter)
  * [Index](#index)
  * [Usage](#usage)
* [Installation](#installation)
  * [Download or clone the repository](#download-or-clone-the-repository)
  * [PHP 8.2 CLI](#php-82-cli)
  * [Composer](#composer)
  * [Test run](#test-run)
* [How it works and configuration](#how-it-works-and-configuration)
  * [Pipelines](#pipelines)
  * [Pipeline Definition File .yml](#pipeline-definition-file-yml)
    * [Configuration parameters](#configuration-parameters)
  * [DataSource](#datasource)
  * [Datasource Definition File .yml](#datasource-definition-file-yml)
    * [Datasource Configuration parameters](#datasource-configuration-parameters)
      * [Database type parameters](#database-type-parameters)
      * [CSV type parameters](#csv-type-parameters)
  * [Processors](#processors)
  * [Processor types](#processor-types)
    * [Species Processor](#species-processor)
<!-- TOC -->

## Usage

   Importer.php <OPTIONS> <COMMAND> ... <skip>

OPTIONS:
   -h, --help                              Display this help screen and exit immediately.

   --no-colors                             Do not use any colors in output. Useful when piping output to other tools or files.

   --loglevel <level>                      Minimum level of messages to display. Default is info. Valid levels are: debug, info,
                                           notice, success, warning, error, critical, alert, emergency.
                                           
COMMANDS:
   This tool accepts a command as first parameter as outlined below:

   clean-species OPTIONS

     Verify species and gets a clean file and a error file


     -p <pipeline_name>, --pipeline         Pipeline filename, in the pipeline config folder. Use this option to load the set of
     <pipeline_name>                        options from the yml file. If this option is not provided, the rest of arguments are
                                            required

     -s <skip>, --skip <skip>               Number of rows to jump over

# Installation

## Download or clone the repository

Get a local copy of the content of this repository using Git clone or downloading the available releases in https://github.com/somms/TaxaImporter/tags

## PHP 8.2 CLI
Command line PHP8.2 is required to use this tool.

For PHP command line installation, please, refer to https://www.php.net/manual/install.php. You do not need a web server, just the command line PHP executable.

To check if you already have php installed in your system, just type `php -v`:

    #> php -v
    PHP 8.2.13 (cli) (built: Nov 24 2023 12:00:00) (NTS)
    Copyright (c) The PHP Group

## Composer

Composer is used for dependency management.

Please refer to https://getcomposer.org/download/ for installing Composer locally in your system.

Once installed, execute this to install all the dependencies:

    composer install

## Test run

If everything worked, you will be able to see the help page typing this from the root directory:

    php src/Importer.php

# How it works and configuration

## Pipelines

A "pipeline" refers to a series of data processing steps that are executed in a specific order to transform or analyze input data and produce desired output. Pipelines are often used for tasks such as data processing, ETL (Extract, Transform, Load), and workflow automation.

Let's break down the key components of the pipeline:

1. **Input Source**
    - The input source from were the data entries are going to be extracted and processed. Every input source will have a parser, that will transform the entries in a processable entry.
2. **Remote Source Processor**
   - The responsable for data entries processing: transformation, verification, etc.
3. **Output OK**
    - The output for correctly processed data entries: a csv file or another pipeline
4. **Output errors**
    - The output for processed data entries with errors: a csv file or another pipeline

In summary, the pipeline is designed to verify and process data. It includes a remote source processor, an input source configuration, and configurations for handling both correctly processed data and errors. The pipeline represents a series of steps and configurations that collectively perform a specific data processing task. The actual processing logic is implemented within the specified processor classes and parsers.

## Pipeline Definition File .yml

Let's start with an example file that defines a pipeline, including input, outputs, and the processor configuration.

```yaml
default: # Pipeline definition file, including input, outputs and the processor configuration
  name: 'Fish and Algae against Observation with a local DB' # Pipeline description
  remote: # Remote source processor configuration
    processor: Observation\ObservationSpeciesProcessor # Processor class, including namespace path under /Provider
    options: # Optional. Processor options, that will depend on every Processor class.
      datasource: obs_species_database # In this processor, this is the datasource used by the
      author_search: false # In this processor, use yes to restrict species search by author
  input: # Input source configuration. This will be ignored if this pipeline is uses as output of other pipeline
    type: datasource # Input source type. It can be "csv", "datasource"
    name: bv_fish_csv # Datasource filename .yml
    parser: Forum4Images\BV\FloraSpeciesParser # Species name parser for this source
  output_ok: # Output for the correctly processed data
    type: csv # Output type. It can be "csv" or "pipeline"
    path: ./data/output/BV_PiscesAlgae-Observation_ok.csv # The path to the output file for CSV, or the name of the pipeline config file
  output_errors: # Output for the errors
    type: pipeline # Output type. It can be "csv" or "pipeline"
    path: BV_PiscesAlgae_GBIF_import # The path to the output file for CSV, or the name of the pipeline config file
```

This file defines a pipeline that:
- Looks for entries in Observation.org, using a local database with a copy of the Observation.org taxonomy
- Gets entries from a CSV file (defined in the `bv_fish_csv` datasource file) coming from a Forum4Images based website (BV)
- Sends found entries to a CSV file in the data/output/ folder
- Sends error entries to another pipeline, BV_PiscesAlgae_GBIF_import, that will check the entries against the GBIF taxonomy backbone.

You have to place this file in /config/pipelines/ folder to use it.

Let's see every field in detail.

### Configuration parameters

**`name`:** Pipeline description

**`remote`:** Remote source Processor configuration
- **`processor`:**  Processor class name, including path under /Provider/ namespace
- **`options`:** Processor options (see processor documentation for description)
    - `datasource`:  Datasource object used by the processor for species matching
    - `author_search`: Restrict species search by authorship

**`input`:** Input source configuration for data entries. This will be ignored if this pipeline is used as output of other pipeline
- **`type`:** Input source type. It can be "csv" or "datasource"
- **`name`:** Datasource filename .yml or CSV file path
- **`parser`:** Species name parser class name for this source, including path under /Provider/ namespace

**`output_ok`:** Output configuration for correctly processed data entries
- **`type`:** Input source type. It can be "csv" or "pipeline"
- **`path`:** Pipeline filename .yml or CSV file path

**`output_errors`:** Output configuration for the errors
- **`type`:** Input source type. It can be "csv" or "pipeline"
- **`path`:** Pipeline filename .yml or CSV file path

## DataSource

A "DataSource" refers to a component or module responsible for retrieving and providing access to a source of data. This source of data can be diverse, such as a database, a file system, an external API, or any other data store.
However, the software only supports CSV files and databases as datasource at this time.

## Datasource Definition File .yml

This example file defines a datasource object, including type, and field names.

```yaml
obs_species_database: # This is the datasource ID, and should match the filename
  type: 'database' # Datasource type: 'database', 'csv' or 'null'
  dsn: 'pgsql:host=localhost;port=25432;dbname=waarneming' # For database type, the PDO DSN with the connection string
  username: 'docker' # For database type, the username for the database
  password: 'docker' # For database type, the password for the database
  table_name: 'obs_species' # For database type, the table to search for the entries
  key_fieldname: 'name_scientific' # The field containing the species name
  author_fieldname: 'author' # The field containing the species authorship
  id_fieldname: 'id' # The field containing the species unique identifier
```
This example file defines a datasource that:

- Is stored in a database
- Can be accessed using the specified DSN string, username, and password.
- Can be found in the specified database table
- Has "name_scientific" as key fieldname (where the species name is stored)
- Has "author" as authorship fieldname
- Has "id" as species id field


This example file is used as datasource to check if a species entry is in Observation.org, but it can also be used as
data entry input. 

Place this kind of files in /config/datasources/ folder.

There is a special predefined *null datasource* with the file null.yml. You can use it for pipelines that will not read
data from external sources, but from another pipelines.

### Datasource Configuration parameters

**`type`:** Datasource type: 'database', 'csv' or 'null' (only valid for chained pipelines)

**`key_fieldname`:** The field containing the entry name (scientific name + authorship, for example)

**`author_fieldname`:** The field containing the species authorship. Only mandatory for the Observation Species Processor, ignored by other processors.

**`id_fieldname`:** The field containing the species unique identifier. Only mandatory for the Observation Species Processor, ignored by other processors.


#### Database type parameters

**`dsn`:** For database type, the PDO DSN with the connection string

**`username`:** For database type, the username for the database connection

**`password`:** For database type, the password for the database connection

**`table_name`:** For database type, the table to search for the entries


#### CSV type parameters

**`delimiter`:** For csv type, the field delimiter character

**`path`:** For csv type, the path to the file relative to the execution folder

## Processors
A Processor is designed to transform input data from one format to another, apply specific operations, or manipulate the data in some way.
It is the core of a data pipeline, executing operations on the data entries received from the input source.
Each processor is responsible for managing errors, and sending the results to the corresponding output in the pipeline.

## Processor types

### Species Processor

A Species Processor is able to perform operations over a species entry. This is the main type of processor currently used in the software.

A processor will perform the following tasks:

- **Preprocess the entry**: Parses the input entry, using the configured Species Parser, getting a entry name and a Species object as result
- **Process the item**: Execute an operation, like checking a remote source (find a match, import the species, etc.). 

There are several processors, depending on the operations to perform with the especies

- **GBIF Species Processor**: 
  - Finds a species match in GBIF using its API
  - **Path**: /GBIF/GBIFSpeciesProcessor
  - **Options**:
    - `datasetKey`: The GBIF species checklist dataset key to restrict the search. If omitted, the default GBIF backbone will be used.
- **GNVerifier Species Processor**:
    - Verifies scientific names using GNVerifier (https://github.con/gnames/gnverifier) from GNames, using fuzzy algorithms to match species names and authors. Very helpful to solve typographic errors in source data. It can be used with a variety of data sources.
    - **Path**: /GNames/GNSpeciesProcessor
    - **Options**;
        - `source`: The source id of the checklist to use as reference. By default, it will use GBIF. See the id list here: https://verifier.globalnames.org/data_sources
- **POWO Species Processor**:
  - Finds a species match in POWO using its API
  - **Path**: /POWO/POWOSpeciesProcessor
- **Observation Species Processor**:
  - Finds a species match in Observation using a local database or their website. Authorship verification is only available with the database option
  - **Path**: /Observation/ObservationSpeciesProcessor
  - **Options**:
    - `datasource`: The datasource config name to use for a database connection. If omitted, it will use the website to do the check. This datasource should be a database connection. CSV is not supported. The database should contain a table with the species download available in the "This Site > Downloads" menu of old.observation.org
    - `author_search`: If the Authorship is included in the species search to avoid problems with homonyms (two species with the same name). It will work only if the datasource parameter is included, because the website does not allow search by author-
- **Observation GBIF Species Import Processor**:
  - Calls to the GBIF import species form in Observation.org, that will try to add the Species to Observation.org
  - **Path**: /Observation/GBIFSpeciesImportProcessor
  - **Options**:
    - `cookie_jar`: A JSON encoded array containing all the session cookies for the authentication. You can get it from your browser cookie storage once authenticated in the website. I.e.:
    ```
    [{"domain":"observation.org","hostOnly":true,"httpOnly":false,"name":"PHPSESSID","path":"/","sameSite":"unspecified","secure":false,"session":true,"storeId":"0","value":"xxxxxxxxxxxx"},{"domain":".observation.org","hostOnly":false,"httpOnly":false,"name":"__utmc","path":"/","sameSite":"unspecified","secure":false,"session":true,"storeId":"0","value":"xxxx"},{"domain":"observation.org","expirationDate":1906977201,"hostOnly":true,"httpOnly":false,"name":"cookielaw_accepted","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"1"},{"domain":".observation.org","expirationDate":1699607885.149821,"hostOnly":false,"httpOnly":false,"name":"_ga_2EFJWZ44T4","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"GSxxxxxxxx"},{"domain":".observation.org","expirationDate":1706879325,"hostOnly":false,"httpOnly":false,"name":"leaflet_basemap_details","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"Google sat"},{"domain":"observation.org","expirationDate":1729697290.154656,"hostOnly":true,"httpOnly":false,"name":"csrftoken","path":"/","sameSite":"no_restriction","secure":true,"session":false,"storeId":"0","value":"xxxxxxxxxxxx"},{"domain":"observation.org","expirationDate":1729780709.443483,"hostOnly":true,"httpOnly":true,"name":"sessionid","path":"/","sameSite":"lax","secure":false,"session":false,"storeId":"0","value":"xxxxxxxxxxxxx"},{"domain":".observation.org","expirationDate":1729501724,"hostOnly":false,"httpOnly":false,"name":"leaflet_basemap","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"OSM human"},{"domain":".observation.org","expirationDate":1700810559.493133,"hostOnly":false,"httpOnly":true,"name":"lang","path":"/","sameSite":"lax","secure":true,"session":false,"storeId":"0","value":"es"},{"domain":".observation.org","expirationDate":1700747542.998559,"hostOnly":false,"httpOnly":true,"name":"token","path":"/","sameSite":"lax","secure":true,"session":false,"storeId":"0","value":"xxxx%x"},{"domain":".observation.org","expirationDate":1698334091,"hostOnly":false,"httpOnly":false,"name":"_gid","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"xxxxxxxxx"},{"domain":"observation.org","expirationDate":1729607658.204463,"hostOnly":true,"httpOnly":false,"name":"django_language","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"es"},{"domain":".observation.org","expirationDate":1732717007.451761,"hostOnly":false,"httpOnly":false,"name":"_ga_E82Y78YY1E","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"xxxxxxxxxx"},{"domain":".observation.org","expirationDate":1732717007.456646,"hostOnly":false,"httpOnly":false,"name":"_ga_M6M73VLLPN","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"xxxxxxxxx"},{"domain":".observation.org","expirationDate":1732807691.429791,"hostOnly":false,"httpOnly":false,"name":"_ga","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"xxxxxxxxxx"},{"domain":".observation.org","expirationDate":1698247751,"hostOnly":false,"httpOnly":false,"name":"_gat","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId":"0","value":"1"}]
    ```