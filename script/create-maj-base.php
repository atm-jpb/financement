<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
 	define('INC_FROM_CRON_SCRIPT', true);
 
	require('../config.php');
	require('../class/commerciaux.class.php');
	require('../class/affaire.class.php');
	require('../class/dossier.class.php');
	require('../class/dossier_integrale.class.php');
	require('../class/simulation.class.php');
	require('../class/score.class.php');
	require('../class/import.class.php');
	require('../class/import_error.class.php');
	require('../class/grille.class.php');

	$ATMdb=new Tdb;
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