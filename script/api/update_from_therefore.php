<?php

if(! defined('NOLOGIN')) define('NOLOGIN', 1);

require '../../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/user/class/user.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');

$TGetpostData = array(
    'ref_dossier' => 'alpha',
    'montant_finance' => 'int',
    'duree' => 'int',
    'date_start' => '',
    'loyer_inter' => 'int',
    'frais_dossier' => 'int',
    'echeance' => 'int',
    'vr' => 'int',
    'terme' => 'int',
    'assurance' => 'int',
    'type' => ''
);
foreach($TGetpostData as $value => $check) {
    ${$value} = GETPOST($value, $check);
}

if(! empty($loyer_inter)) $loyer_inter = round($loyer_inter, 2);
if(! empty($montant_finance)) $montant_finance = round($montant_finance, 2);
if(empty($type)) $type = 'CLIENT';

// HTTP auth for GET method
_check_auth();

$PDOdb = new TPDOdb;
$dossier = new TFin_dossier;
$dossier->loadReference($PDOdb, $ref_dossier);

if($type == 'CLIENT') $financement = $dossier->financement;
else $financement = $dossier->financementLeaser;    // LEASER

$financement->montant = $montant_finance;
$financement->duree = $duree;
$financement->date_debut = $date_start;
$financement->loyer_intercalaire = $loyer_inter;
$financement->frais_dossier = $frais_dossier;
$financement->echeance = $echeance;
$financement->reste = $vr;
$financement->terme = $terme;
$financement->assurance = $assurance;

$res = $financement->save($PDOdb);

print $res."<br>\n";

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
