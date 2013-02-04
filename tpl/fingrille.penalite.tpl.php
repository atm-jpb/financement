<table class="noborder" width="100%">
	<tr class="liste_titre">
		<td>Périodes</td>
		<td align="center"><input type="hidden" value="999999999" name="TPalier[1]" id="TPalier[1]" class="text"></td>
		
	</tr>
	<tr >
		<td align="left"><input type="text" class="flat" name="TPeriode[[coefficient.#]]" size="3" value="[coefficient.$; block=tr;strconv=no;sub1]" /> Trimestres</td>
		<td align="left">
			<input type="hidden" name="TCoeff[[coefficient.#]][[coefficient_sub1.#]][rowid]" value="[coefficient_sub1.rowid; block=td]" />
			<input type="text" class="flat" name="TCoeff[[coefficient.#]][[coefficient_sub1.#]][coeff]" size="5" value="[coefficient_sub1.coeff;]" /> %
		</td>
		<!--<td><input type="text" name="TNewCoeff[[coefficient.$]]" size="5" value="" /> %[onshow;block=td;when [view.mode]=='edit']</td> -->
	</tr>
	<tr >
		<td align="left"><input type="text" class="flat" name="newPeriode" size="3" value="" /> Trimestres[onshow;block=tr;when [view.mode]=='edit']</td>
	</tr>
	
</table>

<input type="submit" name="save" value="Enregistrer" class="button" />

<br /><br />