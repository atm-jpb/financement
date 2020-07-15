#!/usr/bin/php
<?php
/**
 * Génération automatique d'extractions mise à disposition de l'équipe financement
 */

define('INC_FROM_CRON_SCRIPT', true);
$sapi_type = php_sapi_name();
$path = dirname(__FILE__).'/';
require_once $path.'../../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Test if apache or batch mode
if (substr($sapi_type, 0, 3) == 'apa') {
    $eol = '<br />';
    $file = GETPOST('file');
}
else {
    $eol = PHP_EOL;
    $file = isset($argv[1]) ? $argv[1] : null;
}

// Récupération des fichiers SQL du répertoire export
$sqlFileDir = dol_buildpath('/financement/sql/export');
$sqlFiles = dol_dir_list($sqlFileDir, 'files', 0, '.sql');

// Génération des exports et mise à dispo dans Documents / Rapports
$targetDir = DOL_DATA_ROOT.'/17/ecm/Rapports/';
dol_mkdir($targetDir, '', '0777');

foreach($sqlFiles as $requete) {
    if(! empty($file) && substr($requete['name'], 0, -4) !== $file) continue;

	echo '<hr>REQUETE '.$requete['name'].$eol;
    $a = microtime(true);

	$sql = file_get_contents($requete['fullname']);
	$fileName = str_replace( '.sql', '', $requete['name']);
	$fileName.= '.csv';

	// Suppression du fichier si déjà existant
	if(dol_is_file($targetDir.$fileName)) dol_delete_file($targetDir.$fileName);

	// Ajout de la partie écriture fichier dans la requête SQL
	$sqlOutfile = "INTO OUTFILE '".$targetDir.$fileName."'
  					FIELDS TERMINATED BY ';' OPTIONALLY ENCLOSED BY '\"'
  					LINES TERMINATED BY '\n'
  					FROM";
	$tmp = explode('from', $sql, 2);
	$sql = $tmp[0].$sqlOutfile.$tmp[1];

	$resql = $db->query($sql);
	$b = microtime(true);
	if($resql) {
		echo '<hr>'.$sql.$eol;
		echo '<hr> > '.$fileName.$eol;
	} else {
		echo '<hr>'.$sql.$eol;
		echo '<hr>Erreur SQL : '.$db->error().$eol;
	}
	echo $eol.'Execution time : '.($b-$a).' s'.$eol;

	$db->free($resql);
}
