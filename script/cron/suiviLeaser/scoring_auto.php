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

$sql = 'SELECT s.rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s';
$sql.= " WHERE s.accord = 'WAIT'";    // Toutes les simulations "En étude"
$subquery = 'SELECT DISTINCT fk_simulation FROM '.MAIN_DB_PREFIX."fin_simulation_suivi WHERE statut = 'OK'";
$sql.= ' AND s.rowid NOT IN ('.$subquery.')';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

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
    print '<pre>--------------------</pre>'."\n";
    print '<span>Conf "FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN" : ' . $conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN . '</span><br />'."\n";
}

while($obj = $db->fetch_object($resql)) {
    if($debug && ! empty($fk_simu) && $obj->rowid != $fk_simu) continue;
    if($debug) {
        print '<pre>---------------------------------------------------------------------------------------------------------</pre>'."\n";
        var_dump($obj->rowid);
        print "\n";
    }
    $simulation = new TSimulation;
    $simulation->load($PDOdb, $obj->rowid);

    if(empty($simulation->TSimulationSuivi)) {
        if($debug) {
            print '<pre><span style="background-color: #ff0000">&nbsp;&nbsp;&nbsp;</span> Simulation sans suivi Leaser !</pre>'."\n";
        }
        $nb_rollback++;
        continue;
    }
    switchEntity($simulation->entity);  // To load conf from the right entity
    if(empty($conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN)) continue;    // Can't do auto job

    // Spécifique C'Pro OUEST & COPY CONCEPT, pas de scoring auto si 500 <= montant_financé < 1000
    if(in_array($simulation->entity, array(5, 7, 16)) && $simulation->montant >= 500 && $simulation->montant < 1000) {
        $simulation->fk_action_manuelle = 1;    // Can't do scoring auto
        $simulation->save($PDOdb);
        continue;
    }

    if($debug) print '<pre>Nb suivi : '.count($simulation->TSimulationSuivi).'</pre>'."\n";
	$TSuivi = array_values($simulation->TSimulationSuivi);
    foreach($TSuivi as $k => $suivi) {
        if($suivi->date_demande < 0) $suivi->date_demande = null;   // DateTime with this string '0999-11-30 00:00:00' will provide a negative timestamp

        if(empty($suivi->date_demande)) {
            if($suivi->isEDI() && ($k == 0 || $TSuivi[$k-1]->statut == 'KO' || $TSuivi[$k-1]->date_demande + $conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN*60 <= time() && $TSuivi[$k-1]->statut != 'ERR')) {
                if($debug) {
                    var_dump('doActionDemander !!');
                    print "\n";
                }
                $suivi->doActionDemander($PDOdb, $simulation);
                $nb_commit++;
            }
            else if(! $suivi->isEDI() && $suivi->fk_leaser == 18495 && $k == 0) {
                if($debug)  {
                    var_dump('Qui a demandé une LOC PURE ?!');
                    print "\n";
                }
                $suivi->doActionDemander($PDOdb, $simulation);
                if($simulation->entity == 18) $suivi->doActionRefuser($PDOdb, $simulation);    // Spécifique ESUS
                else $suivi->doActionAccepter($PDOdb, $simulation);
            }
            else {
                // Action manuelle demandée !
                $nb_ignored++;
                if($debug) {
                    var_dump('what\'s new here ?');
                    print "\n";
                }

                if($TSuivi[$k-1]->date_demande + $conf->global->FINANCEMENT_EDI_SCORING_AUTO_EVERY_X_MIN*60 <= time()) {
                    $simulation->fk_action_manuelle = 1;    // Can't do scoring auto
                    $simulation->save($PDOdb);
                }
            }

            break;
        }
    }
}
$db->free($resql);

if($debug) {
    ?>
    <hr>
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
            <td>Nb Action manuelle demandee</td>
            <td><?php echo $nb_ignored; ?></td>
        </tr>
    </table>
    <br/>
    <?php
    $b = microtime(true);
    print 'Execution time : ' . ($b - $a) . ' seconds';
}
