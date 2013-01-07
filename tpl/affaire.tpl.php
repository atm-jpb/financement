[onshow;block=begin;when [view.mode]=='view']

	
		<div class="fiche"> <!-- begin div class="fiche" -->
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_financementico.png"> Affaire</a>
		<a href="?id=[affaire.id]" class="tab" id="active">Fiche</a>
		</div>
		
			<div class="tabBar">
				
[onshow;block=end]				
				
			<table width="100%" class="border">
			<tr><td width="20%">Numéro</td><td>[affaire.reference; strconv=no]</td></tr>
			
			<tr><td>Montant du financement</td><td>[affaire.montant; strconv=no]</td></tr>
			<tr><td>Nature du financement</td><td>[affaire.nature_financement; strconv=no]</td></tr>
			<tr><td>type de financement</td><td>[affaire.type_financement; strconv=no]</td></tr>

			<tr><td>type de contrat</td><td>[affaire.contrat; strconv=no]</td></tr>
			<tr><td>type de matériel</td><td>[affaire.type_materiel; strconv=no]</td></tr>
			<tr><td>Date de l'affaire</td><td>[affaire.date_affaire; strconv=no]</td></tr>
			
			</table>
			
			[onshow;block=begin;when [view.mode]!='view']
			<p>Ajouter le dossier numéro : <input type="text" value="" name="dossier_to_add" size="20" id="dossier_to_add" /><input type="button" value="Ajouter" name="add_dossier" class="button" onclick="$('#action').val('add_dossier'); $('#formAff').submit();"></p> 
			<script language="javascript">
				$('#dossier_to_add').autocomplete({
					source: [view.otherDossier; strconv=no; protect=no]
				});
			</script>
			[onshow;block=end]	
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Dossier numéro <!-- [dossier.id] --></td><td><a href="dossier.php?id=[dossier.id]">[dossier.reference; block=table; strconv=no]</a></td></tr>
			<tr><td width="20%">Montant</td><td>[dossier.montant;strconv=no] &euro; à [dossier.taux;strconv=no]%</td></tr>
			<tr><td width="20%">Date de début - fin</td><td>[dossier.date_debut; strconv=no] - [dossier.date_fin; strconv=no]</td></tr>
			<tr><td width="20%">1ère échéance</td><td>[dossier.echeance1; strconv=no] &euro;</td></tr>
			<tr><td width="20%">Echéance</td><td>[dossier.echeance; strconv=no] &euro;</td></tr>
			<tr><td width="20%">Incident de paiement</td><td>[dossier.incident_paiement; strconv=no]</td></tr>
			[onshow;block=begin;when [view.mode]!='view']
			<tr><td colspan="2" align="right">
				<input type="button" id="action-delete-dossier" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete_dossier&id=[affaire.id]&id_dossier=[dossier.id]'">
			</td></tr>
			[onshow;block=end]	
			</table>
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Montant total financé</td><td>[affaire.montant_ok; strconv=no]</td></tr>
			<tr><td width="20%">Montant restant à financer</td><td>[affaire.solde; strconv=no]</td></tr>
			</table>
			
[onshow;block=begin;when [view.mode]=='view']
	
		</div>

		</div>
		
		<div class="tabsAction">
		<input type="button" id="action-delete" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete&id=[affaire.id]'">
		&nbsp; &nbsp; <a href="?id=[affaire.id]&action=edit" class="butAction">Modifier</a>
		</div>
[onshow;block=end]	
[onshow;block=begin;when [view.mode]!='view']

		<p align="center">
			<input type="submit" value="Enregistrer" name="save" class="button"> 
			&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[affaire.id]'">
		</p>
[onshow;block=end]	

<p align="center" style="font-size: 9px;">
	Crée le [affaire.date_cre] - Mis à jour le [affaire.date_maj]
</p>