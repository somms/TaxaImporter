<?php
// Verificar que se haya pasado el speciesgroup_id como argumento
if ($argc != 2) {
    die("Uso: php BVDownloadGroup.php <speciesgroup_id>\n");
}

// Parámetro de la operación
$speciesgroup_id = $argv[1];

// Paso 1: Descargar el archivo CSV
echo "Descargando la base de datos desde Observation.org \n";
$url = "https://admin.observation.org/export/nl_soorten_export3.php?g=$speciesgroup_id&only_ln=0&lang=es";
$csvContent = file_get_contents($url);

if ($csvContent === false) {
    die("Error descargando el archivo CSV");
}

// Guardar temporalmente el archivo CSV descargado
$tempCsvFile = tempnam(sys_get_temp_dir(), 'species_');
file_put_contents($tempCsvFile, $csvContent);

// Paso 2: Abrir el archivo CSV y modificar la primera fila
echo "Modificando la primera fila para acomodarla a la base de datos\n";
$csvFile = fopen($tempCsvFile, 'r');
if ($csvFile === false) {
    die("Error abriendo el archivo CSV");
}

$date = date('Ymd');
$newCsvFileName = "grupodeespecie_$date.csv";
$newCsvFilePath = __DIR__ . '/' . $newCsvFileName;

$newCsvFile = fopen($newCsvFilePath, 'w');
if ($newCsvFile === false) {
    die("Error creando el archivo CSV modificado");
}

// Leer y modificar la primera fila
$header = fgetcsv($csvFile, 0, ';', '"');
$newHeader = [
    "id", "name_common", "name_scientific", "protection_level", "freq", "speciesgroup_id", "SPECIESGROUP", "type",
    "type_name", "euring", "pons", "SEARCHKEY", "family", "AUTHOR", "checklist", "status", "refer_to", "plantlistid",
    "name_order"
];
fputcsv($newCsvFile, $newHeader, ';', '"');

// Leer y escribir el resto del archivo CSV
while (($row = fgetcsv($csvFile, 0, ';', '"')) !== false) {
    fputcsv($newCsvFile, $row, ';', '"');
}

fclose($csvFile);
fclose($newCsvFile);

// Paso 4: Leer configuración de la base de datos desde el archivo YAML
echo "Leyendo configuración de base de datos\n";
$configFile = __DIR__ . '/../config/datasources/obs_species_database.yml';
$config = yaml_parse_file($configFile);

if ($config === false) {
    die("Error leyendo el archivo de configuración");
}

$dbConfig = $config['obs_species_database'];

// Conectar a la base de datos PostgreSQL
echo "Conectando a la base de datos\n";
$dsn = $dbConfig['dsn'];
$username = $dbConfig['username'];
$password = $dbConfig['password'];
$tableName = $dbConfig['table_name'];

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error conectando a la base de datos: " . $e->getMessage());
}

// Paso 5: Borrar especies anteriormente importadas de este grupo
echo "Borrando especies del grupo de especies descargado de la tabla obs_species\n";
try {
    $deleteQuery = "DELETE FROM $tableName WHERE speciesgroup_id = :speciesgroup_id";
    $stmt = $pdo->prepare($deleteQuery);
    $stmt->bindParam(':speciesgroup_id', $speciesgroup_id, PDO::PARAM_INT);
    $stmt->execute();
} catch (PDOException $e) {
    die("Error borrando especies: " . $e->getMessage());
}

// Paso 6: Importar datos del CSV modificado a la base de datos
echo "Insertando datos ...\n";
try {
    $pdo->beginTransaction();

    $handle = fopen($newCsvFilePath, 'r');
    if ($handle === false) {
        throw new Exception("Error abriendo el archivo CSV modificado para lectura");
    }

    // Leer la primera línea del archivo para omitir el encabezado
    $header = fgetcsv($handle, 0, ';', '"');

    // Preparar la consulta de inserción
    $insertQuery = "
        INSERT INTO $tableName (
            id, name_common, name_scientific, protection_level, freq, speciesgroup_id, SPECIESGROUP, type, type_name,
            euring, pons, SEARCHKEY, family, AUTHOR, checklist, status, refer_to, plantlistid, name_order
        ) VALUES (
            :id, :name_common, :name_scientific, :protection_level, :freq, :speciesgroup_id, :SPECIESGROUP, :type, :type_name,
            :euring, :pons, :SEARCHKEY, :family, :AUTHOR, :checklist, :status, :refer_to, :plantlistid, :name_order
        )
    ";
    $stmt = $pdo->prepare($insertQuery);

    // Leer el archivo CSV línea por línea y ejecutar la consulta de inserción
    while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
        $stmt->execute([
            ':id' => $row[0],
            ':name_common' => $row[1],
            ':name_scientific' => $row[2],
            ':protection_level' => $row[3],
            ':freq' => $row[4],
            ':speciesgroup_id' => $row[5],
            ':SPECIESGROUP' => $row[6],
            ':type' => $row[7],
            ':type_name' => $row[8],
            ':euring' => $row[9] == '' ? 0 : $row[9],
            ':pons' => $row[10] == '' ? 0 : $row[10],
            ':SEARCHKEY' => $row[11],
            ':family' => $row[12],
            ':AUTHOR' => $row[13],
            ':checklist' => $row[14],
            ':status' => $row[15],
            ':refer_to' => $row[16] == '' ? 0 : $row[16],
            ':plantlistid' => $row[17],
            ':name_order' => $row[18]
        ]);
    }

    fclose($handle);
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error importando datos: " . $e->getMessage() . "\n Datos: " . implode(";",$row) );
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

echo "Importación completada con éxito.\n";

?>
