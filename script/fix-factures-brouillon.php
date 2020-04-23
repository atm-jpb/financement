<?php

require('../config.php');
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/fourn/class/fournisseur.facture.class.php');

@set_time_limit(0);					// No timeout for this script
@ini_set('memory_limit', '256M');

// Suppression des factures client et fournisseur à 0€ et en brouillon
$PDOdb=new TPDOdb();

echo '<hr>FACTURES CLIENT';
// Récupération des factures client
$sql = "SELECT f.rowid";
$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
$sql.= " WHERE f.total = 0";
$sql.= " AND f.fk_statut = 0";
//echo $sql;
$TData = $PDOdb->ExecuteAsArray($sql);

foreach ($TData as $data) {
	$f = new Facture($db);
	$f->fetch($data->rowid);
	echo '<hr>'.$f->ref;
	//$f->delete($user);
}

echo '<hr>FACTURES FOURNISSEUR';
// Récupération des factures fournisseur
$sql = "SELECT f.rowid";
$sql.= " FROM ".MAIN_DB_PREFIX."facture_fourn f";
$sql.= " WHERE f.total_ht = 0";
$sql.= " AND f.fk_statut = 0";
//echo $sql;
$TData = $PDOdb->ExecuteAsArray($sql);

foreach ($TData as $data) {
	$f = new FactureFournisseur($db);
	$f->fetch($data->rowid);
	echo '<hr>'.$f->ref;
	//$f->delete($user);
}
