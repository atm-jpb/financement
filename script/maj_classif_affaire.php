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
	
	if($file = fopen('maj_classif.csv', 'r')){
		while ($line = fgetcsv($file,1000,';','"')) {
			//pre($line,true);exit;
			$financement = new TFin_financement;
			$financement->loadBy($PDOdb, $line[0], 'reference');
			
			if($financement->getId()){
				$PDOdb->Execute('UPDATE '.MAIN_DB_PREFIX.'fin_affaire
									SET nature_financement = "INTERNE", contrat = '.(($line[3]) ? '"INTEGRAL"' : 'contrat').'
								 WHERE rowid IN (
								 	SELECT fk_fin_affaire
								 	FROM '.MAIN_DB_PREFIX.'fin_dossier_affaire as da
								 		LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier as d ON (da.fk_fin_dossier = d.rowid)
										LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement as df ON (df.fk_fin_dossier = d.rowid)
								 	WHERE df.type = "CLIENT" AND df.reference = "'.$line[0].'"
									)');
								 
				echo "MAJ classification contenant le dossier ".$line[0]."<br>";
				echo '<hr>';
				flush();
			}
		}
	}
	
	//pre($TDossierAssoc,true);	
