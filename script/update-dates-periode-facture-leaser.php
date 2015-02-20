<?php
	
//define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
require('../class/affaire.class.php');
require('../class/dossier.class.php');
require('../class/grille.class.php');

$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


$PDOdb=new TPDOdb;

$sql = "SELECT d.rowid
		FROM ".MAIN_DB_PREFIX."fin_dossier d
		LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement f ON (f.fk_fin_dossier = d.rowid AND f.type = 'LEASER')";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All();

foreach($TData as $obj) {
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $obj->rowid);
	$dossier->load_factureFournisseur($PDOdb,true);
	
	//Parcours de toutes les factures fournisseur associé à un dossier de financement leaser
	//Objectif : peupler proprement les champs date_debut_periode et date_fin_periode
	foreach($dossier->TFactureFournisseur as $echeance => $facture){
		$echeance += 1;
		
		$date_debut_periode = $dossier->getDateDebutPeriode($echeance);
		$date_fin_periode = $dossier->getDateFinPeriode($echeance);

		$sql = "UPDATE ".MAIN_DB_PREFIX."facture_fourn SET date_debut_periode = '".date('d/m/Y',strtotime($date_debut_periode))."' , date_fin_periode = '".date('d/m/Y',strtotime($date_fin_periode))."' WHERE rowid = ".$facture->id;
		$PDOdb->Execute($sql);
	}
}
