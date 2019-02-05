<?php

// Nettoyage des codes client de l'ouest
// Les clients dont le code commence par ABG ou COPEM doivent être "nettoyés"
// Nécessité d'un script car pas possible d'avoir 2 fiches avec le même code client et il y a quelques doublons (à gérer manuellement)

require('../config.php');

$PDOdb=new TPDOdb();

// Sélection clients ABG
$sql = 'SELECT rowid, code_client FROM '.MAIN_DB_PREFIX.'societe ';
$sql.= 'WHERE entity = 5 ';
$sql.= 'AND code_client LIKE "ABG%" ';

$TSocId = $PDOdb->ExecuteAsArray($sql);

$up = $dbl = 0;
foreach ($TSocId as $obj) {
	$codecli = str_replace('ABG', '', $obj->code_client);
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity = 5 AND code_client = "'.$codecli.'"';
	$TDBLId = $PDOdb->ExecuteAsArray($sql);
	if(empty($TDBLId)) {
		echo '<hr>MODIF CLIENT '.$codecli;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe SET code_client = "'.$codecli.'", client = 1 WHERE rowid = '.$obj->rowid;
		$PDOdb->Execute($sql);
		$up++;
	} else {
		echo '<hr>DOUBLON CLIENT '.$codecli;
		$dbl++;
	}
}
echo '<hr>UP '.$up;
echo '<hr>DBL '.$dbl;


/////////////////////////////////////////////////////////////////////////////////////////////////////////

// Sélection clients COPEM
$sql = 'SELECT rowid, code_client FROM '.MAIN_DB_PREFIX.'societe ';
$sql.= 'WHERE entity = 6 ';
$sql.= 'AND code_client LIKE "COPEM%" ';

$TSocId = $PDOdb->ExecuteAsArray($sql);

$up = $dbl = 0;
foreach ($TSocId as $obj) {
	$codecli = str_replace('COPEM', '', $obj->code_client);
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity = 6 AND code_client = "'.$codecli.'"';
	$TDBLId = $PDOdb->ExecuteAsArray($sql);
	if(empty($TDBLId)) {
		echo '<hr>MODIF CLIENT '.$codecli;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe SET code_client = "'.$codecli.'", client = 1 WHERE rowid = '.$obj->rowid;
		$PDOdb->Execute($sql);
		$up++;
	} else {
		echo '<hr>DOUBLON CLIENT '.$codecli;
		$dbl++;
	}
}
echo '<hr>UP '.$up;
echo '<hr>DBL '.$dbl;


// Étape 2, les codes client sur moins de 6 caractères ont le 0 manquant devant pour COPEM
$sql = 'SELECT rowid, code_client FROM '.MAIN_DB_PREFIX.'societe ';
$sql.= 'WHERE entity = 6 ';
$sql.= 'AND LENGTH(code_client) < 6 ';

$TSocId = $PDOdb->ExecuteAsArray($sql);

$up = $dbl = 0;
foreach ($TSocId as $obj) {
	$codecli = str_pad($obj->code_client, 6, '0', STR_PAD_LEFT);
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity = 6 AND code_client = "'.$codecli.'"';
	$TDBLId = $PDOdb->ExecuteAsArray($sql);
	if(empty($TDBLId)) {
		echo '<hr>MODIF CLIENT '.$codecli;
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe SET code_client = "'.$codecli.'", client = 1 WHERE rowid = '.$obj->rowid;
		$PDOdb->Execute($sql);
		$up++;
	} else {
		echo '<hr>DOUBLON CLIENT '.$codecli;
		$dbl++;
	}
}
echo '<hr>UP '.$up;
echo '<hr>DBL '.$dbl;