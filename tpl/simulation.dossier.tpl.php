<table id="[liste.id]" class="liste" width="100%">
	<tr class="liste_titre">
		<td>N° contrat</td>
		<td>Leaser</td>
		<td>Type contrat</td>
		<td>Durée</td>
		<td>Loyer</td>
		<td>Début</td>
		<td>Fin</td>
		<td>Prochaine</td>
		<td>Assurance</td>
		<td>Maintenance</td>
		<td>Solde R.</td>
		<td>Solde NR.</td>
		<td align="center" class="liste_titre">Rachat</td>
		<td>Solde R. P+1</td>
		<td>Solde NR. P+1</td>
		<td align="center" class="liste_titre">Rachat P+1</td>
	</tr>
	<tr [champs.class; strconv=no]>
		<td>[champs.num_contrat;block=tr; strconv=no]</td>
		<td>[champs.leaser; strconv=no]</td>
		<td>[champs.type_contrat; strconv=no]</td>
		<td>[champs.duree; strconv=no]</td>
		<td>[champs.echeance; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.debut; strconv=no; frm=dd/mm/yy]</td>
		<td>[champs.fin; strconv=no; frm=dd/mm/yy]</td>
		<td>[champs.prochaine_echeance; strconv=no]</td>
		<td>[champs.assurance; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.maintenance; strconv=no; frm=0 000,00] &euro;</td>
		[onshow;block=begin;when [champs.display_solde]=='0']
		<td colspan="6" align="center">Soldes non disponibles. Contactez le service financement</td>
		[onshow;block=end]
		[onshow;block=begin;when [champs.display_solde]=='1']
		<td>[champs.solde_r; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.solde_nr; strconv=no; frm=0 000,00] &euro;</td>
		<td align="center">[champs.checkbox; strconv=no]</td>
		<td>[champs.solde_r1; strconv=no; frm=0 000,00] &euro;</td>
		<td>[champs.solde_nr1; strconv=no; frm=0 000,00] &euro;</td>
		<td align="center">[champs.checkbox1; strconv=no]</td>
		[onshow;block=end]
	</tr>
</table>