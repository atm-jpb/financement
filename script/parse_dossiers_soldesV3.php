<?php
set_time_limit(0);
ini_set('memory_limit', '256M');

/*
 * Ce script doit corriger tous les dossiers rachetés pour renseigner correctement les soldes banque NR
 * Et également recalculer les soldes banque R pour s'assurer qu'ils sont corrects
 */

require_once('../config.php');
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$PDOdb = new TPDOdb;

$limit = GETPOST('limit', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

$sql = 'SELECT dr.fk_dossier, dr.rowid, dr.numero_prochaine_echeance, dr.type_solde';
$sql.= ' FROM '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr';
if(! empty($fk_simu)) $sql.= ' WHERE dr.fk_simulation = '.$fk_simu;
$sql.= ' ORDER BY dr.rowid';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$PDOdb = new TPDOdb;
$nbRow = $db->num_rows($resql);

while($obj = $db->fetch_object($resql)) {
    $TNext = explode('/', $obj->numero_prochaine_echeance);
    if(is_array($TNext) && ! empty($TNext)) {
        $next = array_shift($TNext);
    }

    $doss = new TFin_dossier;
    $res = $doss->load($PDOdb, $obj->fk_dossier, false);
    if($res === false) continue;    // Si on ne réussit pas à load, ça sert à rien de continuer : le dossier a été delete

    $doss->load_affaire($PDOdb);    // Apparemment il faut load les affaires pour que le getSolde fonctionne...

    $dossierRachete = new DossierRachete;
    $dossierRachete->load($PDOdb, $obj->rowid);

    // On met à jour les soldes NR
    if($obj->type_solde === 'NR') {
        $dossierRachete->solde_banque_nr_m1 = $doss->getSolde($PDOdb, 'SNRBANK', ($next - 2));
        $dossierRachete->solde_banque_nr = $doss->getSolde($PDOdb, 'SNRBANK', ($next - 1));
        $dossierRachete->solde_banque_nr_p1 = $doss->getSolde($PDOdb, 'SNRBANK', $next);
    }

    // On recalcule les soldes R
    $soldeBanqueR_m1 = $doss->getSolde($PDOdb, 'SRBANK', ($next - 2));
    $soldeBanqueR = $doss->getSolde($PDOdb, 'SRBANK', ($next - 1));
    $soldeBanqueR_p1 = $doss->getSolde($PDOdb, 'SRBANK', $next);

    // On met à jour les soldes R s'ils sont différents
    if(! empty($soldeBanqueR_m1) && $soldeBanqueR_m1 != $dossierRachete->solde_banque_m1) $dossierRachete->solde_banque_m1 = $soldeBanqueR_m1;
    if(! empty($soldeBanqueR) && $soldeBanqueR != $dossierRachete->solde_banque) $dossierRachete->solde_banque = $soldeBanqueR;
    if(! empty($soldeBanqueR_p1) && $soldeBanqueR_p1 != $dossierRachete->solde_banque_p1) $dossierRachete->solde_banque_p1 = $soldeBanqueR_p1;

    $dossierRachete->save($PDOdb);
}
$db->free($resql);
?>
<span>Nb rows : <?php echo $nbRow; ?></span>
