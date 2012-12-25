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
require_once($path."../../../../htdocs/master.inc.php");
// After this $db, $mysoc, $langs and $conf->entity are defined. Opened handler to database will be closed at end of file.

//$langs->setDefaultLang('en_US'); 	// To change default language of $langs
$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


print "***** ".$script_file." (".$version.") *****".$eol;
print '--- start'.$eol;
/*print 'Argument 1='.$argv[1].$eol;
print 'Argument 2='.$argv[2].$eol;*/

// Start of transaction
$db->begin();

// Examples for manipulating class skeleton_class
dol_include_once("/financement/class/import.class.php");
dol_include_once("/financement/class/import_error.class.php");
$imp=new Import($db);
$imp->entity = $conf->entity;
$imp->fk_user_author = $user->id;

$delimiter = ';'; $enclosure = '"';
$listOfFileType = array('client');
$importFolder = '../import/todo/';
$importFolderOK = '../import/done/';
$importFolderMapping = '../import/mappings/';

// STEP 1 : récupération des fichiers source
$imp->getFiles($importFolder);

// STEP 2 : import des fichiers
foreach ($listOfFileType as $fileType) { // Pour chaque type de fichier
	$filePrefix = 'fin_'.$fileType;
	$importScriptFile = 'import_'.$fileType.'.script.php';
	$mappingFile = $fileType.'.mapping';
	$mapping = $imp->getMapping($importFolderMapping.$mappingFile); // Récupération du mapping
	
	$filesToImport = $imp->getListOfFiles($importFolder, $filePrefix); // Récupération des fichiers à importer (dossier todo)
	
	print date('Y-m-d H:i:s').' : Récupération fichiers "'.$filePrefix.'", '.count($filesToImport).' fichier(s) trouvé(s)'.$eol;

	foreach($filesToImport as $fileName) { // Pour chaque fichier à importer
		$imp->filename = $fileName;
		$imp->type_import = $fileType;
		$imp->nb_lines = 0;
		$imp->nb_errors = 0;
		$imp->date = time();
		$imp->create($user); // Création de l'import

		$fileHandler = fopen($importFolder.$fileName, 'r');
		include $importScriptFile;
		
		$imp->update($user); // Mise à jour pour nombre de lignes et nombre d'erreurs
		
		//rename($importFolder.$fileName, $importFolderOK.$fileName);
		
		echo '<pre>';
		print_r($imp);
		echo '</pre>';
	}
}

// Example for inserting creating object in database
/*
dol_syslog($script_file." CREATE", LOG_DEBUG);
$imp->prop1='value_prop1';
$imp->prop2='value_prop2';
$id=$imp->create($user);
if ($id < 0) { $error++; dol_print_error($db,$imp->error); }
else print "Object created with id=".$id.$eol;
*/

// Example for reading object from database
/*
dol_syslog($script_file." FETCH", LOG_DEBUG);
$result=$imp->fetch($id);
if ($result < 0) { $error; dol_print_error($db,$imp->error); }
else print "Object with id=".$id." loaded\n";
*/

// Example for updating object in database ($imp must have been loaded by a fetch before)
/*
dol_syslog($script_file." UPDATE", LOG_DEBUG);
$imp->prop1='newvalue_prop1';
$imp->prop2='newvalue_prop2';
$result=$imp->update($user);
if ($result < 0) { $error++; dol_print_error($db,$imp->error); }
else print "Object with id ".$imp->id." updated\n";
*/

// Example for deleting object in database ($imp must have been loaded by a fetch before)
/*
dol_syslog($script_file." DELETE", LOG_DEBUG);
$result=$imp->delete($user);
if ($result < 0) { $error++; dol_print_error($db,$imp->error); }
else print "Object with id ".$imp->id." deleted\n";
*/


// An example of a direct SQL read without using the fetch method
/*
$sql = "SELECT field1, field2";
$sql.= " FROM ".MAIN_DB_PREFIX."c_pays";
$sql.= " WHERE field3 = 'xxx'";
$sql.= " ORDER BY field1 ASC";

dol_syslog($script_file." sql=".$sql, LOG_DEBUG);
$resql=$db->query($sql);
if ($resql)
{
	$num = $db->num_rows($resql);
	$i = 0;
	if ($num)
	{
		while ($i < $num)
		{
			$obj = $db->fetch_object($resql);
			if ($obj)
			{
				// You can use here results
				print $obj->field1;
				print $obj->field2;
			}
			$i++;
		}
	}
}
else
{
	$error++;
	dol_print_error($db);
}
*/


// -------------------- END OF YOUR CODE --------------------

if (! $error)
{
	$db->commit();
	print '--- end ok'.$eol;
}
else
{
	print '--- end error code='.$error.$eol;
	$db->rollback();
}

$db->close();	// Close database opened handler

return $error;
?>
