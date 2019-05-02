<?php

require('../config.php');

$action = GETPOST('action');

change_all($action);

function change_all($case) {
    global $user;

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
	
	// Recalcul coûts unitaires à partir des engagement passés en paramètres
	$cout_noir = $integrale->calcul_cout_unitaire($engagement_noir, 'noir');
	$cout_coul = $integrale->calcul_cout_unitaire($engagement_coul, 'coul');
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $cout_coul, 'coul');
	
	// Récupération des détails coût pour contrat déjà en place
	$current_fas = $integrale->fas;
	$current_percent = $integrale->calcul_percent_couleur();
	
	if($current_fas != $fas) {
		$new_cout_noir = $integrale->calcul_cout_unitaire_by_fas($TDetailCoutNoir, $engagement_noir, $fas, 100 - $percent);
		$new_cout_coul = $integrale->calcul_cout_unitaire_by_fas($TDetailCoutCoul, $engagement_coul, $fas, $percent);
		$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
		$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');
	}
	
	if($current_percent != $percent) {
		$new_cout_noir = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCoul, $engagement_coul, 100 - $percent, 'noir');
		$new_cout_coul = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCoul, $engagement_coul, $percent, 'couleur');
		$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
		$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');
	}
	
	// Recalcul coûts unitaires à partir des engagement passés en paramètres
	/*$cout_noir = $integrale->calcul_cout_unitaire($engagement_noir, 'noir');
	$cout_coul = $integrale->calcul_cout_unitaire($engagement_coul, 'coul');
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $cout_coul, 'coul');
	
	if($case == 'change_engagement') {
		$new_cout_noir = $integrale->calcul_cout_unitaire($engagement_noir, 'noir');
		$new_cout_coul = $integrale->calcul_cout_unitaire($engagement_coul, 'coul');
	} else if($case == 'change_couleur_percent') {
		$new_cout_noir = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCoul, $engagement_coul, 100 - $percent, 'noir');
		$new_cout_coul = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCoul, $engagement_coul, $percent, 'couleur');
	} else if($case == 'change_fas') {
		$new_cout_noir = $integrale->calcul_cout_unitaire_by_fas($TDetailCoutNoir, $engagement_noir, $fas, 100 - $percent);
		$new_cout_coul = $integrale->calcul_cout_unitaire_by_fas($TDetailCoutCoul, $engagement_coul, $fas, $percent);
	}
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');
	
	// Recalcul coûts unitaires avec les fas passés en paramètres
	$new_cout_noir = $integrale->calcul_cout_unitaire_by_fas($TDetailCoutNoir, $engagement_noir, $fas, 100 - $percent);
	$new_cout_coul = $integrale->calcul_cout_unitaire_by_fas($TDetailCoutCoul, $engagement_coul, $fas, $percent);
	
	$TDetailCoutNoir = $integrale->calcul_detail_cout($engagement_noir, $new_cout_noir, 'noir');
	$TDetailCoutCoul = $integrale->calcul_detail_cout($engagement_coul, $new_cout_coul, 'coul');*/
	
	$total_global = $integrale->calcul_total_global($TDetailCoutNoir, $TDetailCoutCoul, $fas);
	$total_hors_frais = $total_global - $integrale->frais_bris_machine - $integrale->frais_facturation;
	
	$fas_min = $integrale->fas;
	$fas_max = $integrale->calcul_fas_max($TDetailCoutNoir, $TDetailCoutCoul, $engagement_noir, $engagement_coul, $fas);
	$fas_max = max($fas_max, $integrale->fas);
	// Si admin, on autorise à mettre + de FAS
	if($user->rights->financement->admin->write) $fas_max = $total_hors_frais;
	
	$data = array(
		'couts_noir'	=> $TDetailCoutNoir,
		'couts_coul'	=> $TDetailCoutCoul,
		'total_global'	=> $total_global,
		'total_hors_frais'	=> $total_hors_frais,
		'fas_min'		=> $fas_min,
		'fas_max'		=> $fas_max
	);
	
	echo json_encode($data);
}
