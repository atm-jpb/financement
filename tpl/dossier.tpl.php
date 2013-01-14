[onshow;block=begin;when [view.mode]=='view']

	
		<div class="fiche"> <!-- begin div class="fiche" -->
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_reportico.png"> Dossier</a>
		<a href="?id=[dossier.id]" class="tab" id="active">Fiche</a>
		</div>
		
			<div class="tabBar">
				
[onshow;block=end]				
				
		<table width="100%" class="border">
		<tr>
			<td colspan="2">
				<table width="100%">
				<tr>
					<td width="50%" valign="top">
						<table width="100%"  class="border">	
						<tr class="liste_titre"><td colspan="2">Client</td></tr>
						
						<tr><td width="20%">Numéro de Dossier Client</td><td>[financement.reference; strconv=no]</td></tr>
						<tr><td width="20%">Montant financé HT</td><td>[financement.montant; strconv=no]</td></tr>
						<tr><td>Taux</td><td>[financement.taux; strconv=no]</td></tr>
						<tr><td>Périodicité</td><td>[financement.periodicite; strconv=no]</td></tr>
			
						<tr><td>Durée</td><td>[financement.duree; strconv=no]</td></tr>
						<tr><td>Date de début</td><td>[financement.date_debut; strconv=no]</td></tr>
						<tr><td>Date de fin</td><td>[financement.date_fin; strconv=no]</td></tr>
			
						<tr><td>1ère échéance</td><td>[financement.echeance1; strconv=no]</td></tr>
						<tr><td>Echéance</td><td>[financement.echeance; strconv=no]</td></tr>
						<tr><td>N° prochaine échéance</td><td>[financement.numero_prochaine_echeance; strconv=no]</td></tr>
						<tr><td>Date de prochaine échéance</td><td>[financement.date_prochaine_echeance; strconv=no]</td></tr>
						
						<tr><td>Valeur résiduelle</td><td>[financement.reste; strconv=no]</td></tr>
						<tr><td>Mode de réglement</td><td>[financement.reglement; strconv=no]</td></tr>
						<tr><td>Montant de prestation</td><td>[financement.montant_prestation; strconv=no]</td></tr>
						
						<tr><td>Incident de paiement</td><td>[financement.incident_paiement; strconv=no]</td></tr>
						
						</table>
						
						
					</td>
					<td valign="top">
						
						<table width="100%" class="border">
						<tr class="liste_titre"><td colspan="2">Leaser<!-- [onshow;block=((td));when [financementLeaser.id]!=0] --></td></tr>
						<tr><td width="20%">Numéro de Dossier Leaser</td><td>[financementLeaser.reference; strconv=no]</td></tr>
						<tr><td width="20%">Montant financé HT</td><td>[financementLeaser.montant; strconv=no;]</td></tr>
						<tr><td>Taux</td><td>[financementLeaser.taux; strconv=no]</td></tr>
						<tr><td>Périodicité</td><td>[financementLeaser.periodicite; strconv=no]</td></tr>
			
						<tr><td>Durée</td><td>[financementLeaser.duree; strconv=no]</td></tr>
						<tr><td>Date de début</td><td>[financementLeaser.date_debut; strconv=no]</td></tr>
						<tr><td>Date de fin</td><td>[financementLeaser.date_fin; strconv=no]</td></tr>
			
						<tr><td>1ère échéance</td><td>[financementLeaser.echeance1; strconv=no]</td></tr>
						<tr><td>Echéance</td><td>[financementLeaser.echeance; strconv=no]</td></tr>
						<tr><td>N° prochaine échéance</td><td>[financementLeaser.numero_prochaine_echeance; strconv=no]</td></tr>
						<tr><td>Date de prochaine échéance</td><td>[financementLeaser.date_prochaine_echeance; strconv=no]</td></tr>
						
						<tr><td>Valeur résiduelle</td><td>[financementLeaser.reste; strconv=no]</td></tr>
						<tr><td>Mode de réglement</td><td>[financementLeaser.reglement; strconv=no]</td></tr>
						<tr><td>Montant de prestation</td><td>[financementLeaser.montant_prestation; strconv=no]</td></tr>
						
						<tr><td>Incident de paiement</td><td>[financementLeaser.incident_paiement; strconv=no]</td></tr>
						
						</table>
						
						
					</td>
				</tr>
				<tr>
					<td>Echéancier Client</td>
					<td>Echéancier Leaser<!-- [onshow;block=(td);when [financementLeaser.id]!=0] --></td>
				</tr>
				</table>
				
				
			</td>
		</tr>
		<tr><td>Date de mise en relocation</td><td>[dossier.date_relocation; strconv=no]</td></tr>
						
		
		</table>

			[onshow;block=begin;when [view.mode]!='view']
			<p>Ajouter l'affaire numéro : <input type="text" value="" name="affaire_to_add" size="20" id="affaire_to_add" /><input type="button" value="Ajouter" name="add_affaire" class="button" onclick="$('#action').val('add_affaire'); $('#formAff').submit();"></p> 
			<script language="javascript">
				$('#affaire_to_add').autocomplete({
					source: [view.otherAffaire; strconv=no; protect=no]
				});
			</script>
			[onshow;block=end]	
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Affaire numéro <!-- [affaire.id; block=table;] --></td><td><a href="affaire.php?id=[affaire.id]">[affaire.reference]</a></td></tr>
			<tr><td width="20%">Montant</td><td>[affaire.montant; strconv=no] &euro;</td></tr>
			<tr><td width="20%">Nature du financement</td><td>[affaire.nature_financement; strconv=no]</td></tr>
			<tr><td width="20%">Type de financement</td><td>[affaire.type_financement; strconv=no]</td></tr>
			<tr><td width="20%">Type de contrat</td><td>[affaire.contrat; strconv=no]</td></tr>
			<tr><td width="20%">Type de matériel</td><td>[affaire.type_materiel; strconv=no]</td></tr>
			<tr><td width="20%">Date</td><td>[affaire.date_affaire; strconv=no]</td></tr>
			[onshow;block=begin;when [view.mode]!='view']
			<tr><td colspan="2" align="right">
				<input type="button" id="action-delete-dossier" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete_affaire&id=[dossier.id]&id_affaire=[affaire.id]'">
			</td></tr>
			[onshow;block=end]	
			</table>
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Montant total financé</td><td>[dossier.montant_ok; strconv=no] &euro;</td></tr>
			<tr><td width="20%">Montant restant à débloquer</td><td>[dossier.solde; strconv=no] &euro;</td></tr>
			</table>			
			
			
			
[onshow;block=begin;when [view.mode]=='view']
	
		</div>

		</div>
		
		<div class="tabsAction">
		<input type="button" id="action-delete" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete&id=[dossier.id]'">
		&nbsp; &nbsp; <a href="?id=[dossier.id]&action=edit" class="butAction">Modifier</a>
		</div>
[onshow;block=end]	
[onshow;block=begin;when [view.mode]!='view']

		<p align="center">
			<input type="submit" value="Enregistrer" name="save" class="button"> 
			&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[dossier.id]'">
		</p>
[onshow;block=end]	

<p align="center" style="font-size: 9px;">
	Crée le [dossier.date_cre] - Mis à jour le [dossier.date_maj]
</p>
