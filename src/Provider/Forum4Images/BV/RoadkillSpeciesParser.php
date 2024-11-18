<?php

namespace Somms\BV2Observation\Provider\Forum4Images\BV;

use Somms\BV2Observation\Provider\Forum4Images\BV\FloraSpeciesParser;

class RoadkillSpeciesParser extends FloraSpeciesParser
{
    public function preprocessInput($input){
        if(is_array($input)){
            $input = $input['cat_name']; // Esto tendría que estar definido por el InputDataSource
        }
        $parts = explode(" - ",$input);
        if (count($parts)>1){
            $input = $parts[1];
        }
        return parent::preprocessInput($input);
    }

}