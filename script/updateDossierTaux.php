<?php

ini_set('max_execution_time', 0);
require_once '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$PDOdb = new TPDOdb;
$limit = GETPOST('limit', 'int');
$debug = GETPOST('debug', 'int');
$commit = GETPOST('commit', 'int');
$fk_dossier = GETPOST('fk_dossier', 'int');

$sql = 'SELECT fk_fin_dossier';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement';
$sql.= " WHERE type = 'LEASER'";
$sql.= " AND (date_solde is null or date_solde < '1970-01-01')";
$sql.= ' AND montant_solde = 0';
$sql.= ' AND date_fin >= NOW()';
if(! empty($fk_dossier)) $sql.= ' AND fk_fin_dossier = '.$db->escape($fk_dossier);

if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$nbLine = $db->num_rows($resql);
print '<span>Nb Lines : '.$nbLine.'</span>';

while($obj = $db->fetch_object($resql)) {
    if(! empty($debug)) {
        print '<pre>';
        var_dump($obj->fk_fin_dossier);
    }

    $d = new TFin_dossier;
    $d->load($PDOdb, $obj->fk_fin_dossier, false);

    $TFin = array('financementLeaser');
    if($d->nature_financement == 'INTERNE') $TFin[] = 'financement';

    foreach($TFin as $finKey) {
        /** @var TFin_financement $f */
        $f = &$d->$finKey;

        $f->calculTaux();
        if(! empty($debug)) var_dump($f->type, $f->taux);
    }
    if(! empty($commit)) $d->save($PDOdb);
}
$db->free($resql);
if(!empty($debug)) print '</pre>';

?>
<br/>
<span>OK !</span>
