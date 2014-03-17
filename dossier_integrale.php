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

llxHeader('','Suivi intégrale');

$TBS->render('./tpl/dossier_integrale.tpl.php'
	,array(
	
	)
	,array(
		'fin'=>$fin
	)
);

llxFooter();
	