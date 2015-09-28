<?php
 
require('../config.php');

$TEntities = $_REQUEST['entities'];

if(empty($TEntities)) {
	echo 'indiquer en get les nouvelles entites separees par une virgule, ex. : 2,3';
	exit;
}

$TEntities = explode(',', $TEntities);

$sql = "SELECT * FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'FINANCEMENT_%' AND entity = 1";

$resql = $db->query($sql);

while($res = $db->fetch_object($resql)) {
	foreach ($TEntities as $id_entity) {
		$db->query("REPLACE INTO ".MAIN_DB_PREFIX."const(name, entity, value, type, visible, note, tms) VALUES ('".$res->name."', ".$id_entity.", '".$res->value."', '".$res->type."', '".$res->visible."', '".$res->note."', '".date('Y-m-d H:i:s')."')");
		echo 'constante : '.$res->name.' ajoutee/modifiee sur entity '.$id_entity.'<br>';
	}
}
