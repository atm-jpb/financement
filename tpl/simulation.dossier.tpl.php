<table id="[liste.id]" class="liste" width="100%">
	<tr class="liste_titre">
		<td>N° contrat</td>
		<td>Type contrat</td>
		<td>Début</td>
		<td>Fin</td>
		<td>Solde R.</td>
		<td>Solde NR.</td>
		<td>Leaser</td>
		<td align="center" class="liste_titre">Rachat</td>
	</tr>
	<tr class="impair">
		<td>[champs.num_contrat;block=tr; strconv=no]</td>
		<td>[champs.type_contrat; strconv=no]</td>
		<td>[champs.debut; strconv=no; frm=dd/mm/yy]</td>
		<td>[champs.fin; strconv=no; frm=dd/mm/yy]</td>
		<td>[champs.solde_r; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.solde_nr; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.leaser; strconv=no]</td>
		<td align="center">[champs.checkbox; strconv=no]</td>
	</tr>
	<tr class="pair">
		<td>[champs.num_contrat;block=tr; strconv=no]</td>
		<td>[champs.type_contrat; strconv=no]</td>
		<td>[champs.debut; strconv=no; frm=dd/mm/yy]</td>
		<td>[champs.fin; strconv=no; frm=dd/mm/yy]</td>
		<td>[champs.solde_r; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.solde_nr; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.leaser; strconv=no]</td>
		<td align="center">[champs.checkbox; strconv=no]</td>
	</tr>
</table>