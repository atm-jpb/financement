<?php
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

$PDOdb = new TPDOdb;

$sql = 'SELECT s.rowid, s.dossiers, s.dossiers_rachetes_nr, s.dossiers_rachetes_nr_m1, s.dossiers_rachetes_nr_p1';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr ON (dr.fk_simulation=s.rowid)';
$sql.= ' WHERE s.dossiers IS NOT NULL';
$sql.= " AND s.dossiers <> ''";
$sql.= " AND s.dossiers <> 'b:0;'";
$sql.= ' AND dr.rowid IS NULL'; // On prend celles qui n'ont pas (encore ?) de dossiers rachetes
if(! empty($fk_simu)) $sql.= ' AND s.rowid = '.$fk_simu;
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
    $dossiers_nr_m1 = unserialize($obj->dossiers_rachetes_nr_m1);
    $dossiers_nr = unserialize($obj->dossiers_rachetes_nr);
    $dossiers_nr_p1 = unserialize($obj->dossiers_rachetes_nr_p1);
    if($dossiers === false) continue;

    foreach($dossiers as $fk_dossier => $TValue) {
        unset($TValue['leaser']);
        $next = 0;

        if(! empty($TValue['numero_prochaine_echeance'])) {
            $TProchaineEcheance = explode('/', $TValue['numero_prochaine_echeance']);
            $next = $TProchaineEcheance[1]; // Ca représente le numéro de prochaine echéance leaser
        }

        $dossierRachete = new DossierRachete;
        $dossierRachete->set_values($TValue);

        // On récupère les soldes NR s'ils existent
        if($dossiers_nr_m1 !== false && is_array($dossiers_nr_m1) && array_key_exists($fk_dossier, $dossiers_nr_m1) && array_key_exists('montant', $dossiers_nr_m1[$fk_dossier])) {
            $dossierRachete->solde_banque_nr_m1 = $dossiers_nr_m1[$fk_dossier]['montant'];
        }

        if($dossiers_nr !== false && is_array($dossiers_nr) && array_key_exists($fk_dossier, $dossiers_nr) && array_key_exists('montant', $dossiers_nr[$fk_dossier])) {
            $dossierRachete->solde_banque_nr = $dossiers_nr[$fk_dossier]['montant'];
        }

        if($dossiers_nr_p1 !== false && is_array($dossiers_nr_p1) && array_key_exists($fk_dossier, $dossiers_nr_p1) && array_key_exists('montant', $dossiers_nr_p1[$fk_dossier])) {
            $dossierRachete->solde_banque_nr_p1 = $dossiers_nr_p1[$fk_dossier]['montant'];
        }

        // On recalcule les soldes NR s'ils sont vides
        $doss = new TFin_dossier;
        $doss->load($PDOdb, $fk_dossier, false);
        if(empty($dossierRachete->solde_banque_nr_m1) && ! empty($next)) $dossierRachete->solde_banque_nr_m1 = $doss->getSolde($PDOdb, 'SNRBANK', ($next-2));
        if(empty($dossierRachete->solde_banque_nr) && ! empty($next)) $dossierRachete->solde_banque_nr = $doss->getSolde($PDOdb, 'SNRBANK', ($next-1));
        if(empty($dossierRachete->solde_banque_nr_p1) && ! empty($next)) $dossierRachete->solde_banque_nr_p1 = $doss->getSolde($PDOdb, 'SNRBANK', $next);

        $dossierRachete->fk_dossier = $fk_dossier;
        $dossierRachete->fk_simulation = $obj->rowid;

        $res = $dossierRachete->create();
        if($res !== false && $res > 0) $nb_commit++;
        else $nb_rollback++;
    }
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?></span><br />
<span>Nb Rollback : <?php echo $nb_rollback; ?></span>
