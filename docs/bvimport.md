# Procedimiento para la exportación de datos de BV a Observation.org

Este documento describe los pasos necesarios para importar datos de imágenes de la base de datos de Biodiversidad Virtual (BV) a observaciones en Observation.

## Primera parte: Carga de especies desde Observation

Esta primera parte crea una copia local con los datos de Observation relativos al grupo de especies con el que vamos a trabajar.

0.- Definimos primero como parámetro para toda la operación el speciesgroup_id

El identificador de cada grupo se puede sacar de la siguiente tabla:

| speciesgroup_id | Grupo                      |
|-----------------|----------------------------|
| 1     		  | Aves                       |
| 2     		  | Mamíferos                  |
| 3     		  | Reptiles y Anfibios        |
| 4    			  | Mariposas                  |
| 5   			  | Libélulas (Odonata)        |
| 6   			  | Insectos (otras familias)  |
| 7   			  | Moluscos (Mollusca)        |
| 8   			  | Polillas                   |
| 9   			  | Peces                      |
| 10  			  | Plantas                    |
| 11  			  | Setas                      |
| 12  			  | Musgos y Líquenes          |
| 13  			  | Artrópodos (otras familias)|
| 14  			  | Ortópteros (Orthoptera)    |
| 15  			  | Hemípteros (Hemiptera)     |
| 16  			  | Escarabajos (Coleoptera)   |
| 17  			  | Himenópteros (Hymenoptera) |
| 18  			  | Dipteros (Diptera)         |
| 19  			  | Algas y unicelulares       |
| 20  			  | Otros invertebrados        |
| 30  			  | Molestias                  |

Los siguientes pasos están incluidos en el script BVDownloadGroup.php, de manera que para su ejecución basta con lanzar el siguiente comando desde la shell:

```bash
# php BVDownloadGroup.php <speciesgroup_id>
```
**Esto realizará todas las tareas desde la 1 hasta la 6**

1.- Descargar de old.observation.org la lista de especies del grupo en https://old.observation.org/export/nl_soorten_export3.php?g=[speciesgroup_id]&only_ln=0&lang=es, sustituyendo [speciesgroup_id] por el valor antes definido.
Esto nos descargará un fichero CSV con separador ; y delimitador de texto ""
2.- Abrir el fichero CSV descargado y cambiar la primera fila por esta:

"id";"name_common";"name_scientific";"protection_level";"freq";"speciesgroup_id";"SPECIESGROUP";"type";"type_name";"euring";"pons";"SEARCHKEY";"family";"AUTHOR";"checklist";"status";"refer_to";"plantlistid";"name_order"

Sobrescribe de esta manera el nombre de los campos para adecuarlos al de la base de datos

3.- Grabar el fichero modificado usando como nombre de fichero el formato: grupodeespecie_YYYYMMDD.csv, siendo YYYYMMDD la fecha de hoy

4.- Conectate a la base de datos PostgreSQL de trabajo usando los parámetros de conexión disponibles en un fichero de configuración en /config/obs_species_database.yml, con el siguiente contenido:

```yaml
obs_species_database: # This is the datasource ID, and should match the filename
  type: 'database' # Datasource type: 'database', 'csv' or 'null'
  dsn: 'pgsql:host=localhost;port=25432;dbname=waarneming' # For database type, the DSN with the connection string
  username: 'docker' # For database type, the username for the database
  password: 'docker' # For database type, the password for the database
  table_name: 'obs_species' # For database type, the table to search for the species
  key_fieldname: 'name_scientific' # The field containing the species name
  author_fieldname: 'author' # The field containing the species authorship
  id_fieldname: 'id' # The field containing the species unique identifier
```

5.- Borra de la tabla "obs_species" todas las especies anteriormente importadas de este grupo de especies:
	5.1.- Ejecutamos: delete from obs_species where obs_species.speciesgroup_id = %% (sustituyendo %% por el speciesgroup_id)
6.- Importamos a la base de datos, en la tabla obs_species, el contenido del fichero obtenido en el punto 3. Cada columna del CSV tiene su campo correspondiente en la tabla

## Segunda parte: Copiamos los datos desde BV

