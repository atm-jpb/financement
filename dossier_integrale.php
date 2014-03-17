<?php

require('config.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/dossier_integrale.class.php');
require('./class/grille.class.php');

$langs->load('financement@financement');

if (!$user->rights->financement->affaire->read)	{ accessforbidden(); }

$dossier=new TFin_Dossier;
$PDOdb=new TPDOdb;
$TBS = new TTemplateTBS;

$id_dossier = GETPOST('id');
$dossier->load($PDOdb, $id_dossier);
$fin = &$dossier->financement;

$affaire = &$dossier->TLien[0]->affaire;
$client = new Societe($db);
$client->fetch($affaire->fk_soc);

// Affichage spé
$dossier->url_therefore=FIN_THEREFORE_DOSSIER_URL;
$fin->_affterme = $fin->TTerme[$fin->terme];
$fin->_affperiodicite = $fin->TPeriodicite[$fin->periodicite];


$TIntegrale = array();
foreach ($dossier->TFacture as $fac) {
	$integrale = new TIntegrale();
	$integrale->loadBy($PDOdb, $fac->ref, 'facnumber');
	
	$integrale->vol_noir_facture = ($integrale->vol_noir_engage > $integrale->vol_noir_realise) ? $integrale->vol_noir_engage : $integrale->vol_noir_realise;
	$integrale->vol_coul_facture = ($integrale->vol_coul_engage > $integrale->vol_coul_realise) ? $integrale->vol_coul_engage : $integrale->vol_coul_realise;
	
	$integrale->periode = substr($fin->periodicite,0,1);
	$integrale->periode.= ceil(date('n', $fac->date) / $fin->getiPeriode()) . ' ' . date('Y', $fac->date);
	
	$TIntegrale[] = $integrale;
}

llxHeader('','Suivi intégrale');

echo $TBS->render('./tpl/dossier_integrale.tpl.php'
	,array(
		'integrale'=>$TIntegrale
	)
	,array(
		'dossier'=>$dossier
		,'fin'=>$fin
		,'client'=>$client
	)
);

llxFooter();
	