<table class="noborder" width="100%">
	<tr class="liste_titre">
		<td>Périodes / Paliers</td>
		<td align="center">de [palier.lastMontant; block=td] &euro; à [palier.montant;strconv=no] &euro; [palier.toDelete;strconv=no]</td>
		<td></td>
	</tr>
	<tr class="[onshow;block=begin;when [coefficient.#;ope=mod:2]==1]impair[onshow;block=end][onshow;block=begin;when [coefficient.#;ope=mod:2]==0]pair[onshow;block=end]">
		<td align="left"><input type="text" class="flat" name="TPeriode[[view.contrat]][[coefficient.#]]" size="3" value="[coefficient.$; block=tr;strconv=no;sub1]" /> Trimestres</td>
		<td align="center">
			<input type="hidden" name="TCoeff[[view.contrat]][[coefficient.#]][[coefficient_sub1.#]][rowid]" value="[coefficient_sub1.rowid; block=td]" />
			<input type="text" class="flat" name="TCoeff[[view.contrat]][[coefficient.#]][[coefficient_sub1.#]][coeff]" size="5" value="[coefficient_sub1.coeff;]" /> %
		</td>
		<td><a onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette période ?')" href="[page_url]?action=delete_periode&periode=[coefficient.$]&socid=[object.id]&idTypeContrat=[view.contrat]">[img_delete;strconv=no]</a></td>
		<!--<td><input type="text" name="TNewCoeff[[coefficient.$]]" size="5" value="" /> %[onshow;block=td;when [view.mode]=='edit']</td> -->
	</tr>
</table>

<div class="tabsAction">
<input type="submit" name="save" value="Enregistrer" class="button" />
</div>