Se importan los datos originales de BV a la base de datos local, para poder trabajar con ellos con seguridad y sin problemas de rendimiento.
Es recomednable utilizar una herramienta potente de manejo de bases de datos como DBeaver.
El motor de base de datos con el que se ha trabajado para este proyecto es un servidor PostgreSQL montado en un contenedor docker en local. 
Se puede utilizar otro motor de base de datos, si bien las consultas que se incluyen en esta documentación están adaptadas a esta plataforma.

La base de datos original de BV está instalada en un VPS con acceso mediante usuario y contraseña. El servidor es un MySQL.

7.- Conectarse a la base de datos de BV
8.- Copiar la tabla correspondiente a las categorias de la galería ('invert_categories' por ejemplo, para la galería de invertebrados) en la base de datos PostgreSQL de trabajo
9.- Copiar la tabla correspondiente a las imágenes de la galería ('invert_images' por ejemplo, para la galería de invertebrados) en la base de datos PostgreSQL de trabajo
10.- Copiar la tabla correspondiente a los comentarios ('invert_comments' por ejemplo, para la galería de invertebrados) en la base de datos PostgreSQL de trabajo

## Tercera parte: Generamos las vistas para el premapeo

11.- Creamos una vista con el siguiente patrón de nombre taxa_categories_filter, sustituyendo "taxa" por el nombre del taxón más alto con el que estemos trabajando. Por ejemplo: diptera_categories_filter
La vista tiene que tener el siguiente código:

```SQL
CREATE OR REPLACE VIEW public.taxa_categories_filter
AS SELECT get_invert_categories_filter.cat_id,
    get_invert_categories_filter.cat_parent_id,
    get_invert_categories_filter.cat_name,
    get_invert_categories_filter.cat_o_images
   FROM get_invert_categories_filter(ARRAY[81, 36500, 644, 43377, 47575]) get_invert_categories_filter(cat_id, cat_parent_id, cat_name, cat_o_images);
```

NOTA: Los números del array representan los "cat_id" de las categorías padre de BV correspondientes al taxón a importar en las diferentes carpetas. Los números que aparecen en este ejemplo pertenecen a Hymenoptera. Para Diptera serían 342, 36496, 642, 21712, 47571

12.- Creamos una vista con el siguiente patrón de nombre taxa_categories_view, sustituyendo "taxa" por el nombre del taxón más alto con el que estemos trabajando. Por ejemplo: diptera_categories_filter
La vista tiene que tener el siguiente código:

```SQL
CREATE OR REPLACE VIEW public.taxa_categories_view
AS SELECT get_invert_categories_view.cat_id,
    get_invert_categories_view.cat_parent_id,
    get_invert_categories_view.cat_name,
    get_invert_categories_view.cat_o_images
   FROM get_invert_categories_view(ARRAY[81, 36500, 644, 43377, 47575]) get_invert_categories_view(cat_id, cat_parent_id, cat_name, cat_o_images);
```

## Cuarta parte: Creamos los ficheros de configuración del pipeline

Para el mapeo de especies desde BV a Observation vamos a utlizar un proyecto de código abierto desarrollado especificamente para esta tarea: https://github.com/somms/TaxaImporter
Para saber más de esta herramienta, por favor, consultar la documentación correspondiente.
En esta parte del proceso creamos los ficheros de configuración necesarios en TaxaImporter

13.- Crear el fichero con la configuración de acceso a los datos del taxón: /config/datasource/bv_taxa_database.yml (sutituyendo taxa por el nombre del taxón más alto, ej. 'bv_diptera_database.yml')

```yaml
bv_diptera_database:
  type: 'database'
  dsn: 'pgsql:host=localhost;port=25432;dbname=waarneming'
  username: 'docker'
  password: 'docker'
  table_name: 'taxa_categories_filter'
  key_fieldname: 'cat_name'
```

Nota: Sutituir 'taxa_categories_filter' por el nombre de la vista creada en el paso 11

14.- Crear los 3 ficheros de configuración del pipeline en la carpeta /config/pipelines/:

14.1.- Pipeline de entrada, que hace la búsqueda con GNVerifier, y la salida la busca Observation en cualquier caso. Ponemos como ejemplo el de Diptera:

