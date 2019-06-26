<?php

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');

$debug = array_key_exists('debug', $_GET);
$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

$sql = 'SELECT s.rowid, s.dossiers';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossiers_rachetes dr ON (dr.fk_simulation=s.rowid)';
$sql.= " WHERE s.dossiers IS NOT NULL AND s.dossiers <> ''";
$sql.= " AND s.dossiers <> 'b:0;'";
//$sql.= ' AND s.dossiers <> "b:0;"';
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
    if($debug) {
        var_dump($dossiers, '---------------');
    }

    foreach($dossiers as $fk_dossier => $TValue) {
        $dossierRachete = new DossierRachete;

        unset($TValue['leaser']);
        foreach($TValue as $field => $value) {
            if($field == 'object_leaser') {
                $dossierRachete->fk_leaser = $value->id;
            }
            elseif(preg_match('/(date\_)(debut|fin)(\_periode\_)(client|leaser)(\_m1|\_p1)?/', $field)) {
                $lo_value = strtotime($value);
                if($lo_value !== false) $dossierRachete->$field = $lo_value;
            }
            else $dossierRachete->$field = $value;
        }
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
