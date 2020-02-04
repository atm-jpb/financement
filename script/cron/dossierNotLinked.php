#!/usr/bin/php
<?php
$a = microtime(true);
$path = dirname(__FILE__).'/';

define('INC_FROM_CRON_SCRIPT', true);
require_once $path.'../../config.php';

$debug = array_key_exists('debug', $_GET) || isset($argv[1]) && $argv[1] == 'debug';

$filePath = DOL_DATA_ROOT.'/financement/cron/';
if(file_exists($filePath) === false) dol_mkdir($filePath);

$fileName = 'dossierNotLinked_'.date('Ymd-Hi').'.log';

$sql = 'SELECT DISTINCT d.rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier d';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (da.fk_fin_dossier = d.rowid)';
$sql.= ' WHERE year(d.date_cre) > year(NOW()) - 2'; // Dossiers vieux de moins de 2 ans
$sql.= " AND (df.reference is not null OR df.reference <> '')"; // Dossiers qui ont une référence
$sql.= ' AND da.rowid is null'; // Dossiers sans lien avec une affaire

if($debug) print "\n\n".$sql."\n\n";

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$f = fopen($filePath.$fileName, 'w');
if($f === false) exit;  // File can't be opened

while($obj = $db->fetch_object($resql)) fwrite($f, 'Dossier ID : '.$obj->rowid."\n");
$db->free($resql);

$b = microtime(true);
$out = 'Execution time : ' . ($b - $a) . ' seconds';
fwrite($f, $out);

fclose($f);