<?php
/* Copyright (C) 2001-2003,2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011      Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012      Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010           Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2013      Florian Henry		  	<florian.henry@open-concept.pro>
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
 *   \file       htdocs/societe/note.php
 *   \brief      Tab for notes on third party
 *   \ingroup    societe
 */

require 'config.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/lib/financement.lib.php');

$langs->load("companies");
$langs->load("financement@financement");

// Security check
$id = GETPOST('id', 'int');
$result = restrictedArea($user, 'financement', $id, 'fin_simulation&fin_simulation');

$action = GETPOST('action');

$PDOdb = new TPDOdb;
$object = new TSimulation;
if ($id > 0) $object->load($PDOdb, $id, false);
//var_dump($user->rights->financement);exit;
$permissionnote=1;	// Used by the include of actions_setnotes.inc.php
$permission=1;	// Used by the include of notes.tpl.php


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';	// Must be include, not includ_once
if(preg_match('/setnote/', $action)) {
    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&mainmenu=financement');
    exit;
}


/*
 *	View
 */

$form = new Form($db);

llxHeader('',$langs->trans("Simulation").' - '.$langs->trans("Notes"));
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

if ($id > 0)
{
    /*
     * Affichage onglets
     */
    if (! empty($conf->notification->enabled)) $langs->load("mails");

    $head = simulation_prepare_head($object);

    dol_fiche_head($head, 'note', $langs->trans("Simulation"),0,'company');


    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="id" value="'.$id.'">';

    print '<br>';

    $colwidth='25';
    include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';


    dol_fiche_end();
}

llxFooter();
$db->close();

