<?php

namespace Somms\BV2Observation\Provider\POWO;

use Somms\BV2Observation\Data\RemoteSpecies;
use Somms\BV2Observation\Data\Species;
use Somms\PHPKew\POWO\Enums\Name;
use Somms\PHPKew\POWO\POWOApi;

class POWOSpecies extends RemoteSpecies
{
    private static $nextAPICallTime = 0;

    protected function queryRemoteSource(string $speciesName, string $author = null, $options = []): mixed{

        $scientificName = str_replace([' indet.', ' spec.'], '', $speciesName);
        $powoQuery = [
            Name::FULL_NAME => $scientificName,
        ];
        if($author){
            $powoQuery[Name::AUTHOR] = $author;
        }

        $powoApi = new POWOApi();
        while(self::$nextAPICallTime > time()){
            sleep(1);
            echo '.';
        }
        $result = $powoApi->Search($powoQuery);
        $synonymData = null;
        if($result && $result->size() > 0) {
            // Verificamos si la especie está aceptada
            while ($result->current() && !$result->current()["accepted"]) {
                // Verificamos si tiene sinónimo y su validez
                if ($result->current()['synonymOf']['accepted']) {
                    $synonymData = $result->current()['synonymOf'];
                }
                $result->next();
            }

            // Ahora tenemos o un resultado aceptado, o los datos en synonymData
            if ($result->current()  == null && $synonymData != null) {
                if($synonymData != null){
                    // Nos traemos los datos del sinónimo
                    return $this->queryRemoteSource(
                        $synonymData[Name::FULL_NAME],
                        $synonymData[Name::AUTHOR]
                    );
                }
                elseif($author!= null){
                    // Repetimos la búsqueda pero sin autor, a ver si es que el autor estaba mal
                    return $this->queryRemoteSource(
                        $scientificName,
                        null
                    );
                }
            }

            return $result->current();
        }elseif($author!= null){
            // Repetimos la búsqueda pero sin autor, a ver si es que el autor estaba mal
                    return $this->queryRemoteSource(
                        $scientificName,
                        null
                    );
        }
        return false;
    }

    protected function validMatch($originalSpeciesName, $foundSpeciesName)
    {
        $result = false;

        if($originalSpeciesName == $foundSpeciesName){
            $result = $foundSpeciesName;
        }
        else{
            $stripedOriginals = str_replace([' indet.', ' spec.'], '', [$originalSpeciesName, $foundSpeciesName]);
            if($stripedOriginals[0] == $stripedOriginals[1]){
                $result = $foundSpeciesName;
            }
        }

        return $result;

    }

    private static function calculateWaitTime() {
        // Lógica para calcular el tiempo de espera hasta la próxima llamada
        // Puedes ajustar esta lógica según tus necesidades
        return 5; // Ejemplo: esperar 5 segundos entre llamadas
    }
}