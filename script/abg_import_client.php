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

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


print "***** ".$script_file." (".$version.") *****".$eol;
print '--- start'.$eol;
/*print 'Argument 1='.$argv[1].$eol;
print 'Argument 2='.$argv[2].$eol;*/

// Start of transaction
global $db;

// Inclusion des classes utiles pour les imports
dol_include_once("/societe/class/societe.class.php");
		
$ATMdb = new TPDOdb;
$nb_lines = 0;

$fileName = dol_buildpath('/financement/import/todo/abg_import_client.csv',2);
$fileHandler = fopen($fileName, 'r');

// 1ère ligne
fgetcsv($fileHandler, 1024, FIN_IMPORT_FIELD_DELIMITER, FIN_IMPORT_FIELD_ENCLOSURE);
while($dataline = fgetcsv($fileHandler, 1024, FIN_IMPORT_FIELD_DELIMITER, FIN_IMPORT_FIELD_ENCLOSURE)) {
	
	$code_client1 = 'ABG'.str_pad($dataline[7], 6,'0',STR_PAD_LEFT);
	$code_client2 = 'ABG'.str_pad($dataline[8], 6,'0',STR_PAD_LEFT);
	$code_client3 = 'ABG'.str_pad($dataline[9], 6,'0',STR_PAD_LEFT);
	
	echo $code_client1." / ".$dataline[0].'<br>';
	
	if(!empty($dataline[7])) createOrUpdateThird($ATMdb, $code_client1, $dataline);
	if(!empty($dataline[8])) createOrUpdateThird($ATMdb, $code_client2, $dataline);
	if(!empty($dataline[9])) createOrUpdateThird($ATMdb, $code_client3, $dataline);
	
	$nb_lines++;
}
fclose($fileHandler);

print date('Y-m-d H:i:s').' : Fichier "'.$fileName.'" traité, '.$nb_lines.' ligne(s)'.$eol;

$ATMdb->close();

print '--- end'.$eol;

function createOrUpdateThird(&$ATMdb, $code_client, $dataline) {
	global $db;
	
	$TIdSociete = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'societe',array('code_client'=>$code_client));
	
	$siren = trim($dataline[0]);
	$nom = trim($dataline[1]);
	$cp = trim($dataline[4]);
	$ville = trim($dataline[5]);
	$pays = 1;
	
	if(!empty($TIdSociete)) {
		foreach($TIdSociete as $rowid){
			$societe = new Societe($db);
			$societe->fetch($rowid);
			$societe->name = $nom;
			$societe->zip = $cp;
			$societe->town = $ville;
			$societe->country_id = $pays;
			$societe->idprof1 = $siren;
			$societe->array_options['options_other_siren'] = '';
			$societe->update($rowid);
		}
	} else {
		$societe = new Societe($db);
		$societe->entity = 5;
		$societe->name = $nom;
		$societe->zip = $cp;
		$societe->town = $ville;
		$societe->country_id = $pays;
		$societe->idprof1 = $siren;
		$societe->client = 1;  
		$societe->create();
	}
}
?>