<?php
/**
 * Lance la routine pour cocher en automatique les dossiers dont il ne faut pas vérifier la renta neg
 */
ini_set('display_errors', true);

$path=dirname(__FILE__).'/';
define('INC_FROM_CRON_SCRIPT', true);
require_once($path."../config.php");

dol_include_once("/financement/class/affaire.class.php");
dol_include_once('/financement/class/dossier.class.php');
dol_include_once("/financement/class/grille.class.php");
dol_include_once('/financement/lib/financement.lib.php');

@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();

$start = time();
echo "\n".'--------------------------------------------------------------------------------------------';
echo "\n".'START : '.date('d/m/Y H:i:s', $start);
echo "\n";
$PDOdb=new TPDOdb();

ob_start();

$TRule = array('rule1'=>1, 'rule2'=>1, 'rule3'=>1, 'rule4'=>1, 'rule5'=>1, 'rule6'=>1);
get_liste_dossier_renta_negative($PDOdb,0,1,$TRule);

$res = ob_get_clean();
$res = str_ireplace(array("<br />","<br>","<br/>"), "\r\n", $res);
echo $res;

$end = time();
echo "\n".'END : '.date('d/m/Y H:i:s', $end);
echo "\n".'TIME : '.date('i:s', $end - $start);
