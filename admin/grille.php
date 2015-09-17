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

require('../config.php');
dol_include_once('/financement/lib/admin.lib.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

if (!$user->rights->financement->admin->write) accessforbidden();


llxHeader('',$langs->trans("FinancementSetup"));
$head = financement_admin_prepare_head(null);

dol_fiche_head($head, 'grille', $langs->trans("Financement"), 0, 'financementico@financement');
dol_htmloutput_mesg($mesg);

/**
 * ACTIONS
 */

$ATMdb=new TPDOdb;
$idLeaser = isset($_REQUEST['socid']) ? $_REQUEST['socid'] : FIN_LEASER_DEFAULT; // Identifiant de la société associée à la grille (C'PRO ici, sera l'identifiant leaser pour les grilles leaser)
$affaire = new TFin_affaire();
$liste_type_contrat = $affaire->TContrat;
$TGrille=array();

foreach ($liste_type_contrat as $idTypeContrat => $label) {
	$grille = new TFin_grille_leaser;
	$grille->get_grille($ATMdb,$idLeaser, $idTypeContrat);
	
	$TGrille[$idTypeContrat] = $grille;
}


$error = false;
$mesg = '';
$action = GETPOST('action', 'alpha');
if($action == 'save') {
	$TCoeff = GETPOST('TCoeff');
	$TPalier = GETPOST('TPalier');
	$TPeriode = GETPOST('TPeriode');
	
	$idTypeContrat = GETPOST('idTypeContrat');
	$idLeaser = GETPOST('idLeaser');
	
	$newPalier = GETPOST('newPalier');
	$newPeriode = GETPOST('newPeriode');
	//$TNewCoeff = GETPOST('TNewCoeff');
	//print_r($TCoeff);
	
	
	$grille = & $TGrille[$idTypeContrat];
	
	$grille->addPeriode($newPeriode[$idTypeContrat]);
	$grille->addPalier($newPalier[$idTypeContrat]);
	
	//$ATMdb->db->debug=true;
	/*print '<pre>';
		print_r($grille->TGrille);
		print '</pre>';
		*/
		
	if(!empty($TCoeff)) {
		/*print_r($TPalier);
		print_r($TPeriode);
		*/
		foreach($TCoeff[$idTypeContrat] as $i=>$TLigne) {
			$periode = $TPeriode[$idTypeContrat][$i];
							
			foreach($TLigne as $j=>$coeff) {
				$montant = $TPalier[$idTypeContrat][$j];
		//	print "$i/$j $periode/$montant ".$coeff['coeff']."<br>";
				
				$grille->setCoef($ATMdb,$coeff['rowid'], $idLeaser, $idTypeContrat, $periode, $montant, $coeff['coeff'] );
				
			}
		}
		
		$grille->normalizeGrille();
		
		/*print '<pre>';
		print_r($grille->TGrille);
		print '</pre>';
		*/
	}
	
}

// Grille de coeff globale + % de pénalité par option

$mode = 'edit';
foreach ($liste_type_contrat as $idTypeContrat => $label) {
	$grille = & $TGrille[$idTypeContrat];
	
	$TCoeff = $grille->TGrille;
	
	print_titre($label);
	
	//include '../tpl/admin.grille.tpl.php';
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formGrille'.$idTypeContrat,'POST');
	$form->Set_typeaff($mode);
	
	
	$TPalier=array();
	foreach($grille->TPalier as $i=>$palier) {
		$TPalier[]=array(
			'montant'=>$form->texte('','TPalier['.$idTypeContrat.']['.($i+1).']', $palier['montant'],10,255)
			,'lastMontant'=>$palier['lastMontant']
		);
		
		
	}
	
	
	
	echo $form->hidden('action', 'save');
	echo $form->hidden('idTypeContrat', $idTypeContrat );
	echo $form->hidden('idLeaser', $idLeaser);
/*print '<pre>';
	print_r($grille->TPalier);
	print_r($TCoeff);
	print '</pre>';*/
	$TBS=new TTemplateTBS;
	
	print $TBS->render('../tpl/fingrille.tpl.php'
		,array(
			'palier'=>$TPalier
			,'coefficient'=>$TCoeff
		)
		,array(
			'view'=>array('mode'=>$mode, 'contrat'=>$idTypeContrat)
			
		)
	);
	
	print $form->end_form();
	
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$ATMdb->close();

$db->close();