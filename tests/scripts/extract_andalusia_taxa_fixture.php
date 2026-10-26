<?php

/**
 * One-off helper: rebuild tests/fixtures/wfo_andalusia_taxa.txt from the agent transcript
 * that contains the clipboard taxon list (Abutilon … Zoysia).
 */

$transcript = getenv('WFO_TAXA_TRANSCRIPT');
if ($transcript === false || $transcript === '') {
    fwrite(STDERR, "Set WFO_TAXA_TRANSCRIPT to the .jsonl transcript path.\n");
    exit(1);
}

$needle = 'Abutilon theophrasti';
$found = null;
foreach (file($transcript, FILE_IGNORE_NEW_LINES) as $line) {
    $obj = json_decode($line, true);
    if (!is_array($obj) || ($obj['role'] ?? '') !== 'user') {
        continue;
    }
    $text = $obj['message']['content'][0]['text'] ?? '';
    $pos = strpos($text, $needle);
    if ($pos !== false) {
        $found = substr($text, $pos);
        break;
    }
}

if ($found === null) {
    fwrite(STDERR, "Taxon block not found in transcript.\n");
    exit(1);
}

$fixtureDir = dirname(__DIR__) . '/fixtures';
if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0775, true) && !is_dir($fixtureDir)) {
    fwrite(STDERR, "Cannot create {$fixtureDir}\n");
    exit(1);
}
$out = $fixtureDir . '/wfo_andalusia_taxa.txt';
$lines = array_filter(array_map('trim', explode("\n", $found)));
$fp = fopen($out, 'w');
fwrite($fp, "# Andalusia flora taxa (clipboard list for WFO validation)\n");
foreach ($lines as $line) {
    fwrite($fp, $line . "\n");
}
fclose($fp);

echo 'Wrote ' . count($lines) . " lines to {$out}\n";
