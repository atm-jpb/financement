<?php

	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/simulation.class.php');
	
	$PDOdb = new TPDOdb;
	
	$TSimulationSuivi = new TSimulationSuivi;
	
	$sql = "SELECT numero_accord_leaser 
				FROM ".MAIN_DB_PREFIX."fin_simulation_suivi 
				WHERE (fk_leaser = 3382 OR fk_leaser = 19553 OR fk_leaser = 20113)
					AND numero_accord_leaser != '' AND numero_accord_leaser IS NOT NULL
					AND  statut = 'WAIT' ";
	//echo $sql;exit;
	$PDOdb->Execute($sql);
	
	while($PDOdb->Get_line()){
		$TreponseDemandes  = $TSimulationSuivi->_consulterDemandeBNP($PDOdb->Get_field('numero_accord_leaser'));
	
		pre($TreponseDemandes,true);
	}
	
	
