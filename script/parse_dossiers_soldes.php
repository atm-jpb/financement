<?php

require_once('../config.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

$sql = 'SELECT rowid, dossiers';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation';
$sql.= " WHERE dossiers IS NOT NULL AND dossiers <> ''";
$sql.= ' ORDER BY rowid';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$nb_commit = $nb_rollback = 0;

while($obj = $db->fetch_object($resql)) {
    $dossiers = unserialize($obj->dossiers);
    if($dossiers === false) continue;

	var_dump($dossiers);
}exit;
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?><br />Nb Rollback : <?php echo $nb_rollback; ?>
