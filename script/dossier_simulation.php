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
	
	$TidSimulations = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."fin_simulation");
	
	foreach($TidSimulations as $idSimulation){
		$simulation = new TSimulation;
		$simulation->load($PDOdb, $db, $idSimulation);
		
		//pre($simulation->dossiers_rachetes,true);//exit;
		
		if(!empty($simulation->dossiers_rachetes)){
			foreach($simulation->dossiers_rachetes as $idDossier => $Tdata){
				
				$PDOdb->Execute('SELECT reference FROM '.MAIN_DB_PREFIX."fin_dossier_financement WHERE fk_fin_dossier = ".$idDossier." AND type = 'LEASER'");
				$TRes = $PDOdb->Get_All();
				//pre($TRes,true);exit;
				if(count($TRes) > 1){
					echo " ***** ATTENTION : dossier ".$TRes->reference." en doublon dans la BDD ***** "; continue;
				}
				elseif(count($TRes) > 0){
					//pre($TRes,true);exit;
					$TDossierAssoc[$idDossier] = $TRes[0]->reference;
				}
			}
		}
		if(!empty($simulation->dossiers_rachetes_nr)){
			foreach($simulation->dossiers_rachetes_nr as $idDossier => $Tdata){
				
				$PDOdb->Execute('SELECT reference FROM '.MAIN_DB_PREFIX."fin_dossier_financement WHERE fk_fin_dossier = ".$idDossier." AND type = 'LEASER'");
				$TRes = $PDOdb->Get_All();
				//pre($TRes,true);exit;
				if(count($TRes) > 1){
					echo " ***** ATTENTION : dossier ".$TRes->reference." en doublon dans la BDD ***** "; continue;
				}
				elseif(count($TRes) > 0){
					//pre($TRes,true);exit;
					$TDossierAssoc[$idDossier] = $TRes[0]->reference;
				}
			}
		}
		if(!empty($simulation->dossiers_rachetes_p1)){
			foreach($simulation->dossiers_rachetes_p1 as $idDossier => $Tdata){
				
				$PDOdb->Execute('SELECT reference FROM '.MAIN_DB_PREFIX."fin_dossier_financement WHERE fk_fin_dossier = ".$idDossier." AND type = 'LEASER'");
				$TRes = $PDOdb->Get_All();
				//pre($TRes,true);exit;
				if(count($TRes) > 1){
					echo " ***** ATTENTION : dossier ".$TRes->reference." en doublon dans la BDD ***** "; continue;
				}
				elseif(count($TRes) > 0){
					//pre($TRes,true);exit;
					$TDossierAssoc[$idDossier] = $TRes[0]->reference;
				}
			}
		}
		if(!empty($simulation->dossiers_rachetes_nr_p1)){
			foreach($simulation->dossiers_rachetes_nr_p1 as $idDossier => $Tdata){
				
				$PDOdb->Execute('SELECT reference FROM '.MAIN_DB_PREFIX."fin_dossier_financement WHERE fk_fin_dossier = ".$idDossier." AND type = 'LEASER'");
				$TRes = $PDOdb->Get_All();
				//pre($TRes,true);exit;
				if(count($TRes) > 1){
					echo " ***** ATTENTION : dossier ".$TRes->reference." en doublon dans la BDD ***** "; continue;
				}
				elseif(count($TRes) > 0){
					//pre($TRes,true);exit;
					$TDossierAssoc[$idDossier] = $TRes[0]->reference;
				}
			}
		}
	}
		
	header("Content-disposition: attachment; filename=dossiers_simulation.csv");
	header("Content-Type: application/force-download");
	header("Content-Transfer-Encoding: application/octet-stream");
	header("Pragma: no-cache");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
	header("Expires: 0");
		
	print '"Id";';
	print "\"Référence dossier\r\n\"";
	
	foreach($TDossierAssoc as $idDossier => $reference){
	
		print '"'.$idDossier.'";';
		print '"'.$reference."\";\r\n";
	}
	
	//pre($TDossierAssoc,true);	
