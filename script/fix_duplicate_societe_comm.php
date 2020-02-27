<?php

ini_set('max_execution_time', 0);
require_once('../config.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

$sql = 'SELECT GROUP_CONCAT(rowid) as TRowid, fk_user, fk_soc, count(*) as nb';
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe_commerciaux';
$sql.= ' WHERE fk_user IS NOT NULL AND fk_soc IS NOT NULL';
$sql.= ' GROUP BY fk_user, fk_soc';
$sql.= ' HAVING nb > 1';
$sql.= ' ORDER BY nb DESC';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$nb_commit = $nb_rollback = 0;

while($obj = $db->fetch_object($resql)) {
	$TRowid = explode(',', $obj->TRowid);
    $error = 0;

    $db->begin();
    
    // On laisse volontairement le premier enregistrement
    for($i = 1 ; $i < count($TRowid) ; $i++) {
        $sql_delete = 'DELETE FROM '.MAIN_DB_PREFIX.'societe_commerciaux WHERE rowid = '.$TRowid[$i].';';
        $res_delete = $db->query($sql_delete);
        if(! $res_delete || ! empty($force_rollback)) $error++;
    }

    if(empty($error)) {
        $db->commit();
        $nb_commit++;
    }
    else {
        $db->rollback();
        $nb_rollback++;
    }
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?><br />Nb Rollback : <?php echo $nb_rollback; ?>
