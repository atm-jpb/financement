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

$PDOdb = new TPDOdb();

// 1er fichier avec les simulations QUADRA et QSIGD
$sql = "SELECT s.reference, cli.nom as client, cli.siren, s.fk_type_contrat,
				s.montant_total_finance, s.echeance, s.duree, s.opt_periodicite,
				DATE_FORMAT(s.date_simul, '%d/%m/%y %H:%i'), CONCAT(CONCAT(u.firstname, ' '), u.lastname) as user,
				s.accord, s.type_financement, leaser.nom as leaser, s.numero_accord,
				(SELECT CONCAT(sc.civilite_externe, ' ', sc.prenom_externe, ' ', sc.nom_externe)
					FROM llx_fin_score sc
					WHERE sc.fk_soc = cli.rowid
					ORDER BY sc.date_score
					DESC LIMIT 1) as contact
		FROM llx_fin_simulation s
		LEFT JOIN llx_societe cli ON cli.rowid = s.fk_soc
		LEFT JOIN llx_societe leaser ON leaser.rowid = s.fk_leaser
		LEFT JOIN llx_user u ON u.rowid = s.fk_user_author
		WHERE 1 = 1 
		AND s.entity IN (9,11)
		AND s.accord = 'OK'";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All(PDO::FETCH_ASSOC);

$filename = DOL_DATA_ROOT . '/9/financement/extract_simul/quadra_simulations.csv';
$handle = fopen($filename, 'w');

$head = explode(";", "Ref Simulation;Client;Siren;Type Contrat;Montant Total;Echeance;Duree;Periodicite;Date Simulation;Utilisateur;Statut;Type Financement;Leaser;Numero Accord;Contact");
fputcsv($handle, $head, ';');

foreach ($TData as $data) {
	fputcsv($handle, $data, ';');
}

fclose($handle);

// 2ème fichier avec toutes les simulations ABG, CC, QUADRA, QSIGD
$sql = "SELECT s.reference, cli.nom as client, cli.siren, s.fk_type_contrat,
				s.montant_total_finance, s.echeance, s.duree, s.opt_periodicite,
				DATE_FORMAT(s.date_simul, '%d/%m/%y %H:%i'), CONCAT(CONCAT(u.firstname, ' '), u.lastname) as user,
				s.accord, s.type_financement, leaser.nom as leaser, s.numero_accord,
				(SELECT CONCAT(sc.civilite_externe, ' ', sc.prenom_externe, ' ', sc.nom_externe)
					FROM llx_fin_score sc
					WHERE sc.fk_soc = cli.rowid
					ORDER BY sc.date_score
					DESC LIMIT 1) as contact
				, e.label as partenaire
				, CASE WHEN s.opt_terme = 0 THEN 'Échu' ELSE 'À échoir' END as terme
		FROM llx_fin_simulation s
		LEFT JOIN llx_societe cli ON cli.rowid = s.fk_soc
		LEFT JOIN llx_societe leaser ON leaser.rowid = s.fk_leaser
		LEFT JOIN llx_user u ON u.rowid = s.fk_user_author
		LEFT JOIN llx_entity e ON e.rowid = s.entity
		WHERE 1 = 1 
		AND s.entity IN (9,11,7,5)
		AND s.accord = 'OK'";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All(PDO::FETCH_ASSOC);

$filename = DOL_DATA_ROOT . '/9/financement/extract_simul/ouest_simulations.csv';
$handle = fopen($filename, 'w');

$head = explode(";", "Ref Simulation;Client;Siren;Type Contrat;Montant Total;Echeance;Duree;Periodicite;Date Simulation;Utilisateur;Statut;Type Financement;Leaser;Numero Accord;Contact;Partenaire;Terme");
fputcsv($handle, $head, ';');

foreach ($TData as $data) {
	fputcsv($handle, $data, ';');
}

fclose($handle);