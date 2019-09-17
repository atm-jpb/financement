<?php
/* Copyright (C) 2003-2004 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin         <regis.houssin@capnetworks.com>
 * Copyright (C) 2013      Cédric Salvador       <csalvador@gpcsolutions.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 *       \file       htdocs/comm/propal/document.php
 *       \ingroup    propal
 *       \brief      Management page of documents attached to a business proposal
 */

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');

$langs->load('compta');
$langs->load('other');

$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOST('id', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

// Security check
$socid='';
if (! empty($user->societe_id))
{
	$action='';
	$socid = $user->societe_id;
}
$result = restrictedArea($user, 'financement', $fk_simu, 'fin_simulation&societe', '', 'fk_soc', 'rowid');

$soc = new Societe($db);
$PDOdb = new TPDOdb;

$object = new TSimulation;
$object->load($PDOdb, $fk_simu, false);
if ($object->id > 0)
{
    if(! empty($id)) {

    }
    $soc->fetch($object->fk_soc);
	$upload_dir = $conf->financement->dir_output.'/'.dol_sanitizeFileName($object->reference).'/conformite';
	include_once DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_pre_headers.tpl.php';
}
else {
    // Pas de conformite sans id
    header('Location: list.php');
    exit;
}

/*
 * Actions
 */



/*
 * View
 */

llxHeader('',$langs->trans('Simulation'),'');
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

$form = new Form($db);

if ($object->id > 0)
{
	$upload_dir = $conf->financement->dir_output.'/'.dol_sanitizeFileName($object->reference).'/conformite';

	$head = simulation_prepare_head($object);
	dol_fiche_head($head, 'conformite', $langs->trans('Simulation'), 0, 'simulation');

	// Construit liste des fichiers
	$filearray=dol_dir_list($upload_dir,"files",0,'','(\.meta|_preview\.png)$',$sortfield,(strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC),1);
	$totalsize=0;
	foreach($filearray as $key => $file)
	{
		$totalsize+=$file['size'];
	}


	print '<table class="border"width="100%">';

	$linkback='<a href="'.DOL_URL_ROOT.'/comm/propal/list.php'.(! empty($socid)?'?socid='.$socid:'').'">'.$langs->trans("BackToList").'</a>';

	// Ref
	print '<tr><td width="25%">'.$langs->trans('Ref').'</td><td colspan="3">';
	print $object->reference.'&nbsp;'.get_picto($object->accord);
	print '</td></tr>';

	if(! empty($id)) {
	    print '<tr>';
	    print '<td>'.$langs->trans('ConformiteStatus').'</td>';
	    print '</tr>';
    }

	// Customer
	print "<tr><td>".$langs->trans("Company")."</td>";
	print '<td colspan="3">'.$soc->getNomUrl(1).'</td></tr>';

	print '<tr><td>'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
	print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.$totalsize.' '.$langs->trans("bytes").'</td></tr>';

	print '<tr>';
	print '<td>'.$langs->trans('RequiredFiles').'</td>';
	print '<td colspan="3">'.$langs->trans('ListOfRequiredFiles').'</td>';
	print '</tr>';

	print '</table>';

	print '</div>';

	$modulepart = 'financement';
	$permission = $user->rights->financement->admin;
	$param = '&fk_simu=' . $object->id.'&id='.$id;
	include_once DOL_DOCUMENT_ROOT . '/core/tpl/document_actions_post_headers.tpl.php';
}
else
{
	print $langs->trans("ErrorUnknown");
}

print '<div class="tabsAction">';
print '';
print '</div>';

llxFooter();
$db->close();
