<?php
require('../../config.php');
dol_include_once('/compta/facture/class/facture.class.php');

@set_time_limit(0);					// No timeout for this script

$TDoubleFac = array();

// On drop la view precedente si elle existe
$PDOdb = new TPDOdb;
$sql = "DROP VIEW facture_view_5286"; 
$PDOdb->Execute($sql);

// On recréé la view
$sql = "CREATE VIEW facture_view_5286 AS SELECT * from llx_facture GROUP BY facnumber HAVING COUNT(facnumber) > 1";
$PDOdb->Execute($sql);

$nbFact = 0;
// On ressort toutes les factures en doublon
$sql = "SELECT * FROM facture_view_5286";
$res = $PDOdb->Execute($sql);
while($object = $res->fetch(PDO::FETCH_OBJ)){
	$nbFact++;
	$TDoubleFac[] = $object;
}

if(!empty($TDoubleFac)) {
	foreach($TDoubleFac as $goodFacture) {
		$sql = "SELECT rowid,entity from llx_facture WHERE facnumber = '".$goodFacture->facnumber."' AND entity <> ".$goodFacture->entity;
		delete_doublons_from_sql($sql,$PDOdb);
	}
}

echo '****** '.$nbFact.' factures ****** en doublon supprimées ******';


// Fonction qui recupere le sql des doublons et supprimes toutes les factures qu'elle trouve
function delete_doublons_from_sql($sql = null, $PDOdb){
	global $db;
	
	$res = $PDOdb->Execute($sql);
	while($badFacture = $res->fetch(PDO::FETCH_OBJ)){
		$facture = new Facture($db);
		$facture->fetch($badFacture->rowid);
		$facture->delete();
		echo 'Invoice : '.$badFacture->rowid.' deleted<br/>';
	}
}
