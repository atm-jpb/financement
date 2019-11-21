<?php
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/simulation.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

// Il faut récupérer les catégories de leaser pour savoir si on prendre le 'R' ou le 'NR'
$TLeaserCat = getLeaserCategory();
$PDOdb = new TPDOdb;

$sql = 'SELECT s.rowid, s.dossiers, s.dossiers_rachetes_nr, s.dossiers_rachetes_nr_m1, s.dossiers_rachetes_nr_p1, s.fk_leaser,';
$sql.= ' s.dossiers_rachetes_m1 as prev, s.dossiers_rachetes as curr, s.dossiers_rachetes_p1 as next';
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

    $dPrev = unserialize($obj->prev);
    $dCurr = unserialize($obj->curr);
    $dNext = unserialize($obj->next);
    $dossiers_nr_m1 = unserialize($obj->dossiers_rachetes_nr_m1);
    $dossiers_nr = unserialize($obj->dossiers_rachetes_nr);
    $dossiers_nr_p1 = unserialize($obj->dossiers_rachetes_nr_p1);
    if($dossiers === false) continue;

    foreach($dossiers as $fk_dossier => $TValue) {
        unset($TValue['leaser']);

        $TNext = explode('/', $TValue['numero_prochaine_echeance']);
        if(is_array($TNext) && ! empty($TNext)) {
            $next = array_shift($TNext);
        }

        $dossierRachete = new DossierRachete;
        $dossierRachete->set_values($TValue);

        // On renseigne le choice
        if(empty($dossierRachete->choice)) {
            if($dPrev !== false && is_array($dPrev) && array_key_exists($fk_dossier, $dPrev) && array_key_exists('checked', $dPrev[$fk_dossier])
                || $dossiers_nr_m1 !== false && is_array($dossiers_nr_m1) && array_key_exists($fk_dossier, $dossiers_nr_m1) && array_key_exists('checked', $dossiers_nr_m1[$fk_dossier])) {
                $choice = 'prev';
            }
            else if($dCurr !== false && is_array($dCurr) && array_key_exists($fk_dossier, $dCurr) && array_key_exists('checked', $dCurr[$fk_dossier])
                || $dossiers_nr !== false && is_array($dossiers_nr) && array_key_exists($fk_dossier, $dossiers_nr) && array_key_exists('checked', $dossiers_nr[$fk_dossier])) {
                $choice = 'curr';
            }
            else if($dNext !== false && is_array($dNext) && array_key_exists($fk_dossier, $dNext) && array_key_exists('checked', $dNext[$fk_dossier])
                || $dossiers_nr_p1 !== false && is_array($dossiers_nr_p1) && array_key_exists($fk_dossier, $dossiers_nr_p1) && array_key_exists('checked', $dossiers_nr_p1[$fk_dossier])) {
                $choice = 'next';
            }
            else {
                $choice = 'no';
            }

            $dossierRachete->choice = $choice;
        }

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

        if($dossierRachete->choice == 'prev') {
            $offset = -2;
            $dossierRachete->solde_banque_m1 = $dossierRachete->solde_banque_a_periode_identique;
        }
        elseif($dossierRachete->choice == 'curr') {
            $offset = -1;
            $dossierRachete->solde_banque = $dossierRachete->solde_banque_a_periode_identique;
        }
        elseif($dossierRachete->choice == 'next') {
            $offset = 0;
            $dossierRachete->solde_banque_p1 = $dossierRachete->solde_banque_a_periode_identique;
        }

        // On recalcule les soldes NR s'ils sont vides
        $doss = new TFin_dossier;
        $doss->load($PDOdb, $fk_dossier, false);
        $doss->load_affaire($PDOdb);    // Apparemment il faut load les affaires pour que le getSolde fonctionne...
        if(empty($dossierRachete->solde_banque_nr_m1) && ! empty($next)) {
            $dossierRachete->solde_banque_nr_m1 = $doss->getSolde($PDOdb, 'SNRBANK', ($next - $offset));
        }
        if(empty($dossierRachete->solde_banque_nr) && ! empty($next)) {
            $dossierRachete->solde_banque_nr = $doss->getSolde($PDOdb, 'SNRBANK', ($next - $offset));
        }
        if(empty($dossierRachete->solde_banque_nr_p1) && ! empty($next)) {
            $dossierRachete->solde_banque_nr_p1 = $doss->getSolde($PDOdb, 'SNRBANK', ($next - $offset));
        }

        $dossierRachete->fk_dossier = $fk_dossier;
        $dossierRachete->fk_simulation = $obj->rowid;

        $TSimulationSuivi = TSimulation::getSimulationSuivi($obj->rowid);

        // On détermine le type de solde
        $refus = false;
        foreach($TSimulationSuivi as $suivi) {
            if($TLeaserCat[$doss->financementLeaser->fk_soc] == $TLeaserCat[$suivi->fk_leaser] && $suivi->statut == 'KO') {
                $refus = true;
                break;
            }
        }

        if($refus || $TLeaserCat[$obj->fk_leaser] == $TLeaserCat[$doss->financementLeaser->fk_soc]) {
            $solde = 'R';
        }
        else {
            $solde = 'NR';
        }
        $dossierRachete->type_solde = $solde;

        $res = $dossierRachete->create();
        if($res !== false && $res > 0) $nb_commit++;
        else $nb_rollback++;
    }
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?></span><br />
<span>Nb Rollback : <?php echo $nb_rollback; ?></span>

<?php

/**
 * @return array
 */
function getLeaserCategory() {
    global $db;

    $TRes = array();
    $sql = 'SELECT cf.fk_societe as fk_soc, cf.fk_categorie as fk_cat';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'categorie_fournisseur cf';
    $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie c ON (c.rowid = cf.fk_categorie)';
    $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie c2 ON (c2.rowid = c.fk_parent)';
    $sql .= " WHERE c2.label = 'Leaser'";

    $resql = $db->query($sql);
    if($resql) {
        while($obj = $db->fetch_object($resql)) $TRes[$obj->fk_soc] = $obj->fk_cat;
    }

    return $TRes;
}
