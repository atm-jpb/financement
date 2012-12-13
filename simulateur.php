<?php
/* Copyright (C) 2012      Maxime Kohlhaas        <maxime@atm-consulting.fr>
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
 *	\file       financement/simulateur.php
 *	\ingroup    financement
 *	\brief      Outil de calculateur et de simulateur
 */


$res=@include("../main.inc.php");					// For root directory
if (! $res) $res=@include("../../main.inc.php");	// For "custom" directory
dol_include_once('/financement/class/html.formfinancement.class.php');

if (!($user->rights->financement->allsimul->calcul || $user->rights->financement->allsimul->simul ||
		$user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list))
{
	accessforbidden();
}

$langs->load('financement@financement');

$mode=GETPOST("mode")?GETPOST("mode"):'calcul';
$socid=GETPOST("socid");
$search_customer=GETPOST("search_customer");
$calculate=GETPOST("calculate");

/*
 * Actions
 */

// Recherche client
if(GETPOST('search') && !empty($search_customer)) {
	$sql = "SELECT s.nom, s.rowid, s.siren, s.cp, s.ville";
	$sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
	if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE s.entity = ".$conf->entity;
	$sql.= " AND s.client IN (1,2,3)";
	
	if (! $user->rights->societe->client->voir && ! $socid) //restriction
	{
		$sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
	}
	
	$sql.= " AND (s.nom LIKE '%".$db->escape(trim($search_customer))."%'";
	$sql.= " OR s.siren LIKE '%".$db->escape(trim($search_customer))."%')";
	
	$sql.= " ORDER BY s.nom";
	$customer_list=$db->query($sql);
}

// Récupération information client
if(!empty($socid)) {
	$customer = new Societe($db);
	$customer->fetch($socid);
	
	// Récupération du score du client
	$sql = "SELECT s.score, s.encours_max";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_score as s";
	$sql.= " WHERE s.fk_soc = ".$socid;
	$sql.= " ORDER BY s.date DESC";
	$sql.= " LIMIT 1";
	
	$score=$db->query($sql);
	if($score) {
		$obj = $db->fetch_object($dossier_list);
		$customer->score = $obj->score;
		$customer->encours_max = $obj->encours_max;
		$customer->encours_cpro = 0;
	}
	
	// Récupération des dossiers du client
	$sql = "SELECT d.ref, d.montant, d.datedeb, d.datefin";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier as d, ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE d.fk_soc = s.rowid";
	$sql.= " AND s.entity = ".$conf->entity;
	
	if (! $user->rights->financement->alldossier->read) //restriction
	{
		$sql.= " AND d.fk_user_author = " .$user->id;
	}
	
	$sql.= " ORDER BY d.datedeb DESC";
	$dossier_list=$db->query($sql);
}

// Calcule du financement
if($calculate) {
	$type_contrat = GETPOST('type_contrat', 'int');
	$montant = GETPOST('montant', 'int');
	$duration = GETPOST('duration', 'int');
	$periodicite = GETPOST('periodicite', 'int');
	$echeance = GETPOST('echeance', 'int');
	
	// TODO : Revoir calculateur avec les règles finales
	if(empty($montant)) {
		$montant = $duration * $echeance;
	} else if (empty($duration)) {
		$duration = ceil($montant / $echeance);
	} else if (empty($echeance)) {
		$echeance = $montant / $duration * (1 + $coeff / 100);
	}
	
	// TODO : Revoir validation financement avec les règles finales
	if(!(empty($socid))) {
		$accord = false;
		if($customer->score > 50 && $customer->encours_max > ($customer->encours_cpro + $montant) * 0.8) {
			$accord = true;
		}
	}
}

/*
 * View
 */

$extrajs = array('/financement/js/financement.js');
llxHeader('',$langs->trans("Simulator"),'','','','',$extrajs);

//$form = new Form($db);
//$formother = new FormOther($db);
//$formcompany = new FormCompany($db);
$formfin = new FormFinancement($db);

switch ($mode) {
	case 'calcul':
		$tpl = 'tpl/calculateur.tpl.php';
		break;
	case 'simul':
		$tpl = 'tpl/simulateur.tpl.php';
		break;
	case 'list':
		$tpl = 'tpl/simul_list.tpl.php';
		break;
	
	default:
		$tpl = 'tpl/calculateur.tpl.php';
		break;
}

include $tpl;

dol_htmloutput_mesg($mesg);

llxFooter('');

$db->close();

?>