BVDiptera-GNGBIF.yml
```yaml
default:
  name: 'Check Diptera from BV database with GBIF using GNVerifier'
  remote:
    processor: GNames\GNSpeciesProcessor
  input:
    type: datasource
    name: bv_diptera_database # Datasource filename .yml
    parser: Forum4Images\BV\FloraSpeciesParser
  output_ok:
    type: pipeline
    path: BVDiptera-GNGBIF_ObsDB
  output_errors:
    type: pipeline
    path: BVDiptera-NoGBIF_ObsDB 
```

Nota: Sustituir "Diptera" por el nobre del taxón más alto.

14.2.- Pipeline para cuando el nombre de taxón está en GBIF, y se busca en Observation. Si no se encuentra en Observation, lo envía al pipeline de GBIF_import, para intentar importar la especie. La salida va a un par de CSV:

BVDiptera-GNGBIF_ObsDB.yml
```yaml
default: # Pipeline definition file, including input, outputs and the processor configuration
  name: 'Check BV diptera with Observation.org using a local database, and import them if missing' # Pipeline description
  remote: # Remote source processor configuration
    processor: Observation\ObservationSpeciesProcessor # Processor class, including namespace path under /Provider
    options: # Optional. Processor options, that will depend on every Processor class.
      datasource: obs_species_database # In this processor, this is the datasource used by the processor for species matching
      author_search: false # In this processor, use true to restrict species search by author
  input: # Input source configuration. This will be ignored if this pipeline is uses as output of other pipeline
    type: null
    parser: Forum4Images\BV\FloraSpeciesParser # Species name parser for this source
  output_ok: # Output for the correctly processed data
    type: csv # Output type. It can be "csv" or "pipeline"
    path: ./data/output/BVDipteraObservation_ok.csv # The path to the output file for CSV, or the name of the pipeline config file
  output_errors: # Output for the errors
    type: pipeline # Output type. It can be "csv" or "pipeline"
    path: GBIF_import # The path to the output file for CSV, or the name of the pipeline config file
```

Nota: Sustituir "Diptera" por el nobre del taxón más alto.

14.3.- Pipeline para cuando el nombre del taxón no está en GBIF. Lo buscamos el Observation, de todas maneras, y se vuelca en dos ficheros de salida.

BVDiptera-NoGBIF_ObsDB.yml
```yaml
default: # Pipeline definition file, including input, outputs and the processor configuration
  name: 'Check BV diptera with Observation.org using a local database' # Pipeline description
  remote: # Remote source processor configuration
    processor: Observation\ObservationSpeciesProcessor # Processor class, including namespace path under /Provider
    options: # Optional. Processor options, that will depend on every Processor class.
      datasource: obs_species_database # In this processor, this is the datasource used by the processor for species matching
      author_search: false # In this processor, use true to restrict species search by author
  input: # Input source configuration. This will be ignored if this pipeline is uses as output of other pipeline
    type: null
    parser: Forum4Images\BV\FloraSpeciesParser # Species name parser for this source
  output_ok: # Output for the correctly processed data
    type: csv # Output type. It can be "csv" or "pipeline"
    path: ./data/output/BV_Diptera-GBIF_error-Observation_ok.csv # The path to the output file for CSV, or the name of the pipeline config file
  output_errors: # Output for the errors
    type: csv # Output type. It can be "csv" or "pipeline"
    path: ././data/output/BV_Diptera-GBIF_error-Observation_error.csv # The path to the output file for CSV, or the name of the pipeline config file
```

## Quinta parte: Relizamos "premapeo"

Este paso crea un primero mapeo, que permita al equipo de voluntarios hacer las correcciones necesarias y dar de alta lo que corresponda en Observation

15.- Ejecutamos el script Import.php para con el punto de entrada del pipeline. Ponemos de ejemplo el de Diptera:

```sh
./src/Importer.php clean-species -p BVDiptera-GNGBIF
```

Como resultado se obtienen en la carpeta /data/output 5 ficheros:

BV_Diptera-GBIF_error-Observation_error # Taxones que no están en GBIF ni en Observation
BV_Diptera-GBIF_error-Observation_ok 	# Taxones que no están en GBIF pero se han encontrado en Observation
BVDipteraObservation_ok					# Taxones que están en GBIF y se han encontrado en Observation
GBIF_error_import						# Taxones que están en GBIF, no estaban en Observation y no se han podido importar
GBIF_ok_import							# Taxones que están en GBIF, no estaban en Observation pero se han podido importar

