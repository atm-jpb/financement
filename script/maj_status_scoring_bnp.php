<?php

	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	dol_include_once('financement/class/simulation.class.php');
	
	$PDOdb = new TPDOdb;
	
	$TSimulationSuivi = new TSimulationSuivi;
	
	$sql = "SELECT rowid, numero_accord_leaser 
				FROM ".MAIN_DB_PREFIX."fin_simulation_suivi 
				WHERE (fk_leaser = 3382 OR fk_leaser = 19553 OR fk_leaser = 20113)
					AND numero_accord_leaser IS NOT NULL ";
	//echo $sql;exit;
	$TRes = $PDOdb->ExecuteAsArray($sql);
	
	foreach($TRes as $res){
		$TSimulationSuivi->load($PDOdb, $res->rowid);
		$TreponseDemandes  = $TSimulationSuivi->_consulterDemandeBNP($res->numero_accord_leaser);
	
		pre($TreponseDemandes,true);
	}
	
	
