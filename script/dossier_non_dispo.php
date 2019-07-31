<?php
$a = microtime(true);
set_time_limit(0);

require '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/lib/financement.lib.php');

$limit = GETPOST('limit', 'int');

$PDOdb = new TPDOdb;

$sql = 'SELECT d.rowid,';
$sql.= " (CASE d.nature_financement WHEN 'INTERNE' THEN dfcli.reference WHEN 'EXTERNE' THEN dflea.reference END) as ref_contrat,";
$sql.= ' d.nature_financement, e.label as entity, lea.nom as leaser';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier d';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type = 'CLIENT')";
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'entity e ON (d.entity = e.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe lea ON (dflea.fk_soc = lea.rowid)';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$THead = array(
    'ref_contrat',
    'partenaire',
    'leaser',
    'numero_regle'
);

if($conf->entity > 1) $path = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/export/dossier_non_dispo';
else $path = DOL_DATA_ROOT.'/financement/export/dossier_non_dispo';

if(! file_exists($path)) dol_mkdir($path);

$filename = 'extract_dossier_non_dispo_'.date('Ymd-His').'.csv';
$f = fopen($path.'/'.$filename, 'w');
$res = fputcsv($f, $THead, ';');

$TRes = array();
while($obj = $db->fetch_object($resql)) {
    $d = new TFin_dossier;
    $d->load($PDOdb, $obj->rowid);

    $oldEntity = $conf->entity;
    switchEntity($d->entity);

    $ruleError = $d->get_display_solde();
    $TRes[] = $ruleError;
    switchEntity($oldEntity);
    if($ruleError === 1) continue;  // Les soldes sont dispo

    $TData = array(
        $obj->ref_contrat,
        $obj->entity,
        $obj->leaser,
        abs($ruleError)
    );

    fputcsv($f, $TData, ';');
    unset($d);
}

fclose($f);
$db->free($resql);
$b = microtime(true);
print 'Execution time: '.($b-$a).' sec';
// Pour download le fichier
print '<script language="javascript">';
print 'document.location.href = "'.dol_buildpath('/document.php?modulepart=financement&entity='.$conf->entity.'&file=export/dossier_non_dispo/'.$filename, 2).'";';
print '</script>';