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
dol_include_once('/financement/class/grille.class.php');
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

$error = false;
$mesg = '';

// Valeur par défaut
$opt_periodicite = 'opt_trimestriel';
$opt_mode_reglement = 'opt_prelevement';
$opt_terme = 'opt_aechoir';
$vr = 0;
$idLeaser = 1;
$coeff = 0;

/*
 * Actions
 */

$grille = new Grille($db);

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

// Calcul du financement
if($calculate) {
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
		$grille->get_grille($idLeaser, $idTypeContrat, $opt_periodicite, $options); // Récupération de la grille pour les paramètre données
		$calcul = $grille->calcul_financement($montant, $duree, $echeance, $vr, $coeff); // Calcul du financement
		
		if(!$calcul) { // Si calcul no correct
			$mesg = $langs->trans($grille->error);
			$error = true;
		} else { // Sinon, vérification accord à partir du calcul
			$cout_financement = $echeance * $duree - $montant;
			// TODO : Revoir validation financement avec les règles finales
			if(!(empty($socid))) {
				$accord = false;
				if($customer->score > 50 && $customer->encours_max > ($customer->encours_cpro + $montant) * 0.8) {
					$accord = true;
				}
			}
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

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$db->close();

?>
