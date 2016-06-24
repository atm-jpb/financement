<?php

require('../config.php');

$action = GETPOST('action');

switch($action) {
	case 'change_engagement':
		change_engagement();
		break;
	
	case 'change_couleur_percent':
		change_couleur_percent();
		break;
		
	case 'change_fas':
		change_fas();
		break;
}

function change_engagement() {
	$PDOdb=new TPDOdb;
	
	$id_integrale = GETPOST('id_integrale');
	$type = GETPOST('type');
	$fas = GETPOST('fas');
	$percent = GETPOST('percent');
	$cout_noir = GETPOST('cout_unit_noir');
	$cout_coul = GETPOST('cout_unit_coul');
	$engagement_noir = GETPOST('engagement_noir');
	$engagement_coul = GETPOST('engagement_coul');
	
	dol_include_once('/financement/class/dossier_integrale.class.php');
	$integrale = new TIntegrale;
	$integrale->load($PDOdb, $id_integrale);
	
	$new_cout_noir = $integrale->calcul_cout_unitaire($engagement_noir, 'noir');
	$new_cout_coul = $integrale->calcul_cout_unitaire($engagement_coul, 'coul');
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');
	
	$total_global = $integrale->calcul_total_global($TDetailCoutNoir, $TDetailCoutCoul, $fas);
	
	$fas_min = $integrale->fas;
	$fas_max = $integrale->calcul_fas_max($TDetailCoutNoir, $TDetailCoutCoul, $engagement_noir, $engagement_coul);
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $total_global,
		'fas_min'		=> $fas_min,
		'fas_max'		=> $fas_max
	);
	
	echo json_encode($data);
}

function change_couleur_percent() {
	$PDOdb=new TPDOdb;
	
	$id_integrale = GETPOST('id_integrale');
	$fas = GETPOST('fas');
	$percent = GETPOST('percent');
	$cout_noir = GETPOST('cout_unit_noir');
	$cout_coul = GETPOST('cout_unit_coul');
	$engagement_noir = GETPOST('engagement_noir');
	$engagement_coul = GETPOST('engagement_coul');
	
	dol_include_once('/financement/class/dossier_integrale.class.php');
	$integrale = new TIntegrale;
	$integrale->load($PDOdb, $id_integrale);
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $cout_coul, 'coul');
	
	$new_cout_noir = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCoul, $engagement_coul, 100 - $percent, 'noir');
	$new_cout_coul = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCoul, $engagement_coul, $percent, 'couleur');
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');
	
	$total_global = $integrale->calcul_total_global($TDetailCoutNoir, $TDetailCoutCoul, $fas);
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $total_global
	);

	echo json_encode($data);
}

function change_fas() {
	$PDOdb=new TPDOdb;
	
	$id_integrale = GETPOST('id_integrale');
	$fas = GETPOST('fas');
	$percent = GETPOST('percent');
	$cout_noir = GETPOST('cout_unit_noir');
	$cout_coul = GETPOST('cout_unit_coul');
	$engagement_noir = GETPOST('engagement_noir');
	$engagement_coul = GETPOST('engagement_coul');
	
	dol_include_once('/financement/class/dossier_integrale.class.php');
	$integrale = new TIntegrale;
	$integrale->load($PDOdb, $id_integrale);
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout(0, 0, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout(0, 0, 'coul');
	
	$new_cout_noir = $integrale->calcul_cout_unitaire_by_fas($engagement_noir, $TDetailCoutNoir, $fas, $integrale->fas, 100 - $percent);
	$new_cout_coul = $integrale->calcul_cout_unitaire_by_fas($engagement_coul, $TDetailCoutCoul, $fas, $integrale->fas, $percent);
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');
	
	$total_global = $integrale->calcul_total_global($TDetailCoutNoir, $TDetailCoutCoul, $fas);
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $total_global
	);
	
	echo json_encode($data);
}