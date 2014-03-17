<?php

require('config.php');
require('./class/dossier.class.php');
require('./class/dossier_integrale.class.php');

$langs->load('financement@financement');

if (!$user->rights->financement->affaire->read)	{ accessforbidden(); }

$dossier=new TFin_Dossier;
$PDOdb=new TPDOdb;
$tbs = new TTemplateTBS;

$id_dossier = GETPOST('id');
$dossier->load($PDOdb, $id_dossier);
$fin = &$dossier->financement;

$TIntegrale = array();
foreach ($dossier->TFacture as $fact) {
	$integrale = new TIntegrale();
	$integrale->loadBy($PDOdb, $fac->facnumber, 'facnumber');
	$integrale->periode = 'T' . floor((date('n', $fac->date)+4)/4) . ' ' . date('Y', $fac->date);
	
	$TIntegrale[] = $integrale;
}

llxHeader('','Suivi intégrale');

$TBS->render('./tpl/dossier_integrale.tpl.php'
	,array(
		'integrale'=>$TIntegrale
	)
	,array(
		'fin'=>$fin
	)
);

llxFooter();
	