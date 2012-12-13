<?php

/* Copyright (C) 2012	  Maxime Kohlhaas		<maxime.kohlhaas@atm-consulting.fr>
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

/**		\file		tpl/score.tpl.php
 *		\ingroup	financement
 *		\brief		Scores d'un client
 */


$head = societe_prepare_head($societe);
dol_fiche_head($head, 'scores', $langs->trans("ThirdParty"),0,'company');
?>
<table class="border" width="100%">
	<tr>
		<td width="20%"><?php echo $langs->trans('ThirdPartyName') ?></td>
		<td colspan="3">
			<?php echo $form->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom') ?>
		</td>
	</tr>
	<tr>
		<td><?php echo $langs->transcountry('ProfId1',$societe->country_code) ?></td>
		<td><?php echo $societe->idprof1 ?></td>
	</tr>
	<tr>
		<td valign="top"><?php echo $langs->trans('Address') ?></td>
		<td><?php echo dol_print_address($societe->address,'gmap','thirdparty',$societe->id) ?></td>
	</tr>
	<tr>
		<td width="25%"><?php echo $langs->trans('Zip') ?> / <?php echo $langs->trans("Town") ?></td>
		<td><?php echo $societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town ?></td>
	</tr>
	<tr>
		<td><?php echo $langs->trans("Country") ?></td>
		<td>
			<?php
			$img=picto_from_langcode($societe->country_code);
			if ($societe->isInEEC()) print $form->textwithpicto(($img?$img.' ':'').$societe->country,$langs->trans("CountryIsInEEC"),1,0);
			else print ($img?$img.' ':'').$societe->country;
			?>
		</td>
	</tr>
</table>

<div class="tabsAction">

<?php if ((!$action || $cancel) && $user->rights->financement->score->write) { ?>
	<a class="butAction" href="<?php echo $_SERVER["PHP_SELF"] ?>?socid=<?php echo $societe->id ?>&amp;action=new"><?php echo $langs->trans("NewScore") ?></a>
<?php } ?>

</div>
<br>

<?php if (($action == 'new' || $action == 'edit') && $user->rights->financement->score->write) { ?>
<?php print_fiche_titre($langs->trans("NewScore"),'','') ?>
	
<form action="<?php echo $_SERVER["PHP_SELF"] ?>?socid=<?php echo $societe->id ?>" method="POST">
	<input type="hidden" name="token" value="<?php echo $_SESSION['newtoken'] ?>">
	<input type="hidden" name="action" value="add">
	<input type="hidden" name="id" value="<?php echo $object->id ?>">

	<table class="border" width="100%">
	<tr>
		<td><?php echo $langs->trans("Score") ?></td>
		<td><input type="text" name="score" value="<?php echo $object->score ?>" /></td>
	</tr>

	<tr>
		<td width="15%"><?php echo  $langs->trans('EncoursMax') ?></td>
		<td><input type="text" name="encours_max" value="<?php echo $object->encours_max ?>" /></td>
	</tr>

	<tr>
		<td width="15%"><?php echo $langs->trans('Date') ?></td>
		<td><?php echo $form->select_date($object->date,'dt') ?></td>
	</tr>
	</table>
	
	<center><br><input type="submit" class="button" value="<?php echo $langs->trans("Save") ?>">&nbsp;
	<input type="submit" class="button" name="cancel" value="<?php echo $langs->trans("Cancel") ?>"></center>

	<br />
</form>
<?php } ?>