<?php
 
require('../config.php');

// On récupère les id's des nouvelles entités
$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'entity WHERE rowid > 1';
$resql = $db->query($sql);
$TEntities = array();
while($res = $db->fetch_object($resql)) {
	$TEntities[] = $res->rowid;
}


// Si elles n'ont pas encore été créées, on ne fait rien
if(empty($TEntities)) {
	echo 'aucune entite autre que la principale trouvee';
	exit;
}


// Copie des conf de la page "options globales"
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'FINANCEMENT_%' AND entity = 1";
$resql = $db->query($sql);
while($res = $db->fetch_object($resql)) {
	foreach ($TEntities as $id_entity) {
		$db->query("REPLACE INTO ".MAIN_DB_PREFIX."const(name, entity, value, type, visible, note, tms) VALUES ('".$res->name."', ".$id_entity.", '".$res->value."', '".$res->type."', '".$res->visible."', '".$res->note."', '".date('Y-m-d H:i:s')."')");
		echo 'constante : '.$res->name.' ajoutee/modifiee sur entity '.$id_entity.'<br>';
	}
}


// Passage des conf de la page "Grille de coefficients" de l'entité 0 vers l'entité 1
$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_grille_leaser SET entity = 1 WHERE entity = 0';
$db->query($sql);
echo '<br>Configurations de la table '.MAIN_DB_PREFIX.'fin_grille_leaser passees dans l\'entite 1';

// Copie des conf de la page "Grille de coefficients"
$sql = 'SELECT fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type WHERE entity = 1';
