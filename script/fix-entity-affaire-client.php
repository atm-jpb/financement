<?php

require '../config.php';
dol_include_once('/financement/lib/financement.lib.php');

@set_time_limit(0);					// No timeout for this script

$PDOdb = new TPDOdb;

// Récupération des affaires commençant par EXT dont l'entité n'est pas la même que celle du client
$sql = "SELECT a.rowid, a.entity, a.reference, s.rowid as socid, s.entity as entity_soc, s.siren, s.nom, s.address, s.zip, s.town, s.siren, s.siret, s.fk_pays";
$sql.= " FROM ".MAIN_DB_PREFIX."fin_affaire a";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = a.fk_soc";
$sql.= " WHERE a.reference LIKE 'EXT%'";
$sql.= " AND a.entity != s.entity";
//$sql.= " LIMIT 1000";

$resql = $db->query($sql);

if($resql) {
	$i = $j = 0;
	while ($obj = $db->fetch_object($resql)) {
		if(in_array($obj->entity, array(1,2,3)) && in_array($obj->entity_soc, array(1,2,3))) continue;
		if(in_array($obj->entity, array(5,16)) && in_array($obj->entity_soc, array(5,16))) continue;
		if(in_array($obj->entity, array(20,23)) && in_array($obj->entity_soc, array(20,23))) continue;
		$i++;

		$TEntityGroup = getOneEntityGroup($obj->entity, 'thirdparty', array(4, 17));

		echo '<hr>AFFAIRE '.$obj->reference.' ('.$obj->rowid.') : ';
		if(strlen($obj->siren) != 9) {
			echo 'SIREN INCORRECT';
			continue;
		}

		$sql2 = "SELECT s.rowid FROM ".MAIN_DB_PREFIX."societe s WHERE s.entity IN (".implode(',', $TEntityGroup).") AND s.siren = '".$obj->siren."'";
		$resql2 = $db->query($sql2);
		if($db->num_rows($resql2) == 0) {
			echo 'SIREN NON TROUVÉ ('.$sql2.') => ';
			$soc = new Societe($db);
			$soc->name = $obj->nom;
			$soc->address = $obj->address;
			$soc->zip = $obj->zip;
			$soc->town = $obj->town;
			$soc->country_id = $obj->fk_pays;
			$soc->idprof1 = $obj->siren;
			$soc->idprof2 = $obj->siret;
			$soc->entity = $obj->entity;
			$soc->client = 1;
			$soc->status = 1;
			$socid = $soc->create($user);
			if($socid < 0) {
				echo 'ERREUR CREATION '.$socid;
				continue;
			}
		} else {
			$obj2 = $db->fetch_object($resql2);
			$socid = $obj2->rowid;
		}

		$sql3 = "UPDATE ".MAIN_DB_PREFIX."fin_affaire a SET a.fk_soc = ".$socid." WHERE a.rowid = ".$obj->rowid;
		echo $sql3;
		$resql3 = $db->query($sql3);
		if(!$resql3) {
			dol_print_error($db);
		}
		$j++;
	}
} else {
	echo $sql;
}

echo '<hr>'.$j.' / '.$i;
