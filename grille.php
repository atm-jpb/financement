<?php

/* Copyright (C) 2012-2013 Maxime Kohlhaas      <maxime@atm-consulting.fr>
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
 * 	\defgroup   financement     Module Financement
 *  \brief      Module financement pour C'PRO
 *  \file       /financement/core/modules/modFinancement.class.php
 *  \ingroup    Financement
 *  \brief      Description and activation file for module financement
 */
require('config.php');
dol_include_once('/financement/lib/admin.lib.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

$langs->load("companies");
$langs->load("commercial");

if (!$user->rights->financement->admin->write)
	accessforbidden();

$socid = GETPOST('socid', 'int');

$object = new Societe($db);
if (!empty($socid)) $result = $object->fetch($socid);
if ($result <= 0) dol_print_error('', $object->error);

/**
 * ACTIONS
 */
$PDOdb = new TPDOdb;
$affaire = new TFin_affaire();
$liste_type_contrat = $affaire->TContrat;
$TGrille = array();

foreach ($liste_type_contrat as $idTypeContrat => $label)
{
	$grille = new TFin_grille_leaser;
	$grille->get_grille($PDOdb, $socid, $idTypeContrat);

	$TGrille[$idTypeContrat] = $grille;
}


$error = false;
$mesg = '';
$action = GETPOST('action', 'alpha');

if ($action == 'save')
{
	$TCoeff = GETPOST('TCoeff');
	$TPalier = GETPOST('TPalier');
	$TPeriode = GETPOST('TPeriode');
	$idTypeContrat = GETPOST('idTypeContrat');

	$grille = &$TGrille[$idTypeContrat];
	
	if (!empty($TCoeff))
	{
		foreach ($TCoeff[$idTypeContrat] as $i => $TLigne)
		{
			$periode = $TPeriode[$idTypeContrat][$i];
			foreach ($TLigne as $j => $coeff)
			{
				$montant = $TPalier[$idTypeContrat][$j];
				//	print "$i/$j $periode/$montant ".$coeff['coeff']."<br>";

				$res = $grille->setCoef($PDOdb, $coeff['rowid'], $socid, $idTypeContrat, $periode, $montant, $coeff['coeff']);
				if (!empty($res)) $at_least_on_delete = true;
			}
		}

		$grille->normalizeGrille();

		/* print '<pre>';
		  print_r($grille->TGrille);
		  print '</pre>';
		 */
	}
	
	header('Location: '.dol_buildpath('/financement/grille.php', 1).'?socid='.$object->id);
	exit;
}
elseif ($action == 'new_periode')
{
	$idTypeContrat = GETPOST('idTypeContrat');
	$periode = GETPOST('new_periode');
	$grille = &$TGrille[$idTypeContrat];
	$periode_created = false;
	
	if (!empty($periode) && !isset($grille->TGrille[$periode]))
	{
		foreach ($grille->TPalier as $info)
		{
			$grille->setCoef($PDOdb, 0, $socid, $idTypeContrat, $periode, $info['montant'], 0);
			$periode_created = true;
		}
	}
	
	if (empty($grille->TPalier)) setEventMessage($langs->trans('FinCreatePeriodeButNoPalierExist'), 'warnings');
	else if ($periode_created) setEventMessage($langs->trans('FinCreatePeriodeOk'));
	else setEventMessage($langs->trans('FinCreatePeriodeAlreadyExist'), 'warnings');
	
	header('Location: '.dol_buildpath('/financement/grille.php', 1).'?socid='.$object->id);
	exit;
}
elseif ($action == 'new_palier')
{
	$idTypeContrat = GETPOST('idTypeContrat');
	$palier = GETPOST('new_palier');
	$grille = &$TGrille[$idTypeContrat];
	$palier_created = false;
	
	// Ajout d'un nouveau palier donc valeur non vide
	if (!empty($palier))
	{
		if (empty($grille->TGrille)) $grille->addPeriode(1);
		
		foreach ($grille->TGrille as $p => $TPalier)
		{
			// Mais il faut que ce palier n'existe pas
			if (!isset($TPalier[$palier]))
			{
				$grille->setCoef($PDOdb, 0, $socid, $idTypeContrat, $p, $palier, 0);
				$palier_created = true;
			}
		}
	}
	
	if ($palier_created) setEventMessage($langs->trans('FinCreatePalierOk'));
	else setEventMessage($langs->trans('FinCreatePalierAlreadyExist'), 'warnings');
	
	header('Location: '.dol_buildpath('/financement/grille.php', 1).'?socid='.$object->id);
	exit;
}
else if ($action == 'delete_periode')
{
	$idTypeContrat = GETPOST('idTypeContrat');
	$periode = GETPOST('periode');
	
	$grille_leaser = new TFin_grille_leaser;
	$TGrilleLeaser = $grille_leaser->LoadAllBy($PDOdb, array('fk_soc' => $object->id, 'fk_type_contrat' => $idTypeContrat, 'periode' => $periode, 'type' => 'LEASER'));
	foreach ($TGrilleLeaser as &$grille_l)
	{
		$grille_l->delete($PDOdb);
	}
	
	header('Location: '.dol_buildpath('/financement/grille.php', 1).'?socid='.$object->id);
	exit;
	
}
elseif ($action == 'delete')
{
	$TToDelete = GETPOST('tabToDelete');
	if (!empty($TToDelete))
	{
		foreach ($TToDelete as $id)
		{
			$grille_leaser = new TFin_grille_leaser;
			$grille_leaser->load($PDOdb, $id);
			$grille_leaser->delete($PDOdb);
			
			$at_least_on_delete = true;
		}
	}
	
	header('Location: '.dol_buildpath('/financement/grille.php', 1).'?socid='.$object->id);
	exit;
}

/**
 * VIEW
 */
llxHeader();

$head = societe_prepare_head($object);
dol_fiche_head($head, 'grille', $langs->trans("Financement"), -2, 'financementico@financement');

// Grille de coeff globale + % de pénalité par option

$mode = 'edit';
foreach ($liste_type_contrat as $idTypeContrat => $label)
{
	$grille = &$TGrille[$idTypeContrat];

	if (!empty($at_least_on_delete)) $res = $grille->get_grille($PDOdb, $socid, $idTypeContrat);
	$TCoeff = $grille->TGrille;

	foreach ($TCoeff as &$t) ksort($t);

	print_titre($label);

	//include '../tpl/admin.grille.tpl.php';

	
	print '<table class="noborder" width="100%">';
	print '<tr class="pair">';
	print '<td>';
	$form = new TFormCore($_SERVER['PHP_SELF'], 'formGrille'.$idTypeContrat.'_newPeriode', 'POST');
	print $form->hidden('action', 'new_periode');
	print $form->hidden('idTypeContrat', $idTypeContrat);
	print $form->hidden('socid', $socid);
	print $form->texte($langs->trans('NewPeriode'), 'new_periode', '', 3);
	print $form->btsubmit($langs->trans('createNewPeriode'), 'createNewPeriode');
	print $form->end_form();
	print '</td>';
	print '<td>';
	$form = new TFormCore($_SERVER['PHP_SELF'], 'formGrille'.$idTypeContrat.'_newPalier', 'POST');
	print $form->hidden('action', 'new_palier');
	print $form->hidden('idTypeContrat', $idTypeContrat);
	print $form->hidden('socid', $socid);
	print $form->texte($langs->trans('NewPalier'), 'new_palier', '', 10);
	print $form->btsubmit($langs->trans('createNewPalier'), 'createNewPalier');
	print $form->end_form();
	print '</td>';
	print '</tr>';
	print '</table>';
	
	
	$form = new TFormCore($_SERVER['PHP_SELF'], 'formGrille'.$idTypeContrat, 'POST');

	$TTabIDToDelete = array();

	$TPalier = array();
	$ii = 0;
	foreach ($grille->TPalier as $i => $palier)
	{
		$TPalier[$ii] = array(
			'montant' => $form->texte('', 'TPalier['.$idTypeContrat.']['.($i + 1).']', $palier['montant'], 10, 255)
			, 'lastMontant' => $palier['lastMontant']
		);
		$a = '<a onclick="if(!window.confirm(\'Etes vous sûr de vouloir supprimer cette tranche ?\')) return false;" href="'.dol_buildpath('/financement/grille.php', 1).'?action=delete&socid='.$object->id;
		foreach ($TCoeff as $periode => $TData)
		{
			if (!empty($TData[$palier['montant']]['rowid']))
				$a .= '&tabToDelete[]='.$TData[$palier['montant']]['rowid'];
		}
		$a .= '">'.img_delete().'</a>';

		$TPalier[$ii]['toDelete'] = $a;

		$ii++;
	}


	echo $form->hidden('action', 'save');
	echo $form->hidden('idTypeContrat', $idTypeContrat);
	echo $form->hidden('socid', $socid);
	/* print '<pre>';
	  print_r($grille->TPalier);
	  print_r($TCoeff);
	  print '</pre>'; */
	
	$TBS = new TTemplateTBS;
//var_dump($TCoeff);
	print $TBS->render(dol_buildpath('/financement/tpl/fingrille.tpl.php')
		, array(
			'palier' => $TPalier
			,'coefficient' => $TCoeff
		)
		, array(
			'view' => array('mode' => $mode, 'contrat' => $idTypeContrat)
			,'colspan' => count($TCoeff[current(array_keys($TCoeff))]) + 2
			,'object' => $object
			,'page_url' => dol_buildpath('/financement/grille.php', 1)
			,'img_delete' => img_picto('', 'delete')
		)
	);

	print $form->end_form();
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$PDOdb->close();
$db->close();
