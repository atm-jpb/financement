#!/usr/bin/php
<?php

@set_time_limit(0);

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

define('INC_FROM_CRON_SCRIPT', true);
require_once($path."../config.php");

dol_include_once('financement/class/simulation.class.php');
dol_include_once('financement/class/affaire.class.php');
dol_include_once('financement/class/score.class.php');
dol_include_once('financement/class/grille.class.php');
dol_include_once('financement/class/dossier.class.php');
dol_include_once('financement/class/dossier_integrale.class.php');
	
dol_include_once('/financement/class/webservice/webservice.class.php');
dol_include_once('/financement/class/webservice/webservice.grenke.class.php');
dol_include_once('/financement/class/webservice/webservice.bnp.class.php');

// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi')
{
	$eol = '<br />';
    $type = GETPOST('type');
}
else
{
	$eol = PHP_EOL;
	$type = $argv[1];
}



// Global variables
$version='1.0';
$error=0;


// -------------------- START OF YOUR CODE HERE --------------------

// Load user and its permissions
$result=$user->fetch('','admin');	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


print "***** ".$script_file." (".$version.") pid=".dol_getmypid()." *****".$eol;
if (empty($type)) {	// Check parameters
    print "Usage: ".$script_file." [type] ...".$eol;
    print "[type] valeur d'exemple : grenke, bnp ...".$eol;
	exit(-1);
}
print '--- start'.$eol;
print 'Type='.$type.$eol.$eol;


if ($type == 'bnp')
{
	$PDOdb = new TPDOdb;
	
	$TSimulationSuivi = new TSimulationSuivi;
	
	$sql = "SELECT suivi.rowid, suivi.numero_accord_leaser 
			FROM ".MAIN_DB_PREFIX."fin_simulation_suivi suivi
			LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (suivi.fk_leaser = s.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields sext ON (s.rowid = sext.fk_object)
			WHERE sext.edi_leaser = 'BNP'
				AND suivi.numero_accord_leaser IS NOT NULL 
				AND suivi.numero_accord_leaser != ''
				AND suivi.statut_demande = 1
				AND (suivi.statut = 'WAIT' OR suivi.numero_accord_leaser LIKE '000%')
				AND suivi.date_demande > '".date('Y-m-d', strtotime('-20 days'))."'";
	
	print 'sql='.$sql.$eol.$eol;
	$TRes = $PDOdb->ExecuteAsArray($sql);
	
	$simulation = new TSimulation;
	foreach($TRes as $res)
	{
		$TSimulationSuivi->load($PDOdb, $res->rowid);
		$ws = new WebServiceBnp($simulation, $TSimulationSuivi, false, true);
		$ws->run();
	}
}
else if ($type == 'grenke')
{
	$TRes = array();
	
	foreach ($TRes as $res)
	{
		$TSimulationSuivi = new TSimulationSuivi;
		$TSimulationSuivi->load($PDOdb, $res->rowid);
		
		$ws = new WebServiceGrenke($TSimulationSuivi->simulation, $TSimulationSuivi, false, true);
		$ws->run();
	}
}
// Examples for manipulating class skeleton_class
$myobject=new Skeleton_Class($db);

// Example for inserting creating object in database
/*
dol_syslog($script_file." CREATE", LOG_DEBUG);
$myobject->prop1='value_prop1';
$myobject->prop2='value_prop2';
$id=$myobject->create($user);
if ($id < 0) { $error++; dol_print_error($db,$myobject->error); }
else print "Object created with id=".$id."$eol";
*/

// Example for reading object from database
/*
dol_syslog($script_file." FETCH", LOG_DEBUG);
$result=$myobject->fetch($id);
if ($result < 0) { $error; dol_print_error($db,$myobject->error); }
else print "Object with id=".$id." loaded$eol";
*/

// Example for updating object in database ($myobject must have been loaded by a fetch before)
/*
dol_syslog($script_file." UPDATE", LOG_DEBUG);
$myobject->prop1='newvalue_prop1';
$myobject->prop2='newvalue_prop2';
$result=$myobject->update($user);
if ($result < 0) { $error++; dol_print_error($db,$myobject->error); }
else print "Object with id ".$myobject->id." updated$eol";
*/

// Example for deleting object in database ($myobject must have been loaded by a fetch before)
/*
dol_syslog($script_file." DELETE", LOG_DEBUG);
$result=$myobject->delete($user);
if ($result < 0) { $error++; dol_print_error($db,$myobject->error); }
else print "Object with id ".$myobject->id." deleted$eol";
*/


// An example of a direct SQL read without using the fetch method
/*
$sql = "SELECT field1, field2";
$sql.= " FROM ".MAIN_DB_PREFIX."skeleton";
$sql.= " WHERE field3 = 'xxx'";
$sql.= " ORDER BY field1 ASC";

dol_syslog($script_file, LOG_DEBUG);
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
	print '--- end ok'.$eol;
}
else
{
	print '--- end error code='.$error.$eol;
}

$db->close();	// Close $db database opened handler

exit($error);
