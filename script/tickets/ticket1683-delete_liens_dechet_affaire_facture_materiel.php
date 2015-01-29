<?php
	require("../../config.php");
	
	$ATMdb = new Tdb;
	
	//Récupération de tous les liens affaire => facture matériel
	$sql = "SELECT rowid, fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE sourcetype = 'affaire' AND targettype = 'facture'";
	
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		$TAffaireIds[$ATMdb->Get_field('rowid')] = $ATMdb->Get_field('fk_source');
	}

	foreach($TAffaireIds as $id => $affaireId){

		$ATMdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."fin_affaire WHERE rowid = ".$affaireId);
		if($ATMdb->Get_line()){
			continue;
		}
		else{
			$ATMdb->Execute('DELETE FROM '.MAIN_DB_PREFIX.'element_element WHERE rowid = '.$id);
		}
		
	}
	
	/*	Requête de vérification
	 * 
	 * 	SELECT * , COUNT( fk_source ) AS total
		FROM `llx_element_element`
		WHERE `sourcetype` LIKE 'affaire'
		AND `targettype` LIKE 'facture'
		GROUP BY fk_target
		HAVING COUNT( fk_source ) >=2
		ORDER BY `llx_element_element`.`rowid` DESC */
	
	
