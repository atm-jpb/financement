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
dol_include_once('/financement/class/html.formfinancement.class.php');
dol_include_once('/financement/class/grille.class.php');

if (!$user->rights->financement->admin->write) accessforbidden();


llxHeader('',$langs->trans("FinancementSetup"));
print_fiche_titre($langs->trans("FinancementSetup"),'','setup32@financement');
$head = financement_admin_prepare_head(null);

dol_fiche_head($head, 'penalite', $langs->trans("Financement"), 0, 'financementico@financement');
dol_htmloutput_mesg($mesg);

$formfin = new FormFinancement($db);

/**
 * ACTIONS
 */

$error = false;
$mesg = '';
$action = GETPOST('action', 'alpha');

if($action == 'save') {
	$tabCoeff = GETPOST('tabCoeff');
	$tabPeriode = GETPOST('tabPeriode');
	$idTypeContrat = GETPOST('idTypeContrat');
	$idLeaser = GETPOST('idLeaser');
	
	$tabStrConversion = array(',' => '.', ' ' => ''); // Permet de transformer les valeurs en nombres
	
	if(!empty($tabCoeff)) {
		$g = new Grille($db,'PENALITE');
		foreach ($tabCoeff as $iPeriode => $tabVal) {
			foreach ($tabVal as $iPalier => $values) {
				$coeff = floatval(strtr($values['coeff'], $tabStrConversion));
				$rowid = $values['rowid'];
				$periode = intval(strtr($tabPeriode[$iPeriode], $tabStrConversion));
				
print "$iPeriode $iPalier $coeff $rowid $periode $montant<br>";
				if(!empty($periode)) {
					if(!empty($rowid)) { // La valeur existait avant => mise à jour si modifiée
						$g->fetch($rowid);
						
						$g->fk_soc = $idLeaser;
						$g->fk_type_contrat = $idTypeContrat;
						$g->periode = $periode;
						$g->montant = 0;
						$g->coeff = $coeff;
						$g->fk_user = $user->id;
						$res = $g->update($user);
						print "ok";
								
					} else { // Nouvelle valeur => création
						if(!empty($coeff)) {
							$g->fk_soc = $idLeaser;
							$g->fk_type_contrat = $idTypeContrat;
							$g->periode = $periode;
							$g->montant = 0;
							$g->coeff = $coeff;
							$g->fk_user = $user->id;
							$res = $g->create($user);
						}
					}
				} else { // Le montant du palier a été vidé, on supprime les coeff correspondants
					if(!empty($rowid)) {
						$g->fetch($rowid);
						$g->delete($user);
					}
				}
			}
		}
		
		if($res > 0) {
			$mesg = $langs->trans('CoeffCorrectlySaved');
		} else {
			$mesg .= $g->error;
			$error = true;
		}
	}
}

// Grille de coeff globale + % de pénalité par option
$idLeaser = FIN_LEASER_DEFAULT; // Identifiant de la société associée à la grille (C'PRO ici, sera l'identifiant leaser pour les grilles leaser)
$liste_type_contrat = $formfin->array_financement('type_contrat');
foreach ($liste_type_contrat as $idTypeContrat => $label) {
	$grille = new Grille($db, 'PENALITE');
	$liste_coeff = $grille->get_grille($idLeaser, $idTypeContrat,'opt_trimestriel', array(), 'PENALITE');
	
	print_titre('Grille de coefficient de pénalité'.' - '.$label);
	print_r($liste_coeff);
	include '../tpl/admin.grille.penalite.tpl.php';
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$db->close();