
[onshow;block=begin;when [view.type]=='simul']
	
	[view.titre; strconv=no]
	<br />
	
	<table class="simulation_suivi border" width="100%" style="text-align: center;">

		<tr class="liste_titre" style="text-align: center;">
			<td>Leaser</td>
			<!--<td>Demande</td> -->
			<td>Date<br>demande</td>
			<td>Résultat</td>
			<td>N° accord<br>Leaser</td>
			<td>Coeff.<br>Leaser</td>
			<td>Date sélection<br>Leaser</td>
			<td>Utilisateur</td>
			<td>Actions</td>
		</tr>
		
		<tr class="[ligne.class]">
			<td style="text-align: left;"><!-- [ligne.#; block=tr] -->[ligne.leaser;strconv=no]</td>
			<!--<td>[ligne.demande;strconv=no]</td>-->
			<td>[ligne.date_demande]</td>
			<td>[ligne.resultat;strconv=no]</td>
			<td>[ligne.numero_accord_leaser;strconv=no]</td>
			<td>[ligne.coeff_leaser;strconv=no]</td>
			<td>[ligne.date_selection]</td>
			<td>[ligne.utilisateur;strconv=no]</td>
			<td>[ligne.actions;strconv=no]</td>
		</tr>
		
	</table>
	<br /><br />
	
[onshow;block=end]