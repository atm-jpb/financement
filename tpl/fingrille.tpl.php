<table class="noborder" width="100%">
	<tr class="liste_titre">
		<td>Périodes / Paliers</td>
		<td align="center">de [palier.lastMontant; block=td] à [palier.montant;strconv=no]</td>
		<td><input type="text" name="newPalier" value="" />[onshow;block=td;when [view.mode]=='edit']</td>
	</tr>
	<tr >
		<td align="center">[periode.valeur;strconv=no]</td>
		<td align="center">
			<input type="hidden" name="tabCoeff['.$i.']['.$j.'][rowid]" value="'.$rowid.'" />
			<input type="text" class="flat" name="tabCoeff['.$i.']['.$j.'][coeff]" size="5" value="'.$coeff.'" /> %
		</td>
	</tr>
</table>
				