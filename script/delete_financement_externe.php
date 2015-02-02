<?php
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');

	set_time_limit(0);

	$user=new User($db);
	$user->fetch('', DOL_ADMIN_USER);
	$user->getrights();
	print $user->lastname.'<br />';

	$ATMdb=new TPDOdb;

	$sql="SELECT f.rowid as 'rowid'
			FROM ".MAIN_DB_PREFIX."fin_dossier_financement f 
				INNER JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (f.fk_fin_dossier=d.rowid)
			WHERE d.nature_financement = 'EXTERNE'";

	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();

	foreach($Tab as $row) {
		
		$f=new TFin_financement;
		$f->load($ATMdb, $row->rowid);
		
		echo $f->reference." supprimé<br>";
		
		$f->delete($ATMdb);
	}

	
	
