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

dol_fiche_head($head, 'leaser', $langs->trans("Financement"), 0, 'financementico@financement');
dol_htmloutput_mesg($mesg);

/**
 * ACTIONS
 */

$ATMdb=new TPDOdb;

$affaire = new TFin_affaire;
$liste_type_contrat = $affaire->TContrat;
$TGrille=array();

foreach ($liste_type_contrat as $typeContrat => $label) {
	$TFin_grille_suivi = new TFin_grille_suivi;
	$grille = $TFin_grille_suivi->get_grille($ATMdb,$typeContrat);
	
	$TGrille[$typeContrat] = $grille;
}


$error = false;
$mesg = '';
$action = GETPOST('action', 'alpha');

if($action == 'save') {
	
	//Traitement de l'ajout des nouvelles lignes
	if(GETPOST('newline')){
		
		$TNewLine = GETPOST('newline');
		foreach($TNewLine as $typeLine => $Tline){
			
			//pre($Tline,true);exit;
			
			$TFin_grille_suivi = new TFin_grille_suivi;
			$res = $TFin_grille_suivi->addLine($Tline,$typeLine);

			if($res) $TFin_grille_suivi->save($ATMdb);
			else $error = 'ErrorNewLine'.$typeLine;
		}
	}
	
	//Traitement de la mise à jour des lignes existante
	if(GETPOST('TGrille')){
		
		$TAllGrille = GETPOST('TGrille');
		foreach($TAllGrille as $typeGrille => $TGrille){
			
		}
	}

}

//Toujours en mode edition
$mode = 'edit';

/* *********************************
 * Afficahge des grilles de Leasers
 * *********************************/

//pre($TFin_grille_suivi->TLeaserByCategories,true);
//pre($TFin_grille_suivi->TLeaser,true);

foreach ($liste_type_contrat as $typeContrat => $label) {
	
	$grille = &$TGrille[$typeContrat];

	print_titre($label);

	$form=new TFormCore($_SERVER['PHP_SELF'],'formGrille'.$typeContrat,'POST');
	$form->Set_typeaff($mode);

	echo $form->hidden('action', 'save');
	echo $form->hidden('typeContrat', $typeContrat );

	$TBS=new TTemplateTBS;
	
	pre($grille,true);
	
	print $TBS->render('../tpl/fingrille.suivi.tpl.php'
		,array(
			'grille'=>$grille
		)
		,array(
			'view'=>array(
				'mode'=>$mode
				,'contrat'=>$typeContrat
			)
			,'newline'=>array(
				'solde' => $form->combo("", "newline[".$typeContrat."][solde]", $TFin_grille_suivi->TLeaser, '-1')
				,'montant' => 'de '.((is_null($grille[end(array_keys($grille))]['montant'])) ? '0' : $grille[end(array_keys($grille))]['montant']).' € à '.$form->texte('', "newline[".$typeContrat."][montant]", '', 5)
				,'entreprise' => $form->combo("", "newline[".$typeContrat."][entreprise]", $TFin_grille_suivi->TLeaserByCategories,'')
				,'administration' => $form->combo("", "newline[".$typeContrat."][administration]", $TFin_grille_suivi->TLeaserByCategories,'')
				,'association' => $form->combo("", "newline[".$typeContrat."][association]", $TFin_grille_suivi->TLeaserByCategories,'')
			)
			
		)
	);

	print $form->end_form();

}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$ATMdb->close();

$db->close();