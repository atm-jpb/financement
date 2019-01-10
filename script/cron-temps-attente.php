<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

define('INC_FROM_CRON_SCRIPT', true);

require_once __DIR__.'/../config.php';

dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/class/grille.class.php');

$ATMdb = new TPDOdb;

$sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "fin_simulation";
$sql.= " WHERE 1=1 ";
$sql.= " AND accord NOT IN ('OK', 'KO', 'SS', 'WAIT_MODIF') ";
$sql.= " ORDER BY date_simul DESC";
$res = $db->query($sql);
print "Début du calcul " . date("d-m-Y H:i:s")."\r";
while($obj = $db->fetch_object($res)){
    
    $simulation = new TSimulation();
    $simulation->load($ATMdb, $obj->rowid);
    print "Calcul simulation n°".$obj->rowid;
    $simulation->get_attente($ATMdb);
    print " terminé \r";
}
print "Fin du calcul " . date("d-m-Y H:i:s")."\r";

$db->free($res);

$db->close();
$ATMdb->close();