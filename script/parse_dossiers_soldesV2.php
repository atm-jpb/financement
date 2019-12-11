<?php
set_time_limit(0);
ini_set('memory_limit', '256M');

/*
 * Ce script doit corriger tous les dossiers rachetés pour renseigner correctement le type de solde
 */

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

$PDOdb = new TPDOdb;

$limit = GETPOST('limit', 'int');
//$force_rollback = GETPOST('force_rollback', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

$sql = 'SELECT s.rowid as fk_simu, dr.fk_dossier, dr.rowid, s.fk_leaser';
$sql.= ' FROM '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (dr.fk_simulation=s.rowid)';
$sql.= " WHERE s.accord = 'OK'";    // On ne peux calculer le type de solde que sur les simulations en accord
if(! empty($fk_simu)) $sql.= ' AND s.rowid = '.$fk_simu;
$sql.= ' ORDER BY dr.rowid';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$PDOdb = new TPDOdb;
$nb_commit = $nb_rollback = 0;

while($obj = $db->fetch_object($resql)) {
    $doss = new TFin_dossier;
    $doss->load($PDOdb, $obj->fk_dossier, false);

    $solde = TSimulation::getTypeSolde($obj->fk_simu, $doss->rowid, $obj->fk_leaser);

    $dossierRachete = new DossierRachete;
    $dossierRachete->load($PDOdb, $obj->rowid);

    $dossierRachete->type_solde = $solde;

    $dossierRachete->save($PDOdb);
}
$db->free($resql);
?>
<span>Capri... c'est fini !</span>