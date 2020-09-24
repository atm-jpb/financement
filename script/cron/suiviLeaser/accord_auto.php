#!/usr/bin/php
<?php
$a = microtime(true);
$path = dirname(__FILE__).'/';

define('INC_FROM_CRON_SCRIPT', true);
require_once $path.'../../../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/class/grille.class.php');

$PDOdb = new TPDOdb;
$fk_simu = GETPOST('fk_simu', 'int');
$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');
$debug = array_key_exists('debug', $_GET) || isset($argv[1]) && $argv[1] == 'debug';

global $user, $langs, $db, $conf;

$user->fetch(1035); // Admin_financement
$user->getrights();
$langs->load('financement@financement');

// On veut toutes les simul qui sont prêtes à un potentiel accord auto
$sql = 'SELECT s.rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= " WHERE s.accord = 'WAIT'";    // Toutes les simulations "En étude"
$subquery = 'SELECT DISTINCT fk_simulation FROM '.MAIN_DB_PREFIX."fin_simulation_suivi WHERE statut = 'OK'";
$sql.= ' AND s.rowid IN ('.$subquery.')';

if($debug) {
    print '<p>'.$sql.'</p><br />'."\n";
}

$resql2 = $db->query($sql);
if(! $resql2) {
    dol_print_error($db);
    exit;
}

$nb_commit = $nb_rollback = $nb_ignored = 0;
$nb_record = $db->num_rows($resql2);
if($debug) {
    print '<span>Nb record : ' . $nb_record . '</span><br />'."\n";
}

while($obj = $db->fetch_object($resql2)) {
    $simulation = new TSimulation;
    $simulation->load($PDOdb, $obj->rowid);

    if(empty($simulation->TSimulationSuivi)) {
        if($debug) {
            print '<pre><span style="background-color: #ff0000">&nbsp;&nbsp;&nbsp;</span> Simulation sans suivi Leaser !</pre>'."\n";
        }
        $nb_rollback++;
        continue;
    }

    $TSuivi = array_values($simulation->TSimulationSuivi);
    foreach($TSuivi as $k => $suivi) {
        if($suivi->statut != 'OK') continue;

        $suivi->accordAuto($PDOdb, $simulation);
    }
}
