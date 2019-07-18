<?php
set_time_limit(0);

require 'config.php';
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossier.class.php');

$limit = GETPOST('limit', 'int');

$langs->load('financement@financement');
$PDOdb = new TPDOdb;

// Il faut récupérer les catégories de leaser pour savoir si on prendre le 'R' ou le 'NR'
$TLeaserCat = getLeaserCategory();

$sql = 'SELECT s.reference as ref_simul, cli.nom as client_name, e.label as partenaire, dfcli.reference as num_contrat_client, dflea.reference as num_contrat_leaser, slea.nom as leaser_name';
$sql .= ', dr.date_debut_periode_client_m1, dr.date_fin_periode_client_m1, dr.solde_vendeur_m1, dr.solde_banque_m1, dr.solde_banque_nr_m1';   // Prev
$sql .= ', dr.date_debut_periode_client, dr.date_fin_periode_client, dr.solde_vendeur, dr.solde_banque, dr.solde_banque_nr';   // Curr
$sql .= ', dr.date_debut_periode_client_p1, dr.date_fin_periode_client_p1, dr.solde_vendeur_p1, dr.solde_banque_p1, dr.solde_banque_nr_p1';   // Next
$sql .= ', choice, s.rowid, dflea.fk_soc';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'entity e ON (s.entity = e.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr ON (s.rowid = dr.fk_simulation)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dr.fk_dossier = dfcli.fk_fin_dossier AND dfcli.type = 'CLIENT')";
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe cli ON (dfcli.fk_soc = cli.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dr.fk_dossier = dflea.fk_fin_dossier AND dflea.type = 'LEASER')";
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe slea ON (s.fk_leaser = slea.rowid)';
$sql.= ' WHERE s.entity IN ('.getEntity('fin_simulation', true).')';
$sql.= " AND s.accord = 'OK'";
$sql.= " AND dr.choice <> 'no'";    // On veut des dossiers soldés
$sql.= " AND DATE_FORMAT(s.date_validite, '%Y-%m-%d') >= '".date('Y-m-d')."'";    // On prend les simuls dont la date de validité n'est pas dépassée
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$THead = array(
    'Num_simul',
    'client',
    'partenaire',
    'num_dossier_client',
    'num_dossier_leaser',
    'montant_vendeur',
    'montant_leaser',
    'periode_solde',
    'date_debut_periode',
    'date_fin_periode',
    'type_solde',
    'leaser_accord'
);

if($conf->entity > 1) $path = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/export/soldes';
else $path = DOL_DATA_ROOT.'/financement/export/soldes';

if(! file_exists($path)) dol_mkdir($path);

$filename = 'extract_simul_soldes_'.date('Ymd-His').'.csv';
$f = fopen($path.'/'.$filename, 'w');
fputcsv($f, $THead, ';');

while($obj = $db->fetch_object($resql)) {
    // ça c'est la partie facile
    $TData = array(
        $obj->ref_simul,
        $obj->client_name,
        $obj->partenaire,
        $obj->num_contrat_client,
        $obj->num_contrat_leaser,
    );

    $simu = new TSimulation;
    $simu->load($PDOdb, $obj->rowid, false);
    $simu->load_suivi_simulation($PDOdb);

    $refus = false;
    foreach($simu->TSimulationSuivi as $suivi) {
        if($TLeaserCat[$obj->fk_soc] == $TLeaserCat[$suivi->fk_leaser] && $suivi->statut == 'KO') {
            $refus = true;
            break;
        }
    }

    if($refus || $TLeaserCat[$simu->fk_leaser] == $TLeaserCat[$obj->fk_soc]) {
        $solde = 'R';
    }
    else {
        $solde = 'NR';
    }

    if($obj->choice == 'prev') {
        $TData[] = $obj->solde_vendeur_m1;
        if($solde == 'R') $TData[] = $obj->solde_banque_m1;
        else $TData[] = $obj->solde_banque_nr_m1;
        $TData[] = $obj->choice;
        $TData[] = $obj->date_debut_periode_client_m1;
        $TData[] = $obj->date_fin_periode_client_m1;
    }
    elseif($obj->choice == 'curr') {
        $TData[] = $obj->solde_vendeur;
        if($solde == 'R') $TData[] = $obj->solde_banque;
        else $TData[] = $obj->solde_banque_nr;
        $TData[] = $obj->choice;
        $TData[] = $obj->date_debut_periode_client;
        $TData[] = $obj->date_fin_periode_client;
    }
    else {  // 'next'
        $TData[] = $obj->solde_vendeur_p1;
        if($solde == 'R') $TData[] = $obj->solde_banque_p1;
        else $TData[] = $obj->solde_banque_nr_p1;
        $TData[] = $obj->choice;
        $TData[] = $obj->date_debut_periode_client_p1;
        $TData[] = $obj->date_fin_periode_client_p1;
    }
    $TData[] = $solde;
    $TData[] = $obj->leaser_name;

    fputcsv($f, $TData, ';');
}

fclose($f);

// Pour download le fichier
print '<script language="javascript">';
print 'document.location.href = "'.dol_buildpath('/document.php?modulepart=financement&entity='.$conf->entity.'&file=export/soldes/'.$filename, 2).'";';
print '</script>';

/**
 * @return array
 */
function getLeaserCategory() {
    global $db;

    $TRes = array();
    $sql = 'SELECT cf.fk_societe as fk_soc, cf.fk_categorie as fk_cat';
    $sql .= ' FROM llx_categorie_fournisseur cf';
    $sql .= ' LEFT JOIN llx_categorie c ON (c.rowid = cf.fk_categorie)';
    $sql .= ' LEFT JOIN llx_categorie c2 ON (c2.rowid = c.fk_parent)';
    $sql .= " WHERE c2.label = 'Leaser'";

    $resql = $db->query($sql);
    if($resql) {
        while($obj = $db->fetch_object($resql)) $TRes[$obj->fk_soc] = $obj->fk_cat;
    }

    return $TRes;
}
