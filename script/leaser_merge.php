<?php

ini_set('display_errors', true);

// Include Dolibarr environment
require_once("../config.php");

$PDOdb = new TPDOdb();

// ETAPE 1 : on supprime les grille de coeff LEASER des autres entités que CPRO et sur les leasers autre que CPRO
$sql = 'DELETE FROM llx_fin_grille_leaser WHERE entity > 1 AND fk_soc > 1 AND type = \'LEASER\'';
$PDOdb->Execute($sql);

// ETAPE 2 : on remplace les leaser créés par entités par ceux de CPRO (leaser générique)

// A : récupération des leaser générique CPRO
$sqlLeaser = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe';
$sqlLeaser.= ' WHERE entity = 1';
$sqlLeaser.= ' AND fournisseur = 1';

$TIdLeaser = TRequeteCore::_get_id_by_sql($PDOdb, $sqlLeaser);

$TReplace = array();


// B : on créé le tableau de correspondance qui donnera pour chaque ID leaser spécifique, le leaser générique correspondant
foreach ($TIdLeaser as $idLeaser) {
	$lea = new Societe($db);
	$lea->fetch($idLeaser);
	
	$sqlOther = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe';
	$sqlOther.= ' WHERE entity > 1';
	$sqlOther.= ' AND fournisseur = 1';
	$sqlOther.= ' AND nom LIKE \'%'.$lea->name.'\'';
	
	$TIdOther = TRequeteCore::_get_id_by_sql($PDOdb, $sqlOther);
	
	if(!empty($TIdOther)) {
		echo $lea->id.' : '.$lea->name . '<hr>';
	
		foreach ($TIdOther as $idOther) {
			$lea2 = new Societe($db);
			$lea2->fetch($idOther);
			echo $lea2->id.' : '.$lea2->name . '<br>';
			
			$TReplace[$lea2->id] = $lea->id;
		}
		
		echo '<hr>';
	}
}
//pre($TReplace,true);

// C : pour chaque table qui contient une colonne concernant un leaser, on prépare un UPDATE
// Get all tables
$TTable = get_tables($PDOdb);
$TTableToIgnore = array();

$TRes = array();
foreach ($TTable as $table) {
	if(in_array($table, $TTableToIgnore)) continue;
	
	// Deal only with table with fk_soc column in it
	$col = 'fk_soc';
	if(has_column($PDOdb, $table, $col)) {
		foreach ($TReplace as $idSource => $idTarget) {
			$sql = "SELECT count(*) as nb FROM $table WHERE $col = ".$idSource;
			$PDOdb->Execute($sql);
			$PDOdb->Get_line();
			$nb = $PDOdb->Get_field('nb');
			if($nb > 0) {
				$TRes[] = array(
					'table' => $table,
					'records' => $nb,
					'sql' => update_record_with_col($PDOdb, $table, $col, $idSource, $idTarget)
				);
			}
		}
	}
	// Deal only with table with fk_leaser column in it
	$col = 'fk_leaser';
	if(has_column($PDOdb, $table, $col)) {
		foreach ($TReplace as $idSource => $idTarget) {
			$sql = "SELECT count(*) as nb FROM $table WHERE $col = ".$idSource;
			$PDOdb->Execute($sql);
			$PDOdb->Get_line();
			$nb = $PDOdb->Get_field('nb');
			if($nb > 0) {
				$TRes[] = array(
					'table' => $table,
					'records' => $nb,
					'sql' => update_record_with_col($PDOdb, $table, $col, $idSource, $idTarget)
				);
			}
		}
	}
	// Deal only with table with fk_leaser_solde column in it
	$col = 'fk_leaser_solde';
	if(has_column($PDOdb, $table, $col)) {
		foreach ($TReplace as $idSource => $idTarget) {
			$sql = "SELECT count(*) as nb FROM $table WHERE $col = ".$idSource;
			$PDOdb->Execute($sql);
			$PDOdb->Get_line();
			$nb = $PDOdb->Get_field('nb');
			if($nb > 0) {
				$TRes[] = array(
					'table' => $table,
					'records' => $nb,
					'sql' => update_record_with_col($PDOdb, $table, $col, $idSource, $idTarget)
				);
			}
		}
	}
}
//pre($TRes,true);

// D : on affiche les UPADTE (pour éviter de lancer le script par accident)
foreach ($TRes as $data) {
	echo $data['sql'].'<br>';
}
exit;

// Get list of all tables in database
function get_tables(&$PDOdb) {
	$Tab=array();
	
	$PDOdb->Execute("SHOW TABLES");
	
	while($row = $PDOdb->Get_line()){
		$nom = current($row);
		$Tab[] = $nom;
	}
	
	return $Tab;
}

// Check if table has a defined column
function has_column(&$PDOdb, $table, $col) {
	$PDOdb->Execute("SHOW COLUMNS FROM ".$table);
	
	while($row = $PDOdb->Get_line()){
		if($PDOdb->Get_field('Field') == $col) return true;
	}
	
	return false;
}

// Update all records in a table with a new fk_soc
function update_record_with_col(&$PDOdb, $table, $col, $socSource, $socTarget) {
	$sql = "UPDATE $table SET $col = $socTarget WHERE $col = $socSource;";
	//$PDOdb->Execute($sql);
	return $sql;
}
