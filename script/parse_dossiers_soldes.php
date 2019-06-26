<?php

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

$sql = 'SELECT s.rowid, s.dossiers';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr ON (dr.fk_simulation=s.rowid)';
$sql.= " WHERE s.dossiers IS NOT NULL AND s.dossiers <> ''";
$sql.= " AND s.dossiers <> 'b:0;'";
$sql.= ' AND dr.rowid IS NULL'; // On prend celles qui n'ont pas (encore ?) de dossiers rachetes
$sql.= ' ORDER BY s.rowid';
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

    foreach($dossiers as $fk_dossier => $TValue) {
        unset($TValue['leaser']);

        $dossierRachete = new DossierRachete;
        $dossierRachete->set_values($TValue);

        $dossierRachete->fk_dossier = $fk_dossier;
        $dossierRachete->fk_simulation = $obj->rowid;

        $res = $dossierRachete->create();
        if($res !== false && $res > 0) $nb_commit++;
        else $nb_rollback++;
    }
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?><br />Nb Rollback : <?php echo $nb_rollback; ?>
