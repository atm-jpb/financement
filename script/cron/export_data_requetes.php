#!/usr/bin/php
<?php
/**
 * Génération automatique d'extractions mise à disposition de l'équipe financement
 */

define('INC_FROM_CRON_SCRIPT', true);
$path=dirname(__FILE__).'/';
require_once($path.'../../config.php');
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Récupération des fichiers SQL du répertoire export
$sqlFileDir = dol_buildpath('/financement/sql/export');
$sqlFiles = dol_dir_list($sqlFileDir, 'files', 0, '.sql');

// Génération des exports et mise à dispo dans Documents / Rapports
$targetDir = DOL_DATA_ROOT.'/17/ecm/Rapports/';
dol_mkdir($targetDir, '', '0777');

foreach($sqlFiles as $requete) {
	echo '<hr>REQUETE '.$requete['name'];
	$sql = file_get_contents($requete['fullname']);
	$fileName = str_replace( '.sql', '', $requete['name']);
	$fileName.= '_'.date('Ymd').'.csv';

	// Suppression du fichier si déjà existant
	if(dol_is_file($targetDir.$fileName)) dol_delete_file($targetDir.$fileName);

	// Ajout de la partie écriture fichier dans la requête SQL
	$sqlOutfile = "INTO OUTFILE '".$targetDir.$fileName."'
  					FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
  					LINES TERMINATED BY '\n'
  					FROM";
	$tmp = explode('from', $sql, 2);
	$sql = $tmp[0].$sqlOutfile.$tmp[1];

	$resql = $db->query($sql);
	if($resql) {
		echo '<hr>'.$sql;
		echo '<hr> > '.$fileName;
	} else {
		echo '<hr>'.$sql;
		echo '<hr>Erreur SQL : '.$db->error();
	}
}