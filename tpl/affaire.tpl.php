[onshow;block=begin;when [view.mode]=='view']
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_financementico.png"> Affaire</a>
		<a href="?id=[affaire.id]" class="tab" id="active">Fiche</a>
		<a href="http://srvtherefore/TWA/TheGetDoc.aspx?CtgryNo=4&N_Affaire=[affaire.reference]" class="tab" id="active">Therefore</a>
		</div>
		
			<div class="tabBar">
				
[onshow;block=end]
				
			<table width="100%" class="border">
			<tr><td width="20%">Numéro d'affaire Artis</td><td>[affaire.reference; strconv=no]</td></tr>
			<tr><td width="20%">Client</td><td>[affaire.societe; strconv=no]</td></tr>
			
			
			
			<tr><td>Montant de l'affaire</td><td>[affaire.montant; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td>Nature du financement</td><td>[affaire.nature_financement; strconv=no]</td></tr>
			<tr><td>Type de financement</td><td>[affaire.type_financement; strconv=no]</td></tr>

			<tr><td>Type de contrat</td><td>[affaire.contrat; strconv=no]</td></tr>
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
			<tr><td width="20%">Montant</td><td>[dossier.montant;strconv=no; frm=0 000,00] &euro; à [dossier.taux;strconv=no] %</td></tr>
			<tr><td width="20%">Date de début - fin</td><td>[dossier.date_debut; strconv=no] - [dossier.date_fin; strconv=no]</td></tr>
			<tr><td width="20%">1ère échéance</td><td>[dossier.echeance1; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Echéance</td><td>[dossier.echeance; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Incident de paiement</td><td>[dossier.incident_paiement; strconv=no]</td></tr>
			[onshow;block=begin;when [view.mode]!='view']
			<tr><td colspan="2" align="right">
				<input type="button" id="action-delete-dossier" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete_dossier&id=[affaire.id]&id_dossier=[dossier.id]'">
			</td></tr>
			[onshow;block=end]	
			</table>
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Equipement numéro</td><td><a href="../equipement/fiche.php?id=[asset.rowid]">[asset.serial_number; block=table; strconv=no]</a> </td></tr>
			<tr><td width="20%">Produit</td><td>[asset.produit;strconv=no]</td></tr>
			<tr><td width="20%">Coût copie Noire</td><td>[asset.copy_black; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Coût copie Couleure</td><td>[asset.copy_color; strconv=no; frm=0 000,00] &euro;</td></tr>
			</table>

			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Montant total financé</td><td>[affaire.montant_ok; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Montant restant à financer</td><td>[affaire.solde; strconv=no; frm=0 000,00] &euro;</td></tr>
			</table>
			
[onshow;block=begin;when [view.mode]=='view']
	
		</div>
		
		<div class="tabsAction">
			[onshow; block=div; when [view.userRight]==1]	
		<input type="button" id="action-delete" value="Supprimer" name="cancel" class="butActionDelete" onclick="document.location.href='?action=delete&id=[affaire.id]'">
		&nbsp; &nbsp; <a href="?id=[affaire.id]&action=edit" class="butAction">Modifier</a>
		&nbsp; &nbsp; <a href="dossier.php?action=new&fk_fin_affaire=[affaire.id]&montant=[affaire.montant_val]&nature_financement=[affaire.nature_financement_val]" class="butAction">[onshow; block=a; when [affaire.addDossierButton]==1]Créer un dossier de financement Client</a>
		</div>
[onshow;block=end]	
[onshow;block=begin;when [view.mode]!='view']

		<p align="center">
			[onshow; block=p; when [view.userRight]==1]	
			<input type="submit" value="Enregistrer" name="save" class="button"> 
			&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[affaire.id]'">
		</p>
[onshow;block=end]	

<p align="center" style="font-size: 9px;">
	Crée le [affaire.date_cre] - Mis à jour le [affaire.date_maj]
</p>