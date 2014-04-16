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
$sql = "SELECT f.reference, COUNT(i.rowid) as nbFact, SUM(CASE WHEN i.type = 0 THEN 1 ELSE -1 END) as echeance_passee
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
}
