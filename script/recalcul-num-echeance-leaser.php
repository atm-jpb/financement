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
$sql = "SELECT  df.rowid, df.fk_fin_dossier, df.reference, df.numero_prochaine_echeance, df.date_prochaine_echeance, df.periodicite,df.echeance,df.`loyer_intercalaire`
		, count(ee.rowid) as nbf, MAX(f.datef)
		FROM ".MAIN_DB_PREFIX."fin_dossier_financement df
		LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_source = df.fk_fin_dossier AND ee.sourcetype = 'dossier' AND ee.targettype = 'invoice_supplier')
		LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn f ON (f.rowid = ee.fk_target)
		WHERE df.type = 'LEASER'
		AND df.date_solde < '2010-12-31'
		AND `okPourFacturation` = 'AUTO'
		GROUP BY df.fk_fin_dossier
		HAVING MAX(f.datef) < '2015-01-01'
		AND ((nbf < df.numero_prochaine_echeance AND `loyer_intercalaire` > 0) OR nbf +1 < df.numero_prochaine_echeance)";
//echo $sql; exit;
$PDOdb->Execute($sql);
$TData = $PDOdb->Get_All();

foreach($TData as $data) {
	$fin = new TFin_financement();
	if($fin->loadReference($PDOdb, $data->reference, 'LEASER')) {
	
		echo $fin->reference.' : '.$fin->numero_prochaine_echeance.' - '.$fin->get_date('date_prochaine_echeance');
		
		$fin->initEcheance();
		$fin->setEcheance($data->numero_prochaine_echeance - 1);
		$fin->save($PDOdb);
		
		echo ' ==> '.$fin->numero_prochaine_echeance.' - '.$fin->get_date('date_prochaine_echeance').'<hr>';
		$cpt ++;
	}
}
echo "TOTAL : ".$cpt;
