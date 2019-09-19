<?php

if(! defined('NOLOGIN')) define('NOLOGIN', 1);

require '../../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

// HTTP auth for GET method
_check_auth();

$ref_dossier = GETPOST('ref_dossier', 'alpha');
$montant_finance = GETPOST('montant_finance', 'int');
$periodicite = GETPOST('periodicite');
$duree = GETPOST('duree');
$date_start = GETPOST('date_start');
$loyer_inter = GETPOST('loyer_inter', 'int');
$frais_dossier = GETPOST('frais_dossier', 'int');
$echeance = GETPOST('echeance', 'int');
$vr = GETPOST('vr', 'int');
$terme = GETPOST('terme', 'int');
$assurance = GETPOST('assurance', 'int');
$mode_reglement = GETPOST('mode_reglement');
$ref_dossier_leaser = GETPOST('ref_dossier_leaser', 'alpha');
$montant_finance_leaser = GETPOST('montant_finance_leaser', 'int');
$leaser = GETPOST('nom_leaser', 'alpha');
$entityLabel = GETPOST('source');

if(! empty($loyer_inter)) $loyer_inter = round($loyer_inter, 2);
if(! empty($montant_finance)) $montant_finance = round($montant_finance, 2);

_check_dossier($ref_dossier);

$user->fetch('', 'admin_financement');
$user->getrights();

$PDOdb = new TPDOdb;
$dossier = new TFin_dossier;
$dossier->loadReference($PDOdb, $ref_dossier, false, $entity);
if(empty($dossier->rowid)) {
    header('Content-Type: application/json');
    print json_encode(array(
            'error' => array(
                'code' => 400,
                'message' => 'Invalid dossier'
            )
        ));
    http_response_code(400);
    exit;
}

// La référence Leaser existe déjà, on ne met pas à jour
if(! empty($dossier->financementLeaser->reference)) {
    header('Content-Type: application/json');
    print json_encode(array(
        'error' => array(
            'code' => 200,
            'message' => 'OK'
        )
    ));
    http_response_code(200);
    exit;
}

$dossier->load_affaire($PDOdb);
$fk_leaser = _getLeaserByName($leaser);

if(in_array($fk_leaser, array(19068, 19483)) && $duree == 22 && $periodicite == 'TRIMESTRE') $duree_leaser = 21;   // Spécifique Lixxbail Adossé ou Mandaté

if(! empty($date_start)) $date_start_leaser = _get_date($date_start);
if(! empty($periodicite) && ! empty($montant_finance) && ! empty($duree)) $echeance_leaser = _get_echeance($PDOdb, $fk_leaser, $dossier->TLien[0]->affaire->contrat, $periodicite, $montant_finance, $duree);
$vr_leaser = _get_vr($fk_leaser);

if($dossier->nature_financement == 'INTERNE') { // Côté Client
    if(! empty($montant_finance)) $dossier->financement->montant = $montant_finance;
    if(! empty($periodicite)) $dossier->financement->periodicite = $periodicite;
    if(! empty($duree)) $dossier->financement->duree = $duree;
    if(! empty($date_start)) $dossier->financement->date_debut = $date_start;
    if(! empty($loyer_inter)) $dossier->financement->loyer_intercalaire = $loyer_inter;
    if(! empty($frais_dossier)) $dossier->financement->frais_dossier = $frais_dossier;
    if(! empty($echeance)) $dossier->financement->echeance = $echeance;
    if(! empty($vr)) $dossier->financement->reste = $vr;
    if(! empty($terme)) $dossier->financement->terme = $terme;
    if(! empty($assurance)) $dossier->financement->assurance = $assurance;
    if(! empty($mode_reglement)) $dossier->financement->reglement = $mode_reglement;
}

// Côté Leaser
if(! empty($fk_leaser)) $dossier->financementLeaser->fk_soc = $fk_leaser;
if(! empty($ref_dossier_leaser)) $dossier->financementLeaser->reference = $ref_dossier_leaser;
if(! empty($montant_finance_leaser)) $dossier->financementLeaser->montant = $montant_finance_leaser;
if(! empty($date_start_leaser)) $dossier->financementLeaser->date_debut = strtotime($date_start_leaser);
if(! empty($duree_leaser)) {    // Cas du spécifique Lixxbail
    $dossier->financementLeaser->duree = $duree_leaser;
}
else {
    $dossier->financementLeaser->duree = $duree;
}
if(! empty($echeance_leaser)) $dossier->financementLeaser->echeance = $echeance_leaser;
if(! empty($vr_leaser)) $dossier->financementLeaser->reste = $vr_leaser;

$res = $dossier->save($PDOdb);

$TRes = array('code' => 200, 'message' => 'OK');
if($res === false) $TRes = array('code' => 400, 'message' => 'KO');
header('Content-Type: application/json');
print json_encode(array(
    'error' => $TRes
));
http_response_code($TRes['code']);
exit;

function _check_auth() {
    global $db, $user;

    $auth_error = false;

    $user = new User($db);
    $res = $user->fetch('', $_SERVER['PHP_AUTH_USER']);

    if($res > 0) {
        if(md5($_SERVER['PHP_AUTH_PW']) != $user->pass_indatabase_crypted) $auth_error = true;
    }
    else $auth_error = true;

    if($auth_error) {
        header('Content-Type: application/json');
        print json_encode(array(
                'error' => array(
                    'code' => 401,
                    'message' => 'Bad Credentials'
                )
            ));
        http_response_code(401);
        exit;
    }
    else {
        $user->getrights('financement');
    }
}

function _check_dossier($ref_dossier) {
    if(empty($ref_dossier)) {
        header('Content-Type: application/json');
        print json_encode(array(
                'error' => array(
                    'code' => 400,
                    'message' => 'Empty ref_dossier'
                )
            ));
        http_response_code(400);
        exit;
    }
}

/**
 * @param int $date timestamp
 * @return false|string
 */
function _get_date($date) {
    if(in_array(date('dm', $date), array('0101', '0104', '0107', '0110'))) return date('Y-m-d', $date);

    $calc = (3 - ((date('n', $date)-1) % 3 ));  // Get nb day to add
    $datet = date('Y-m-d', strtotime('first day of +'.$calc.' month', $date));  // Get next quarter
    return $datet;
}

function _get_echeance($PDOdb, $fk_leaser, $type_contrat, $periodicite, $montant, $duree) {
    $grille = new TFin_grille_leaser;
    $TCoef = $grille->get_coeff($PDOdb, $fk_leaser, $type_contrat, $periodicite, $montant, $duree);

    $coef = 0;
    if(is_array($TCoef)) $coef = $TCoef[0];

    return $montant * $coef/100;
}

function _get_vr($fk_leaser) {
    switch($fk_leaser) {
        default:
            return 0.15;
    }
}

/**
 * @param   string $entityLabel
 * @return  int
 */
function _getEntityByLabel($entityLabel) {
    global $db;

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'entity';
    $sql.= " WHERE label LIKE '".$db->escape($entityLabel)."'";

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    if($obj = $db->fetch_object($resql)) {
        return $obj->rowid;
    }

    return 0;
}

/**
 * @param   string $leaserName
 * @return  int
 */
function _getLeaserByName($leaserName) {
    global $db;

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= " WHERE nom LIKE '".$db->escape($leaserName)."'";
    $sql.= ' AND fournisseur = 1';

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    if($obj = $db->fetch_object($resql)) return $obj->rowid;

    return 0;
}