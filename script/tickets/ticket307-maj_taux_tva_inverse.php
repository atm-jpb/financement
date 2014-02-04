<?php
	require("../../config.php");
	//require('config.php');
	
	include_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
	include_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
	global $db;
	
	$ATMdb = new Tdb;
	
	/******************************
	 * FACTURE
	 *****************************/
	
	$sql = "SELECT DISTINCT(c.rowid) as 'idCmd'
			FROM ".MAIN_DB_PREFIX."facture as c
				LEFT JOIN ".MAIN_DB_PREFIX."facturedet as cd ON (cd.fk_facture = c.rowid)
			WHERE cd.tva_tx = 20.000
			AND datef<='2014-01-01'
			
			";
				
				
	
	echo $sql;
	
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		
		$facture = new Facture($db);
		$facture->fetch($ATMdb->Get_field('idCmd'));
		
		print "Facture ".$facture->id.'<br />';

		foreach($facture->lines as $line) {
			print "Mise à jour de ligne de facture(".$line->rowid.") pour facture ".$facture->id." (".$facture->ref.")<br />";		
			//$facture->updateline($line->rowid, $line->desc, $line->pu_ht, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, 19.6);
		}

		//$facture->set_unpaid($user);
	//	exit;
	}
	
	
