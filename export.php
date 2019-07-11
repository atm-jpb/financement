<?php
set_time_limit(0);
//ini_set('display_errors', true);

require 'config.php';
//require DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
//dol_include_once('/financement/class/import.class.php');
//dol_include_once('/financement/class/import_error.class.php');
//dol_include_once('/financement/class/commerciaux.class.php');
//dol_include_once('/financement/class/affaire.class.php');
//dol_include_once('/financement/class/dossier.class.php');
//dol_include_once('/financement/class/grille.class.php');
//dol_include_once('/financement/class/score.class.php');
//dol_include_once('/financement/lib/financement.lib.php');
//dol_include_once('/asset/class/asset.class.php');
//dol_include_once('/societe/class/societe.class.php');
//dol_include_once('/compta/facture/class/facture.class.php');
//dol_include_once('/product/class/product.class.php');
//dol_include_once('/core/class/html.form.class.php');
//dol_include_once('/fourn/class/fournisseur.facture.class.php');
//
//require_once(DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php');

$limit = GETPOST('limit', 'int');

$langs->load('financement@financement');
//$PDOdb = new TPDOdb;

$sql = 'SELECT s.rowid, e.label';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'entity e ON (s.entity = e.rowid)';
$sql.= ' WHERE s.entity IN ('.getEntity('fin_simulation', true).')';
$sql.= " AND s.accord = 'OK'";
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

$filename = 'extract_simul_soldes_'.date('Ymd-His').'.csv';
$f = fopen($filename, 'w');
fputcsv($f, $THead, ';');

while($obj = $db->fetch_object($resql)) {
    // TODO: Construire le fichier CSV !
}

fclose($f);
