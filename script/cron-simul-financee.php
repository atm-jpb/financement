<?php

define('INC_FROM_CRON_SCRIPT', true);

require_once __DIR__.'/../config.php';

$PDOdb = new TPDOdb();

$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_simulation simu ';
$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.reference = simu.numero_accord) ';
$sql.= 'SET simu.fk_fin_dossier = df.fk_fin_dossier ';
$sql.= 'WHERE df.type = \'LEASER\' ';
$sql.= 'AND simu.numero_accord != \'\' ';
$sql.= 'AND simu.fk_fin_dossier = 0 ';

$res = $PDOdb->Execute($sql);

pre($res,true);
pre($PDOdb, true);
