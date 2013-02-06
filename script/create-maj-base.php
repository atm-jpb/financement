<?php
/*
 * Script créant et vérifiant que les champs requis s'ajoutent bien
 * 
 */
	require('../config.php');
	require('../class/commerciaux.class.php');
	require('../class/affaire.class.php');
	require('../class/dossier.class.php');
	require('../class/simulation.class.php');
	require('../class/score.class.php');

	require('../class/grille.class.php');
//	require('../class/grille.leaser.class.php');

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
	
	$o=new TFin_grille_leaser;
	$o->init_db_by_vars($ATMdb);
	
	/*$o=new TFin_grille;
	$o->init_db_by_vars($ATMdb);
	*/
	
	$s=new TScore;
	$s->init_db_by_vars($ATMdb);