<?php
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');
	require('../class/affaire.class.php');
	require('../class/grille.class.php');
	dol_include_once('/asset/class/asset.class.php');
	dol_include_once('/compta/facture/class/facture.class.php');
	dol_include_once('/fourn/class/fournisseur.facture.class.php');

	set_time_limit(0);

	$user=new User($db);
	$user->fetch('', DOL_ADMIN_USER);
	$user->getrights();
	print $user->lastname.'<br />';

	$ATMdb=new TPDOdb;

	$sql="SELECT a.rowid as 'rowid'
			FROM ".MAIN_DB_PREFIX."fin_affaire as a
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire as da ON (da.fk_fin_affaire = a.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as df ON (da.fk_fin_dossier = df.fk_fin_dossier)
			WHERE a.reference LIKE '%EXT%'
				OR (a.nature_financement = 'EXTERNE' AND (df.fk_soc = 3382 OR df.fk_soc = 7411 OR df.fk_soc = 4440 OR df.fk_soc = 6065 OR df.fk_soc = 3306 ))";
	
	echo $sql.'<br>';
	
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
			echo " --- dossier : ".$dossier->reference." supprimé<br>";
		}
		
		if(strpos($a->reference, 'EXT') !== FALSE){
			echo "affaire : ".$a->reference." supprimé<br>";
			$a->delete($ATMdb);
		}
	}

	
	
