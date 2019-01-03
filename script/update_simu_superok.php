<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');

require_once('../config.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/class/grille.class.php');

$ATMdb = new TPDOdb;
print "Début du calcul " . date("d-m-Y H:i:s")."\r";
$sql = "SELECT DISTINCT fs.rowid, fs.entity, fss.fk_user_author, fss.date_selection 
FROM llx_fin_simulation as fs

LEFT JOIN llx_fin_simulation_accord_log as fsa on fsa.fk_simulation = fs.rowid
LEFT JOIN llx_fin_simulation_suivi as fss on fss.fk_simulation = fsa.fk_simulation

WHERE fss.date_selection not in ('0000-00-00 00:00:00', '1000-01-01 00:00:00')
AND fs.rowid NOT IN (SELECT DISTINCT fk_simulation FROM `llx_fin_simulation_accord_log` WHERE `accord` = 'OK' OR `accord` = 'SS')";

$res = $db->query($sql);
$TSimu = array();

if ($res && $db->num_rows($res))
{
	
	while ($obj = $db->fetch_object($res))
	{
		$sql_insert = "INSERT INTO `llx_fin_simulation_accord_log` (`rowid`, `entity`, `fk_simulation`, `fk_user_author`, `datechange`, `accord`) VALUES (NULL, '".$obj->entity."', '".$obj->rowid."', '".$obj->fk_user_author."', '".$obj->date_selection."', 'OK'); ";
		$res2 = $db->query($sql_insert);
		
		$TSimu[] = $obj->rowid;
	}
}


foreach ($TSimu as $sid)
{    
    $simulation = new TSimulation();
    $simulation->load($ATMdb, $sid, false);
    print "Calcul simulation n°".$sid;
    $simulation->get_attente($ATMdb);
    print " terminé \r";
}
print "Fin du calcul " . date("d-m-Y H:i:s")."\r";

