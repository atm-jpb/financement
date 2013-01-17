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

	$db=new Tdb;
	$db->db->debug=true;

	$o=new TCommercialCpro;
	$o->init_db_by_vars($db);
	
	$o=new TFin_affaire_commercial;
	$o->init_db_by_vars($db);
	
	$o=new TFin_affaire;
	$o->init_db_by_vars($db);

	$o=new TFin_dossier_affaire;
	$o->init_db_by_vars($db);

	$o=new TFin_dossier;
	$o->init_db_by_vars($db);
	
	$o=new TFin_financement;
	$o->init_db_by_vars($db);
	
	$o=new TSimulation;
	$o->init_db_by_vars($db);