[onshow;block=begin;when [view.mode]=='view']
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_financementico.png"> Affaire</a>
		<a href="?id=[affaire.id]" class="tab" id="active">Fiche</a>
		<a href="[affaire.url_therefore][affaire.ref]" class="tab" target="_blank">Therefore</a>
		</div>
		
			<div class="tabBar">
				
[onshow;block=end]
				
			<table width="100%" class="border">
			<tr><td width="20%">Partenaire</td><td>[affaire.entity; strconv=no]</td></tr>
			<tr><td width="20%">Numéro d'affaire Artis</td><td>[affaire.reference; strconv=no]</td></tr>
			<tr><td width="20%">Client</td><td>
				[onshow;block=begin;when [view.mode]=='view']
				[affaire.societe; strconv=no]
				[onshow;block=end]
				[onshow;block=begin;when [view.mode]!='view']
				<input type="text" value="[affaire.societe]" name="soc" id="soc" />
				<input type="hidden" value="[affaire.socid]" name="socid" id="socid" />
				<script language="javascript">
					$('#soc').autocomplete({
						source: [view.otherSoc; strconv=no; protect=no]
						,minLength: 3
						,select: function(event, ui) {
							$('#socid').val(ui.item.value);
							$('#soc').val(ui.item.label);
							return false;
						}
					});
				</script>
				[onshow;block=end]
				</td></tr>
			<tr><td>Montant de l'affaire</td><td>[affaire.montant; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td>Nature du financement</td><td>[affaire.nature_financement; strconv=no]</td></tr>
			<tr><td>Type de financement</td><td>[affaire.type_financement; strconv=no]</td></tr>

			<tr><td>Type de contrat</td><td>[affaire.contrat; strconv=no]</td></tr>
			<tr><td>Date de l'affaire</td><td>[affaire.date_affaire; strconv=no]</td></tr>
			[onshow;block=begin;when [view.mode]!='view']
				[onshow;block=begin;when [view.financement_verouille]=='verrouille']
					<tr><td><span style="color: red">Financement Leaser verrouillé, veuillez cocher cette case pour forcer la modification de la classification de l'affaire</span></td><td>[affaire.force_update; strconv=no]</td></tr>
				[onshow;block=end]
			[onshow;block=end]
			</table>
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr><td width="20%">Montant total financé</td><td>[affaire.montant_ok; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Montant restant à financer</td><td>[affaire.solde; strconv=no; frm=0 000,00] &euro;</td></tr>
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
			<tr><td width="20%">Dossier numéro <!-- [dossier.id] -->[dossier.modif_dossier;strconv=no]</td>
					<td><a href="dossier.php?id=[dossier.id]">[dossier.reference; block=table; strconv=no]</a></td></tr>
			<tr><td width="20%">Montant</td><td>[dossier.montant;strconv=no; frm=0 000,00] &euro; à [dossier.taux;strconv=no] %</td></tr>
			<tr><td width="20%">Date de début - fin</td><td>[dossier.date_debut; strconv=no] - [dossier.date_fin; strconv=no]</td></tr>
			<tr><td width="20%">Durée / Périodicité</td><td>[dossier.duree; strconv=no;]</td></tr>
			<tr><td width="20%">Echéance</td><td>[dossier.echeance; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Incident de paiement</td><td>[dossier.incident_paiement; strconv=no]</td></tr>
			[onshow;block=begin;when [view.mode]!='view']
			<tr><td colspan="2" align="right">
				<input type="button" id="action-delete-dossier" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete_dossier&id=[affaire.id]&id_dossier=[dossier.id]'">
			</td></tr>
			[onshow;block=end]	
			</table>
			
			[onshow;block=begin;when [view.mode]!='view']
			<p>Ajouter la facture matériel numéro : <input type="text" value="" name="facture_mat_to_add" size="20" id="facture_mat_to_add" /><input type="button" value="Ajouter" name="add_facture_mat" class="button" onclick="$('#action').val('add_facture_mat'); $('#formAff').submit();"></p> 
			<script language="javascript">
				$('#facture_mat_to_add').autocomplete({
					source: [view.otherFactureMat; strconv=no; protect=no]
				});
				 $("#facture_mat_to_add" ).autocomplete("option", "minLength", 3);
			</script>
			[onshow;block=end]
			
[onshow;block=begin;when [view.mode]=='view']
	
		</div>
		
		<div class="tabsAction">
			[onshow; block=div; when [view.userRight]==1]	
		<input type="button" id="action-delete" value="Supprimer" name="delete" class="butActionDelete" onclick="delete_elem([affaire.id],'affaire');">
		&nbsp; &nbsp; <a href="?id=[affaire.id]&action=edit" class="butAction">Modifier</a>
		[onshow;block=begin;when [view.creer_affaire]=='ok']
			&nbsp; &nbsp; <a href="dossier.php?action=new&fk_fin_affaire=[affaire.id]&montant=[affaire.montant_val]&nature_financement=[affaire.nature_financement_val]" class="butAction">[onshow; block=a; when [affaire.addDossierButton]==1]Créer un dossier de financement Client</a>
		[onshow;block=end]
		</div>
[onshow;block=end]	
[onshow;block=begin;when [view.mode]!='view']
<p style="margin-top: 30px; font-weight: bold;">Création de la facture matériel</p>
        <table width="100%" class="border">
            <tr>
                <td>Numéro de facture</td>
                <td>[fac.reference; strconv=no]</td>
            </tr>
            <tr>
                <td>Date de facture</td>
                <td>[fac.date; strconv=no]</td>
            </tr>
            <tr>
                <td>Numéro de série</td>
                <td>[fac.num_serie; strconv=no]</td>
            </tr>
            <tr>
                <td>Référence matériel</td>
                <td>[fac.refMat; strconv=no]</td>
            </tr>
            <tr>
                <td>Libellé matériel</td>
                <td>[fac.label; strconv=no]</td>
            </tr>
            <tr>
                <td>Leaser</td>
                <td>[fac.leaser; strconv=no]</td>
            </tr>
        </table>
		<p align="center">
			[onshow; block=p; when [view.userRight]==1]	
			<input type="submit" value="Enregistrer" name="save" class="button"> 
			&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=[affaire.id]'">
		</p>
[onshow;block=end]
			
			<table width="100%" class="border" style="margin-top:20px;">
			<tr class="liste_titre"><td>Equipement numéro</td><td>Matériel</td><td>Facture matériel</td></tr>
			<tr><td><a href="../assetatm/fiche.php?id=[asset.rowid]">[asset.serial_number; block=tr; strconv=no]</a> </td><td>[asset.produit;strconv=no]</td><td>[asset.facture;strconv=no]</td></tr>
			<!--<tr><td width="20%">Coût copie Noire</td><td>[asset.copy_black; strconv=no; frm=0 000,00] &euro;</td></tr>
			<tr><td width="20%">Coût copie Couleure</td><td>[asset.copy_color; strconv=no; frm=0 000,00] &euro;</td></tr>-->
			</table>

<p align="center" style="font-size: 9px;">
	Crée le [affaire.date_cre] - Mis à jour le [affaire.date_maj]
</p>