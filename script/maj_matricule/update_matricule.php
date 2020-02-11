<?php

$a = microtime(true);
define('INC_FROM_CRON_SCRIPT', true);
require_once __DIR__.'/../../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/asset/class/asset.class.php');

set_time_limit(0);
ini_set('memory_limit', '256M');

$PDOdb = new TPDOdb;
$TData = array();

$f = fopen(__DIR__.'/extraction_num_serie_copypark.csv', 'r');
$i = 0;
while($TLine = fgetcsv($f, 2048, ';', '"')) {
    $i++;
    if($i > 1) {
        getUsefulData($TData, $TLine);
    }
}
fclose($f);

$upd = 0;
$TError = array();

foreach($TData as $ref => $TMatricule) {
    $upd += updateMatricule($PDOdb, $TError, $ref, $TMatricule);
}
$b = microtime(true);

echo $upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier';
echo '<br/>Execution time '.($b-$a);
echo '<br/>Dossiers introuvables : ';
echo '<br/>'.implode('<br/>', $TError);

function getUsefulData(&$TData, $TLine) {
    $TIndex = array(
        'matricule' => 0,
        'reference' => 1
    );

    // Une référence dossier peut être présente plusieurs fois dans le CSV car peut-être plusieurs matricules à associer
    if(empty($TData[$TLine[$TIndex['reference']]])) $TData[$TLine[$TIndex['reference']]] = array($TLine[$TIndex['matricule']]);
    else $TData[$TLine[$TIndex['reference']]][] = $TLine[$TIndex['matricule']];
}

function updateMatricule(TPDOdb &$PDOdb, &$TError, $reference, $TMatricule) {
    $d = new TFin_dossier;
    $res = $d->loadReference($PDOdb, $reference);
    if($res === false) {
        $TError[] = $reference;
        return 0;
    }

    $d->load_affaire($PDOdb);
    $a = &$d->TLien[0]->affaire;
    $a->loadEquipement($PDOdb);

    // On enlève tous les asset existant sur l'affaire
    foreach($a->TAsset as $k => $assetLink) $assetLink->delete($PDOdb);
    $a->TAsset = array();

    // On remet les assets qui sont dans le fichier CSV
    foreach($TMatricule as $serialNumber) {
        $asset = new TAsset;
        $res = $asset->loadReference($PDOdb, $serialNumber);
        if($res === false) {
            // Aucun asset avec ce numéro de série : on en crée un !
            $asset->fk_soc = $a->fk_soc;
            $asset->serial_number = $serialNumber;
            $asset->save($PDOdb);
        }
        $a->addEquipement($PDOdb, $asset->rowid);
    }

    return 1;
}
