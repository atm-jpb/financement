<?php
set_time_limit(0);

require 'config.php';
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossier.class.php');

$limit = GETPOST('limit', 'int');

global $langs, $conf, $db;

$langs->load('financement@financement');

$sql = 'SELECT s.reference as ref_simul, cli.nom as client_name, e.label as partenaire, dfcli.reference as num_contrat_client,lea.nom as leaserNameSolde, dflea.reference as num_contrat_leaser, slea.nom as leaser_name';
$sql .= ', dr.date_debut_periode_client_m1, dr.date_fin_periode_client_m1, dr.solde_vendeur_m1, dr.solde_banque_m1, dr.solde_banque_nr_m1';   // Prev
$sql .= ', dr.date_debut_periode_client, dr.date_fin_periode_client, dr.solde_vendeur, dr.solde_banque, dr.solde_banque_nr';   // Curr
$sql .= ', dr.date_debut_periode_client_p1, dr.date_fin_periode_client_p1, dr.solde_vendeur_p1, dr.solde_banque_p1, dr.solde_banque_nr_p1';   // Next
$sql .= ', dr.choice, s.rowid, dflea.fk_soc, s.fk_leaser, dr.fk_dossier';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'entity e ON (s.entity = e.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.DossierRachete::$tablename.' dr ON (s.rowid = dr.fk_simulation)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dr.fk_dossier = dfcli.fk_fin_dossier AND dfcli.type = 'CLIENT')";
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (dr.fk_dossier = da.fk_fin_dossier)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe cli ON (a.fk_soc = cli.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dr.fk_dossier = dflea.fk_fin_dossier AND dflea.type = 'LEASER')";
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'llx_societe lea ON (dflea.fk_soc = lea.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe slea ON (s.fk_leaser = slea.rowid)';
$sql.= ' WHERE s.entity IN ('.getEntity('fin_simulation', true).')';
$sql.= " AND s.accord = 'OK'";
$sql.= " AND dr.choice <> 'no'";    // On veut des dossiers soldés
$sql.= " AND DATE_FORMAT(s.date_validite, '%Y-%m-%d') >= '".date('Y-m-d')."'";    // On prend les simuls dont la date de validité n'est pas dépassée
$sql.= " AND DATE_FORMAT(s.date_cre, '%Y-%m-%d') >= '2019-12-01'";
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$THead = [
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
];

if($conf->entity > 1) $path = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/export/soldes';
else $path = DOL_DATA_ROOT.'/financement/export/soldes';

if(! file_exists($path)) dol_mkdir($path);

$filename = 'extract_simul_soldes_'.date('Ymd-His').'.csv';
$f = fopen($path.'/'.$filename, 'w');
fputcsv($f, $THead, ';');

while($obj = $db->fetch_object($resql)) {
    // ça c'est la partie facile
    $TData = [
        $obj->ref_simul,
        $obj->client_name,
        $obj->partenaire,
        $obj->num_contrat_client,
        $obj->leaserNameSolde,
        $obj->num_contrat_leaser,
    ];

    $solde = TSimulation::getTypeSolde($obj->rowid, $obj->fk_dossier, $obj->fk_leaser);

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
print '<script type="text/javascript">';
print 'document.location.href = "'.dol_buildpath('/document.php?modulepart=financement&entity='.$conf->entity.'&file=export/soldes/'.$filename, 2).'";';
print '</script>';
