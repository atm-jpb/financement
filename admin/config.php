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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/financement/lib/admin.lib.php');

if (!$user->rights->financement->admin->write) accessforbidden();

$langs->load('financement@financement');
$langs->load("admin");
$langs->load("errors");
$langs->load('other');

$action = GETPOST('action','alpha');
$ATMdb = new TPDOdb;

/*
 * Actions
 */

if(substr($action,0,4) == 'set_') {
	$key = substr($action, 4);
	$action = 'setvalue';
	$value = GETPOST($key);
} else {
	$value = GETPOST('value','alpha');
}

if ($action == 'setvalue')
{
	$res = dolibarr_set_const($db,$key,$value,'chaine',0,'',$conf->entity);

	if (! $res > 0) $error++;

 	if (! $error)
    {
        $mesg = "<font class=\"ok\">".$langs->trans("SetupSaved")."</font>";
    }
    else
    {
        $mesg = "<font class=\"error\">".$langs->trans("Error")."</font>";
    }
}

if ($action == 'save_penalites_simulateur') {
	foreach($_REQUEST['penalite'] as $rowid => $penalite) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."fin_grille_penalite SET penalite = ".floatval($penalite)." WHERE rowid = ".$rowid;
		$ATMdb->Execute($sql);
	}
}

/*
 * View
 */

llxHeader('',$langs->trans("FinancementSetup"));
$head = financement_admin_prepare_head(null);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("GlobalOptionsForFinancementSimulation"), $linkback);

dol_fiche_head($head, 'config', $langs->trans("Financement"), 0, 'financementico@financement');


print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center">'.$langs->trans("Value").'</td>';
print '<td width="80">&nbsp;</td>';
print "</tr>\n";
$var=true;

// % validation montant pour simulateur
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_PERCENT_VALID_AMOUNT" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("AmountValidationPercent").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_PERCENT_VALID_AMOUNT" value="'.$conf->global->FINANCEMENT_PERCENT_VALID_AMOUNT.'" /> %';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// % validation part de rachat
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_PERCENT_RACHAT_AUTORISE" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("RachatPartPercent").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_PERCENT_RACHAT_AUTORISE" value="'.$conf->global->FINANCEMENT_PERCENT_RACHAT_AUTORISE.'" /> %';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// % validation score pour simulateur
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SCORE_MINI" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("AmountValidationScore").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_SCORE_MINI" value="'.$conf->global->FINANCEMENT_SCORE_MINI.'" /> / 20';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// naf blacklistés pour simulateur
$var=! $var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_NAF_BLACKLIST" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("NAFBlackList").'</td>';
print '<td><textarea name="FINANCEMENT_NAF_BLACKLIST" class="flat" cols="80">'.$conf->global->FINANCEMENT_NAF_BLACKLIST.'</textarea>';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// montant max simulation pour accord auto
$var=! $var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_MONTANT_MAX_ACCORD_AUTO" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("AmountMaxForAutoAgreement").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_MONTANT_MAX_ACCORD_AUTO" value="'.$conf->global->FINANCEMENT_MONTANT_MAX_ACCORD_AUTO.'" /> &euro;';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// % écart mini pour alerte commercial sur facturation intégrale
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("EcartAlerteEmailIntegrale").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL" value="'.$conf->global->FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL.'" /> %';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// % rétribution copies sup N et C * quote part
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_PERCENT_RETRIB_COPIES_SUP" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("PercentRetribCopiesSup").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_PERCENT_RETRIB_COPIES_SUP" value="'.$conf->global->FINANCEMENT_PERCENT_RETRIB_COPIES_SUP.'" /> %';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// nb trimestre pour lequel on fait la somme des copues sup N et C
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_NB_TRIM_COPIES_SUP" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("PercentNbTrimCopiesSup").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_NB_TRIM_COPIES_SUP" value="'.$conf->global->FINANCEMENT_NB_TRIM_COPIES_SUP.'" /> T';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// % augmentation Solde CRD client pour loc adossée ou mandatée
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_PERCENT_AUG_CRD" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("PercentAugCRD").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_PERCENT_AUG_CRD" value="'.$conf->global->FINANCEMENT_PERCENT_AUG_CRD.'" /> %';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// indisponibilité du solde dans la simulation si X factures non payées
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_NB_INVOICE_UNPAID" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("NbInvoiceUnpaid").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_NB_INVOICE_UNPAID" value="'.$conf->global->FINANCEMENT_NB_INVOICE_UNPAID.'" /> factures';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

