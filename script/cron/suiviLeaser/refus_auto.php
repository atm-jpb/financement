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

// On veut toutes les simul qui sont prêtes à un potentiel refus auto
$sql = "SELECT ss.fk_simulation, count(*) as total, sum(case ss.statut when 'KO' then 1 else 0 end) as nbKO";
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation_suivi ss';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (ss.fk_simulation = s.rowid)';
$sql.= " WHERE accord = 'WAIT'";    // Toutes les simulations "En étude"
$sql.= ' AND s.entity = 18';    // Uniquement pour l'entité ESUS
$sql.= ' GROUP BY ss.fk_simulation';
$sql.= ' HAVING total = nbKO';

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
    print '<span>Nb record : '.$nb_record.'</span><br />'."\n";
}

while($obj = $db->fetch_object($resql)) {
    $simulation = new TSimulation;
    $simulation->load($PDOdb, $obj->fk_simulation);

    $simulation->accord = 'KO';
    $simulation->save($PDOdb);
    $simulation->generatePDF($PDOdb);

    $simulation->send_mail_vendeur();

    // Oui c'est un test inutile vu que toutes les simuls sont sur ESUS... Mais je pense à l'avenir là :)
    if(in_array($simulation->entity, [18, 25, 28]) && empty($simulation->opt_no_case_to_settle)) {
        $simulation->send_mail_vendeur_esus();
    }
}
