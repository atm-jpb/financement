<?php
 
require('../config.php');
dol_include_once('/financement/class/grille.class.php');


$ATMdb = new TPDOdb;


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


/***************************"Grille de coefficients"********************************/
// Passage des conf de la page "Grille de coefficients" de l'entité 0 vers l'entité 1
$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_grille_leaser SET entity = 1 WHERE entity = 0';
$db->query($sql);
echo '<br>Configurations de la table '.MAIN_DB_PREFIX.'fin_grille_leaser passees dans l\'entite 1';

// Copie des conf de la page "Grille de coefficients" vers les nouvelles entités
$sql = 'SELECT MAX(rowid) as max_rowid FROM '.MAIN_DB_PREFIX.'fin_grille_leaser';
$resql = $db->query($sql);
$res = $db->fetch_object($resql);
$max_rowid = $res->max_rowid + 1;

//$sql = 'SELECT fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type FROM '.MAIN_DB_PREFIX.'fin_grille_leaser WHERE entity = 1';
$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'fin_grille_leaser WHERE entity = 1';
$resql = $db->query($sql);
while($res = $db->fetch_object($resql)) {
	$grille = new TFin_grille_leaser;
	$grille->load($ATMdb, $res->rowid);
	foreach ($TEntities as $id_entity) {
		if($grille->rowid > 0) {
			$grille->rowid = null;
			$grille->date_cre = strtotime(date('Y-m-d H:i:s'));
			$grille->date_maj = null;
			$grille->entity = $id_entity;
			$grille->save($ATMdb);
		}
	}
}
/***************************"Grille de coefficients"********************************/



/***************************"Grille suivi"********************************/
// Passage des conf de la table "Grille suivi" de l'entité 0 vers l'entité 1
$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_grille_suivi SET entity = 1 WHERE entity = 0';
$db->query($sql);
echo '<br>Configurations de la table '.MAIN_DB_PREFIX.'fin_grille_suivi passees dans l\'entite 1';

// Copie des conf de la page "Grille de coefficients" vers les nouvelles entités
$sql = 'SELECT MAX(rowid) as max_rowid FROM '.MAIN_DB_PREFIX.'fin_grille_suivi';
$resql = $db->query($sql);
$res = $db->fetch_object($resql);
$max_rowid = $res->max_rowid + 1;

//$sql = 'SELECT fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type FROM '.MAIN_DB_PREFIX.'fin_grille_leaser WHERE entity = 1';
$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'fin_grille_suivi WHERE entity = 1';
$resql = $db->query($sql);
while($res = $db->fetch_object($resql)) {
	$grille = new TFin_grille_suivi;
	$grille->load($ATMdb, $res->rowid);
	foreach ($TEntities as $id_entity) {
		if($grille->rowid > 0) {
			$grille->rowid = null;
			$grille->date_cre = strtotime(date('Y-m-d H:i:s'));
			$grille->date_maj = null;
			$grille->entity = $id_entity;
			$grille->save($ATMdb);
		}
	}
}
/***************************"Grille suivi"********************************/

