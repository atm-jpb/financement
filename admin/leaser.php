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

$error = false;
$mesg = '';
$action = GETPOST('action', 'alpha');

//pre($_REQUEST,true);

if($action == 'save') {
	
	//Traitement de l'ajout des nouvelles lignes
	if(GETPOST('newline')){

		$TNewLine = GETPOST('newline');
		foreach($TNewLine as $typeLine => $Tline){

			if($typeLine == 'DEFAUT_LOCATION' || $typeLine == 'DEFAUT_FORFAIT' || $typeLine == 'DEFAUT_INTEGRAL'){
				$Tline = array(
					"solde" => ($Tline['leaser'] == 0) ? -1 : $Tline['leaser']
					,"montantbase" => $Tline['ordre']
					,"montantfin" => 0
					,"entreprise" => 0
					,"administration" => 0 
					,"association" => 0
				);
			}
			
			$TFin_grille_suivi = new TFin_grille_suivi;
			$res = $TFin_grille_suivi->addLine($Tline,$typeLine);

			if($res) $TFin_grille_suivi->save($ATMdb);
			else $error = 'ErrorNewLine'.$typeLine;
		}
	}
	
	//Traitement de la mise à jour des lignes existante
	if(GETPOST('TGrille')){
		
		$TAllGrille = GETPOST('TGrille');

		foreach($TAllGrille as $typeGrille => $grille_temp){
			
			foreach($grille_temp as $rowid => $linegrille){				
				
				if($typeLine == 'DEFAUT_LOCATION' || $typeLine == 'DEFAUT_FORFAIT' || $typeLine == 'DEFAUT_INTEGRAL'){
					$linegrille = array(
						"solde" => ($linegrille['leaser'] == 0) ? -1 : $linegrille['leaser']
						,"montantbase" => $linegrille['ordre']
						,"montantfin" => 0
						,"entreprise" => 0
						,"administration" => 0 
						,"association" => 0
					);
				}
				
				$TFin_grille_suivi = new TFin_grille_suivi;
				$TFin_grille_suivi->load($ATMdb, $rowid);
				
				if($linegrille['solde'] == -1 || $linegrille['leaser'] == -1){
					$TFin_grille_suivi->delete($ATMdb);
				}
				else{
					$res = $TFin_grille_suivi->addLine($linegrille,$typeGrille);
					
					if($res) $TFin_grille_suivi->save($ATMdb);
					else $error = 'ErrorUpdateLine'.$typeLine;
				}
			}
		}
	}

}

foreach ($liste_type_contrat as $typeContrat => $label) {
	$TFin_grille_suivi = new TFin_grille_suivi;
	$grille = $TFin_grille_suivi->get_grille($ATMdb,$typeContrat);
	
	$TGrille[$typeContrat] = $grille;
}


//Toujours en mode edition
$mode = 'edit';

/* *********************************
 * Affichage des grilles de Leasers
 * *********************************/

foreach ($liste_type_contrat as $typeContrat => $label) {
	
	$grille = &$TGrille[$typeContrat];

	print_titre($label);

	$form=new TFormCore($_SERVER['PHP_SELF'],'formGrille'.$typeContrat,'POST');
	$form->Set_typeaff($mode);

	echo $form->hidden('action', 'save');
	echo $form->hidden('typeContrat', $typeContrat );

	$TBS=new TTemplateTBS;
	
	//pre($grille,true);
	
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
				,'montant' => 'de '.$form->texte('', "newline[".$typeContrat."][montantbase]", '', 10).' € à '.$form->texte('', "newline[".$typeContrat."][montantfin]", '', 10).' €'
				,'entreprise' => $form->combo("", "newline[".$typeContrat."][entreprise]", $TFin_grille_suivi->TLeaserByCategories,'')
				,'administration' => $form->combo("", "newline[".$typeContrat."][administration]", $TFin_grille_suivi->TLeaserByCategories,'')
				,'association' => $form->combo("", "newline[".$typeContrat."][association]", $TFin_grille_suivi->TLeaserByCategories,'')
			)
			
		)
	);

	print $form->end_form();

}

echo '<hr><br><br>';

/* *************************************************************************
 * Affichage du tableau permettant de définir l'ordre par défaut des leasers
 * ************************************************************************/
 
$typeContrat = "DEFAUT_LOCATION";
print_titre('Ordre des leasers par défaut Location Simple');

_affOrdreLeaser($ATMdb,$TBS,$TFin_grille_suivi,$mode,$typeContrat);

$typeContrat = "DEFAUT_FORFAIT";
print_titre('Ordre des leasers par défaut Forfait global');

_affOrdreLeaser($ATMdb,$TBS,$TFin_grille_suivi,$mode,$typeContrat);

$typeContrat = "DEFAUT_INTEGRAL";
print_titre('Ordre des leasers par défaut Integral');

_affOrdreLeaser($ATMdb,$TBS,$TFin_grille_suivi,$mode,$typeContrat);

function _affOrdreLeaser(&$ATMdb,&$TBS,&$TFin_grille_suivi,$mode,$typeContrat){
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formGrille'.$typeContrat,'POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('action', 'save');
	echo $form->hidden('typeContrat', $typeContrat );
	
	$ATMdb->Execute("SELECT rowid, fk_leaser_solde, montantbase FROM ".MAIN_DB_PREFIX."fin_grille_suivi WHERE fk_type_contrat = '".$typeContrat."' ORDER BY montantbase ASC");
	$ordre = 1;
	$grille = array();
	while ($ATMdb->Get_line()) {
		$grille[] = array(
			'rowid'=>$ATMdb->Get_field('rowid')
			,'leaser'=>$form->combo("", "TGrille[".$typeContrat."][".$ATMdb->Get_field('rowid')."][leaser]", $TFin_grille_suivi->TLeaserByCategories,$ATMdb->Get_field('fk_leaser_solde'))
			,'ordre'=>$form->hidden("TGrille[".$typeContrat."][".$ATMdb->Get_field('rowid')."][ordre]",$ATMdb->Get_field('montantbase'))
		);
		$ordre  = $ATMdb->Get_field('montantbase')+1;
	}
	
	//pre($TFin_grille_suivi->TLeaserByCategories,true);exit;
	
	print $TBS->render('../tpl/findefaut.suivi.tpl.php'
		,array(
			'grille'=>$grille
		)
		,array(
			'view'=>array(
				'mode'=>$mode
			)
			,'newline'=>array(
				'leaser' => $form->combo("", "newline[".$typeContrat."][leaser]", $TFin_grille_suivi->TLeaserByCategories,'')
				,'ordre' => $form->hidden("newline[".$typeContrat."][ordre]",$ordre)
			)
			
		)
	);
	
	print $form->end_form();
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$ATMdb->close();

$db->close();