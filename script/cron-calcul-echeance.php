<?php

set_time_limit(0);
define('INC_FROM_CRON_SCRIPT', true);

require_once __DIR__.'/../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$user=new User($db);
$user->fetch('', DOL_ADMIN_USER);
$user->getrights();

$PDOdb = new TPDOdb();

$sql = "SELECT fin.rowid, fin.fk_fin_dossier ";
$sql.= "FROM ".MAIN_DB_PREFIX."fin_dossier_financement fin ";
//$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement df ON (df.reference = simu.numero_accord) ";
$sql.= "WHERE (fin.date_solde < '1000-01-01' OR fin.date_solde IS NULL) ";
$sql.= "AND fin.date_prochaine_echeance <= '".date('Y-m-d')."' ";
$sql.= "AND fin.reference != '' ";
$sql.= "AND fin.date_debut > '1000-01-01' ";
//$sql.= "AND fin.rowid = 97378";

echo $sql.'<hr>';
$TRowid = $PDOdb->ExecuteAsArray($sql);

//pre($TRowid,true);exit;

foreach ($TRowid as $obj) {
	echo $obj->rowid.' - '. $obj->fk_fin_dossier .'<br>';
	$fin = new TFin_financement();
	$fin->load($PDOdb, $obj->rowid);
	
	$dossier = new TFin_dossier();
	$dossier->load($PDOdb, $obj->fk_fin_dossier,false);
	$echeance = $dossier->_get_num_echeance_from_date(time());
	$echeance = $echeance +2;
	//echo $echeance.'<br>';
	$fin->numero_prochaine_echeance = $echeance;
	//echo $dossier->getDateDebutPeriode($echeance);
	echo 'ECH : '.$echeance.' - DATE : '.$dossier->getDateDebutPeriode($echeance-1,'LEASER').'<br>';
	$fin->set_date('date_prochaine_echeance', $dossier->getDateDebutPeriode($echeance-1,'LEASER'));
	$fin->save($PDOdb);
}

