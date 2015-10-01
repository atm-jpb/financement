<?php
set_time_limit(0);
ini_set('memory_limit','1024M');

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


if(isset($_REQUEST['conf']) || isset($_REQUEST['all'])) {
	
	// Copie des conf de la page "options globales"
	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'FINANCEMENT_%' AND entity = 1";
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) {
		foreach ($TEntities as $id_entity) {
			$db->query("REPLACE INTO ".MAIN_DB_PREFIX."const(name, entity, value, type, visible, note, tms) VALUES ('".$res->name."', ".$id_entity.", '".$res->value."', '".$res->type."', '".$res->visible."', '".$res->note."', '".date('Y-m-d H:i:s')."')");
			echo 'constante : '.$res->name.' ajoutee/modifiee sur entity '.$id_entity.'<br>';
		}
	}
}



/***************************"Grille de coefficients"********************************/
if(isset($_REQUEST['coef']) || isset($_REQUEST['all'])) {
	// Passage des conf de la page "Grille de coefficients" de l'entité 0 vers l'entité 1
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_grille_leaser SET entity = 1 WHERE entity = 0';
	$db->query($sql);
	
	// Copie des conf de la page "Grille de coefficients" vers les nouvelles entités
	$sql = 'SELECT MAX(rowid) as max_rowid FROM '.MAIN_DB_PREFIX.'fin_grille_leaser';
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	$max_rowid = $res->max_rowid + 1;
	
	$sql = 'SELECT fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type FROM '.MAIN_DB_PREFIX.'fin_grille_leaser WHERE entity = 1';
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) {
		foreach ($TEntities as $id_entity) {
			// En sql et non par objet parce que sinon la fonction save de l'objet std va remettre le champ entity à getEntity()
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_grille_leaser(rowid, fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, tms, type, date_cre, date_maj, entity)
					VALUES ('.$max_rowid.', '.$res->fk_soc.', "'.$res->fk_type_contrat.'", '.$res->montant.', '.$res->periode.', '.$res->coeff.', '.$res->fk_user.', "'.date('Y-m-d H:i:s').'", "'.$res->type.'", "'.date('Y-m-d H:i:s').'", "'.date('Y-m-d H:i:s').'", '.$id_entity.')';
					
			$db->query($sql);
			$max_rowid++;
		}
	}
	echo '<br>Configurations de la table '.MAIN_DB_PREFIX.'fin_grille_leaser copiees dans les nouvelles entites';
}
/***************************"Grille de coefficients"********************************/



/***************************"Grille suivi"********************************/
if(isset($_REQUEST['suivi']) || isset($_REQUEST['all'])) {
	// Passage des conf de la table "Grille suivi" de l'entité 0 vers l'entité 1
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_grille_suivi SET entity = 1 WHERE entity = 0';
	$db->query($sql);
	
	// Copie des conf de la page "Grille de coefficients" vers les nouvelles entités
	$sql = 'SELECT MAX(rowid) as max_rowid FROM '.MAIN_DB_PREFIX.'fin_grille_suivi';
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	$max_rowid = $res->max_rowid + 1;
	
	//$sql = 'SELECT fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type FROM '.MAIN_DB_PREFIX.'fin_grille_leaser WHERE entity = 1';
	$sql = 'SELECT fk_type_contrat, fk_leaser_solde, fk_leaser_entreprise, fk_leaser_administration, fk_leaser_association, montantbase, montantfin FROM '.MAIN_DB_PREFIX.'fin_grille_suivi WHERE entity = 1';
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) {
		foreach ($TEntities as $id_entity) {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_grille_suivi(rowid, date_cre, date_maj, fk_type_contrat, fk_leaser_solde, fk_leaser_entreprise, fk_leaser_administration, fk_leaser_association, montantbase, montantfin, entity)
					VALUES ('.$max_rowid.', "'.date('Y-m-d H:i:s').'", "'.date('Y-m-d H:i:s').'", "'.$res->fk_type_contrat.'", '.$res->fk_leaser_solde.', '.$res->fk_leaser_entreprise.', '.$res->fk_leaser_administration.', '.$res->fk_leaser_association.', '.$res->montantbase.', '.$res->montantfin.', '.$id_entity.')';
			$db->query($sql);
			$max_rowid++;
		}
	}
	echo '<br>Configurations de la table '.MAIN_DB_PREFIX.'fin_grille_suivi copiees dans les nouvelles entites';
}
/***************************"Grille suivi"********************************/


/***************************"Grille pénalités"****************************/
if(isset($_REQUEST['penalite']) || isset($_REQUEST['all'])) {
	// Pénalités :
	$db->query('ALTER TABLE '.MAIN_DB_PREFIX.'fin_grille_penalite ADD COLUMN entity int');
	$db->query('UPDATE '.MAIN_DB_PREFIX.'fin_grille_penalite SET entity = 1 WHERE entity IS NULL');
	$sql = 'SELECT opt_name, opt_value, penalite FROM '.MAIN_DB_PREFIX.'fin_grille_penalite WHERE entity = 1';
	$resql = $db->query($sql);
	while($res = $db->fetch_object($resql)) {
		foreach ($TEntities as $id_entity) {
			$db->query('REPLACE INTO '.MAIN_DB_PREFIX.'fin_grille_penalite(opt_name, opt_value, penalite, entity) VALUES("'.$res->opt_name.'", "'.$res->opt_value.'", '.$res->penalite.', '.$id_entity.')');
		}
	}
	echo '<br>Configurations de la table '.MAIN_DB_PREFIX.'fin_grille_penalite copiees dans les nouvelles entites';
}
/***************************"Grille pénalités"****************************/
