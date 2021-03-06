<?php
/* Copyright (C) 2012	  Maxime Kohlhaas		<maxime.kohlhaas@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *      \file       script/import.script.php
 *		\ingroup    financement
 *      \brief      This file is an example for a command line script
 *					Initialy built by build_class_from_table on 2012-12-20 12:18
 */

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
    echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
    exit;
}

// Global variables
$eol = ($sapi_type == 'cli' ? "\n" : "<br />");
$version='1';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------
// Include Dolibarr environment
define('INC_FROM_CRON_SCRIPT', true);
require_once($path."../config.php");

// After this $db, $mysoc, $langs and $conf->entity are defined. Opened handler to database will be closed at end of file.

$langs->setDefaultLang('fr_FR'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
$langs->load("financement@financement");
@set_time_limit(0);					// No timeout for this script
ini_set('display_errors', true);
ini_set('memory_limit','1024M');

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();

print "***** ".$script_file." (".$version.") *****".$eol;
print '--- start'.$eol;
/*print 'Argument 1='.$argv[1].$eol;
print 'Argument 2='.$argv[2].$eol;*/

// Start of transaction
$db->begin();

// Inclusion des classes utiles pour les imports
dol_include_once("/financement/class/import.class.php");
dol_include_once("/financement/class/import_error.class.php");
dol_include_once("/financement/class/commerciaux.class.php");
dol_include_once("/financement/class/affaire.class.php");
dol_include_once("/financement/class/dossier.class.php");
dol_include_once("/financement/class/dossier_integrale.class.php");
dol_include_once("/financement/class/grille.class.php");
dol_include_once("/financement/class/score.class.php");
dol_include_once("/financement/lib/financement.lib.php");
dol_include_once("/asset/class/asset.class.php");
dol_include_once("/societe/class/societe.class.php");
dol_include_once("/compta/facture/class/facture.class.php");
dol_include_once("/product/class/product.class.php");
dol_include_once("/core/class/html.form.class.php");
dol_include_once("/categories/class/categorie.class.php");

switchEntity(17); // Bascule sur l'entité TEAM ADMIN pour avoir la vue globale de l'import

$artis = $argv[1];
$folder = '';
if($artis == 'ouest') $folder = 'ouest/';

$ATMdb = new TPDOdb();
$imp=new TImport();
$imp->entity = $conf->entity;
$imp->fk_user_author = $user->id;
$imp->artis = $artis;

$listOfFileType = $imp->TType_import_interne;
$importFolder = FIN_IMPORT_FOLDER.'todo/'.$folder;
$importFolderOK = FIN_IMPORT_FOLDER.'done/'.$folder;
$importFolderMapping = FIN_IMPORT_FOLDER.'mappings/';

// STEP 1 : récupération des fichiers source
$imp->getFiles($importFolder);

// STEP 2 : import des fichiers
foreach ($listOfFileType as $fileType => $libelle) { // Pour chaque type de fichier
	$mappingFile = $fileType.'.mapping';
	$imp->getMapping($importFolderMapping.$mappingFile); // Récupération du mapping

	$filePrefix = 'fin_'.$fileType;
	$filesToImport = $imp->getListOfFiles($importFolder, $filePrefix); // Récupération des fichiers à importer (dossier todo)
	print date('Y-m-d H:i:s').' : Récupération fichiers "'.$filePrefix.'", '.count($filesToImport).' fichier(s) trouvé(s)'.$eol;

	foreach($filesToImport as $fileName) { // Pour chaque fichier à importer
		switchEntity(17); // Bascule sur l'entité TEAM ADMIN pour avoir la vue globale de l'import
		$imp->start();
		$imp->init($fileName, $fileType);
		$imp->save($ATMdb); // Création de l'import

		$imp->importFile($ATMdb, $importFolder.$fileName);

		$imp->save($ATMdb); // Mise à jour pour nombre de lignes et nombre d'erreurs

		print date('Y-m-d H:i:s').' : Fichier "'.$fileName.'" traité, '.$imp->nb_lines.' ligne(s)'.$eol;

		rename($importFolder.$fileName, $importFolderOK.$fileName);
	}
}

$ATMdb->close();

print '--- end'.$eol;

return $error;
?>
