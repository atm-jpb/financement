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

$first = true;
?>	
<table class="border" width="100%">
<?php foreach($liste_coeff as $periode => $palier) { ?>
	
	<?php if($first) { ?>	
	<tr class="liste_titre"><td><?php echo $langs->trans("Periode").' / '.$langs->trans("Paliers") ?></td>
	<?php } ?>
	
	<?php
		if($first) {
			$i = 0;
			$min = 0;
			foreach ($palier as $montant => $values) {
				$coeff = $values['coeff'];
				$rowid = $values['rowid'];
				$max = $montant;
				print '<td align="center">'.$langs->trans('From');
				print ' '.$min.' ';
				print $langs->trans('To');
				print ' '.$max;
				$i++;
				$min = $max;
			}
			$first = false;
		}
		
		if($opt_periodicite == 'opt_trimestriel') {
			$opt_periodicite_label = $langs->trans("Trimestres");
		} else if($opt_periodicite == 'opt_mensuel') {
			$opt_periodicite_label = $langs->trans("Mois");
		}  else {
			$opt_periodicite_label = '';
		}
	?>
	
	<tr><td><?php echo $periode . " " . $opt_periodicite_label ?></td>
	
	<?php
		$i = 0;
		foreach ($palier as $montant => $values) {
			$coeff = $values['coeff'];
			$rowid = $values['rowid'];
			print '<td align="center">';
			print ' '.number_format($coeff, 2, ',', ' ').' %';
			print '</td>';
			$i++;
		}
	?>
	</tr>
<?php } ?>
</table>
<br />