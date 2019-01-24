<?php

if(! defined('NOLOGIN')) define('NOLOGIN', 1);

require '../../config.php';
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/user/class/user.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

// HTTP auth for GET method
_check_auth();

$TGetpostData = array(
    'ref_dossier' => 'alpha',
    'montant_finance' => 'int',
    'periodicite' => '',
    'duree' => 'int',
    'date_start' => '',
    'loyer_inter' => 'int',
    'frais_dossier' => 'int',
    'echeance' => 'int',
    'vr' => 'int',
    'terme' => 'int',
    'assurance' => 'int',
    'type' => '',
    'fk_leaser' => 'int',   // Voir si possible d'identifier le leaser comme ça
    'mode_reglement' => ''
);
foreach($TGetpostData as $value => $check) {
    ${$value} = GETPOST($value, $check);
}

if(! empty($loyer_inter)) $loyer_inter = round($loyer_inter, 2);
if(! empty($montant_finance)) $montant_finance = round($montant_finance, 2);
if(empty($type)) $type = 'CLIENT';

_check_dossier($ref_dossier);

$PDOdb = new TPDOdb;
$dossier = new TFin_dossier;
$dossier->loadReference($PDOdb, $ref_dossier);
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

$dossier->load_affaire($PDOdb);

if($type == 'CLIENT') $financement = &$dossier->financement;
else {  // LEASER
    $financement = &$dossier->financementLeaser;
    if(in_array($fk_leaser, array(19068, 19483)) && $duree == 22 && $periodicite == 'TRIMESTRE') $duree = 21;   // Spécifique Lixxbail Adossé ou Mandaté

    $date_start = _get_date($date_start);
    $echeance = _get_echeance($PDOdb, $fk_leaser, $dossier->TLien[0]->affaire->contrat, $periodicite, $montant_finance, $duree);
    $vr = _get_vr($fk_leaser);
}

$financement->montant = $montant_finance;
$financement->periodicite = $periodicite;
$financement->duree = $duree;
$financement->date_debut = $date_start;
$financement->loyer_intercalaire = $loyer_inter;
$financement->frais_dossier = $frais_dossier;
$financement->echeance = $echeance;
$financement->reste = $vr;
$financement->terme = $terme;
$financement->assurance = $assurance;
$financement->reglement = $mode_reglement;

$res = $dossier->save($PDOdb);

print $res."<br />\n";

function _check_auth() {
    global $db;

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
 * @param int $date    timestamp
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
