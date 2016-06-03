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
}

function change_engagement() {
	$PDOdb=new TPDOdb;
	
	$id_integrale = GETPOST('id_integrale');
	$type = GETPOST('type');
	$engagement = GETPOST('engagement');
	
	dol_include_once('/financement/class/dossier_integrale.class.php');
	$integrale = new TIntegrale;
	$integrale->load($PDOdb, $id_integrale);
	
	$new_cout = $integrale->calcul_cout_unitaire($engagement, $type);
	//$new_cout *= $pourcentage_sup_mois_decembre;
	
	// Get detail
	$TDetailCout = $integrale->calcul_detail_cout($engagement, $new_cout, $type);
	
	echo json_encode($TDetailCout);
}

function change_couleur_percent() {
	
	
	$integrale->calcul_cout_unitaire_by_repartition($engagement);
	$data = array(
		'cout_u' => 10
	);

	echo json_encode($data);
}
	