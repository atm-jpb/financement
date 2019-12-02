<?php

// Include Dolibarr environment
$path=dirname(__FILE__).'/';
define('INC_FROM_CRON_SCRIPT', true);
require_once($path."../config.php");

$langs->setDefaultLang('fr_FR'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
$langs->load("financement@financement");
@set_time_limit(0);					// No timeout for this script
ini_set('display_errors', true);
ini_set('memory_limit','1024M');

dol_include_once('/categories/class/categorie.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();

$PDOdb = new TPDOdb();

$sql = "SELECT rowid
		FROM ".MAIN_DB_PREFIX."fin_dossier d
		WHERE 1 = 1
		AND d.date_solde < '1970-00-00 00:00:00'
		AND (d.quote_part_couleur > 0 OR d.quote_part_noir > 0)
		ORDER BY reference_contrat_interne ASC";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All(PDO::FETCH_ASSOC);

//$filename = DOL_DATA_ROOT . '/9/financement/extract_simul/quadra_simulations.csv';
//$handle = fopen($filename, 'w');

$head = explode(";", "Contrat;Quote-part noir;Copies sup noir;Quote-part couleur;Copies sup couleur;Retrait");
echo implode(';', $head) . '<br>';
//fputcsv($handle, $head, ';');

foreach ($TData as $data) {
	$dossier = new TFin_dossier();
	$dossier->load($PDOdb, $data['rowid'],false,false);
	$dossier->load_financement($PDOdb);
	$dossier->load_facture($PDOdb,true);
	
	$sommeCopieSupCouleur = $sommeCopieSupNoir = 0;
	list($sommeCopieSupNoir,$sommeCopieSupCouleur) = $dossier->getSommesIntegrale($PDOdb,true);
	
	$decompteCopieSupNoir = $sommeCopieSupNoir * $dossier->quote_part_noir;
	$decompteCopieSupCouleur = $sommeCopieSupCouleur * $dossier->quote_part_couleur;
	
	$soldepersointegrale = $decompteCopieSupCouleur + $decompteCopieSupNoir;

	$soldepersointegrale = ($soldepersointegrale * ($conf->global->FINANCEMENT_PERCENT_RETRIB_COPIES_SUP/100));
	
	$data = array(
		$dossier->reference
		,$dossier->quote_part_noir
		,$sommeCopieSupNoir
		,$dossier->quote_part_couleur
		,$sommeCopieSupCouleur
		,$soldepersointegrale
	);
	
	print implode(';', $data) . '<br>';
}

//fclose($handle);