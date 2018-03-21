<?php

ini_set('display_errors', true);

// Include Dolibarr environment
require_once("../config.php");

?>
Script déplaçant tous les éléments de la base de l\'entité "entity_source" vers l\'entité "entity_target"
<br><br>
<?php

// Récupération des paramètres
$entitySource = GETPOST('entity_source');
$entityTarget = GETPOST('entity_target');
$confirm = GETPOST('confirm');

if(empty($entitySource) || empty($entityTarget)) {
	echo 'Ce script nécessite 2 paramètres : "entity_source" et "entity_target"';
	exit();
}

// Chargement des entités
$e1 = new DaoMulticompany($db);
$e1->fetch($entitySource);
//pre($e1,true);
$e2 = new DaoMulticompany($db);
$e2->fetch($entityTarget);

?>
Tous les éléments de l'entité <strong><?php echo $entitySource . ' - ' .$e1->label ?></strong>
vont être transférés sur l'entité <strong><?php echo $entityTarget . ' - ' .$e2->label ?></strong>.
<br><br>
<?php

// Demande de confirmation
if(empty($confirm)) {
?>
	<form method="POST">
		<input type="hidden" name="entity_source" value="<?php echo $entitySource ?>" />
		<input type="hidden" name="entity_target" value="<?php echo $entityTarget ?>" />
		
		<input type="submit" name="confirm" value="Confirmer" />
	</form>
<?php
	exit();
}

// Go
$PDOdb = new TPDOdb();

$TTables = get_tables($PDOdb);

foreach ($TTables as $table) {
	if(has_entity($PDOdb, $table)) {
		
		$sql = "SELECT count(*) as nb FROM ".$table." WHERE entity = ".$entitySource;
		$PDOdb->Execute($sql);
		$PDOdb->Get_line();
		$nb = $PDOdb->Get_field('nb');
		if($nb > 0) echo $table.' - '.$nb.' records<br>';
	}
}

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

// Check if table has an "entity" column
function has_entity(&$PDOdb, $table) {
	$PDOdb->Execute("SHOW COLUMNS FROM ".$table);
	
	while($row = $PDOdb->Get_line()){
		if($PDOdb->Get_field('Field') == 'entity') return true;
	}
	
	return false;
}