Es decir, los ficheros que contienen los problemas a resolver por el equipo de voluntarios son:

BV_Diptera-GBIF_error-Observation_error
GBIF_error_import

16.- Revisar manualmente los ficheros con los errores, realizando las correcciones necesarias. Lo mejor es usar un Google Sheet para esto.

## Sexta parte: Realizamos un "mapeo"

Se realiza ahora un mapeo definitivo, que ya identifique los problemas residuales a corregir. Ya no se puede modificar nada en BV ninguna de las carpetas bajo el taxón.

17.- Actualizar la copia local de taxones,imágenes y comentarios, repitiendo los pasos del 0 al 10

18.- Realizar el "mapeo" definitivo, repitiendo el paso 15 y 16.

19.- Se hace un "merge" de los ficheros de salida del paso anterior, obteniendo como resultado el fichero: BVDipteraObservation_ok_merged.csv
Este fichero debe tener solo las columnas cat_id;cat_name;obs_id;obs_species siendo:

cat_id		# Identificador numérico del taxón en BV
cat_name	# Nombre del taxón en BV
obs_id		# Identificador numérico del taxón en Observation
obs_species	# Nombre del taxón en Observation

Si quedara algún taxón sin su correspondiente "obs_id", completar usando el resultado de la revisión manual.

## Séptima parte: Creación de tablas de mapeo

Se crea las vistas y tablas definitivas para realizar la importación de las imágenes, basado en el resultado previo

20.- Importar el fichero resultante del paso anterior a la base de datos local de trabajo con el nombre "taxa_matched_categories", sutituyendo "taxa" por el nombre del taxón de mayor nivel.

21.- Creación de la vista de mapeo de categorías a especies, denominada "complex_taxa_matched_view". Aquí de ejemplo el código de Diptera:
```SQL
-- public.complex_diptera_matched_view source

CREATE OR REPLACE VIEW public.complex_diptera_matched_view
AS SELECT complex_diptera.original_cat_id AS cat_id,
    complex_diptera.original_cat_name AS cat_name,
    omc.obs_id,
    omc.obs_species,
    complex_diptera.obs_activity,
    complex_diptera.obs_appearance
   FROM ( SELECT ic.cat_id AS original_cat_id,
            ic.cat_name AS original_cat_name,
            COALESCE(ocf.cat_id, pc.cat_id) AS cat_id,
            COALESCE(ocf.cat_name, pc.cat_name) AS cat_name,
                CASE
                    WHEN ic.cat_name::text = 'Emergencia'::text THEN 'moulting'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['CT Tela'::text, 'Tela'::text]) THEN 'in web'::text
                    ELSE 'present'::text
                END AS obs_activity,
                CASE
                    WHEN ic.cat_name::text = ANY (ARRAY['CT Exuvia'::text, 'Exuvia'::text]) THEN 'exuviae'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['CT Larva/ninfa'::text, 'Larva/Ninfa'::text, 'Lara/ninfa'::text, 'Larva/ninfa'::text, 'Larva'::text]) THEN 'caterpillar'::text
                    WHEN ic.cat_name::text = 'Pupa'::text THEN 'pupa'::text
                    WHEN ic.cat_name::text = 'Imago'::text THEN 'imago'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Emergencia'::text, 'Adulto (recién salido)'::text]) THEN 'fresh imago'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Puesta'::text, 'Huevo'::text]) THEN 'egg'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Muda/piel'::text, 'CT Muda/piel'::text]) THEN 'molt pollicle'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Nido'::text, 'Galería'::text, 'CT Galería'::text]) THEN 'mine'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Agalla'::text, 'CT Agalla'::text]) THEN 'gall'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Bolsa/cápsula'::text, 'CT Bolsa/cápsula'::text]) THEN 'pocket/case'::text
                    WHEN ic.cat_name::text = ANY (ARRAY['Crisálida'::text]) THEN 'pupa'::text
                    ELSE NULL::text
                END AS obs_appearance,
            ic.cat_o_images
           FROM diptera_categories_view ic
             LEFT JOIN diptera_categories_filter ocf ON ic.cat_id = ocf.cat_id
             LEFT JOIN invert_categories pc ON ic.cat_parent_id = pc.cat_id
          WHERE ic.cat_o_images > 0) complex_diptera
     LEFT JOIN diptera_matched_categories omc ON complex_diptera.cat_id = omc.cat_id;
```
21.- Creación de la vista de mapeo de imágenes, denominada "taxa_images_obs_view". Aquí el código de ejemplo de "Lepidoptera":

