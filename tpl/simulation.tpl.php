<table width="100%"cellpadding="0" cellspacing="0">
<tr>
[onshow;block=begin;when [view.type]=='simul']
<td valign="top" width="50%" style="padding-right: 5px;">
	<div id="simulateur" style="width: 100%;">
	[simulation.titre_simul; strconv=no]
	<br />
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4">Informations client</td>
		</tr>
		<tr>
			<td width="25%">Nom du tiers</td>
			<td colspan="3">[client.societe; strconv=no]</td>
		</tr>
		<tr>
			<td width="25%">Adresse</td>
			<td width="25%">[client.adresse]</td>
			<td width="25%">SIRET</td>
			<td width="25%">[client.siret]</td>
		</tr>
		<tr>
			<td>CP / Ville</td>
			<td>[client.cpville]</td>
			<td>Code Artis</td>
			<td>[client.code_client]</td>
		</tr>
		[onshow;block=begin;when [client.score]!='']
		<tr>
			<td>Date du score</td>
			<td align="right">[client.score_date]</td>
			<td>Score</td>
			<td align="center">[client.score] / 100</td>
		</tr>
		<tr>
			<td>Encours CPRO</td>
			<td align="right">[client.encours_cpro]</td>
			<td>Encours Max</td>
			<td align="right">[client.encours_max]</td>
		</tr>
		[onshow;block=end]
	</table>
	<br />
	[client.liste_dossier; strconv=no]
		
	</div>
</td>
[onshow;block=end]
<td valign="top" style="padding-left: 5px;">
	<div id="calculateur" style="width: 100%;">
	[simulation.titre_calcul; strconv=no]
	<br />
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4">Paramètres globaux</td>
		</tr>
		<tr>
			<td width="25%"><span class="fieldrequired">Type de contrat</span></td>
			<td width="25%">[simulation.fk_type_contrat; strconv=no]</td>
			<td width="25%">Administration</td>
			<td width="25%">[simulation.opt_administration; strconv=no]</td>
		</tr>
		<tr>
			<td>Periodicite</td>
			<td>[simulation.opt_periodicite; strconv=no]</td>
			<td>Crédit-Bail</td>
			<td>[simulation.opt_creditbail; strconv=no]</td>
		</tr>
		<tr>
			<td>Mode de règlement</td>
			<td>[simulation.opt_mode_reglement; strconv=no]</td>
			<td>Terme</td>
			<td>[simulation.opt_terme; strconv=no]</td>
		</tr>
		<tr class="liste_titre">
			<td colspan="4">Paramètres financiers</td>
		</tr>
		<tr>
			<td>Montant</td>
			<td>[simulation.montant; strconv=no; frm=0 000,00] &euro;</td>
			<td>Utilisateur</td>
			<td>[simulation.user; strconv=no]</td>
		</tr>
		<tr>
			<td><span class="fieldrequired">Durée</span></td>
			<td>[simulation.duree; strconv=no]</td>
			<td>Date</td>
			<td>[simulation.date; frm=dd/mm/yyyy]</td>
		</tr>
		<tr>
			<td>&Eacute;chéance</td>
			<td>[simulation.echeance; strconv=no; frm=0 000,00] &euro;</td>
			<td colspan="2" rowspan="3" align="center">
				[onshow;block=begin;when [view.calcul]==1]
				<span style="font-size: 14px;">Cout total du financement : [simulation.cout_financement; frm=0 000,00] &euro;</span>
				<br /><br />
				<span style="font-size: 14px;">[simulation.accord]</span>
				[onshow;block=end]
			</td>
		</tr>
		<tr>
			<td>Valeur Résiduelle</td>
			<td>[simulation.vr; strconv=no; frm=0,00] &euro;</td>
		</tr>
		<tr>
			<td>Coefficient</td>
			<td>[simulation.coeff; strconv=no; frm=0,00] %</td>
		</tr>
		[onshow;block=begin;when [view.mode]=='edit']
		<tr>
			<td align="center" colspan="2">
				<input type="submit" name="calculate" value="Calculer" class="button" />
				[onshow;block=begin;when [view.type]=='simul']
				<input type="submit" name="cancel" value="Annuler" class="button">
				[onshow;block=end]
			</td>
			[onshow;block=begin;when [view.type]=='simul'; when [view.calcul]==1]
			<td align="center" colspan="2">
				<input type="submit" name="validate_simul" value="Valider simulation" class="button" />
			</td>
			[onshow;block=end]
		</tr>
		[onshow;block=end]
	</table>	
	</div>
</td>
</tr>
</table>

[onshow;block=begin;when [view.mode]=='view']
<div class="tabsAction">
	<input type="button" id="action-delete" value="Supprimer" name="cancel" class="butActionDelete" onclick="document.location.href='?action=delete&id=[simulation.id]'">
	<a href="?id=[simulation.id]&action=edit" class="butAction">Modifier</a>
</div>
<br />
[onshow;block=end]

[onshow;block=begin;when [view.mode]=='edit']
<br />
<center>
	
</center>
<br />
[onshow;block=end]