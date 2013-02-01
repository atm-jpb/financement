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
print_fiche_titre($langs->trans("FinancementSetup"),'','setup32@financement');
$head = financement_admin_prepare_head(null);

dol_fiche_head($head, 'grille', $langs->trans("Financement"), 0, 'financementico@financement');
dol_htmloutput_mesg($mesg);

/**
 * ACTIONS
 */

$ATMdb=new Tdb;
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
	
	$idTypeContrat = GETPOST('idTypeContrat');
	$idLeaser = GETPOST('idLeaser');
	
	$newPalier = GETPOST('newPalier');
	$newPeriode = GETPOST('newPeriode');
	$TNewCoeff = GETPOST('TNewCoeff');
	print_r($TCoeff);
	
	$tabStrConversion = array(',' => '.', ' ' => ''); // Permet de transformer les valeurs en nombres

	$grille = & $TGrille[$idTypeContrat];
	
	$grille->addPalier($newPalier);
	$grille->addPeriode($newPeriode);
	
	$ATMdb->db->debug=true;
	
	if(!empty($TCoeff)) {
		
		foreach($TCoeff as $iPeriode=>$TPalier) {
			
			$periode = $TPeriode[$iPeriode];
			
			foreach($TPalier as $montant=>$palier) {
				$grilleLigne = new TFin_grille_leaser;
				if($palier['rowid']>0)	$grilleLigne->load($ATMdb, $palier['rowid']);
				
				if($palier['rowid']>0 && empty($palier['coeff'])) $grilleLigne->delete($ATMdb);
				else {
					$grilleLigne->coeff=(double)strtr($palier['coeff'], $tabStrConversion);
					$grilleLigne->montant=(double)strtr($montant, $tabStrConversion);
					$grilleLigne->periode=(int)$palier['periode'];
					$grilleLigne->fk_soc = $idLeaser;
					$grilleLigne->fk_type_contrat = $idTypeContrat;
					$grilleLigne->save($ATMdb);
					
				}
			}
		}
		
	
	}
}

// Grille de coeff globale + % de pénalité par option

$mode = 'edit';
foreach ($liste_type_contrat as $idTypeContrat => $label) {
	$grille = & $TGrille[$idTypeContrat];
	
	$TCoeff = $grille->TGrille;
	
	print_titre($langs->trans("GlobalCoeffGrille").' - '.$label);
	
	//include '../tpl/admin.grille.tpl.php';
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formGrille'.$idTypeContrat,'POST');
	$form->Set_typeaff($mode);
	
	
	$TPalier=array();
	foreach($grille->TPalier as $i=>$palier) {
		$TPalier[]=array(
			'montant'=>$form->texte('','TPalier['.$i.']', $palier['montant'],10,255).' &euro;'
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
			'view'=>array('mode'=>$mode)
			
		)
	);
	
	print $form->end_form();
	
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$ATMdb->close();

$db->close();