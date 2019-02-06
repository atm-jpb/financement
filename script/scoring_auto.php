<?php

require_once('../config.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');

if(empty($conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN)) exit;    // No need to run this script if empty conf

$PDOdb = new TPDOdb;
$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');
$debug = array_key_exists('debug', $_GET);

$sql = 'SELECT s.rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= " WHERE s.accord = 'WAIT'";    // Toutes les simulations "En étude"
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

if($debug) {
    print '<p>'.$sql.'</p><br />';
}

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$nb_commit = $nb_rollback = $nb_ignored = 0;
$nb_record = $db->num_rows($resql);
print '<span>Nb record : '.$nb_record.'</span>';

while($obj = $db->fetch_object($resql)) {
    if($debug) var_dump($obj->rowid);
    $simu = new TSimulation;
    $simu->load($PDOdb, $obj->rowid, false);
    $simu->load_suivi_simulation($PDOdb);

    if(empty($simu->TSimulationSuivi)) {
        if($debug) {
            print '<pre><span style="background-color: #ff0000">&nbsp;&nbsp;&nbsp;</span> Simulation sans suivi Leaser !</pre><br />';
        }
        $nb_rollback++;
        continue;
    }
    foreach($simu->TSimulationSuivi as $suivi) {
        if($suivi->statut == 'OK') {    // No need to do something else
            $nb_ignored++;
            break;
        }

        // TODO: Continue !
    }
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?></span><br />
<span>Nb Rollback : <?php echo $nb_rollback; ?></span><br />
<span>Nb Ignored : <?php echo $nb_ignored; ?></span>
