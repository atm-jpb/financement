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
$sql = 'SELECT rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation';
$sql.= " WHERE accord = 'WAIT'";    // Toutes les simulations "En étude"
$subquery = 'SELECT DISTINCT fk_simulation FROM '.MAIN_DB_PREFIX."fin_simulation_suivi WHERE statut = 'KO'";    // On prend toutes les simuls qui ont au moins 1 refus
$sql.= ' AND rowid IN ('.$subquery.')';

if($debug) {
    print '<p>'.$sql.'</p><br />'."\n";
}

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$nb_commit = $nb_rollback = $nb_ignored = 0;
$nb_record = $db->num_rows($resql);
if($debug) {
    print '<span>Nb record : ' . $nb_record . '</span><br />'."\n";
}

while($obj = $db->fetch_object($resql)) {
    $simulation = new TSimulation;
    $simulation->load($PDOdb, $obj->rowid);

    if(empty($simulation->TSimulationSuivi)) {
        if($debug) {
            print '<pre><span style="background-color: #ff0000">&nbsp;&nbsp;&nbsp;</span> Simulation sans suivi Leaser !</pre>'."\n";
        }
        $nb_rollback++;
        continue;
    }

    $TSuiviStatut = array_unique(array_map(function($suivi) { return $suivi->statut; }, $simulation->TSimulationSuivi));

    if(count($TSuiviStatut) === 1 && array_shift($TSuiviStatut) === 'KO') {
        $simulation->accord = 'KO';
        $simulation->save($PDOdb);
    }
}
