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

dol_include_once('/categories/class/categorie.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

$PDOdb = new TPDOdb();

// Récupération Leaser / Catégorie
global $TLeaserCat;
$sql = 'SELECT cf.fk_societe, cf.fk_categorie FROM '.MAIN_DB_PREFIX.'categorie_fournisseur cf ';
$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'categorie c ON (c.rowid = cf.fk_categorie) ';
$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'categorie c2 ON (c2.rowid = c.fk_parent) ';
$sql.= 'WHERE c2.label = \'Leaser\'';
$TLeaserCat = TRequeteCore::get_keyval_by_sql($PDOdb, $sql, 'fk_societe', 'fk_categorie');

// Récupération nom Leaser
global $TLeaserName;
$sql = 'SELECT s.rowid, s.nom FROM '.MAIN_DB_PREFIX.'societe s ';
$sql.= 'WHERE s.fournisseur = 1';
$TLeaserName = TRequeteCore::get_keyval_by_sql($PDOdb, $sql, 'rowid', 'nom');

// Fonction qui va chercher toutes les simulations OK des entités souhaitées pour construire un fichier csv de données
$filename = DOL_DATA_ROOT . '/9/financement/extract_simul/quadra_simulations_soldes.csv';
generate_csv_simul_solde($PDOdb, array(9,11), $filename); // Pour QUADRA / QSIGD

$filename = DOL_DATA_ROOT . '/9/financement/extract_simul/ouest_simulations_soldes.csv';
generate_csv_simul_solde($PDOdb, array(9,11,7,5), $filename); // Pour QUADRA / QSIGD / ABG / CC

$filename = DOL_DATA_ROOT . '/financement/extract_simul/est_simulations_soldes.csv';
generate_csv_simul_solde($PDOdb, array(1,2,3), $filename); // Pour CPRO







function generate_csv_simul_solde(&$PDOdb, $entities, $filename='') {
	global $db;
	
	if(empty($filename)) return false;

	// 1er fichier avec les simulations QUADRA et QSIGD
	$sql = "SELECT s.rowid, e.label
			FROM llx_fin_simulation s
			LEFT JOIN llx_entity e ON e.rowid = s.entity
			WHERE 1 = 1 
			AND s.entity IN (".implode(',', $entities).")
			AND s.accord = 'OK'
			";
	
	$PDOdb->Execute($sql);
	$TData = $PDOdb->Get_All(PDO::FETCH_ASSOC);
	
	$handle = fopen($filename, 'w');
	
	$head = explode(";", "Ref unique;Ref simulation;Ref contrat;Montant solde vendeur;Date fin periode;Montant solde banque;Type solde;Client;Type contrat;Leaser;Partenaire");
	fputcsv($handle, $head, ';');
	//echo '<pre>';
	foreach ($TData as $res) {
		$simu = new TSimulation();
		$simu->load($PDOdb, $db, $res['rowid'], false);
		$simu->societe = new Societe($db);
		$simu->societe->fetch($simu->fk_soc);
		$simu->no_auto_edi = true;
		$simu->load_suivi_simulation($PDOdb);
		$TDossiers = $simu->_getDossierSelected();
		//pre($simu,true);
		if(!empty($TDossiers)) {
			foreach ($TDossiers as $idDossier) {
				$d = $simu->dossiers[$idDossier];
				$choice = $d['choice'];
				$suffix = '';
				if($choice == 'next') {
					$suffix = '_p1';
				} else if ($choice == 'prev') {
					$suffix = '_m1';
				}
				list($date, $solde, $typesolde, $leaser) = get_date_et_solde($PDOdb, $simu, $idDossier);
				$data = array(
					$simu->reference . '-' . $d['num_contrat_leaser']	// Clé unique pour eux
					,$simu->reference									// Ref simulation
					,$d['num_contrat_leaser']							// Ref contrat leaser
					,$d['solde_vendeur'.$suffix]						// Solde coché vendeur
					,$date												// Période concernée
					,$solde												// Solde calculé (R ou NR)
					,$typesolde											// R ou NR
					,$simu->societe->name								// Client
					,$d['type_contrat']									// Type contrat
					,$leaser											// Leaser chez qui le contrat est soldé											// Leaser chez qui le contrat est soldé
					,$res['label']
				);
				
				//echo implode(' || ', $data).'<br>';
				fputcsv($handle, $data, ';');
			}
		}
	}
	
	fclose($handle);
}


function get_date_et_solde(&$PDOdb, &$simu, $idDossier) {
	global $db, $TLeaserCat, $TLeaserName;
	
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
	$date_fin = $d->getDateFinPeriode($echeance);
	
	// Solde final : si LEASER dossier = LEASER PRECO => SOLDE R
	// Sinon, si LEASER DOSSIER A REFUSÉ (dans le suivi) => SOLDE R
	// sinon, NR
	$solde = 0;
	
	// On compare les Leaser par leur catégorie (il y a 3 BNP par exemple...)
	$sameLeaser = ($TLeaserCat[$d->financementLeaser->fk_soc] == $TLeaserCat[$simu->fk_leaser]);
	
	// On vérifie s'il y a eu un refus sur le suivi leaser
	$refus = false;
	foreach($simu->TSimulationSuivi as $suivi) {
		if($TLeaserCat[$d->financementLeaser->fk_soc] == $TLeaserCat[$suivi->fk_leaser]
			&& $suivi->statut == 'KO')
		{
			$refus = true;	
		}
	}
	
	if($sameLeaser || $refus) {
		$solde = $d->getSolde($PDOdb, 'SRCPRO', $echeance + 1);
		$typesolde = 'R';
	} else {
		$solde = $d->getSolde($PDOdb, 'SNRCPRO', $echeance + 1);
		$typesolde = 'NR';
	}

	$leaser = $TLeaserName[$d->financementLeaser->fk_soc];

	return array($date_fin, round($solde,2), $typesolde, $leaser);
}
