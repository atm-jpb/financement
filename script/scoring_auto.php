<?php
$a = microtime(true);

require_once('../config.php');
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');

if(empty($conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN)) exit('empty conf !');    // No need to run this script if empty conf

$PDOdb = new TPDOdb;
$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');
$debug = array_key_exists('debug', $_GET);

$sql = 'SELECT s.rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= " WHERE s.accord = 'WAIT'";    // Toutes les simulations "En étude"
$subquery = 'SELECT DISTINCT fk_simulation FROM '.MAIN_DB_PREFIX."fin_simulation_suivi WHERE statut = 'OK'";
$sql.= ' AND s.rowid NOT IN ('.$subquery.')';
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
if($debug) {
    print '<span>Nb record : ' . $nb_record . '</span><br />';
    print '<pre>--------------------</pre>';
    print '<span>Conf "FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN" : ' . $conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN . '</span>';
}

while($obj = $db->fetch_object($resql)) {
    if($debug) {
        print '<br />------------------------------------------------------------------------------------------------------------------------';
        var_dump($obj->rowid);
    }
    $simu = new TSimulation;
    $simu->load($PDOdb, $obj->rowid, false);
    $simu->load_suivi_simulation($PDOdb);

    if(empty($simu->TSimulationSuivi)) {
        if($debug) {
            print '<pre><span style="background-color: #ff0000">&nbsp;&nbsp;&nbsp;</span> Simulation sans suivi Leaser !</pre>';
        }
        $nb_rollback++;
        continue;
    }

    if($debug) print '<pre>Nb suivi : '.count($simu->TSimulationSuivi).'</pre>';

    foreach($simu->TSimulationSuivi as $k => $suivi) {
        if($suivi->date_demande < 0) $suivi->date_demande = null;   // DateTime with this string '0999-11-30 00:00:00' will provide a negative timestamp

        if(empty($suivi->date_demande)) {
            if(isEDI($suivi) && ($k == 0 || $simu->TSimulationSuivi[$k-1]->date_demande <= (time() + $conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN*60))) {
                if($debug) var_dump('doActionDemander !!');
//                $suivi->doActionDemander($PDOdb, $simu);
                $nb_commit++;
            }
            else {
                // TODO: Action manuelle demandée ?
                $nb_ignored++;
                if($debug) var_dump('what\'s new here ?');
            }

            break;
        }
    }
}
$db->free($resql);

function isEDI(TSimulationSuivi $suivi) {
    global $db;

    $leaser = new Societe($db);
    $leaser->fetch($suivi->fk_leaser);
    if(empty($leaser->array_options)) $leaser->fetch_optionals();

    return (! empty($leaser->array_options['options_edi_leaser']) && in_array($leaser->array_options['options_edi_leaser'], array('BNP', 'GE', 'LIXXBAIL', 'CMCIC')));
}
if($debug) {
    ?>
    <table>
        <tr>
            <th colspan="2">Recap</th>
        </tr>
        <tr>
            <td width="100">Nb doActionDemander</td>
            <td><?php echo $nb_commit; ?></td>
        </tr>
        <tr>
            <td>Nb Erreur</td>
            <td><?php echo $nb_rollback; ?></td>
        </tr>
        <tr>
            <td>Nb Action manuelle demandée</td>
            <td><?php echo $nb_ignored; ?></td>
        </tr>
    </table>
    <br/>
    <!--<span>Nb doActionDemander : --><?php //echo $nb_commit; ?><!--</span><br />-->
    <!--<span>Nb Erreur : --><?php //echo $nb_rollback; ?><!--</span><br />-->
    <!--<span>Nb Action manuelle demandée : --><?php //echo $nb_ignored; ?><!--</span>-->
    <?php
    $b = microtime(true);
    print 'Execution time : ' . ($b - $a) . ' seconds';
}
