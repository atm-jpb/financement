<div id="suivi_leaser"></div>
[onshow;block=begin;when [view.type]=='simul']
	
	[view.titre; strconv=no]
	<br />
	
	<table class="simulation_suivi border" width="100%" style="text-align: center;">

		<tr class="liste_titre" style="text-align: center;">
			<td>Leaser</td>
			<td>Montant Renta</td>
			<td>% renta</td>
			<!--<td>Demande</td> -->
			<td>Date<br>demande</td>
			<td>Résultat</td>
			<td>N° étude / Accord Leaser</td>
			<td>Retours Leaser</td>
			<td>Commentaire Interne</td>
			<td>Enregistrer</td>
			<td>Doc</td>
			<td>Actions</td>
		</tr>
		
		<tr class="[ligne.class]">
			<td style="text-align: left;"><!-- [ligne.#; block=tr] -->[ligne.leaser;strconv=no]</td>
			<td style="text-align: right;">[ligne.object.renta_amount;strconv=no; frm=0,00]</td>
			<td style="text-align: right;">[ligne.show_renta_percent;strconv=no]</td>
			<!--<td>[ligne.demande;strconv=no]</td>-->
			<td>[ligne.date_demande]</td>
			<td>[ligne.resultat;strconv=no]</td>
			<td>[ligne.numero_accord_leaser;strconv=no]</td>
			<td>[ligne.commentaire;strconv=no]</td>
			<td>[ligne.commentaire_interne;strconv=no]</td>
			<td>[ligne.action_save;strconv=no]</td>
			<td>[ligne.doc;strconv=no]</td>
			<td>[ligne.actions;strconv=no]</td>
		</tr>
		
	</table>
	<br /><br />
	
	[view.titre_history; strconv=no]
	<br />
	
	<table class="simulation_suivi border" width="100%" style="text-align: center;">

		<tr class="liste_titre" style="text-align: center;">
			<td>Leaser</td>
			<td>Montant Renta</td>
			<td>% renta</td>
			<!--<td>Demande</td> -->
			<td>Date<br>demande</td>
			<td>Résultat</td>
			<td>N° étude / Accord Leaser</td>
			<td>Commentaire</td>
            <td>Commentaire Interne</td>
		</tr>
		
		<tr class="[TLigneHistorized.class]">
			<td style="text-align: left;"><!-- [TLigneHistorized.#; block=tr] -->[TLigneHistorized.leaser;strconv=no]</td>
			<td style="text-align: right;">[ligne.object.renta_amount;strconv=no; frm=0,00]</td>
			<td style="text-align: right;">[ligne.show_renta_percent;strconv=no]</td>
			<!--<td>[TLigneHistorized.demande;strconv=no]</td>-->
			<td>[TLigneHistorized.date_demande]</td>
			<td>[TLigneHistorized.resultat;strconv=no]</td>
			<td>[TLigneHistorized.numero_accord_leaser;strconv=no]</td>
			<td>[TLigneHistorized.commentaire;strconv=no]</td>
            <td>[TLigneHistorized.commentaire_interne;strconv=no]</td>
		</tr>
		
	</table>
	<br /><br />
	
[onshow;block=end]
</div>