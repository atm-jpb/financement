<?php

require('../config.php');

$action = GETPOST('action');

switch($action) {
	case 'change_engagement':
		change_engagement();
		break;
		
	case 'get_percent_couleur':
		get_percent_couleur();
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
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $TDetailCoutNoir['nouveau_cout_total'] + $TDetailCoutCoul['nouveau_cout_total'] + $fas
							+ $integrale->fass + $integrale->frais_bris_machine + $integrale->frais_facturation
	);
	
	echo json_encode($data);
}

function get_percent_couleur() {
	dol_include_once('/financement/class/dossier_integrale.class.php');
	$integrale = new TIntegrale;
	
	$cout_noir = GETPOST('cout_unit_noir_loyer');
	$cout_coul = GETPOST('cout_unit_coul_loyer');
	$engagement_noir = GETPOST('engagement_noir');
	$engagement_coul = GETPOST('engagement_coul');
	
	$data = $integrale->calcul_percent_couleur($cout_noir, $engagement_noir, $cout_coul, $engagement_coul);
	
	echo json_encode(array('percent' => $data));
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
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $TDetailCoutNoir['nouveau_cout_total'] + $TDetailCoutCoul['nouveau_cout_total'] + $fas
							+ $integrale->fass + $integrale->frais_bris_machine + $integrale->frais_facturation
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
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $TDetailCoutNoir['nouveau_cout_total'] + $TDetailCoutCoul['nouveau_cout_total'] + $fas
							+ $integrale->fass + $integrale->frais_bris_machine + $integrale->frais_facturation
	);
	
	echo json_encode($data);
}