```SQL
CREATE OR REPLACE VIEW public.lepidoptera_images_obs_view
AS SELECT DISTINCT m.image_id,
    m.cat_id,
    m.user_id,
    c.cat_name AS cat_name_original,
    c.obs_species AS obs_species_name,
    m.image_name,
    m.image_description,
    m.image_keywords,
    m.image_date,
    m.image_active,
    m.image_media_file,
    m.image_thumb_file,
    m.image_download_url,
    m.image_allow_comments,
    m.image_comments,
    m.image_downloads,
    m.image_votes,
    m.image_rating,
    m.image_hits,
    m.image_altura,
    m.image_habitat,
    m.image_host,
    m.image_pais,
    m.image_provincia,
    m.image_localidad,
    m.image_sublocation,
    m.image_fecha,
    m.image_sex,
    m.image_stage,
    m.image_age,
    m.image_equipo,
    m.image_mgrs,
    m.image_utm,
    COALESCE(ip.image_gmap_longitude, m.image_gmap_longitude) AS image_gmap_longitude,
    COALESCE(ip.image_gmap_latitude, m.image_gmap_latitude) AS image_gmap_latitude,
    m.image_gmap_show,
    m.image_nombre_cientifico,
    m.image_expert_remark,
    m.image_expert_id,
    m.image_fecha_id,
    m.image_testing,
    (('https://www.biodiversidadvirtual.org/insectarium/data/media/'::text || m.cat_id) || '/'::text) || m.image_media_file::text AS image_url,
        CASE
            WHEN m.image_sex::text = 'Macho'::text THEN 'Male'::text
            WHEN m.image_sex::text = 'Hembra'::text THEN 'Female'::text
            ELSE 'unknown'::text
        END AS obs_sex,
    COALESCE(c.obs_appearance,
        CASE
            WHEN m.image_stage::text = ANY (ARRAY['Adulto'::text, 'Imago'::text]) THEN 'imago'::text
            WHEN m.image_stage::text = ANY (ARRAY['Inmaduro'::text, 'Larva'::text, 'Larva-Oruga'::text, 'Ninfa'::text]) THEN 'caterpillar'::text
            WHEN m.image_stage::text = 'Subimago'::text THEN 'fresh imago'::text
            WHEN m.image_stage::text = 'Crisálida'::text THEN 'pupa'::text
            WHEN m.image_stage::text = 'Bolsa/cápsula'::text THEN 'pocket/case'::text
            WHEN m.image_stage::text = ANY (ARRAY['Exuvia'::text, 'Muda'::text]) THEN 'exuviae'::text
            WHEN m.image_stage::text = ANY (ARRAY['Huevo'::text, 'Puesta'::text]) THEN 'egg'::text
            WHEN m.image_stage::text = ANY (ARRAY['Galería'::text]) THEN 'mine'::text
            WHEN m.image_stage::text = ANY (ARRAY['Agalla'::text]) THEN 'gull'::text
            ELSE 'unknown'::text
        END) AS obs_age,
    c.obs_id AS obs_species_id,
    COALESCE(c.obs_activity, 'present'::text) AS obs_activity,
    'unknown'::text AS obs_method,
    0 AS is_escape,
    to_char(to_timestamp(m.image_date::double precision), 'YYYY-MM-DD HH24:MI:SS'::text) AS obs_upload_date,
    b.obs_user_id,
    COALESCE(ip.obs_date, to_char(to_timestamp(m.image_fecha::double precision), 'YYYY-MM-DD HH24:MI:SS'::text)) AS obs_date,
        CASE
            WHEN m.image_nombre_cientifico::text > ''::text THEN 1
            ELSE 0
        END AS obs_validated,
        CASE
            WHEN m.image_expert_id::text = m.user_id::text THEN 917851::bigint
            ELSE b_rev.obs_user_id
        END AS obs_validator_user_id,
    to_char(to_timestamp(m.image_fecha_id::double precision), 'YYYY-MM-DD HH24:MI:SS'::text) AS obs_validation_date,
    concat_ws('
'::text, concat('Nombre de la imagen: ', COALESCE(m.image_name, ''::text::character varying)), concat('Identificador: ', COALESCE(m.image_id::text, ''::character varying::text)), concat('Descripción de la imagen: ', COALESCE(m.image_description, ''::text)), concat('Habitat: ', COALESCE(m.image_habitat, ''::text)), concat('Lugar: ', COALESCE(m.image_pais, ''::character varying), '-', COALESCE(m.image_provincia, ''::character varying), ' ', COALESCE(m.image_localidad, ''::character varying), ' ', COALESCE(m.image_sublocation, ''::character varying)), concat('Sexo: ', COALESCE(m.image_sex, ''::character varying)), concat('Estado: ', COALESCE(m.image_stage, ''::character varying)), concat('Edad: ', COALESCE(m.image_age, ''::character varying)), concat('Equipo: ', COALESCE(m.image_equipo, ''::character varying)), concat('MGRS: ', COALESCE(m.image_mgrs, ''::character varying)), concat('UTM: ', COALESCE(m.image_utm, ''::character varying)), concat('Nombre científico asignado original: ', COALESCE(m.image_nombre_cientifico, ''::character varying)),
        CASE
            WHEN m.image_expert_id = m.user_id THEN 'Validado originalmente por el autor de la observación'::text
            ELSE ''::text
        END,
        CASE
            WHEN m.image_testing > 0 THEN concat_ws('
'::text, concat('Testing: ', COALESCE(t.testing_name, ''::character varying)), concat('Detalle del testing: ', COALESCE(t.testing_description, ''::text)))
            ELSE ''::text
        END) AS obs_description
   FROM invert_images m
     LEFT JOIN "4images_testings" t ON m.image_testing = t.testing_id
     JOIN complex_lepidoptera_matched_view c ON m.cat_id = c.cat_id
     LEFT JOIN bv_users_obs_safe b ON m.user_id = b.user_id
     LEFT JOIN bv_users_obs_safe b_rev ON m.image_expert_id = b_rev.user_id
     LEFT JOIN invert_patch ip ON m.image_id = ip.image_id
  WHERE c.obs_id IS NOT NULL;
```

