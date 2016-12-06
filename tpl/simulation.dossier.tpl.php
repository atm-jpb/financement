<table id="[liste.id]" class="liste" width="100%">
	<tr class="liste_titre">
		<td align="center">N° contrat / Partenaire<br>Leaser</td>
		<td align="center">Type contrat</td>
		[onshow;block=begin;when [liste.display_montant]=='1']
		<td align="center">Montant</td>
		[onshow;block=end]
		<td align="center">Durée<br>Terme</td>
		<td align="center">Loyer<br>Loyer act.</td>
		<td align="center">D&eacute;but<br>Fin</td>
		<td align="center">Prochaine<br>&eacute;ch&eacute;ance</td>
		<td align="center">Ass.<br>Ass. act</td>
		<td align="center">Maint.</td>
		<td align="center">R.P-1</td>
		<td align="center">NR.P-1</td>
		<td align="center">Solde R.</td>
		<td align="center">Solde NR.</td>
		<td align="center">R. P+1</td>
		<td align="center">NR. P+1</td>
		<td align="center">Retrait<br>copies</td>
	</tr>
	<tr [champs.class; strconv=no]>
		<td id="numcontrat_entity_leaser" style="width:150px">[champs.numcontrat_entity_leaser;block=tr; strconv=no]</td>
		<td id="type_contrat">[champs.type_contrat; strconv=no]</td>
		[onshow;block=begin;when [liste.display_montant]=='1']
		<td id="Montant" align="right" nowrap="nowrap">[champs.montant; strconv=no; frm=0 000,00] &euro;</td>
		[onshow;block=end]
		<td id="duree" align="center">
			[champs.duree; strconv=no]<br>
			[onshow;block=begin;when [champs.terme]=='Echu']
			<span style="color: #FF0000">[champs.terme; strconv=no]</span>
			[onshow;block=end]
			[onshow;block=begin;when [champs.terme]!='Echu']
			[champs.terme; strconv=no]
			[onshow;block=end]
		</td>
		<td id="echeance" align="right" nowrap="nowrap">
			[champs.echeance; strconv=no; frm=0 000,00] &euro;<br>
			[champs.loyer_actualise; strconv=no; frm=0 000,00] &euro;
		
		</td>
		<td id="debut_fin" align="center">
			[champs.debut; strconv=no; frm=dd/mm/yy]<br>
			[champs.fin; strconv=no; frm=dd/mm/yy]
		</td>
		<td id="prochaine_echeance" align="center">
			[champs.prochaine_echeance; strconv=no; frm=dd/mm/yy]<br>
			[champs.avancement; strconv=no]
			[onshow;block=begin;when [champs.reloc]=='OUI']
			<span style="color: #FF0000; font-weight: bold;">Rel</span>
			[onshow;block=end]
		</td>
		<td id="assurance" align="right" nowrap="nowrap">
			[champs.assurance; strconv=no; frm=0 000,00] &euro;<br>
			[champs.assurance_actualise; strconv=no; frm=0 000,00] &euro;
		</td>
		<td id="maintenance" align="right" nowrap="nowrap">[champs.maintenance; strconv=no; frm=0 000,00] &euro;</td>
		[onshow;block=begin;when [champs.display_solde]=='0']
		<td colspan="6" align="center">Soldes non disponibles. Contactez le service financement</td>
		[onshow;block=end]
		[onshow;block=begin;when [champs.display_solde]=='1']
		<td id="solde_rm1" align="right" nowrap="nowrap" class="solde"[champs.checkedrm1;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxrm1; strconv=no] [champs.solde_rm1; strconv=no; frm=0 000,00] &euro; [champs.montantrm1; strconv=no; frm=0 000,00]</td>
		<td id="solde_nrm1" align="right" nowrap="nowrap" class="solde"[champs.checkednrm1;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxnrm1; strconv=no] [champs.solde_nrm1; strconv=no; frm=0 000,00] &euro; [champs.montantnrm1; strconv=no; frm=0 000,00]</td>
		<td id="solde_r" align="right" nowrap="nowrap" class="solde"[champs.checkedr;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxr; strconv=no] [champs.solde_r; strconv=no; frm=0 000,00] &euro; [champs.montantr; strconv=no; frm=0 000,00]</td>
		<td id="solde_nr" align="right" nowrap="nowrap" class="solde"[champs.checkednr;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxnr; strconv=no] [champs.solde_nr; strconv=no; frm=0 000,00] &euro; [champs.montantnr; strconv=no; frm=0 000,00]</td>
		<td id="solde_r1" align="right" nowrap="nowrap" class="solde"[champs.checkedr1;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxr1; strconv=no] [champs.solde_r1; strconv=no; frm=0 000,00] &euro; [champs.montantr1; strconv=no; frm=0 000,00]</td>
		<td id="solde_nr1" align="right" nowrap="nowrap" class="solde"[champs.checkednr1;if [val]=1; then ' style="background-color: #00FF00;"'; else '']>[champs.checkboxnr1; strconv=no] [champs.solde_nr1; strconv=no; frm=0 000,00] &euro; [champs.montantnr1; strconv=no; frm=0 000,00]</td>
		[onshow;block=end]
		<td id="solde_perso" align="right" nowrap="nowrap" class="solde">[champs.checkboxperso; strconv=no] [champs.soldeperso; strconv=no; frm=0 000,00] &euro;</td>
	</tr>
</table>