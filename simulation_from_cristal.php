<?php


if(! defined('NOLOGIN')) define('NOLOGIN', 1);

require 'config.php';
dol_include_once('/financement/class/dossier.class.php');   // à voir si on en a besoin
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

$type_contrat = GETPOST('type_contrat');
$fk_type_contrat = TSimulation::getTypeContratFromCristal($type_contrat);

$periodicite = GETPOST('periodicite');
switch($periodicite) {
    case 1:
        $periodicite = 'MOIS';
        break;
    case 3:
        $periodicite = 'TRIMESTRE';
        break;
    case 6:
        $periodicite = 'SEMESTRE';
        break;
    case 12:
        $periodicite = 'ANNEE';
        break;
}

$montant_finance = GETPOST('montant_finance');

$method = GETPOST('method');
$method = strtoupper($method);

$code_entity = GETPOST('code_cristal');
$TEntity = TSimulation::getEntityFromCristalCode($code_entity);
if(empty($TEntity)) $TEntity[] = $conf->entity;

if(empty($fk_simu)) exit('empty fk_simu!');

$user = new User($db);
$res = $user->fetch('', $_SERVER['PHP_AUTH_USER']);

$auth_error = false;
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

$soc = new Societe($db);
$PDOdb = new TPDOdb;
$simu = new TSimulation;
$simu->load($PDOdb, $db, $fk_simu, false);

if($method == 'GET') {
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
if(empty($code_artis)) {
    header('Location: '.dol_buildpath('/financement/simulation.php', 1));
    exit;
}

llxHeader();

if($method == 'PUT') {
    $url = dol_buildpath('/financement/simulation.php', 2);

    if($simu->rowid > 0) {
        // Fetch reussi : On va sur la fiche en mode edit
        $url.= '?id='.$fk_simu;
        $url.= '&action=edit';

        ?>
        <form method="POST" id="to_submit" action="<?php echo $url; ?>">
            <?php
            if(! empty($duree)) print '<input type="hidden" name="duree" value="'.$duree.'" />';
            if(! empty($montant_finance)) print '<input type="hidden" name="montant" value="'.$montant_finance.'" />';
            if(! empty($loyer)) print '<input type="hidden" name="echeance" value="'.$loyer.'" />';
            if(! empty($periodicite)) print '<input type="hidden" name="opt_periodicite" value="'.$periodicite.'" />';
            if(! empty($fk_type_contrat)) print '<input type="hidden" name="fk_type_contrat" value="'.$fk_type_contrat.'" />';
            if(! empty($type_materiel)) print '<input type="hidden" name="type_materiel" value="'.$type_materiel.'" />';
            ?>
        </form>
        <script type="text/javascript">
            $('#to_submit').submit();
        </script>
        <?php
        exit;   // On est pas censé arriver ici
    }
    else {
        // Simulation non présente sur LeaseBoard
        // On regarde si le Tiers a d'autres simulations
        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
        $sql.= " WHERE code_client='".$db->escape($code_artis)."'";
        $sql.= ' AND entity IN ('.$db->escape(implode(',', $TEntity)).')';

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        if($obj = $db->fetch_object($resql)) {
            $list = false;

            $TSimu = $simu->load_by_soc($PDOdb, $db, $obj->rowid);
            $nb_simu = count($TSimu);

            if(empty($nb_simu)) $action = 'new';   // Pas de simulations pour ce Tiers => NEW
            else if($nb_simu == 1) {
                // Une simulation existe déjà pour ce Tiers => EDIT
                $simulation = array_shift($TSimu);
                $url.= '?id='.$simulation->rowid;
                $action = 'edit';
            }
            else {
                // Plusieurs simulations => LIST
                $url = '?socid='.$obj->rowid;
                $list = true;
            }

            ?>
            <form id="to_submit" action="<?php echo $url; ?>">
                <?php
                if(! empty($action)) {
                ?>
                    <input type="hidden" name="action" value="<?php echo $action; ?>" />
                <?php
                }
                if(! $list) {
                    if(! empty($duree)) print '<input type="hidden" name="duree" value="'.$duree.'" />';
                    if(! empty($montant_finance)) print '<input type="hidden" name="montant" value="'.$montant_finance.'" />';
                    if(! empty($loyer)) print '<input type="hidden" name="echeance" value="'.$loyer.'" />';
                    if(! empty($periodicite)) print '<input type="hidden" name="opt_periodicite" value="'.$periodicite.'" />';
                    if(! empty($type_contrat)) print '<input type="hidden" name="fk_type_contrat" value="'.$type_contrat.'" />';
                    if(! empty($type_materiel)) print '<input type="hidden" name="type_materiel" value="'.$type_materiel.'" />';
                }
                ?>
            </form>
            <script type="text/javascript">
                $('#to_submit').submit();
            </script>
            <?php
            exit;
        }
    }
}

function _has_valid_simulations(&$PDOdb, $socid) {
    global $db;

    $simu = new TSimulation;
    $TSimulations = $simu->load_by_soc($PDOdb, $db, $socid);

    foreach($TSimulations as $simulation) {
        if($simulation->date_validite > dol_now()) {
            return true;
        }
    }
    return false;
}

llxFooter();