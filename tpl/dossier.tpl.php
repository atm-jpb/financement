[onshow;block=begin;when [view.mode]=='view']

	
		<div class="fiche"> <!-- begin div class="fiche" -->
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_reportico.png"> Dossier</a>
		<a href="?id=[dossier.id]" class="tab" id="active">Fiche</a>
		</div>
		
			<div class="tabBar">
				
[onshow;block=end]				
				
			<table width="100%" class="border">
			<tr><td width="20%">Numéro</td><td>[dossier.reference; strconv=no]</td></tr>
			
			<tr><td>Montant financé HT</td><td>[dossier.montant; strconv=no]</td></tr>
			<tr><td>Taux</td><td>[dossier.taux; strconv=no]</td></tr>
			<tr><td>Périodicité</td><td>[dossier.periodicite; strconv=no]</td></tr>

			<tr><td>Durée</td><td>[dossier.duree; strconv=no]</td></tr>
			<tr><td>Date de début</td><td>[dossier.date_debut; strconv=no]</td></tr>
			<tr><td>Date de fin</td><td>[dossier.date_fin; strconv=no]</td></tr>

			<tr><td>1ère échéance</td><td>[dossier.echeance1; strconv=no]</td></tr>
			<tr><td>Echéance</td><td>[dossier.echeance; strconv=no]</td></tr>
			<tr><td>N° prochaine échéance</td><td>[dossier.numero_prochaine_echeance; strconv=no]</td></tr>
			<tr><td>Date de prochaine échéance</td><td>[dossier.date_prochaine_echeance; strconv=no]</td></tr>
			
			<tr><td>Valeur résiduelle</td><td>[dossier.reste; strconv=no]</td></tr>
			<tr><td>Mode de réglement</td><td>[dossier.reglement; strconv=no]</td></tr>
			<tr><td>Montant de prestation</td><td>[dossier.montant_prestation; strconv=no]</td></tr>
			
			<tr><td>Incident de paiement</td><td>[dossier.incident_paiement; strconv=no]</td></tr>
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
