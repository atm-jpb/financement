<?php
/*
 * Script permettant de basculer les données de llx_fin_dossier.date_reception_papier vers llx_fin_conformite.date_reception_papier
 */
require_once '../config.php';
dol_include_once('/financement/class/conformite.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

// Cette requête ne prend que les dossiers qui sont liés à des conformités
$sql = 'SELECT c.rowid, d.date_reception_papier';
$sql.= ' FROM '.MAIN_DB_PREFIX.Conformite::$tablename.' c';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (c.fk_simulation = s.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (s.fk_fin_dossier = d.rowid)';
$sql.= ' WHERE d.date_reception_papier IS NOT NULL';
$sql.= " AND d.date_reception_papier > '1970-01-01'";
$sql.= " AND (c.date_reception_papier IS NULL OR c.date_reception_papier < '1970-01-01')";  // ça empêche de modifier 2 fois la même conformité
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$nb_commit = $nb_rollback = 0;

while($obj = $db->fetch_object($resql)) {
    $db->begin();

    $sql = 'UPDATE '.MAIN_DB_PREFIX.Conformite::$tablename;
    $sql.= " SET date_reception_papier = '".date('Y-m-d', strtotime($obj->date_reception_papier))."'";
    $sql.= ' WHERE rowid = '.$obj->rowid;

    $resqlUpdate = $db->query($sql);
    if(! $resqlUpdate || ! empty($force_rollback)) {
        $db->rollback();
        $nb_rollback++;
    }
    else {
        $db->commit();
        $nb_commit++;
    }
    $db->free($resqlUpdate);
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?></span><br />
<span>Nb Rollback : <?php echo $nb_rollback; ?></span>
