#!/usr/bin/php
<?php

@set_time_limit(0);

$sapi_type = php_sapi_name();
$script_file = basename(__FILE__);
$path=dirname(__FILE__).'/';

define('INC_FROM_CRON_SCRIPT', true);
require_once($path."../../config.php");

dol_include_once('financement/class/simulation.class.php');
dol_include_once('financement/class/affaire.class.php');
dol_include_once('financement/class/score.class.php');
dol_include_once('financement/class/grille.class.php');
dol_include_once('financement/class/dossier.class.php');
dol_include_once('financement/class/dossier_integrale.class.php');
	
dol_include_once('/financement/class/webservice/webservice.class.php');
dol_include_once('/financement/class/webservice/webservice.grenke.class.php');
dol_include_once('/financement/class/webservice/webservice.bnp.class.php');

// Test if apache or batch mode
if (substr($sapi_type, 0, 3) == 'apa')
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

$PDOdb = new TPDOdb;

dol_syslog($script_file, LOG_DEBUG);
if ($type == 'bnp')
{
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
		$result = $ws->run();
		
		if ($result) print '--- run (suivi_id='.$res->rowid.') ok'.$eol.$eol;
		else print '--- run (suivi_id='.$res->rowid.') $ws->message_soap_returned='.$ws->message_soap_returned.$eol.$eol;
	}
}
else if ($type == 'grenke')
{
	$sql = "SELECT suivi.rowid, suivi.leaseRequestID 
			FROM ".MAIN_DB_PREFIX."fin_simulation_suivi suivi
			LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (suivi.fk_leaser = s.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields sext ON (s.rowid = sext.fk_object)
			WHERE sext.edi_leaser = 'GRENKE'
			AND suivi.statut_demande = 1
			AND suivi.statut = 'WAIT'
			AND suivi.leaseRequestID IS NOT NULL
			AND suivi.leaseRequestID <> ''
			AND suivi.date_demande > '".date('Y-m-d', strtotime('-20 days'))."'";
	
	print 'sql='.$sql.$eol.$eol;
	$TRes = $PDOdb->ExecuteAsArray($sql);
	
	foreach ($TRes as $res)
	{
		$TSimulationSuivi = new TSimulationSuivi;
		$TSimulationSuivi->load($PDOdb, $res->rowid);
		
		$ws = new WebServiceGrenke($TSimulationSuivi->simulation, $TSimulationSuivi, false, true);
		$result = $ws->run();
		
		if ($result) print '--- run (suivi_id='.$res->rowid.') ok'.$eol.$eol;
		else print '--- run (suivi_id='.$res->rowid.') $ws->message_soap_returned='.$ws->message_soap_returned.$eol.$eol;
	}
}


// -------------------- END OF YOUR CODE --------------------

print '--- End'.$eol;
$db->close();	// Close $db database opened handler

exit;
