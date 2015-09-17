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


$ATMdb=new TPDOdb;
$importFolder = FIN_IMPORT_FOLDER.'todo/';
$importFolderOK = FIN_IMPORT_FOLDER.'done/';
$fileName = 'new_dossier.csv';

$fileHandler = fopen($importFolder.$fileName, 'r');
$TInfosGlobale = array();
while($dataline = fgetcsv($fileHandler, 1024, FIN_IMPORT_FIELD_DELIMITER, FIN_IMPORT_FIELD_ENCLOSURE)) {
	change_dossier_ref($ATMdb, $dataline);
}
fclose($fileHandler);

//rename($importFolder.$fileName, $importFolderOK.$fileName);

function change_dossier_ref(&$ATMdb, $dataline) {
	$oldref = str_pad($dataline[7],8,'0',STR_PAD_LEFT);
	$newref = str_pad($dataline[0],8,'0',STR_PAD_LEFT);
	
	echo $oldref.';'.$newref.';';
	
	$oldf = new TFin_financement();
	if($oldf->loadReference($ATMdb, $oldref, 'CLIENT')) { // On arrive à charger l'ancien
		echo 'ancien trouvé;';
		$oldDossier = new TFin_dossier();
		$oldDossier->load($ATMdb, $oldf->fk_fin_dossier, false);
		
		$newf = new TFin_financement();
		if($newf->loadReference($ATMdb, $newref, 'CLIENT')) { // On arrive à charger le nouveau
			echo 'nouveau trouvé;';
			$newDossier = new TFin_dossier();
			$newDossier->load($ATMdb, $newf->fk_fin_dossier, false);
			
			// Les factures de location du nouveau dossier sont transférées vers l'ancien
			$updateLinks = "UPDATE ".MAIN_DB_PREFIX."element_element SET fk_source = ".$oldDossier->getId();
			$updateLinks.= " WHERE sourcetype = 'dossier' AND fk_source = ".$newDossier->getId();
			$ATMdb->Execute($updateLinks);
			
			$newDossier->delete($ATMdb);
			echo 'nouveau supprimé;';
		} else {
			echo 'nouveau non trouvé;';
		}
		
		$oldDossier->commentaire = 'Ancien contrat Artis '.$oldf->reference."\n".$oldDossier->commentaire;
		$oldDossier->save($ATMdb);
		
		$oldf->reference = $newref;
		$oldf->save($ATMdb);
		
		$oldDossier->load($ATMdb, $oldDossier->getId()); // Rechargement du dossier pour recalculs soldes et renta
		$oldDossier->save($ATMdb);
		echo 'ancien maj;';
	} else {
		echo 'ancien non trouvé;';
	}
	
	echo '<br/>';
}