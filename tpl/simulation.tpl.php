<table width="100%"cellpadding="0" cellspacing="0">
<tr>
[onshow;block=begin;when [view.type]=='simul']
<td valign="top">
	<div id="simulateur" style="width: 100%;">
	[simulation.titre_simul; strconv=no]
	<br />
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4">Informations client</td>
		</tr>
		<tr>
			<td width="20%">Nom du tiers</td>
			<td width="30%">[client.societe; strconv=no] [client.autres_simul; strconv=no]</a></td>
			<td width="20%">Contact</td>
			<td>[client.contact_externe]</td>
		</tr>
		<tr>
			<td>Adresse</td>
			<td>[client.adresse]</td>
			<td>Code Artis</td>
			<td>[client.code_client]</td>
		</tr>
		<tr>
			<td>CP / Ville</td>
			<td>[client.cpville]</td>
			<td>SIRET</td>
			<td>[client.siret]</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>Code NAF</td>
			<td>[client.naf]</td>
		</tr>
		[onshow;block=begin;when [client.display_score]=1]
		<tr class="liste_titre">
			<td colspan="4">Scoring</td>
		</tr>
		<tr>
			<td>Date du score</td>
			<td align="center">[client.score_date]</td>
			<td>Score</td>
			<td align="center">[client.score] / 20</td>
		</tr>
		<tr>
			<td>Encours CPRO</td>
			<td align="right">[client.encours_cpro; frm=0 000,00] &euro;</td>
			<td>Encours Max</td>
			<td align="right">[client.encours_conseille; frm=0 000,00] &euro;</td>
		</tr>
		[onshow;block=end]
	</table>
	<br />
	[simulation.titre_dossier; strconv=no]
	<br />
	[client.liste_dossier; strconv=no]
	<br />
		
	</div>
</td></tr>
[onshow;block=end]
<tr><td valign="top">
	<div id="calculateur" style="width: 100%;">
	[simulation.titre_calcul; strconv=no]
	<br />
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4">Paramètres globaux</td>
		</tr>
		<tr>
			<td width="30%"><span class="fieldrequired">Type de contrat</span></td>
			<td width="25%">[simulation.fk_type_contrat; strconv=no]</td>
			[onshow;block=begin;when [view.type]=='simul']
			<td width="20%">Référence</td>
			<td width="25%">[simulation.ref; strconv=no]</td>
			[onshow;block=end]
			[onshow;block=begin;when [view.type]!='simul']
			<td width="20%">&nbsp;</td>
			<td width="25%">&nbsp;</td>
			[onshow;block=end]
		</tr>
		<tr>
			<td>Periodicite</td>
			<td>[simulation.opt_periodicite; strconv=no]</td>
			<td>[simulation.user; strconv=no]</td>
			<td>[simulation.date; frm=dd/mm/yyyy]</td>
		</tr>
		<tr>
			<td>Mode de règlement</td>
			<td>[simulation.opt_mode_reglement; strconv=no]</td>
			<td>Type de matériel financé</td>
			<td>[simulation.type_materiel; strconv=no]</td>
			
		</tr>
		<tr>
			<td>Terme</td>
			<td>[simulation.opt_terme; strconv=no]</td>
			<td width="20%">Administration</td>
			<td width="25%">[simulation.opt_administration; strconv=no]</td>
		</tr>
		<tr>
			<td>Calage</td>
			<td>[simulation.opt_calage; strconv=no]</td>
			<td>Date démarrage</td>
			<td>[simulation.date_demarrage; strconv=no]</td>
		</tr>
		<tr class="liste_titre">
			<td colspan="4">Paramètres financiers</td>
		</tr>
		<tr>
			<td>Montant</td>
			<td>[simulation.montant; strconv=no; frm=0 000,00] &euro;</td>
			<td colspan="2" rowspan="3" align="center">
				[onshow;block=begin;when [view.calcul]==1]
				<span style="font-size: 14px;">Montant total financé : [simulation.total_financement; frm=0 000,00] &euro;</span>
				[onshow;block=end]
			</td>
		</tr>
		<tr>
			<td>dont montant rachat</td>
			<td>[simulation.montant_rachete; strconv=no; frm=0 000,00] &euro;</td>
		</tr>
		<tr>
			<td>dont montant rachat concurrence</td>
			<td>[simulation.montant_rachete_concurrence; strconv=no; frm=0 000,00] &euro;</td>
		</tr>
		<tr>
			<td><span class="fieldrequired">Durée</span></td>
			<td>[simulation.duree; strconv=no]</td>
			<td colspan="2">
				[onshow;block=begin;when [view.type]=='simul']
				<center>
				<span style="font-size: 14px;">[simulation.accord; strconv=no]</span><br />
				[simulation.date_validite]
				</center>
				[onshow;block=end]
			</td>
		</tr>
		<tr>
			<td>Coefficient</td>
			<td>[simulation.coeff; strconv=no; frm=0,00] %</td>
			<td colspan="2" rowspan="3">
				[onshow;block=begin;when [view.type]=='simul']
				<center>
				<span style="font-size: 14px;">[simulation.commentaire; strconv=no]</span>
				</center>
				[onshow;block=end]
			</td>
		</tr>
		<tr>
			<td>&Eacute;chéance (Hors prestations)</td>
			<td>[simulation.echeance; strconv=no; frm=0 000,00] &euro;</td>
		</tr>
		<tr>
			<td>Montant trimestriel prestation</td>
			<td>[simulation.montant_presta_trim; strconv=no; frm=0 000,00] &euro;</td>
		</tr>
		[onshow;block=begin;when [view.mode]=='edit']
		<tr>
			<td align="center" colspan="2">
				[simulation.bt_calcul; strconv=no]
				[onshow;block=begin;when [view.type]=='simul']
				[simulation.bt_cancel; strconv=no]
				[onshow;block=end]
			</td>
			<td align="center" colspan="2">
				[onshow;block=begin;when [view.type]=='simul']
				[onshow;block=begin;when [view.calcul]==1]
				[simulation.bt_save; strconv=no]
				[onshow;block=end]
				[onshow;block=end]
			</td>
		</tr>
		[onshow;block=end]
		
		[onshow;block=begin;when [simulation.display_preco]==1]
		<tr class="liste_titre">
			<td colspan="4">Préconisation</td>
		</tr>
		<tr>
			<td>Type de financement</td>
			<td>[simulation.type_financement; strconv=no]</td>
			<td>Leaser</td>
			<td>[simulation.leaser; strconv=no]</td>
		</tr>
		<tr>
			<td>Coefficient final</td>
			<td>[simulation.coeff_final; strconv=no; frm=0,00] %</td>
			<td>Numéro accord</td>
			<td>[simulation.numero_accord; strconv=no]</td>
		</tr>
		[onshow;block=end]
	</table>	
	</div>
</td>
</tr>
</table>

[onshow;block=begin;when [view.mode]=='view']
[onshow;block=begin;when [simulation.accord_confirme]==0; when [simulation.display_preco]==1]
<div class="tabsAction">
	<input type="button" id="action-delete" value="Supprimer" name="cancel" class="butActionDelete" onclick="document.location.href='?action=delete&id=[simulation.id]'">
	<a href="?id=[simulation.id]&action=edit" class="butAction">Modifier</a>
</div>
<br />
[onshow;block=end]
[onshow;block=end]

[onshow;block=begin;when [view.mode]=='edit']
<br />
<center>
	
</center>
<br />
[onshow;block=end]