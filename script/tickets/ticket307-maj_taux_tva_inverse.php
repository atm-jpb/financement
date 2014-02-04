<?php
	require("../../config.php");
	//require('config.php');
	
	include_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
	include_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
	global $db,$user;
	
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
		
		$last_statut = $facture->statut;
		$facture->set_draft($user);
		
		foreach($facture->lines as $line) {
			print "Mise à jour de ligne de facture(".$line->rowid.") pour facture ".$facture->id." (".$facture->ref.")<br />";		
			print (int)$facture->updateline($line->rowid, $line->desc, $line->subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, 19.6);
		}
		
		switch ($last_statut) {
			case 1:
				$facture->set_paid($user);
				break;
			case 2:
				$facture->set_paid($user);
				break;
			case 3:
				$facture->set_canceled($user);
				break;
		}

		//$facture->set_unpaid($user);
		exit;
	}
	
	
