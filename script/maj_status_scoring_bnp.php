<?php

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
	dol_include_once('/financement/class/webservice/webservice.bnp.class.php');
	
	global $db;
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
	echo $sql.'<br>';
	$TRes = $PDOdb->ExecuteAsArray($sql);
	
	$simulation = new TSimulation;
	foreach($TRes as $res){
		
		$TSimulationSuivi->load($PDOdb, $res->rowid);
		$ws = new WebServiceBnp($simulation, $TSimulationSuivi, false, true);
		$ws->run();
//		$TreponseDemandes  = $TSimulationSuivi->_consulterDemandeBNP($res->numero_accord_leaser);
//	
//		pre($TreponseDemandes,true);
	}
	
	
