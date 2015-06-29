<?php

	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/simulation.class.php');
	
	$TSimulationSuivi = new TSimulationSuivi;
	pre($TSimulationSuivi->_consulterDemandeBNP());
