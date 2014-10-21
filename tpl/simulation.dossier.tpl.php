<table id="[liste.id]" class="liste" width="100%">
	<tr class="liste_titre">
		<td>N° contrat</td>
		<td style="width: 130px;">Leaser</td>
		<td>Type contrat</td>
		<td align="center">Montant</td>
		<td align="center">D</td>
		<td align="center">Loyer</td>
		<td align="center">Dates</td>
		<td align="center">Prochaine</td>
		<td align="center">Ass.</td>
		<td align="center">Maint.</td>
		<td align="center">Solde R.</td>
		<td align="center">Solde NR.</td>
		<td align="center">R. P+1</td>
		<td align="center">NR. P+1</td>
		<td align="center">Perso</td>
	</tr>
	<tr [champs.class; strconv=no]>
		<td>[champs.num_contrat;block=tr; strconv=no]</td>
		<td>[champs.leaser; strconv=no]</td>
		<td>[champs.type_contrat; strconv=no]</td>
		<td align="right" nowrap="nowrap">[champs.montant; strconv=no; frm=0 000,00] &euro;</td>
		<td align="center">[champs.duree; strconv=no]</td>
		<td align="right" nowrap="nowrap">[champs.echeance; strconv=no; frm=0 000,00] &euro;</td>
		<td align="center">[champs.debut; strconv=no; frm=dd/mm/yy]<br>[champs.fin; strconv=no; frm=dd/mm/yy]</td>
		<td align="center">[champs.prochaine_echeance; strconv=no; frm=dd/mm/yy]<br>[champs.avancement; strconv=no]</td>
		<td align="right" nowrap="nowrap">[champs.assurance; strconv=no; frm=0 000,00] &euro;</td>
		<td align="right" nowrap="nowrap">[champs.maintenance; strconv=no; frm=0 000,00] &euro;</td>
		[onshow;block=begin;when [champs.display_solde]=='0']
		<td colspan="4" align="center">Soldes non disponibles. Contactez le service financement</td>
		[onshow;block=end]
		[onshow;block=begin;when [champs.display_solde]=='1']
		<td align="right" nowrap="nowrap" class="solde"[champs.checkedr;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxr; strconv=no] [champs.solde_r; strconv=no; frm=0 000,00] &euro;</td>
		<td align="right" nowrap="nowrap" class="solde"[champs.checkednr;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxnr; strconv=no] [champs.solde_nr; strconv=no; frm=0 000,00] &euro;</td>
		<td align="right" nowrap="nowrap" class="solde"[champs.checkedr1;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxr1; strconv=no] [champs.solde_r1; strconv=no; frm=0 000,00] &euro;</td>
		<td align="right" nowrap="nowrap" class="solde"[champs.checkednr1;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxnr1; strconv=no] [champs.solde_nr1; strconv=no; frm=0 000,00] &euro;</td>
		[onshow;block=end]
		<td align="right" nowrap="nowrap" class="solde"[champs.checkedperso;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxperso; strconv=no] [champs.soldeperso; strconv=no; frm=0 000,00] &euro;</td>
	</tr>
</table>