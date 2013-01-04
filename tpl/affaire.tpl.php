<?php

	if($mode=='view') {
		?>
		<div class="fiche"> <!-- begin div class="fiche" -->
		
		<div class="tabs">
		<a class="tabTitle"><img border="0" title="" alt="" src="./images/affaire.png"> Affaire</a>
		<a href="?id=<?=$affaire->rowid ?>" class="tab" id="active">Fiche</a>
		</div>
		
			<div class="tabBar"><?
		
	}
	/*
	 * affichage données équipement 
	 */	
		
		?><table width="100%" class="border">
			<tr><td width="20%">Numéro</td><td><?=$form->texte('', 'reference', $affaire->reference, 100,255,'','','à saisir') ?></td></tr>
			
			<tr><td>Nature du financement</td><td><?=$form->combo('', 'nature_financement', $affaire->TNatureFinancement , $affaire->nature_financement) ?></td></tr>
			<tr><td>type de financement</td><td><?=$form->combo('', 'type_financement', $affaire->TTypeFinancement , $affaire->type_financement) ?></td></tr>

			<tr><td>type de contrat</td><td><?=$form->combo('', 'contrat', $affaire->TContrat , $affaire->contrat) ?></td></tr>
			<tr><td>type de matériel</td><td><?=$form->combo('', 'type_materiel', $affaire->TTypeMateriel , $affaire->type_materiel) ?></td></tr>
			
			</table>
		<?
	
	if($mode=='view') {
	
	?></div>

		</div>
		
		<div class="tabsAction">
		<input type="button" id="action-delete" value="Supprimer" name="cancel" class="button" onclick="document.location.href='?action=delete&id=<?=$asset->rowid ?>'">
		&nbsp; &nbsp; <a href="?id=<?=$asset->rowid ?>&action=edit" class="butAction">Modifier</a>
		</div><?

	}
	else {
		
		?>
		<p align="center">
			<input type="submit" value="Enregistrer" name="save" class="button"> 
			&nbsp; &nbsp; <input type="button" value="Annuler" name="cancel" class="button" onclick="document.location.href='?id=<?=$asset->rowid ?>'">
		</p>
		<?
	}
