<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Somms\BV2Observation\Provider\WFO\WfoMatchClient;

$client = new WfoMatchClient();
$result = $client->match('Quercus ilex L.', ['accept_single_candidate' => true]);
$out = dirname(__DIR__, 2) . '/data/wfo_smoke.json';
file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT));
echo $result ? "match ok\n" : "no match\n";
