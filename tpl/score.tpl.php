[client.dolibarr_societe_head; strconv=no]

<table class="border" width="100%">
	<tr>
		<td width="20%">Nom du tiers</td>
		<td colspan="3">
			[client.showrefnav; strconv=no]
		</td>
	</tr>
	<tr>
		<td>Id prof. 1 SIREN</td>
		<td>[client.idprof1]</td>
	</tr>
	<tr>
		<td valign="top">Adresse</td>
		<td>[client.adresse]</td>
	</tr>
	<tr>
		<td width="25%">Code postal / Ville</td>
		<td>[client.cpville]</td>
	</tr>
	<tr>
		<td>Pays</td>
		<td>[client.pays; strconv=no]</td>
	</tr>
</table>
<br />

[onshow;block=begin;when [view.mode]=='edit']
[score.titre; strconv=no]

	<table class="border" width="100%">
	<tr>
		<td>Score</td>
		<td>[score.score; strconv=no] / 20</td>
	</tr>

	<tr>
		<td width="15%">Encours conseillé</td>
		<td>[score.encours_conseille; strconv=no]</td>
	</tr>

	<tr>
		<td width="15%">Date du score</td>
		<td>[score.date_score; strconv=no]</td>
	</tr>
	</table>
	
	<center><br />[score.bt_save; strconv=no]&nbsp;[score.bt_cancel; strconv=no]</center>

	<br />
</form>
[onshow;block=end]