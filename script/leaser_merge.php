<?php

ini_set('display_errors', true);

// Include Dolibarr environment
require_once("../config.php");

$PDOdb = new TPDOdb();

$sqlLeaser = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe';
$sqlLeaser.= ' WHERE entity = 1';
$sqlLeaser.= ' AND fournisseur = 1';

$TIdLeaser = TRequeteCore::_get_id_by_sql($PDOdb, $sqlLeaser);

$TReplace = array();

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

// Get all tables
$TTable = get_tables($PDOdb);
$TTableToIgnore = array('llx_fin_grille_leaser', 'llx_fin_grille_leaser_date');

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
}
//pre($TRes,true);

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
	$sql = "UPDATE $table SET $col = $socTarget WHERE fk_soc = $socSource;";
	//$PDOdb->Execute($sql);
	return $sql;
}
