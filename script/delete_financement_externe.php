<?php
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');
	require('../class/affaire.class.php');
	require('../class/grille.class.php');

	set_time_limit(0);

	$user=new User($db);
	$user->fetch('', DOL_ADMIN_USER);
	$user->getrights();
	print $user->lastname.'<br />';

	$ATMdb=new TPDOdb;

	$sql="SELECT a.rowid as 'rowid'
			FROM ".MAIN_DB_PREFIX."fin_affaire as a
			WHERE a.reference LIKE '%EXT%'";
	
	//echo $sql.'<br>';
	
	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();
	
	//pre($Tab,true);
	
	foreach($Tab as $row) {
		
		$a=new TFin_affaire;
		$a->load($ATMdb, $row->rowid);
		
		foreach($a->TLien as $i => $TFin_dossier_affaire){
			$dossier = new TFin_dossier;
			$dossier->load($ATMdb, $TFin_dossier_affaire->dossier->rowid);
			
			//pre($dossier,true);
			$dossier->delete($ATMdb);
			
			echo "affaire : ".$a->reference." supprimé<br>";
		}
		
		$a->delete($ATMdb);
	}

	
	
