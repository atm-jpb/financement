<?php
	
//define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


$PDOdb=new TPDOdb;

/*
 * CODE AVANT (COMPLETEMENT INCORRECTE ET NON FONCTIONNEL)
 */

/*$sql = "SELECT f.reference, COUNT(i.rowid) as nbFact, SUM(CASE WHEN i.type = 0 THEN 1 ELSE -1 END) as echeance_passee
FROM ".MAIN_DB_PREFIX."fin_dossier_financement f
LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON ee.fk_source = f.fk_fin_dossier AND ee.sourcetype = 'dossier' AND ee.targettype = 'facture'
LEFT JOIN ".MAIN_DB_PREFIX."facture i ON i.rowid = ee.fk_target
WHERE i.rowid IS NOT NULL
GROUP BY f.reference";

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All();

foreach($TData as $data) {
	$fin = new TFin_financement();
	if($fin->loadReference($PDOdb, $data->reference, 'CLIENT')) {
	
		echo $fin->reference.' : '.$fin->numero_prochaine_echeance.' - '.$fin->get_date('date_prochaine_echeance');
		
		$fin->initEcheance();
		$fin->setEcheance($data->echeance_passee);
		$fin->save($PDOdb);
		
		echo ' ==> '.$fin->numero_prochaine_echeance.' - '.$fin->get_date('date_prochaine_echeance').'<hr>';
	}
}*/

//On ne prends que les dossiers non soldé
//On ne prends pas les dossier dont la date de prochaine échéance est aujourd'hui car se mettra à jour tout seul
//On ne traite que les dossier qui ont une date de prochaine échéance comprise entre le 01/01/2015 et aujourd'hui

$sql = "SELECT fk_fin_dossier 
		FROM ".MAIN_DB_PREFIX."fin_dossier_financement
		WHERE date_solde < '1970-00-00 00:00:00' 
			AND montant_solde = 0
			AND type = 'CLIENT'
			AND date_prochaine_echeance <= '".date('Y-m-d')."%'
			AND date_fin >= '".date('Y-m-d')."%'";
			//AND date_prochaine_echeance BETWEEN '2015-01-01 00:00:00' AND '".date('Y-m-d')." 00:00:00'";

echo $sql.'<br>';

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All();

foreach($TData as $data){
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $data->fk_fin_dossier);
	
	echo $dossier->financement->reference." ==> ".date('d/m/Y', $dossier->financement->date_prochaine_echeance).'<br>';
	
	$dossier->save($PDOdb,false);
	
	echo $dossier->financement->reference." ==> ".date('d/m/Y', $dossier->financement->date_prochaine_echeance).'<br>';
	$cpt ++;
}

echo " ------ ".$cpt." échéancier client MAJ ------";