Nota: Hacer las modificaciones necesarias para los campos obs_sex y obs_age, ajustando los casos para el mapeo de los valores de image_sex y image_stage en la tabla de imágenes a importar con los valores disponibles en Observation para este grupo de especies

22.- Creación de la vista de comentarios "taxa_comments_obs_view", sutituyendo "taxa" por el nombre del taxón más alto e "invert_comments", por la tabla original con los comentarios

```SQL
CREATE OR REPLACE VIEW public.taxa_comments_obs_view
AS SELECT buo.obs_user_id,
    ac.comment_id,
    ac.image_id,
    ac.user_id,
    concat_ws('
'::text, concat('Asunto: ', COALESCE(ac.comment_headline, ''::character varying)), concat(' Mensaje:
', COALESCE(ac.comment_text, ''::text))) AS comment,
    to_char(to_timestamp(ac.comment_date::double precision), ' YYYY-MM-DD HH24:MI:SS'::text) AS comment_date
   FROM invert_comments ac
     JOIN bv_users_obs_safe buo ON ac.user_id = buo.user_id
     JOIN taxa_images_obs_view oiov ON ac.image_id = oiov.image_id;
```

## Octava parte: Exportación de datos y envío a Observation

Se vuelcan los datos anteriores a ficheros para su procesado en Observation

23.- Exportar los datos de las dos vistas creadas en los pasos 21 y 22 a sendas tablas, usando como nombre el mismo de las vistas pero eliminando el "view" del final
24.- Exportar el contenido de las dos tablas creadas en el paso 23 a sendos ficheros CSV, con comillas dobles como separador de texto ("), punto y coma como separador de celdas (;) y UTF8.
25.- Enviar los ficheros resultantes a Observation.org para su importación
26.- Eliminar la marca de agua de la galería correspondiente en BV (si no estuviera ya eliminada)