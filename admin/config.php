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

$form = new Form($db);

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
	if ($key == 'FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI' && !empty($value)) $value = implode(',', $value);
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

if ($action =='save_horaires_travail'){
    $debutMatin = GETPOST('DebutMatin');
    $finMatin = GETPOST('FinMatin');
    $debutAprem = GETPOST('DebutAprem');
    $finAprem = GETPOST('FinAprem');

    // valeurs par défaut au cas où
    if($debutMatin == '') $debutMatin = '08:30';
    if($finMatin == '') $finMatin = '12:30';
    if($debutAprem == '') $debutAprem = '14:00';
    if($finAprem == '') $finAprem = '18:00';
    
    $res = dolibarr_set_const($db,'FINANCEMENT_HEURE_DEBUT_MATIN',$debutMatin,'chaine',0,'',$conf->entity);
    if (! $res > 0) $error++;
    
    $res = dolibarr_set_const($db,'FINANCEMENT_HEURE_FIN_MATIN',$finMatin,'chaine',0,'',$conf->entity);
    if (! $res > 0) $error++;
    
    $res = dolibarr_set_const($db,'FINANCEMENT_HEURE_DEBUT_APREM',$debutAprem,'chaine',0,'',$conf->entity);
    if (! $res > 0) $error++;
    
    $res = dolibarr_set_const($db,'FINANCEMENT_HEURE_FIN_APREM',$finAprem,'chaine',0,'',$conf->entity);
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

if ($action == 'save_seuils_alerte'){
    $first = (int)GETPOST('FINANCEMENT_FIRST_WAIT_ALARM');
    $second = (int)GETPOST('FINANCEMENT_SECOND_WAIT_ALARM');
    
    $res = dolibarr_set_const($db,'FINANCEMENT_FIRST_WAIT_ALARM',$first,'chaine',0,'',$conf->entity);
    if (! $res > 0) $error++;
    
    $res = dolibarr_set_const($db,'FINANCEMENT_SECOND_WAIT_ALARM',$second,'chaine',0,'',$conf->entity);
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

/*
 * View
 */
$TJs = $TCss = array();
if (empty($conf->global->MAIN_USE_JQUERY_MULTISELECT))
{
	$conf->global->MAIN_USE_JQUERY_MULTISELECT = 'select2';
	$TJS[] = '/financement/js/select2.full.min.js';
	$TCss[] = '/financement/css/select2.min.css';
}

llxHeader('',$langs->trans("FinancementSetup"), '', '', 0, 0, $TJS, $TCss);
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
print '<input type="hidden" name="action" value="set_FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("NbMoisSoldeNotAvailable").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH" value="'.$conf->global->FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH.'" /> mois';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_MONTANT_PRESTATION_OBLIGATOIRE" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("SimulationMontantPrestationObligatoire").'</td>';
print '<td align="right">';
print $form->selectyesno("FINANCEMENT_MONTANT_PRESTATION_OBLIGATOIRE",$conf->global->FINANCEMENT_MONTANT_PRESTATION_OBLIGATOIRE,1);
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE").'</td>';
print '<td align="right"><input placeholder="50000" size="10" class="flat" type="text" name="FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE" value="'.$conf->global->FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE.'" /> &euro;';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_IMPORT_PREFIX_FOR_ENTITY" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FINANCEMENT_IMPORT_PREFIX_FOR_ENTITY").'</td>';
print '<td align="right"><input size="40" class="flat" type="text" name="FINANCEMENT_IMPORT_PREFIX_FOR_ENTITY" value="'.$conf->global->FINANCEMENT_IMPORT_PREFIX_FOR_ENTITY.'" />';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_PERCENT_MODIF_SIMUL_AUTORISE" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FINANCEMENT_PERCENT_MODIF_SIMUL_AUTORISE").'</td>';
print '<td align="right"><input size="10" class="flat" type="text" name="FINANCEMENT_PERCENT_MODIF_SIMUL_AUTORISE" value="'.$conf->global->FINANCEMENT_PERCENT_MODIF_SIMUL_AUTORISE.'" /> %';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI" />';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI").'</td>';
print '<td align="right">';
// L'ordre défini ici sera aussi celui qui sera respecté lors d'un save, ce qui veux dire qu'on peut mettre via l'interface les methode dans le désordre elle seront sauvegardé dans le sens de ce tableau
$TMethod = array('calcSurfact' => 'Surfact', 'calcSurfactPlus' => 'Surfact+', 'calcComm' => 'Commission', 'calcIntercalaire' => 'Intercalaire', 'calcDiffSolde' => 'Différence solde', 'calcPrimeVolume' => 'Prime Volume', 'calcTurnOver' => 'Turn over');
print $form->multiselectarray('FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI', $TMethod, explode(',', $conf->global->FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI), 0, 0, '', 0, 300);
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

print '</form>';

print_titre("Horaires de travail");

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="save_horaires_travail" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>	Heure de début</td>';
print '<td>	Heure de fin</td>';
print '<td width="80"><input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" /></td>';
print "</tr>\n";
$var=true;

