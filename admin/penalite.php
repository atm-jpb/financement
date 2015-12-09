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

define('MONTANT_PALIER_DEFAUT', 100000000);
 
require('../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

if (!$user->rights->financement->admin->write) accessforbidden();

if(empty($_REQUEST['socid'])) { // Redirection sur l'admin financement si pas de société spécifiée
	header('Location: '.DOL_MAIN_URL_ROOT.'/custom/financement/admin/config.php'); exit;
}

$langs->load('financement@financement');

$socid = $_REQUEST['socid'];
$societe = new Societe($db);
$societe->fetch($socid);
$typePenalite = empty($_REQUEST['type']) ? 'R': $_REQUEST['type'];

llxHeader('',$langs->trans("PenaliteSetup"));

$ATMdb=new TPDOdb;
$idLeaser = $socid;
$affaire = new TFin_affaire();
$liste_type_contrat = $affaire->TContrat;
$TGrille=array();

foreach ($liste_type_contrat as $idTypeContrat => $label) {
	$grille = new TFin_grille_leaser('PENALITE_'.$_REQUEST['type']);
	$grille->get_grille($ATMdb,$idLeaser, $idTypeContrat);

	$TGrille[$idTypeContrat] = $grille;
}

/**
 * ACTIONS
 */

$error = false;
$mesg = '';
$action = GETPOST('action', 'alpha');
if($action == 'save') {
	$TCoeff = GETPOST('TCoeff');
	$TPalier = GETPOST('TPalier');
	$TPeriode = GETPOST('TPeriode');
	
	$idTypeContrat = GETPOST('idTypeContrat');
	$idLeaser = GETPOST('idLeaser');

	$newPeriode = GETPOST('newPeriode');	
	
	$grille = & $TGrille[$idTypeContrat];
	
	$grille->addPeriode($newPeriode);
	if(count($grille->TPalier)==0) $grille->addPalier(MONTANT_PALIER_DEFAUT); // il n'y aura d'un palier caché
	
	if(!empty($TCoeff)) {
		foreach($TCoeff as $i=>$TLigne) {
			$periode = $TPeriode[$i];
							
			foreach($TLigne as $j=>$coeff) {
				$grille->setCoef($ATMdb,$coeff['rowid'], $idLeaser, $idTypeContrat, $periode, MONTANT_PALIER_DEFAUT, $coeff['coeff'] );
			}
		}
		
		$grille->normalizeGrille();	
	}
}

/**
 * VIEW
 */

// Affichage résumé client
$formDoli = new Form($db);

$TBS=new TTemplateTBS();

print $TBS->render(dol_buildpath('/financement/tpl/client_entete.tpl.php')
	,array(
		
	)
	,array(
		'client'=>array(
			'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'penalite'.$typePenalite, $langs->trans("ThirdParty"),0,'company')
			,'showrefnav'=>$formDoli->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom')
			,'idprof1'=>$societe->idprof1
			,'adresse'=>$societe->address
			,'cpville'=>$societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town
			,'pays'=>picto_from_langcode($societe->country_code).' '.$societe->country
		)
		,'view'=>array(
			'mode'=>'view'
		)
	)
);

if($societe->fournisseur == 0) {
	echo $langs->trans('PenaliteOnlyOnFournisseur');
} else {
	// Grille de coeff globale + % de pénalité par option
	
	$mode = 'edit';
	foreach ($liste_type_contrat as $idTypeContrat => $label) {
		$grille = & $TGrille[$idTypeContrat];
		
		$TCoeff = $grille->TGrille;
		
		print_titre($label);
		
		$form=new TFormCore($_SERVER['PHP_SELF'],'formGrille'.$idTypeContrat,'POST');
		$form->Set_typeaff($mode);
		
		
		$TPalier=array();
		foreach($grille->TPalier as $i=>$palier) {
			$TPalier[]=array(
				'montant'=>$form->texte('','TPalier['.($i+1).']', $palier['montant'],10,255)
				,'lastMontant'=>$palier['lastMontant']
			);
		}
		
		echo $form->hidden('action', 'save');
		echo $form->hidden('idTypeContrat', $idTypeContrat );
		echo $form->hidden('idLeaser', $idLeaser);
		echo $form->hidden('socid', $socid);
		echo $form->hidden('type', $typePenalite);
		
		$TBS=new TTemplateTBS;
		
		print $TBS->render(dol_buildpath('/financement/tpl/fingrille.penalite.tpl.php')
			,array(
				'palier'=>$TPalier
				,'coefficient'=>$TCoeff
			)
			,array(
				'view'=>array('mode'=>$mode, 'MONTANT_PALIER_DEFAUT'=>MONTANT_PALIER_DEFAUT)
			)
		);
		
		print $form->end_form();
		
	}
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$ATMdb->close();

$db->close();