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

/**		\file		reports/tpl/simulateur.tpl.php
 *		\ingroup	financement
 *		\brief		Outil de simulation
 */

?>
<div id="calculateur" style="width: 100%;">

<?php print_fiche_titre($langs->trans("Calculator"),'','simul32.png@financement'); ?>
<br />
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="mode" value="<?php echo $mode ?>" />
	<input type="hidden" name="socid" value="<?php echo $socid ?>" />

	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4"><?php echo $langs->trans('GlobalParameters') ?></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('TypeContrat') ?></td>
			<td><?php echo $formfin->select_financement('type_contrat', $type_contrat, 'type_contrat') ?></td>
			<td><?php echo $langs->trans('Administration') ?></td>
			<td><input type="checkbox" name="opt_administration" value="1" /></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('Periodicite') ?></td>
			<td><?php echo $formfin->select_periodicite($periodicite, 'periodicite') ?></td>
			<td><?php echo $langs->trans('CreditBail') ?></td>
			<td><input type="checkbox" name="opt_creditbail" value="1" /></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('ModeReglement') ?></td>
			<td><?php echo $formfin->select_financement('type_contrat', $type_contrat, 'type_contrat') ?></td>
			<td><?php echo $langs->trans('TermeEchu') ?></td>
			<td><input type="checkbox" name="opt_terme_echu" value="1" /></td>
		</tr>
		<tr class="liste_titre">
			<td colspan="4"><?php echo $langs->trans('FinancialParameters') ?></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('Amount') ?></td>
			<td><input type="text" name="montant" value="<?php echo $montant ?>" /></td>
			<td colspan="2" rowspan="5" align="center">
				<?php if($calcul_ok) { ?>
					<?php if($accord) { ?>
						<span style="font-size: 14px;">Financement OK</span><br /><br />
					<?php } else { ?>
						<span style="font-size: 14px;">Financement en attente</span><br /><br />
					<?php } ?>
					<input type="submit" name="validate_simul" value="<?php echo $langs->trans('ValidateSimul') ?>" class="button" />
				<?php } ?>
			</td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('Duration') ?></td>
			<td><?php echo $formfin->select_duree($type_contrat, $periodicite, $duree, 'duree') ?></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('Echeance') ?></td>
			<td><input type="text" name="echeance" value="<?php echo $echeance ?>" /></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('VR') ?></td>
			<td><input type="text" name="vr" value="<?php echo $vr ?>" /></td>
		</tr>
		<tr>
			<td colspan="2" align="center"><input type="submit" name="calculate" value="<?php echo $langs->trans('Calculate') ?>" class="button" /></td>
		</tr>
	</table>
</form>

</div>

<br />