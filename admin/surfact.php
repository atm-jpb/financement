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

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/financement/lib/admin.lib.php');

global $user, $langs, $db, $conf, $bc;

if(! $user->rights->financement->admin->write) accessforbidden();

$langs->load('financement@financement');
$langs->load("admin");
$langs->load("errors");
$langs->load('other');

$form = new Form($db);

$action = GETPOST('action', 'alpha');
$ATMdb = new TPDOdb;

/*
 * Actions
 */
if(substr($action, 0, 4) == 'set_') {
    $error = 0;

    $key = substr($action, 4);
    $value = GETPOST($key);

    $res = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
    if(! $res > 0) $error++;

    if(! $error) {
        setEventMessages($langs->trans("SetupSaved"), []);
    }
    else {
        setEventMessages($langs->trans("Error"), [], 'errors');
    }
}

/*
 * View
 */

$TJS = $TCss = [];
if(empty($conf->global->MAIN_USE_JQUERY_MULTISELECT)) {
    $conf->global->MAIN_USE_JQUERY_MULTISELECT = 'select2';
    $TJS[] = '/financement/js/select2.full.min.js';
    $TCss[] = '/financement/css/select2.min.css';
}

llxHeader('', $langs->trans("FinancementSetup"), '', '', 0, 0, $TJS, $TCss);
$head = financement_admin_prepare_head();

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("GlobalOptionsForFinancementSimulation"), $linkback);

dol_fiche_head($head, 'surfact', $langs->trans("Financement"), -2, 'financementico@financement');

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center" width="20%" colspan="2">'.$langs->trans("Value").'</td>';
print "</tr>\n";
$var = true;

$var = ! $var;

$TCalulationMethod = [
    'same' => 'surfactSameTerm', 'diff' => 'surfactDifferentTerm'
];

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FinancementSurfactCalculationMethod").'</td>';
print '<td width="80" style="white-space: nowrap;">';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SURFACT_CALCULATION_METHOD" />';
print Form::selectarray('FINANCEMENT_SURFACT_CALCULATION_METHOD', $TCalulationMethod, $conf->global->FINANCEMENT_SURFACT_CALCULATION_METHOD, 1, 0, 0, '', 1);
print '&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" />';
print '</form>';
print '</td>';
print '</tr>';

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FinancementSurfactPercent").'</td>';
print '<td width="80" style="white-space: nowrap;">';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SURFACT_PERCENT" />';
print '<input type="number" name="FINANCEMENT_SURFACT_PERCENT" min="0" value="'.$conf->global->FINANCEMENT_SURFACT_PERCENT.'" />';
print '&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" />';
print '</form>';
print '</td>';
print '</tr>';

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FinancementFraisDossier").'</td>';
print '<td width="80" style="white-space: nowrap;">';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_CONFORMITE_FRAIS_DOSSIER" />';
print '<input type="number" name="FINANCEMENT_CONFORMITE_FRAIS_DOSSIER" min="0" value="'.$conf->global->FINANCEMENT_CONFORMITE_FRAIS_DOSSIER.'" />';
print '&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" />';
print '</form>';
print '</td>';
print '</tr>';

print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FinancementAssurance").'</td>';
print '<td width="80" style="white-space: nowrap;">';
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_CONFORMITE_ASSURANCE" />';
print '<input type="number" name="FINANCEMENT_CONFORMITE_ASSURANCE" min="0" value="'.$conf->global->FINANCEMENT_CONFORMITE_ASSURANCE.'" />';
print '&nbsp;';
print '<input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" />';
print '</form>';
print '</td>';
print '</tr>';

print '</table>';

dol_fiche_end();

$db->close();

llxFooter();