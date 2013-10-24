<?php
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');

	set_time_limit(0);

	$ATMdb=new TPDOdb;

	$sql="SELECT f.rowid as 'rowid'
	FROM ".MAIN_DB_PREFIX."fin_dossier_financement f INNER JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (f.fk_fin_dossier=d.rowid)
	INNER JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (da.fk_fin_dossier=d.rowid) 
	INNER JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (da.fk_fin_affaire=a.rowid)
	WHERE a.nature_financement='EXTERNE' AND f.date_solde='0000-00-00'
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
			if(!empty($_REQUEST['reel'])&& $_REQUEST['reel']=='OUI')$f->save($ATMdb);

		}		
	}

	
	
