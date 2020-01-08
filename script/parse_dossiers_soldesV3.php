<?php
set_time_limit(0);
ini_set('memory_limit', '256M');

/*
 * Ce script doit corriger tous les dossiers rachetés pour renseigner correctement le solde banque NR
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

$sql = 'SELECT dr.fk_dossier, dr.rowid, dr.numero_prochaine_echeance';
$sql.= ' FROM '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (dr.fk_simulation = s.rowid)';
$sql.= " WHERE dr.type_solde = 'NR'";    // On ne corrige que les soldes NR
if(! empty($fk_simu)) $sql.= ' AND s.rowid = '.$fk_simu;
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
    $doss->load($PDOdb, $obj->fk_dossier, false);
    $doss->load_affaire($PDOdb);    // Apparemment il faut load les affaires pour que le getSolde fonctionne...

    $dossierRachete = new DossierRachete;
    $dossierRachete->load($PDOdb, $obj->rowid);

    $dossierRachete->solde_banque_nr_m1 = $doss->getSolde($PDOdb, 'SNRBANK', ($next - 2));
    $dossierRachete->solde_banque_nr = $doss->getSolde($PDOdb, 'SNRBANK', ($next - 1));
    $dossierRachete->solde_banque_nr_p1 = $doss->getSolde($PDOdb, 'SNRBANK', $next);

    $dossierRachete->type_solde = $solde;

    $dossierRachete->save($PDOdb);
}
$db->free($resql);
?>
<span>Nb rows : <?php echo $nbRow; ?></span>
