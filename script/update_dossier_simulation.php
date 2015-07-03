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
	
	if($file = fopen('dossiers_simulation.csv', 'r')){
		while ($line = fgetcsv($file,1000,';','"')) {
			//pre($line,true);exit;
			$financement = new TFin_financement;
			$financement->loadBy($PDOdb, $line[1], 'reference');
			
			if($financement->getId() != $line[0] && !empty($line[0]) && !empty($financement->fk_fin_dossier)){
				$PDOdb->Execute('UPDATE '.MAIN_DB_PREFIX.'fin_simulation
									SET dossiers_rachetes = REPLACE(dossiers_rachetes,"'.$line[0].'","'.$financement->fk_fin_dossier.'")
									, dossiers_rachetes_p1 = REPLACE(dossiers_rachetes_p1,"'.$line[0].'","'.$financement->fk_fin_dossier.'")
									, dossiers_rachetes_nr = REPLACE(dossiers_rachetes_nr,"'.$line[0].'","'.$financement->fk_fin_dossier.'")
									, dossiers_rachetes_nr_p1 = REPLACE(dossiers_rachetes_nr_p1,"'.$line[0].'","'.$financement->fk_fin_dossier.'")
								 WHERE INSTR("'.$line[0].'",dossiers_rachetes)
								 	OR INSTR("'.$line[0].'",dossiers_rachetes_p1)
									OR INSTR("'.$line[0].'",dossiers_rachetes_nr)
									OR INSTR("'.$line[0].'",dossiers_rachetes_nr_p1)');
				echo "MAJ simulations contenant le dossier ".$line[0]." => ".$financement->fk_fin_dossier."<br>";
				echo '<hr>';
				flush();
			}
		}
	}
	
	//pre($TDossierAssoc,true);	
