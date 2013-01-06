[onshow;block=begin;when [view.mode]=='view']

	
		<div class="fiche"> <!-- begin div class="fiche" -->
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./images/affaire.png"> Affaire</a>
		<a href="?id=[affaire.id]" class="tab" id="active">Fiche</a>
		</div>
		
			<div class="tabBar">
				
[onshow;block=end]				
				
			<table width="100%" class="border">
			<tr><td width="20%">Numéro</td><td>[affaire.reference; strconv=no]</td></tr>
			
			<tr><td>Nature du financement</td><td>[affaire.nature_financement; strconv=no]</td></tr>
			<tr><td>type de financement</td><td>[affaire.type_financement; strconv=no]</td></tr>

			<tr><td>type de contrat</td><td>[affaire.contrat; strconv=no]</td></tr>
			<tr><td>type de matériel</td><td>[affaire.type_materiel; strconv=no]</td></tr>
			
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