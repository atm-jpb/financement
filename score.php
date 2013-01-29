<?php
/* Copyright (C) 2012      Maxime Kohlhaas        <maxime@atm-consulting.fr>
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
 *	\file       financement/simulateur.php
 *	\ingroup    financement
 *	\brief      Outil de calculateur et de simulateur
 */


require('config.php');

dol_include_once('/financement/class/score.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

if (!($user->rights->financement->score->read))
{
	accessforbidden();
}

$langs->load('financement@financement');

$error = false;
$mesg = '';

$id=GETPOST("id");
$socid=GETPOST("socid");
$action=GETPOST("action");
$cancel=GETPOST("cancel");

$ATMdb = new Tdb();
$societe = new Societe($db);
$societe->fetch($socid);
$object = new TScore($db);
$object->load($ATMdb, $id);

/*
 * Actions
 */

if ($action == 'add' && empty($cancel) && $user->rights->financement->score->write)
{
	$object->fk_soc = $socid;
	$object->score = GETPOST('score', 'int');
	$object->encours_max = GETPOST('encours_max', 'int');
	$object->date=dol_mktime(0, 0, 0, GETPOST('dtmonth'), GETPOST('dtday'), GETPOST('dtyear'));
	$object->fk_user_author=$user->id;
	
	if(empty($id)) {
		$result = $object->create($user);
	} else {
		$result = $object->update($user);
	}
	
	if($result > 0) {
		$action = '';
		$mesg = '<div class="ok">'.$langs->trans("RecordSaved").'</div>';
	}
	else
	{
		$mesg = '<div class="error">'.$object->error.'</div>';
		$error = true;
	}
}
else if ($action == 'delete' && !empty($id) && $user->rights->financement->score->delete)
{
	$result=$object->delete($user);
	if ($result < 0) {
		$mesg='<div class="error">'.$object->error.'</div>';
		$error = true;
	}
}

/*
 * View
 */

$form = new Form($db);

llxHeader('',$langs->trans("ScoreList"));

include 'tpl/score.tpl.php';

/*
 * Evolution des scores
 */
$sql = "SELECT s.rowid, s.score, s.encours_conseille, s.date_score, u.login";
$sql.= " FROM ".MAIN_DB_PREFIX."fin_score as s";
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON s.fk_user_author = u.rowid';
$sql.= " WHERE fk_soc = ".$societe->id;
$sql.= " ORDER BY s.date_score DESC";
//$sql .= $db->plimit();

$result = $db->query($sql);
if ($result)
{
	$num = $db->num_rows($result);

	if ($num > 0)
	{
		print '<br>';

		print '<table class="noborder" width="100%">';

		print '<tr class="liste_titre">';
		print '<td class="liste_titre">'.$langs->trans("ScoreDate").'</td>';

		print '<td class="liste_titre" align="center">'.$langs->trans("Score").'</td>';
		print '<td class="liste_titre" align="center">'.$langs->trans("EncoursMax").'</td>';
		print '<td class="liste_titre" align="center">'.$langs->trans("Author").'</td>';
		if ($user->rights->financement->score->write || $user->rights->financement->score->delete) {
			print '<td class="liste_titre" align="right">&nbsp;</td>';
		}
		print '</tr>';

		$var=true;
		$userstatic=new User($db);
		$i = 0;
		while ($i < $num)
		{
			$obj = $db->fetch_object($result);
			$userstatic->id=$obj->fk_user_author;
			$userstatic->login=$obj->login;

			$var=!$var;
			print '<tr class="'.($var ? 'pair' : 'impair').'">';
			print '<td>'.dol_print_date($db->jdate($obj->date_score),"day").'</td>';
			print '<td align="center">'.$obj->score.'</td>';
			print '<td align="center">'.$obj->encours_conseille.'</td>';
			print '<td align="center">'.$userstatic->getLoginUrl(1).'</td>';

			// Action
			print '<td align="right">';
			if ($user->rights->financement->score->write) {
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=edit&amp;socid='.$societe->id.'&amp;id='.$obj->rowid.'">';
				print img_edit();
				print '</a>';
			}
			if ($user->rights->financement->score->delete)
			{
				print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&amp;socid='.$societe->id.'&amp;id='.$obj->rowid.'">';
				print img_delete();
				print '</a>';
			}
			print '</td>';

			print "</tr>\n";
			$i++;
		}
		$db->free($result);
		print "</table>";
		print "<br>";
	}
}
else
{
	dol_print_error($db);
}

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

// Footer
llxFooter();
// Close database handler
$db->close();
?>
