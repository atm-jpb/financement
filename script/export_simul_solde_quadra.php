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

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();

dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');

$PDOdb = new TPDOdb();

$sql = "SELECT s.rowid
		FROM llx_fin_simulation s
		WHERE 1 = 1 
		AND s.entity = 9
		AND s.accord = 'OK'
		LIMIT 1
		";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All(PDO::FETCH_ASSOC);

$filename = DOL_DATA_ROOT . '/9/financement/extract_simul/quadra_simulations_soldes.csv';
$handle = fopen($filename, 'w');

$head = explode(";", "Ref Simulation;Ref Contrat;Montant Solde;Date Solde;Type Solde");
fputcsv($handle, $head, ';');

foreach ($TData as $res) {
	$simu = new TSimulation();
	$simu->load($PDOdb, $db, $res['rowid'], false);
	$TDossiers = $simu->_getDossierSelected();
	pre($simu,true);
	if(!empty($TDossiers)) {
		foreach ($TDossiers as $idDossier) {
			$d = $simu->dossiers[$idDossier];
			$data = array(
				$simu->reference
				,$d['num_contrat_leaser']
				,$d['solde_vendeur']
				,get_date_solde($PDOdb, $simu, $idDossier)
				// Solde final
			);
			
			// Solde final : si LEASER dossier = LEASER PRECO => SOLDE R
			// Sinon, si LEASER DOSSIER A REFUSÉ (dans le suivi) => SOLDE R
			// sinon, NR
			echo implode(' || ', $data).'<br>';
			//fputcsv($handle, $data, ';');
		}
	}
}

fclose($handle);




function get_date_solde(&$PDOdb, &$simu, $idDossier) {
	
	$d = new TFin_dossier();
	$d->load($PDOdb, $idDossier, false, false);
	$d->load_financement($PDOdb);
	
	$echeance = $d->_get_num_echeance_from_date($simu->date_simul);
	
	// Si coché P-1 => on calcule la date de période précédente, P en cours, P+1 période suivante
	if(!empty($simu->dossiers_rachetes_m1[$idDossier]['checked']) || !empty($simu->dossiers_rachetes_nr_m1[$idDossier]['checked'])) {
		$echeance--;
	} else if (!empty($simu->dossiers_rachetes_p1[$idDossier]['checked']) || !empty($simu->dossiers_rachetes_nr_p1[$idDossier]['checked'])) {
		$echeance++;
	}
	
	$date_periode = $d->getDateDebutPeriode($echeance);
	
	return $date_periode;
}
