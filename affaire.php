<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');

	llxHeader('','Affaires');
	
	$id=GETPOST("id");
	$socid=GETPOST("socid");
	$action=GETPOST("action");
	$cancel=GETPOST("cancel");
	
	$affaire=new TAffaire;
	
	llxFooter();