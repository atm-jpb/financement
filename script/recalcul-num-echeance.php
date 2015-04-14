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
		WHERE date_solde = '0000-00-00 00:00:00' 
			AND montant_solde = 0
			AND type = 'CLIENT'
			AND date_prochaine_echeance NOT LIKE '".date('Y-m-d')."%'";
			//AND date_prochaine_echeance BETWEEN '2015-01-01 00:00:00' AND '".date('Y-m-d')." 00:00:00'";

echo $sql.'<br>';

$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All();

foreach($TData as $data){
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $data->fk_fin_dossier);
	$dossier->load_facture($PDOdb);
	//pre($dossier,true);
	
	//On récupère le numéro de la dernière échéance facturée +1
	$echeance = array_pop(array_keys($dossier->TFacture));
	$echeance++;
	
	//On récupère la date de prochaine échéance
	$date_echeance = $dossier->getDateDebutPeriode($echeance,'CLIENT');
	$date_echeance = date('d/m/Y',strtotime($date_echeance));
	
	if($echeance != 1 ) $echeance ++;
	
	$dossier->financement->numero_prochaine_echeance = $echeance;
	$dossier->financement->set_date('date_prochaine_echeance', $date_echeance);
	
	$dossier->financement->save($PDOdb);
	echo $dossier->financement->reference." ==> ".$echeance." ==> ".$date_echeance.'<br>';
	$cpt ++;
}

echo " ------ ".$cpt." échéancier client MAJ ------";
