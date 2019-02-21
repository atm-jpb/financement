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
//    var_dump($key, $value);exit;
    $res = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
    if(!$res > 0) $error++;

    if(!$error) {
//        $mesg = "<font class=\"ok\">".$langs->trans("SetupSaved")."</font>";
        setEventMessages($langs->trans("SetupSaved"), array());
    }
    else {
//        $mesg = "<font class=\"error\">".$langs->trans("Error")."</font>";
        setEventMessages($langs->trans("Error"), array(), 'errors');
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
$head = financement_admin_prepare_head();

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("GlobalOptionsForFinancementSimulation"), $linkback);

dol_fiche_head($head, 'accord_auto', $langs->trans("Financement"), 0, 'financementico@financement');

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td align="center" width="20%">'.$langs->trans("Value").'</td>';
print "</tr>\n";
$var=true;

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$form->textwithpicto($langs->trans('FinancementActivateAccordAuto'), $langs->trans('FinancementActivateAccordAuto_tooltip', $conf->global->FINANCEMENT_SIMUL_MAX_AMOUNT)).'</td>';
print '<td>'.ajax_constantonoff('FINANCEMENT_ACTIVATE_ACCORD_AUTO').'</td>';
print '</tr>';

$var=!$var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" name="action" value="set_FINANCEMENT_SIMUL_MAX_AMOUNT" />';
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FinancementSimulMaxAmount").'</td>';
print '<td width="80">';
print '<input type="number" name="FINANCEMENT_SIMUL_MAX_AMOUNT" min="0" value="'.$conf->global->FINANCEMENT_SIMUL_MAX_AMOUNT.'" />';
print '<input type="submit" class="button" value="'.$langs->trans("Enregistrer").'" />';
print '</td>';
print '</tr>';
print '</form>';

//print '<tr '.$bc[$var].'>';
//print '<td>'.$langs->trans('FinancementSimulMaxAmount').'</td>';
//print '<td>'.ajax_constantonoff('FINANCEMENT_SIMUL_MAX_AMOUNT').'</td>';
//print '</tr>';

print '</table>';


dol_htmloutput_mesg($mesg);

dol_fiche_end();

$db->close();

llxFooter();