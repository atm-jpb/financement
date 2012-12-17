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

// Mode admin, possibilité d'ajouter une période / un palier et les coeff correspondant
if(!empty($liste_coeff)) {
	$first_elem = reset($liste_coeff); // On recopie les coeff de la premiere ligne
	foreach($first_elem as &$values) $values = array(); // On vide les valeurs
	$liste_coeff[''] = $first_elem; // On ajoute cette ligne vide à la fin du tableau
} else {
	$liste_coeff[''] = array(); // Pas de coeff préexistant, on permet d'en créer un
}

?>
<form action="<?php echo $_SERVER["PHP_SELF"] ?>" method="POST">
	<input type="hidden" name="action" value="save">
	<input type="hidden" name="idTypeContrat" value="<?php echo $idTypeContrat ?>">
	<input type="hidden" name="idLeaser" value="<?php echo $idLeaser ?>">
	
	<table class="noborder" width="100%">
<?php $i = 0; ?>
<?php foreach($liste_coeff as $periode => $palier) { ?>
	
	<?php if($first) { ?>	
	<tr class="liste_titre"><td><?php echo $langs->trans("Periode").' / '.$langs->trans("Paliers") ?></td>
	<?php } ?>
	
	<?php
		$palier[''] = '';
		if($first) {
			$j = 0;
			$min = 0;
			foreach ($palier as $montant => $values) {
				$coeff = $values['coeff'];
				$rowid = $values['rowid'];
				$max = $montant;
				print '<td align="center">'.$langs->trans('From');
				print ' '.$min.' ';
				print $langs->trans('To');
				print ' <input type="text" class="flat" style="text-align: center;" name="tabPalier['.$j.']" size="8" value="'.$max.'" /> &euro;</td>';
				$j++;
				$min = $max;
			}
			$first = false;
		}
	?>
	
	<tr>
		<td>
			<input type="text" class="flat" name="tabPeriode[<?php echo $i ?>]" size="2" value="<?php echo $periode ?>" />
			<?php echo $langs->trans("Trimestres") ?>
		</td>
	
	<?php
		$j = 0;
		foreach ($palier as $montant => $values) {
			$coeff = $values['coeff'];
			$rowid = $values['rowid'];
			print '<td align="center">';
			print '<input type="hidden" name="tabCoeff['.$i.']['.$j.'][rowid]" value="'.$rowid.'" />';
			print '<input type="text" class="flat" name="tabCoeff['.$i.']['.$j.'][coeff]" size="5" value="'.$coeff.'" /> %';
			print '</td>';
			$j++;
		}
		
		$i++;
	?>
	</tr>
<?php } ?>
</table>
<input type="submit" name="save" value="<?php echo $langs->trans('Save') ?>" class="button" />
</form>
<br />