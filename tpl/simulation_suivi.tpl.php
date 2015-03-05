
[onshow;block=begin;when [view.type]=='simul']
	
	[view.titre; strconv=no]
	<br />
	
	<table class="simulation_suivi border" width="100%">

		<tr class="liste_titre">
			<td>Leaser</td>
			<td>Demande</td>
			<td>Date<br>demande</td>
			<td>Résultat</td>
			<td>N° accord<br>Leaser</td>
			<td>Coeff.<br>Leaser</td>
			<td>Actions</td>
		</tr>
		
		<tr class="impair classfortooltip">
			<td><!-- [ligne.#; block=tr] -->[ligne.leaser;strconv=no]</td>
			<td>[ligne.demande]</td>
			<td>[ligne.date_demande]</td>
			<td>[ligne.resultat]</td>
			<td>[ligne.numero_accord_leaser]</td>
			<td>[ligne.coeff_leaser]</td>
			<td>[ligne.actions]</td>
		</tr>
		
	</table>

	
[onshow;block=end]
