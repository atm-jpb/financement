<?php
	/*
	 * script permettant de récupérer tous les dossiers liés aux simulations (id / référence)
	 * 
	 */	
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');
	dol_include_once('/financement/class/simulation.class.php');
	dol_include_once('/financement/class/score.class.php');
	dol_include_once('/financement/class/affaire.class.php');
	dol_include_once('/financement/class/grille.class.php');
	
	set_time_limit(0);
	
	global $db;
	
	$PDOdb = new TPDOdb;
	
	if($file = fopen($_REQUEST['filename'], 'r')){
		while ($line = fgetcsv($file,1000,';','"')) {
			//pre($line,true);exit;
			$financement = new TFin_financement;
			$financement->loadBy($PDOdb, $line[1], 'reference');

			if(!$financement->getId()) echo "Erreur -> financement ".$line[1]." n'existe pas.<br><hr>";
		}
	}
	
	//pre($TDossierAssoc,true);	