// NB jours avant disponibilité des dossiers dans les simulation si dossier déjà sélectionné
$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SIMU_NB_JOUR_DOSSIER_INDISPO" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("NbDayDossierIndispo").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_SIMU_NB_JOUR_DOSSIER_INDISPO" value="'.$conf->global->FINANCEMENT_SIMU_NB_JOUR_DOSSIER_INDISPO.'" /> jours';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("NbMoisSoldeBANK").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH" value="'.$conf->global->FINANCEMENT_SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH.'" /> mois';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("NbMoisSoldeCPRO").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH" value="'.$conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH.'" /> mois';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_LIMIT_AMOUNT_TO_SHOW_SOLDE" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FINANCEMENT_MIN_AMOUNT_TO_SHOW_SOLDE").'</td>';
print '<td align="right">>= <input placeholder="50000" size="10" class="flat" type="text" name="FINANCEMENT_MIN_AMOUNT_TO_SHOW_SOLDE" value="'.$conf->global->FINANCEMENT_MIN_AMOUNT_TO_SHOW_SOLDE.'" /> &euro;';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

print '</table><br /><br />';

print_titre($langs->trans("PenalitesForSimulation"));

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="save_penalites_simulateur" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center">'.$langs->trans("Value").'</td>';
print '<td width="80"><input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" /></td>';
print "</tr>\n";
$var=true;

// Pénalités simulateur
$ATMdb->Execute("SELECT rowid, opt_name, opt_value, penalite FROM ".MAIN_DB_PREFIX."fin_grille_penalite WHERE entity IN(".getEntity().")");
$var=! $var;

while($ATMdb->Get_line()) {
	print '<tr '.$bc[$var].'><td>';
	print $langs->trans($ATMdb->Get_field('opt_name')).' : '.$langs->trans($ATMdb->Get_field('opt_value')).'</td>';
	print '<td colspan="2"><input type="text" name="penalite['.$ATMdb->Get_field('rowid').']" value="'.$ATMdb->Get_field('penalite').'" size="5"/> %';
	print '</td>';
	print "</tr>\n";
	$var=! $var;
}

print '</table><br />';

print '</form>';

print_titre($langs->trans("PenalitesForSuiviIntegrale"));

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_PENALITE_SUIVI_INTEGRALE" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center">'.$langs->trans("Value").'</td>';
print '<td width="80"><input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" /></td>';
print "</tr>\n";
$var=true;

print '<tr '.$bc[$var].'><td>';
print 'Pourcentage à appliquer</td>';
print '<td colspan="2"><input type="text" name="FINANCEMENT_PENALITE_SUIVI_INTEGRALE" value="'.$conf->global->FINANCEMENT_PENALITE_SUIVI_INTEGRALE.'" size="5"/> %';
print '</td>';
print "</tr>\n";

print '</table><br />';

print_titre($langs->trans("ScriptsManuallyLaunchable"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center" width="60">'.$langs->trans("Value").'</td>';
print '<td width="80">&nbsp;</td>';
print "</tr>\n";
$var=true;

?>
<tr>
	<td>Génération des factures Leaser</td>
	<td colspan="2">
		<a href="../script/create-facture-leaser.php" target="_blank">Lancer le script</a>
	</td>
</tr>
<?php

/*
$var=! $var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="setforcedate" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("AmountValidationPercent");
print '</td><td width="60" align="center">';
print $form->selectyesno("forcedate",$conf->global->FINANCEMENT_PERCENT_VALID_AMOUNT,1);
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';
*/





dol_htmloutput_mesg($mesg);

dol_fiche_end();

$db->close();

llxFooter();