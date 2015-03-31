<?php
	
//define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
require('../class/affaire.class.php');
require('../class/dossier.class.php');
require('../class/grille.class.php');
dol_include_once("/fourn/class/fournisseur.facture.class.php");

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
//$sql .= " WHERE d.rowid = 2273";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All();

foreach($TData as $obj) {
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $obj->rowid);
	//$dossier->load_factureFournisseur($PDOdb,true);
	
	$sql = "SELECT fk_target";
	$sql.= " FROM ".MAIN_DB_PREFIX."element_element";
	$sql.= " WHERE sourcetype='dossier'";
	$sql.= " AND targettype='invoice_supplier'";
	$sql.= " AND fk_source=".$dossier->getId();
	
	$PDOdb->Execute($sql);
	
	while($PDOdb->Get_line()) {
		$fact = new FactureFournisseur($db);
		$fact->fetch($PDOdb->Get_field('fk_target'));
		
		// Permet d'afficher la facture en face de la bonne échéance, le numéro de facture fournisseur finissant par /XX (XX est le numéro d'échéance)
		$TTmp = explode('/', $fact->ref_supplier);
		$echeance = array_pop($TTmp);
		$fact->echeance = $echeance;

		$dossier->TFactureFournisseur[] = $fact;
		
	}
	
	//Parcours de toutes les factures fournisseur associé à un dossier de financement leaser
	//Objectif : peupler proprement les champs date_debut_periode et date_fin_periode
	foreach($dossier->TFactureFournisseur as $facture){
		
		$date_debut_periode = $dossier->getDateDebutPeriode($facture->echeance-1,'LEASER');
		$date_fin_periode = $dossier->getDateFinPeriode($facture->echeance-1);
		
		/*echo date('d/m/Y',$dossier->date_debut)." ".$dossier->financementLeaser->calage.'<br>';
		echo $echeance.'<br>';
		echo $date_debut_periode.'<br>';
		echo $date_fin_periode.'<br><hr>';*/
		
		$sql = "UPDATE ".MAIN_DB_PREFIX."facture_fourn SET date_debut_periode = '".date('Y-m-d',strtotime($date_debut_periode))."' , date_fin_periode = '".date('Y-m-d',strtotime($date_fin_periode))."' WHERE rowid = ".$facture->id;
		$PDOdb->Execute($sql);
	}
}
