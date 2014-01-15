<?php
	require("../../config.php");
	//require('config.php');
	
	include_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");

	global $db;
	
	$ATMdb = new Tdb;
	
	/******************************
	 * FACTURE
	 *****************************/
	
	$sql = "SELECT DISTINCT(c.rowid) as 'idCmd'
			FROM ".MAIN_DB_PREFIX."facture_fourn as c
				LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn_det as cd ON (cd.fk_facture_fourn = c.rowid)
			WHERE cd.tva_tx = 19.600
			AND datec>='2014-01-01'
			
			";
				
				
	
	//echo $sql;
	
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		
		$facture = new FactureFournisseur($db);
		$facture->fetch($ATMdb->Get_field('idCmd'));

		$TLigne =$facture->fetch_lines();
		
		foreach($TLigne as $line) {
			print "Mise à jour de ligne de facture(".$line->rowid.") pour facture ".$facture->id." (".$facture->ref.")<br />";
			//$facture->updateline($line->rowid, $line->libelle, $line->pu_ht, 20.0, $line->localtax1_tx, $line->localtax2_tx, $line->qty, $line->fk_product);			
			
		}

		//$facture->set_unpaid($user);

	}
	
	