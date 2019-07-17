<?php

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

$sql = 'SELECT s.rowid, dr.fk_dossier, s.dossiers_rachetes_m1 as prev, s.dossiers_rachetes as curr, s.dossiers_rachetes_p1 as next';
$sql.= 's.dossiers_rachetes_nr_m1 as prev_nr, s.dossiers_rachetes_nr as curr_nr, s.dossiers_rachetes_nr_p1 as next_nr';
$sql.= ' FROM '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (dr.fk_simulation=s.rowid)';
$sql.= ' WHERE dr.choice IS NULL';
if(! empty($fk_simu)) $sql.= ' AND s.rowid = '.$fk_simu;
$sql.= ' ORDER BY dr.rowid';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$dr = new DossierRachete;
$nb_commit = $nb_rollback = 0;

/**
 * Il faut parcourir les 6 tableaux $prev(\_nr)?, $curr(\_nr)? et $next(\_nr)? pour savoir quelle période on a sélectionné pour quel dossier
 */
while($obj = $db->fetch_object($resql)) {
    $prev = unserialize($obj->prev);
    $curr = unserialize($obj->curr);
    $next = unserialize($obj->next);
    $prev_nr = unserialize($obj->prev_nr);
    $curr_nr = unserialize($obj->curr_nr);
    $next_nr = unserialize($obj->next_nr);

    if($prev !== false && is_array($prev) && array_key_exists($obj->fk_dossier, $prev) && array_key_exists('checked', $prev[$obj->fk_dossier])
    || $prev_nr !== false && is_array($prev_nr) && array_key_exists($obj->fk_dossier, $prev_nr) && array_key_exists('checked', $prev_nr[$obj->fk_dossier])) {
        $choice = 'prev';
    }
    else if($curr !== false && is_array($curr) && array_key_exists($obj->fk_dossier, $curr) && array_key_exists('checked', $curr[$obj->fk_dossier])
    || $curr_nr !== false && is_array($curr_nr) && array_key_exists($obj->fk_dossier, $curr_nr) && array_key_exists('checked', $curr_nr[$obj->fk_dossier])) {
        $choice = 'curr';
    }
    else if($next !== false && is_array($next) && array_key_exists($obj->fk_dossier, $next) && array_key_exists('checked', $next[$obj->fk_dossier])
    || $next_nr !== false && is_array($next_nr) && array_key_exists($obj->fk_dossier, $next_nr) && array_key_exists('checked', $next_nr[$obj->fk_dossier])) {
        $choice = 'next';
    }
    else {
        $choice = 'no';
    }

    $TDr = $dr->fetchAllBy(array('fk_dossier' => $obj->fk_dossier, 'fk_simulation' => $obj->rowid));
    if(! empty($TDr)) $dossierRachete = array_shift($TDr);

    $dossierRachete->choice = $choice;
    $dossierRachete->update();
}
$db->free($resql);
?>
<span>Capri... c'est fini !</span>