<?php

require_once '../config.php';
dol_include_once('/financement/class/dossier.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

$sql = 'SELECT GROUP_CONCAT(rowid) as TRowid, siret, count(*) as nb';
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
$sql.= ' WHERE siret IS NOT NULL';
$sql.= ' AND entity = 26';  // Omniburo
$sql.= ' GROUP BY siret';
$sql.= ' HAVING nb > 1';
$sql.= ' ORDER BY nb DESC';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;
print $sql;
$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$PDOdb = new TPDOdb;
$nb_commit = $nb_rollback = 0;

while($obj = $db->fetch_object($resql)) {
	$TRowid = explode(',', $obj->TRowid);
    $error = 0;

    $db->begin();
    
    // On laisse volontairement le premier enregistrement
    for($i = 1 ; $i < count($TRowid) ; $i++) {
        // On delete tous les dossiers liés au Tiers
        $sql2 = 'SELECT distinct fk_fin_dossier';
        $sql2.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement';
        $sql2.= ' WHERE fk_soc = '.$TRowid[$i];

        $resql2 = $db->query($sql2);
        if(! $resql2) {
            dol_print_error($db);
            exit;
        }

        while($obj = $db->fetch_object($resql2)) {
            $dossier = new TFin_dossier;
            $dossier->load($PDOdb, $obj->fk_fin_dossier);
            $dossier->delete($PDOdb);
        }
        $db->free($resql2);

        // On delete le Tiers
        $sql_delete = 'DELETE FROM '.MAIN_DB_PREFIX.'societe WHERE rowid = '.$TRowid[$i].';';
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
<span>Nb Commit : <?php echo $nb_commit; ?></span><br />
<span>Nb Rollback : <?php echo $nb_rollback; ?></span>
