<table class="noborder" width="100%">
	<tr class="liste_titre">
		<td align="center">Solde ?</td>
		<td align="center">Montant</td>
		<td align="center">Entreprises</td>
		<td align="center">Administrations</td>
		<td align="center">Associations</td>
	</tr>
	<tr class="[onshow;block=begin;when [grille.#;ope=mod:2]==1]impair[onshow;block=end][onshow;block=begin;when [grille.#;ope=mod:2]==0]pair[onshow;block=end]">
		<!-- [grille.rowid; block=tr;strconv=no;sub1] -->
		<td align="center">[grille.solde;strconv=no]</td>
		<td align="center">[grille.montant;strconv=no]</td>
		<td align="center">[grille.entreprise;strconv=no]</td>
		<td align="center">[grille.administration;strconv=no]</td>
		<td align="center">[grille.association;strconv=no]</td>
	</tr>
	<tr  class="[onshow;block=begin;when [grille.#;ope=mod:2]==1]pair[onshow;block=end][onshow;block=begin;when [grille.#;ope=mod:2]==0]impair[onshow;block=end]">
		<td align="center">[newline.solde;strconv=no]</td>
		<td align="center">[newline.montant;strconv=no]</td>
		<td align="center">[newline.entreprise;strconv=no]</td>
		<td align="center">[newline.administration;strconv=no]</td>
		<td align="center">[newline.association;strconv=no]</td>
	</tr>
	
</table>

<div class="tabsAction">
<input type="submit" name="save" value="Enregistrer" class="button" />
</div>