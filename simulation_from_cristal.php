<?php

$method = empty($_GET['method']) ? 'PUT' : $_GET['method'];
$method = strtoupper($method);

if($method == 'GET' && ! defined('NOLOGIN')) define('NOLOGIN', 1);

require 'config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/financement/class/simulation.class.php');

$code_artis = GETPOST('code_artis', 'alpha');
$fk_simu = GETPOST('id', 'int');
$fk_projet = GETPOST('fk_projet', 'int');
$duree = GETPOST('duree');
$budget = GETPOST('budget');
$loyer = GETPOST('loyer');
$coef = GETPOST('coef');
$type_materiel = GETPOST('type_materiel');
$montant_finance = GETPOST('montant_finance');

$fk_type_contrat = TSimulation::getTypeContratFromCristal(GETPOST('type_contrat'));
$periodicite = _get_periodicite(GETPOST('periodicite'));
$TEntity = TSimulation::getEntityFromCristalCode(GETPOST('code_cristal'));
if(empty($TEntity)) $TEntity[] = $conf->entity;

if(empty($fk_simu)) exit('empty id!');

// HTTP auth for GET method
if($method == 'GET') _check_auth();

$soc = new Societe($db);
$PDOdb = new TPDOdb;
$simu = new TSimulation;
$simu->loadBy($PDOdb, $fk_simu, 'fk_simu_cristal');

if($method == 'GET') {
    _get_info($simu);
}

if(empty($code_artis)) {
    header('Location: '.dol_buildpath('/financement/simulation.php', 1));
    exit;
}

// On récupère l'identifiant du Tiers avec son code_client
$fk_soc = _get_socid_from_code_artis($code_artis, $TEntity);
if(empty($fk_soc)) {
    header('Location: '.dol_buildpath('/comm/list.php', 2));
    exit;
}

llxHeader();

if($method == 'PUT') {
    $url = dol_buildpath('/financement/simulation.php', 2);
    $TParam = array(
        'duree' => $duree,
        'montant' => $montant_finance,
        'echeance' => $loyer,
        'opt_periodicite' => $periodicite,
        'fk_type_contrat' => $fk_type_contrat,
        'type_materiel' => $type_materiel
    );

    if($simu->rowid > 0) {
        // Fetch reussi : On va sur la fiche en mode edit
        $url.= '?id='.$simu->rowid;
        $url.= '&action=edit';

        _get_autosubmit_form($url, $TParam);
    }
    else {
        // Simulation non présente sur LeaseBoard
        // On regarde si le Tiers a d'autres simulations
        $TSimu = TSimulation::getAllByCode($PDOdb, $simu, $fk_soc);

        $TSimu = _keep_valid_simulations($TSimu);
        $nb_simu = count($TSimu);

        if(empty($nb_simu)) {
            // Pas de simulations pour ce Tiers => NEW
            $TParam['action'] = 'new';
            $url.= '?fk_soc='.$fk_soc;
            _get_autosubmit_form($url, $TParam);
        }
        else {
            // Une ou plusieurs simulations => LIST
            $url.= '?socid='.$fk_soc;
            _get_autosubmit_form($url);
        }
    }
    exit;   // On est pas censé arriver ici
}

function _keep_valid_simulations($TSimulations) {
    $TSimu = array();

    foreach($TSimulations as $simulation) {
        if($simulation->date_validite > dol_now()) {
            $TSimu[] = $simulation;
        }
    }
    return $TSimu;
}

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

function _get_info(&$simu) {
    global $db;

    header('Content-Type: application/json');
    if($simu->rowid > 0) {
        $leaser = new Societe($db);
        $leaser->fetch($simu->fk_leaser);

        $simu->code_artis_leaser = $leaser->code_client;

        unset($simu->TChamps);
        unset($simu->TConstraint);
        if(empty($simu->TSimulationSuivi)) unset($simu->TSimulationSuivi);
        unset($simu->TStatut);
        unset($simu->TStatutIcons);
        unset($simu->TStatutShort);
        unset($simu->TTerme);
        unset($simu->TMarqueMateriel);
        unset($simu->user);

        print json_encode($simu);
    }
    else {
        // Simulation not found
        print json_encode(array(
            'error' => array(
                'code' => 404,
                'message' => 'Id not found'
            )
        ));
        http_response_code(404);
    }
    exit;
}

function _get_periodicite($periodicite) {
    switch($periodicite) {
        case 1:
            $res = 'MOIS';
            break;
        case 6:
            $res = 'SEMESTRE';
            break;
        case 12:
            $res = 'ANNEE';
            break;
        case 3:
        default:
            $res = 'TRIMESTRE';
            break;
    }

    return $res;
}

function _get_autosubmit_form($url, $TParam = array()) {
    ?>
    <form method="POST" id="to_submit" action="<?php echo $url; ?>">
    <?php
        if(! empty($TParam['action'])) print '<input type="hidden" name="action" value="'.$TParam['action'].'" />';

        if(! empty($TParam['duree'])) print '<input type="hidden" name="duree" value="'.$TParam['duree'].'" />';
        if(! empty($TParam['montant'])) print '<input type="hidden" name="montant" value="'.$TParam['montant'].'" />';
        if(! empty($TParam['echeance'])) print '<input type="hidden" name="echeance" value="'.$TParam['echeance'].'" />';
        if(! empty($TParam['opt_periodicite'])) print '<input type="hidden" name="opt_periodicite" value="'.$TParam['opt_periodicite'].'" />';
        if(! empty($TParam['fk_type_contrat'])) print '<input type="hidden" name="fk_type_contrat" value="'.$TParam['fk_type_contrat'].'" />';
        if(! empty($TParam['type_materiel'])) print '<input type="hidden" name="type_materiel" value="'.$TParam['type_materiel'].'" />';
    ?>
    </form>
    <script type="text/javascript">
        $('#to_submit').submit();
    </script>
    <?php
}

function _get_socid_from_code_artis($code_artis, &$TEntity = array()) {
    global $db;

    if(empty($TEntity)) $TEntity[] = $conf->entity;
    $str_entities = implode(',', $TEntity);

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= " WHERE code_client='".$db->escape($code_artis)."'";
    $sql.= ' AND entity IN ('.$db->escape($str_entities).')';

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        return -1;
    }

    if($obj = $db->fetch_object($resql)) {
        return $obj->rowid;
    }

    return 0;
}

llxFooter();