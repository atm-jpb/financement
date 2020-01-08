<?php

$a = microtime(true);
define('INC_FROM_CRON_SCRIPT', true);
require_once __DIR__.'/../../config.php';

set_time_limit(0);
ini_set('memory_limit', '256M');

$forceRollback = array_key_exists('forceRollback', $_GET) || (isset($argv[1]) && $argv[1] == 'forceRollback');

$TFileName = array(
    'siren_siret_aura.csv',
    'siren_siret_copem.csv',
    'siren_siret_ebm.csv',
    'siren_siret_ouest.csv',
    'siren_siret_sud.csv',
);
$TData = array();

foreach($TFileName as $filename) {
    $f = fopen(__DIR__.'/'.$filename, 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData[] = getUsefulData($TLine);
        }
    }
    fclose($f);
}

$upd = 0;
foreach($TData as $datadoss) {
    $upd += updateSiret($datadoss, $forceRollback);
}
$b = microtime(true);

echo $upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier';
echo '<br/>\nExecution time '.($b-$a);

function getUsefulData($TLine) {
    $TIndex = array(
        'siren' => 0,
        'siret' => 1
    );

    return array(
        'siren' => $TLine[$TIndex['siren']],
        'siret' => $TLine[$TIndex['siret']]
    );
}

function updateSiret($TData, $forceRollback = false) {
    global $db;

    $db->begin();

    $sql = 'UPDATE '.MAIN_DB_PREFIX.'societe';
    $sql.= " SET siret = '".$db->escape($TData['siret'])."'";
    $sql.= " WHERE siren = '".$db->escape($TData['siren'])."'";;
    $sql.= ' AND char_length(siret) <> 14'; // On ne met pas à jour les siret qui ont déjà 14 caractères

    $resql = $db->query($sql);
    if(! $resql || $forceRollback) {
        $db->rollback();
        return 0;
    }

    $db->commit();
    return 1;
}
