<?php

require('../config.php');

@set_time_limit(0);					// No timeout for this script

$PDOdb=new TPDOdb();

// Nettoyage des doublons Wonderbase
$file = dol_buildpath('/financement/script/fix-clients-doublons/').'clients_code_wb';
$fileHandler = fopen($file, 'r');

while($dataline = fgetcsv($fileHandler, 4096, FIN_IMPORT_FIELD_DELIMITER, FIN_IMPORT_FIELD_ENCLOSURE)) {
	$codeArtis = $dataline[7];
	$codeWb = $dataline[10];
	fusion_doublon_client($PDOdb, $codeArtis, $codeWb);
}
fclose($fileHandler);

// Nettoyage des doublons Cristal
/*$file = dol_buildpath('/financement/script/fix-clients-doublons/').'clients_code_cristal';
$fileHandler = fopen($file, 'r');

while($dataline = fgetcsv($fileHandler, 4096, FIN_IMPORT_FIELD_DELIMITER, FIN_IMPORT_FIELD_ENCLOSURE)) {
	$codeArtis = $dataline[7];
	$codeCristal = $dataline[10];
	fusion_doublon_client($PDOdb, $codeArtis, $codeCristal);
}
fclose($fileHandler);*/


function fusion_doublon_client(&$PDOdb, $codeClient, $codeDoublon) {
	global $db;
	
	$TCli = TRequeteCore::get_id_from_what_you_want($PDOdb,MAIN_DB_PREFIX.'societe',array('code_client'=>$codeClient));
	$TDbl = TRequeteCore::get_id_from_what_you_want($PDOdb,MAIN_DB_PREFIX.'societe',array('code_client'=>$codeDoublon,'client'=>2));
	
	// On ne fusionne que si on a trouvé une fiche avec le code client et une avec le code doublon
	if(count($TCli) == 1 && count($TDbl) == 1) {
		echo $codeClient . ' => '.$TCli[0] . ' - ' . $codeDoublon . ' => '. $TDbl[0];
		
		// Fusion des simulations
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_simulation ';
		$sql.= 'SET fk_soc = '.$TCli[0].' ';
		$sql.= 'WHERE fk_soc = '.$TDbl[0].' ';
		
		$PDOdb->Execute($sql);
		
		// Fusion des affaires
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_affaire ';
		$sql.= 'SET fk_soc = '.$TCli[0].' ';
		$sql.= 'WHERE fk_soc = '.$TDbl[0].' ';
		
		$PDOdb->Execute($sql);
		
		// Fusion des equipement
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'asset ';
		$sql.= 'SET fk_soc = '.$TCli[0].' ';
		$sql.= 'WHERE fk_soc = '.$TDbl[0].' ';
		
		$PDOdb->Execute($sql);
		
		// Fusion des scores
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_score ';
		$sql.= 'SET fk_soc = '.$TCli[0].' ';
		$sql.= 'WHERE fk_soc = '.$TDbl[0].' ';
		
		// Fusion des commerciaux
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_commerciaux ';
		$sql.= 'SET fk_soc = '.$TCli[0].' ';
		$sql.= 'WHERE fk_soc = '.$TDbl[0].' ';
		
		$PDOdb->Execute($sql);
		
		$dbl = new Societe($db);
		$dbl->id = $TDbl[0];
		$dbl->delete($dbl->id);
		
		echo '<br>';
		
		return 1;
	}
	
	return 0;
}
