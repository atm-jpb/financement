<input type="hidden" value="[view.MONTANT_PALIER_DEFAUT]" name="TPalier[1]" id="TPalier[1]" class="text">
<table class="noborder" width="100%">
	<tr class="liste_titre">
		<td align="left">Périodes</td>
		<td align="left">Pénalité leaser</td>
		[onshow;block=begin;when [view.show_pen_interne;noerr=true]==1]
			<td align="left">Pénalité Interne</td>
			<td width="20%">Date d'application (Pen. Int.) [view.selectDate;strconv=no;noerr=true]</td>
		[onshow;block=end]
	</tr>
	<tr class="[onshow;block=begin;when [coefficient.#;ope=mod:2]==1]impair[onshow;block=end][onshow;block=begin;when [coefficient.#;ope=mod:2]==0]pair[onshow;block=end]">
		<td align="left"><input type="text" class="flat" name="TPeriode[[coefficient.#]]" size="3" value="[coefficient.$; block=tr;strconv=no;sub1]" /> Trimestres</td>
		<td align="left">
			<input type="hidden" name="TCoeff[[coefficient.#]][[coefficient_sub1.#]][rowid]" value="[coefficient_sub1.rowid; block=tr]" />
			<input type="text" class="flat" name="TCoeff[[coefficient.#]][[coefficient_sub1.#]][coeff]" size="5" value="[coefficient_sub1.coeff;]" /> %
		</td>
		[onshow;block=begin;when [view.show_pen_interne;noerr=true]==1]
			<td><input type="text" class="flat" name="TCoeff[[coefficient.#]][[coefficient_sub1.#]][coeff_interne]" size="5" value="[coefficient_sub1.coeff_interne;]" /> %</td>
			<td></td>	
		[onshow;block=end]
		<!--<td><input type="text" name="TNewCoeff[[coefficient.$]]" size="5" value="" /> %[onshow;block=td;when [view.mode]=='edit']</td> -->
	</tr>
	<tr class="[onshow;block=begin;when [coefficient.#;ope=mod:2]==1]pair[onshow;block=end][onshow;block=begin;when [coefficient.#;ope=mod:2]==0]impair[onshow;block=end]"><td colspan="[onshow;if [view.show_pen_interne;noerr=true]=1;then '4';else '3']"></td></tr>
	<tr class="[onshow;block=begin;when [coefficient.#;ope=mod:2]==1]impair[onshow;block=end][onshow;block=begin;when [coefficient.#;ope=mod:2]==0]pair[onshow;block=end]">
		<td colspan="[onshow;if [view.show_pen_interne;noerr=true]=1;then '4';else '3']" align="left">
			<input type="text" class="flat" name="newPeriode" size="3" value="" /> Trimestres[onshow;block=tr;when [view.mode]=='edit']
		</td>
	</tr>
	
</table>
<div class="tabsAction">[onshow;block=div;when [view.mode]=='edit']
<input type="submit" name="save" value="Enregistrer" class="button" />
</div>
