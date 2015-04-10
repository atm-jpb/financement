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
	FROM ".MAIN_DB_PREFIX."fin_dossier_financement f INNER JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (f.fk_fin_dossier=d.rowid)
	WHERE f.date_solde='0000-00-00' AND f.type='LEASER' AND (f.reference != '' OR f.reference IS NOT NULL)
	";

	
	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();
	
	foreach($Tab as $row) {
		
		$f=new TFin_financement;
		$f->load($ATMdb, $row->rowid);
	
		print "Recalcule financement (".$f->fk_fin_dossier.' : '.$f->reference.") ".$f->get_date('date_prochaine_echeance')." ".$f->numero_prochaine_echeance."...";
	
		if(!$f->setEcheanceExterne()) {
			print "Erreur dates financement <br />";
		}
		else {
		
			print $f->get_date('date_prochaine_echeance')." ".$f->numero_prochaine_echeance."<br />";
			
	//		$ATMdb->debug=true;
			if(!empty($_REQUEST['reel'])&& $_REQUEST['reel']=='OUI') {
				if(!$f->save($ATMdb)) print "user sans droit !<br/>";
			}
//$ATMdb->debug=false;
		}		
	}

	
	
