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
	
	//On supprime tous les financements de type EXTERNE lié au 5 leasers : 3382 7411 4440 6065 3306
	$sql="SELECT df.fk_fin_dossier as 'rowid'
			FROM ".MAIN_DB_PREFIX."fin_affaire as a
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire as da ON (da.fk_fin_affaire = a.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as df ON (da.fk_fin_dossier = df.fk_fin_dossier)
			WHERE a.nature_financement = 'EXTERNE' AND (df.fk_soc = 3382 OR df.fk_soc = 7411 OR df.fk_soc = 4440 OR df.fk_soc = 6065 OR df.fk_soc = 3306 )";
	
	echo "<br><br>".$sql.'<br>';
	
	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();
	
	echo "<br><br> ***** Suppression des Financements de type EXTERNE lié au Leasers : 3382 7411 4440 6065 3306 *****<br>";
	foreach($Tab as $row) {
		
		$dossier = new TFin_dossier;
		$dossier->load($ATMdb, $row->rowid);
		
		//pre($dossier,true);
		$dossier->delete($ATMdb,false,false,false);
		echo " --- dossier : ".$dossier->reference." supprimé<br>";
	}
	
	//On supprime les affaires dont la référence est "%EXT%"
	$sql="SELECT a.rowid as 'rowid'
			FROM ".MAIN_DB_PREFIX."fin_affaire as a
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire as da ON (da.fk_fin_affaire = a.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as df ON (da.fk_fin_dossier = df.fk_fin_dossier)
			WHERE a.reference LIKE '%EXT%'";

	echo $sql.'<br>';

	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();

	echo "<br><br> ***** Suppression des AFFAIRES avec référence EXT lié au Leasers : 3382 7411 4440 6065 3306 *****<br>";
	foreach($Tab as $row) {
		
		$a=new TFin_affaire;
		$a->load($ATMdb, $row->rowid);
		$a->delete($ATMdb);

		echo " --- affaire : ".$a->reference." supprimé<br>";
	}

	
	
