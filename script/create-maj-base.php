<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	define('INC_FROM_CRON_SCRIPT', true);
 
	require('../config.php');
	dol_include_once('/financement/class/commerciaux.class.php');
	dol_include_once('/financement/class/affaire.class.php');
	dol_include_once('/financement/class/dossier.class.php');
	dol_include_once('/financement/class/dossier_integrale.class.php');
	dol_include_once('/financement/class/simulation.class.php');
	dol_include_once('/financement/class/score.class.php');
	dol_include_once('/financement/class/import.class.php');
	dol_include_once('/financement/class/import_error.class.php');
	dol_include_once('/financement/class/grille.class.php');

	$ATMdb=new TPDOdb;
	$ATMdb->db->debug=true;

	$o=new TCommercialCpro;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TFin_affaire_commercial;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TFin_affaire;
	$o->init_db_by_vars($ATMdb);

	$o=new TFin_dossier_affaire;
	$o->init_db_by_vars($ATMdb);

	$o=new TFin_dossier;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TFin_financement;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TSimulation;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TSimulationSuivi;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TFin_grille_leaser;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TFin_grille_suivi;
	$o->init_db_by_vars($ATMdb);
	
	/*$o=new TFin_grille;
	$o->init_db_by_vars($ATMdb);
	*/
	
	$o=new TScore;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TImport;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TImportError;
	$o->init_db_by_vars($ATMdb);
	
	$o=new TImportHistorique;
	$o->init_db_by_vars($ATMdb);
	
	// Intégrale
	$o=new TIntegrale();
	$o->init_db_by_vars($ATMdb);

	$o=new TFin_facture_fournisseur;
	$o->init_db_by_vars($ATMdb);