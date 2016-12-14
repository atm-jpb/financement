<?php

require('../../config.php');
require('../../class/affaire.class.php');
require('../../class/dossier.class.php');
require('../../class/grille.class.php');
dol_include_once('/asset/class/asset.class.php');
dol_include_once('/compta/facture/class/facture.class.php');

@set_time_limit(0);					// No timeout for this script

// On drop la view precedente si elle existe
$atmdb = new TPDOdb; $sql = "DROP VIEW facture_view_5286"; 
$atmdb->Execute($sql);

// On recréé la view
$sql = "CREATE VIEW facture_view_5286 AS SELECT * from llx_facture GROUP BY facnumber HAVING COUNT(facnumber) > 1";
$atmdb->Execute($sql);

$nbFact = 0;

while($ATMdb->Get_line()){
	$nbFact++;
	$facture = new Facture($db);
	$idfac = 
	$facture->fetch($idfac);
	echo '<br><br>';
}

echo $nbFact.' ****** en doublon ';
