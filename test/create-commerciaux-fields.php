<?php
/*
 * Script vérifiant que les champs requis s'ajoutent bien
 * 
 */
	require('../config.php');
	require('../class/commerciaux.class.php');

	$db=new Tdb;
	$db->db->debug=true;

	$c=new TCommerciauxCpro;
	
	$c->addFieldsInDb($db);
