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
dol_include_once('/financement/class/html.formfinancement.class.php');
require_once(DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php');

$langs->load("main");
$langs->load('financement@financement');

/*
 * View
 */


top_httphead();

//print '<!-- Ajax page called with url '.$_SERVER["PHP_SELF"].'?'.$_SERVER["QUERY_STRING"].' -->'."\n";

dol_syslog(join(',',$_POST));
//print_r($_POST);

switch ($_POST['mode']) {
	case 'grille':
		get_grille();
		break;
	case 'duree':
		get_duree();
		break;
	case 'calcul':
		get_calcul();
		break;
	
	default:
		
		break;
}

function get_duree() {
	global $db, $conf, $langs, $user;
	
	$outjson = GETPOST('outjson', 'int');

	$formfin = new FormFinancement($db);
	$idTypeContrat = GETPOST('idTypeContrat');
	$opt_periodicite = GETPOST('opt_periodicite');

	$htmlresult = $formfin->select_duree($idTypeContrat, $opt_periodicite, '', 'duree');
	
	$db->close();
	
	if ($outjson) print json_encode($htmlresult);
}

function get_grille() {
	global $db, $conf, $langs, $user;

	$outjson = GETPOST('outjson', 'int');
	$idTypeContrat = GETPOST('idTypeContrat', 'int');
	$idLeaser = GETPOST('idLeaser', 'int');
	$opt_periodicite = GETPOST('opt_periodicite');
	$options = GETPOST('options');

	if (empty($idTypeContrat)) {
		print json_encode('KO');
		exit();
	}
	
	$formfin = new FormFinancement($db);
	$grille = new Grille($db);
	$liste_coeff = $grille->get_grille($idLeaser, $idTypeContrat, $opt_periodicite, $options);
	
	if (empty($liste_coeff)) {
		print json_encode('KO');
		exit();
	}

	ob_start();
	include 'tpl/grille.tpl.php';
	$htmlresult = ob_get_clean();
	
	$db->close();
	
	if ($outjson) print json_encode($htmlresult);
}

function get_calcul() {
	$idLeaser = GETPOST('idLeaser', 'int');
	$idTypeContrat = GETPOST('idTypeContrat', 'int');
	$opt_periodicite = GETPOST('opt_periodicite');
	$montant = GETPOST('montant', 'int');
	$duree = GETPOST('duree', 'int');
	$echeance = GETPOST('echeance', 'int');
	$vr = GETPOST('vr', 'int');
	
	$options = array();
	foreach($_POST as $k => $v) {
		if(substr($k, 0, 4) == 'opt_') $options[] = $v;
		${$k} = $v;
	}
	
	if(empty($duree)) {
		$mesg = $langs->trans('ErrorDureeRequired');
		$error = true;
	} else if(empty($montant) && empty($echeance)) {
		$mesg = $langs->trans('ErrorMontantOrEcheanceRequired');
		$error = true;
	} else {
		$grille->get_grille($idLeaser, $idTypeContrat);
		$calcul_ok = $grille->calcul_financement($idLeaser, $idTypeContrat, $opt_periodicite, $montant, $duree, $echeance, $vr, $options);
		
		// TODO : Revoir validation financement avec les règles finales
		if(!(empty($socid))) {
			$accord = false;
			if($customer->score > 50 && $customer->encours_max > ($customer->encours_cpro + $montant) * 0.8) {
				$accord = true;
			}
		}
	}
}
