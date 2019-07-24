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
    // Dans certains cas, on souhaite forcer l'entité de la conf
    $entity = $conf->entity;
    if(array_key_exists('entity', $_REQUEST)) $entity = $_REQUEST['entity'];

	if ($key == 'FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI' && !empty($value)) $value = implode(',', $value);
    $res = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $entity);

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
$head = financement_admin_prepare_head();

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("GlobalOptionsForFinancementSimulation"), $linkback);

dol_fiche_head($head, 'webservice', $langs->trans("Financement"), 0, 'financementico@financement');

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
print '</table>';

print '<br />';
print_fiche_titre('CM CIC', '', '');
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td colspan="2" align="center">'.$langs->trans("Value").'</td>';
print "</tr>\n";
$var=true;

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
print '<input type="hidden" name="entity" value="0">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC">';
print '<input type="text" name="FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC" value="'.$conf->global->FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC.'" size="60" placeholder="'.dol_buildpath('/financement/script/webservice/scoring_cmcic.php?wsdl', 2).'" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_CMCIC_B2B_VENDEUR_ID").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="entity" value="0">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_CMCIC_B2B_VENDEUR_ID">';
print '<input type="text" name="FINANCEMENT_CMCIC_B2B_VENDEUR_ID" value="'.$conf->global->FINANCEMENT_CMCIC_B2B_VENDEUR_ID.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_CMCIC_USERNAME").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="entity" value="0">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_CMCIC_USERNAME">';
print '<input type="text" name="FINANCEMENT_CMCIC_USERNAME" value="'.$conf->global->FINANCEMENT_CMCIC_USERNAME.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_CMCIC_PASSWORD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="entity" value="0">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_CMCIC_PASSWORD">';
print '<input type="password" name="FINANCEMENT_CMCIC_PASSWORD" value="'.$conf->global->FINANCEMENT_CMCIC_PASSWORD.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';
print '</table>';

print '<br />';
print_fiche_titre('GRENKE', '', '');
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td colspan="2" align="center">'.$langs->trans("Value").'</td>';
print "</tr>\n";
$var=true;

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_WSDL_GRENKE_RECETTE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_WSDL_GRENKE_RECETTE">';
print '<input type="text" name="FINANCEMENT_WSDL_GRENKE_RECETTE" value="'.$conf->global->FINANCEMENT_WSDL_GRENKE_RECETTE.'" size="60" placeholder="https://uatleasingapifr.grenke.net/mainservice.asmx?WSDL" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_WSDL_GRENKE_PROD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_WSDL_GRENKE_PROD">';
print '<input type="text" name="FINANCEMENT_WSDL_GRENKE_PROD" value="'.$conf->global->FINANCEMENT_WSDL_GRENKE_PROD.'" size="60" placeholder="https://TODO.wsdl" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_GRENKE_USERNAME").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_GRENKE_USERNAME">';
print '<input type="text" name="FINANCEMENT_GRENKE_USERNAME" value="'.$conf->global->FINANCEMENT_GRENKE_USERNAME.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_GRENKE_PASSWORD").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_GRENKE_PASSWORD">';
print '<input type="password" name="FINANCEMENT_GRENKE_PASSWORD" value="'.$conf->global->FINANCEMENT_GRENKE_PASSWORD.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_GRENKE_CODE_CESSION").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_GRENKE_CODE_CESSION">';
print '<input type="text" name="FINANCEMENT_GRENKE_CODE_CESSION" value="'.$conf->global->FINANCEMENT_GRENKE_CODE_CESSION.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print '</td></tr>';

$var=!$var;
print '<tr '.$bc[$var].'>';
print '<td>'.$langs->trans("FINANCEMENT_GRENKE_CODE_MANDATEE").'</td>';
print '<td align="center" width="20">&nbsp;</td>';
print '<td align="right" width="600">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_FINANCEMENT_GRENKE_CODE_MANDATEE">';
print '<input type="text" name="FINANCEMENT_GRENKE_CODE_MANDATEE" value="'.$conf->global->FINANCEMENT_GRENKE_CODE_MANDATEE.'" size="30" />';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
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