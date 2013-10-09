<?php
/* Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
 * Copyright (C) 2005-2011 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2007-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/product/ajaxproducts.php
 *       \brief      File to return Ajax response on product list request
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL',1); // Disables token renewal
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
if (! defined('NOCSRFCHECK'))    define('NOCSRFCHECK','1');
if (empty($_GET['keysearch']) && ! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');

require('config.php');
dol_include_once('/financement/class/grille.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php');

$langs->load("main");
$langs->load('financement@financement');

$ATMdb = new Tdb();
//$ATMdb->db->debug = true;

/*
 * View
 */


top_httphead();

//print '<!-- Ajax page called with url '.$_SERVER["PHP_SELF"].'?'.$_SERVER["QUERY_STRING"].' -->'."\n";

dol_syslog(join(',',$_POST));
//print_r($_POST);

switch ($_POST['mode']) {
	case 'grille':
		get_grille($ATMdb);
		break;
	case 'duree':
		get_duree($ATMdb);
		break;
	
	default:
		
		break;
}

function get_duree(&$ATMdb) {
	global $db, $conf, $langs, $user;
	
	$outjson = GETPOST('outjson', 'int');

	$grille = new TFin_grille_leaser();
	$form = new TFormCore();
	$idTypeContrat = GETPOST('fk_type_contrat');
	$opt_periodicite = GETPOST('opt_periodicite');

	$htmlresult = $form->combo('','duree', $grille->get_duree($ATMdb, FIN_LEASER_DEFAULT, $idTypeContrat, $opt_periodicite), '');
	
	$db->close();
	
	if ($outjson) print json_encode($htmlresult);
}

function get_grille(&$ATMdb) {
	global $db, $conf, $langs, $user;

	$outjson = GETPOST('outjson', 'int');
	$fk_type_contrat = GETPOST('fk_type_contrat');
	$idLeaser = GETPOST('idLeaser', 'int');
	$opt_periodicite = GETPOST('opt_periodicite');
	$options = GETPOST('options');

	if (empty($fk_type_contrat)) {
		print json_encode('KO');
		exit();
	}
	
	$grille = new TFin_grille_leaser();
	$grille->get_grille($ATMdb, $idLeaser, $fk_type_contrat, $opt_periodicite, $options);
	
	
	if (empty($grille->TGrille)) {
		print json_encode('KO');
		exit();
	}
	
	$TBS=new TTemplateTBS;
	
	$htmlresult = $TBS->render('tpl/view.fingrille.tpl.php'
		,array(
			'palier'=>$grille->TPalier
			,'coefficient'=>$grille->TGrille
		)
		,array(
			'view'=>array('mode'=>'view')
			,'label_periode' => strtr($opt_periodicite, array('MOIS'=>'mois','TRIMESTRE'=>'trimestres','SEMESTRE'=>'semestres','ANNEE'=>'années'))
		)
	);
	
	$db->close();
	
	if ($outjson) print json_encode($htmlresult);
	else print $htmlresult;
}
