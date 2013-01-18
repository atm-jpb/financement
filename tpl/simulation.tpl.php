[onshow;block=begin;when [view.mode]=='edit']

<table width="100%"cellpadding="0" cellspacing="0">
<tr>
[onshow;block=begin;when [view.type]=='simul']
<td valign="top" width="50%" style="padding-right: 5px;">
	<div id="simulateur" style="width: 100%;">
	[simulation.titre_simul; strconv=no]
	
	[client.titre_client; strconv=no]
	<table class="border" width="100%">
		<tr>
			<td width="25%">ThirdPartyName</td>
			<td colspan="3">[client.societe; strconv=no]</td>
		</tr>
		<tr>
			<td width="25%">Address</td>
			<td width="25%">[client.adresse]</td>
			<td width="25%">SIRET</td>
			<td width="25%">[client.siret]</td>
		</tr>
		<tr>
			<td>CP / Ville</td>
			<td>[client.cpville]</td>
			<td>ArtisCode</td>
			<td>[client.code_client]</td>
		</tr>
		[onshow;block=begin;when [client.score]=='edit']
		<tr>
			<td>CustomerScoreDate</td>
			<td align="right">[client.score_date]</td>
			<td>CustomerScore</td>
			<td align="center">[client.score] / 100</td>
		</tr>
		<tr>
			<td>CustomerEncoursCPRO</td>
			<td align="right">[client.encours_cpro]</td>
			<td>CustomerEncoursMax</td>
			<td align="right">[client.encours_max]</td>
		</tr>
		[onshow;block=end]
	</table>
		
	</div>
</td>
[onshow;block=end]
<td valign="top" style="padding-left: 5px;">
	<div id="calculateur" style="width: 100%;">
	
	[simulation.titre_calcul; strconv=no]
	<br />
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4">Paramètres globaux></td>
		</tr>
		<tr>
			<td><span class="fieldrequired">TypeContrat</span></td>
			<td>[simulation.fk_type_contrat; strconv=no]</td>
			<td>Administration</td>
			<td>[simulation.opt_administration; strconv=no]</td>
		</tr>
		<tr>
			<td>Periodicite</td>
			<td>[simulation.opt_periodicite; strconv=no]</td>
			<td>CreditBail</td>
			<td>[simulation.opt_creditbail; strconv=no]</td>
		</tr>
		<tr>
			<td>ModeReglement</td>
			<td>[simulation.opt_mode_reglement; strconv=no]</td>
			<td>Terme</td>
			<td>[simulation.opt_terme; strconv=no]</td>
		</tr>
		<tr class="liste_titre">
			<td colspan="4">Paramètres financiers</td>
		</tr>
		<tr>
			<td>Amount</td>
			<td>[simulation.montant; strconv=no]</td>
			
			<td colspan="2" rowspan="5" align="center">
				<span style="font-size: 14px;">CoutTotalFinancement : [simulation.cout_financement]</span>
				[onshow;block=begin;when [view.type]=='simul']
				<br /><br />
				<span style="font-size: 14px;">[simulation.accord]</span>
				[onshow;block=end]
			</td>
		</tr>
		<tr>
			<td><span class="fieldrequired">Duration</span></td>
			<td>[simulation.duree; strconv=no]</td>
		</tr>
		<tr>
			<td>Echeance</td>
			<td>[simulation.echeance; strconv=no]</td>
		</tr>
		<tr>
			<td>VR</td>
			<td>[simulation.vr; strconv=no]</td>
		</tr>
		<tr>
			<td>Coefficient</td>
			<td>[simulation.coefficient; strconv=no]</td>
		</tr>
		<tr>
			<td align="center" colspan="2"><input type="submit" name="calculate" value="Calculer" class="button" /></td>
			[onshow;block=begin;when [view.type]=='simul']
			<td align="center" colspan="2">
				<input type="submit" name="validate_simul" value="Valider simulation" class="button" />
			</td>
			[onshow;block=end]
		</tr>
	</table>	
	</div>
</td>
</tr>
</table>
<br />
[onshow;block=end]