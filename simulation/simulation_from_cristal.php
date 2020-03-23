<?php

$method = empty($_GET['method']) ? 'PUT' : $_GET['method'];
$method = strtoupper($method);

if($method == 'GET' && ! defined('NOLOGIN')) define('NOLOGIN', 1);

require '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/user/class/user.class.php');
dol_include_once('/financement/class/simulation.class.php');

$code_artis = GETPOST('code_artis', 'alpha');
$fk_simu = GETPOST('fk_simu', 'int');
$fk_projet = GETPOST('id', 'int');
$duree = GETPOST('duree');
$budget = GETPOST('budget');
$loyer = GETPOST('loyer');
$coef = GETPOST('coef');
$type_materiel = GETPOST('type_materiel');
$montant_finance = GETPOST('montant_finance');

if(! empty($loyer)) $loyer = round($loyer, 2);
if(! empty($montant_finance)) $montant_finance = round($montant_finance, 2);

$fk_type_contrat = TSimulation::getTypeContratFromCristal(GETPOST('type_contrat'));
$periodicite = _get_periodicite(GETPOST('periodicite'));
$TEntity = TSimulation::getEntityFromCristalCode(GETPOST('code_cristal'));
if(empty($TEntity)) $TEntity[] = $conf->entity;

// HTTP auth for GET method
if($method == 'GET') _check_auth();

$PDOdb = new TPDOdb;
$simu = new TSimulation;
$simu->loadBy($PDOdb, $fk_projet, 'fk_projet_cristal');   // Vu avec Benjamin : 1 demande de fi par projet Cristal

if($method == 'GET') {
    _get_info($simu);
}

if(empty($simu->rowid)) {
    if(empty($code_artis)) {
        $siret = GETPOST('siret');
        if(empty($siret)) exit('empty siret!');

        $fk_soc = _get_socid('', $TEntity, $siret);

        // Créer la fiche client s'il n'existe pas
        if(empty($fk_soc)) {
            $soc = new Societe($db);
            $soc->name = GETPOST('nom');
            $soc->address = GETPOST('address');
            $soc->zip = GETPOST('cp');
            $soc->town = GETPOST('ville');
			$soc->country_id = 1;
			$soc->idprof1 = substr($siret,0,9);
            $soc->idprof2 = $siret;
            $soc->entity = $conf->entity;
			$soc->commercial_id = $user->id;
			$soc->client = 2;

            $fk_soc = $soc->create($user);
            if($fk_soc <= 0) {
                dol_print_error($db);
                exit;
            }
        }
    }
    else {
        // On récupère l'identifiant du Tiers avec son code_client
        $fk_soc = _get_socid($code_artis, $TEntity);
    }
}
else {
    $fk_soc = $simu->fk_soc;
}

if(empty($fk_soc)) {
    header('Location: '.dol_buildpath('/societe/list.php', 2).'?type=c');
    exit;
}

llxHeader();

if($method == 'PUT') {
    $url = dol_buildpath('/financement/simulation/simulation.php', 2);
    $TParam = array(
        'duree' => $duree,
        'montant' => $montant_finance,
        'echeance' => $loyer,
        'opt_periodicite' => $periodicite,
        'fk_type_contrat' => $fk_type_contrat,
        'type_materiel' => $type_materiel,
        'fk_projet_cristal' => $fk_projet,
        'fk_simu_cristal' => $fk_simu
    );

    if($simu->rowid > 0) {
        // Fetch reussi : On va sur la fiche en mode edit
        $url.= '?id='.$simu->rowid;
        $url.= '&action=edit';

        // Vu avec Benjamin : Si on trouve une simulation, on ne pré-rempli pas les données
        _get_autosubmit_form($url);
        exit;
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
        }
        else {
            // Une ou plusieurs simulations => LIST
            $url.= '?socid='.$fk_soc;
        }
    }
    _get_autosubmit_form($url, $TParam);

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
		$simu->opt_periodicite = _get_periodicite($simu->opt_periodicite, true);

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

function _get_periodicite($periodicite, $get_number = false) {
	$TPeriode = array(
		1 => 'MOIS',
		3 => 'TRIMESTRE',
		6 => 'SEMESTRE',
		12 => 'ANNEE'
	);

	if($get_number) {
		if(! in_array($periodicite, $TPeriode)) return 3;	// Default is 'TRIMESTRE'
		return array_search($periodicite, $TPeriode);	// Return the array key
	}
	else {
		if(! array_key_exists($periodicite, $TPeriode)) return 'TRIMESTRE';
		return $TPeriode[$periodicite];
	}
}

function _get_autosubmit_form($url, $TParam = array()) {
    ?>
    <form method="POST" id="to_submit" action="<?php echo $url; ?>">
    <?php
        foreach($TParam as $name => $value) print '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
    ?>
    </form>
    <script type="text/javascript">
        $('#to_submit').submit();
    </script>
    <?php
}

function _get_socid($code_artis, &$TEntity = array(), $siret = '') {
    global $conf, $db;

    if(empty($TEntity)) $TEntity[] = $conf->entity;
    $str_entities = implode(',', $TEntity);

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= ' WHERE entity IN ('.$db->escape($str_entities).')';
    if(! empty($code_artis)) $sql.= " AND code_client='".$db->escape($code_artis)."'";
    if(! empty($siret)) $sql.= " AND siret='".$db->escape($siret)."'";

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
