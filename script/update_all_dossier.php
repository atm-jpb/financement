<?php
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');
	dol_include_once('/financement/class/simulation.class.php');
	dol_include_once('/financement/class/score.class.php');
	dol_include_once('/financement/class/affaire.class.php');
	dol_include_once('/financement/class/grille.class.php');

	set_time_limit(0);

	$user=new User($db);
	$user->fetch('', DOL_ADMIN_USER);
	$user->getrights();
	print $user->lastname.'<br />';

	$ATMdb=new TPDOdb;

	$sql="SELECT DISTINCT(d.rowid) as 'rowid'
		  FROM ".MAIN_DB_PREFIX."fin_dossier_financement f 
			INNER JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (f.fk_fin_dossier=d.rowid)
		  WHERE f.type='LEASER' 
			AND (f.fk_soc = 3382 OR f.fk_soc = 19553 OR f.fk_soc = 20113)
			AND d.nature_financement = 'EXTERNE'";
	
	echo $sql.'<br>';
	
	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();
	
$cpt = 0;
	foreach($Tab as $row) {
		$cpt++;
		$d=new TFin_dossier;
		$d->load($ATMdb, $row->rowid);
		$d->save($ATMdb);
		echo "dossier : ".$d->getId()." ".$d->financementLeaser->reference." sauvegardé<br><hr>";
// if($cpt>5)break;
	}

	
	