print '<tr '.$bc[$var].'><td>';
print 'Matin </td>';
print '<td>'. _select_time($conf->global->FINANCEMENT_HEURE_DEBUT_MATIN, 'DebutMatin');
print '<td colspan="2">'. _select_time($conf->global->FINANCEMENT_HEURE_FIN_MATIN, 'FinMatin');
print '</td>';
print "</tr>\n";

$var=!$var;

print '<tr '.$bc[$var].'><td>';
print 'Après-midi </td>';
print '<td>'. _select_time($conf->global->FINANCEMENT_HEURE_DEBUT_APREM, 'DebutAprem');
print '<td colspan="2">'. _select_time($conf->global->FINANCEMENT_HEURE_FIN_APREM, 'FinAprem');
print '</td>';
print "</tr>\n";

print '</table><br />';

print '</form>';

print_titre("Seuils d'attente simulation");

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="save_seuils_alerte" />';
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center">'.$langs->trans("Value").'</td>';
print '<td width="80"><input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" /></td>';
print "</tr>\n";
$var=true;

print '<tr '.$bc[$var].'><td>';
print 'Seuil d\'attente moyen</td>';
print '<td colspan="2"><input type="text" name="FINANCEMENT_FIRST_WAIT_ALARM" value="'.$conf->global->FINANCEMENT_FIRST_WAIT_ALARM.'" size="5"/> minutes';
print '</td>';
print "</tr>\n";
$var=!$var;

print '<tr '.$bc[$var].'><td>';
print 'Seuil d\'attente alerte (supérieur au moyen)</td>';
print '<td colspan="2"><input type="text" name="FINANCEMENT_SECOND_WAIT_ALARM" value="'.$conf->global->FINANCEMENT_SECOND_WAIT_ALARM.'" size="5"/> minutes';
print '</td>';
print "</tr>\n";

print '</table><br />';

print '</form>';

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

print '</table>';


print_titre($langs->trans("WebService"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td width="80">&nbsp;</td>';
print '<td align="center">'.$langs->trans("Value").'</td>';
print "</tr>\n";
$var=true;


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_SHOW_RECETTE_BUTTON").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="600">';
print ajax_constantonoff('FINANCEMENT_SHOW_RECETTE_BUTTON',array(),0);
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_WEBSERVICE_ACTIVATE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="600">';
print ajax_constantonoff('FINANCEMENT_WEBSERVICE_ACTIVATE',array(),0);
print '</td></tr>';


$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_WEBSERVICE_ACTIVE_FOR_PROD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="center" width="600">';
print ajax_constantonoff('FINANCEMENT_WEBSERVICE_ACTIVE_FOR_PROD',array(),0);
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_ENDPOINT_CALF_RECETTE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_ENDPOINT_CALF_RECETTE">';
print '<input type="text" name="FINANCEMENT_ENDPOINT_CALF_RECETTE" value="'.$conf->global->FINANCEMENT_ENDPOINT_CALF_RECETTE.'" size="60" placeholder="https://hom-archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_ENDPOINT_CALF_PROD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_ENDPOINT_CALF_PROD">';
print '<input type="text" name="FINANCEMENT_ENDPOINT_CALF_PROD" value="'.$conf->global->FINANCEMENT_ENDPOINT_CALF_PROD.'" size="60" placeholder="https://archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print '<tr '.$bc[$var].'><td>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_WSDL_CMCIC_RECETTE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_WSDL_CMCIC_RECETTE">';
print '<input type="text" name="FINANCEMENT_WSDL_CMCIC_RECETTE" value="'.$conf->global->FINANCEMENT_WSDL_CMCIC_RECETTE.'" size="60" placeholder="https://uat-www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_WSDL_CMCIC_PROD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_WSDL_CMCIC_PROD">';
print '<input type="text" name="FINANCEMENT_WSDL_CMCIC_PROD" value="'.$conf->global->FINANCEMENT_WSDL_CMCIC_PROD.'" size="60" placeholder="https://www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC">';
print '<input type="text" name="FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC" value="'.$conf->global->FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC.'" size="60" placeholder="'.dol_buildpath('/financement/script/webservice/scoring_cmcic.php?wsdl', 2).'" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

print '<tr '.$bc[$var].'><td>';
print '</td></tr>';


print '</table>';


dol_htmloutput_mesg($mesg);

dol_fiche_end();

$db->close();

llxFooter();

function _select_time($selectval = '', $htmlname = 'period', $enabled = 1) {
    $time = 5;
    $heuref = 23;
    $min = 0;
    $options = '<option value=""></option>' . "\n";
    while ( $time < $heuref ) {
        if ($min == 60) {
            $min = 0;
            $time ++;
        }
        
        $ftime = sprintf("%02d", $time) . ':' . sprintf("%02d", $min);
        
        if ($selectval == $ftime)
            $selected = ' selected="selected"';
            else
                $selected = '';
                $options .= '<option value="' . $ftime . '"' . $selected . '>' . $ftime . '</option>' . "\n";
                $min += 15;
    }
    if (empty($enabled)) {
        $disabled = ' disabled="disabeld" ';
    } else {
        $disabled = '';
    }
    
    return '<select class="flat" ' . $disabled . ' name="' . $htmlname . '">' . "\n" . $options . "\n" . '</select>' . "\n";
}