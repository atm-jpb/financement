[onshow;block=begin;when [view.mode]=='view']
		
	<div class="tabs">
	<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_import.png">Import</a>
	<a href="?id=[import.id]" class="tab" id="active">Fiche</a>
	</div>
		
	<div class="tabBar">				
		<table width="100%" class="border">
		<tr><td width="20%">Numéro d'import</td><td>[import.id; strconv=no]</td><td width="20%">Date</td><td>[import.date; strconv=no]</td></tr>
		<tr><td width="20%">Type</td><td>[import.type_import; strconv=no]</td><td width="20%">Fichier</td><td>[import.filename; strconv=no]</td></tr>
		<tr><td>Nombre de lignes</td><td>[import.nb_lines; strconv=no]</td><td>Nombre d'erreurs</td><td>[import.nb_errors; strconv=no]</td></tr>
		<tr><td>Nombre de création</td><td>[import.nb_create; strconv=no]</td><td>Nombre de mise à jour</td><td>[import.nb_update; strconv=no]</td></tr>
		</table>
	</div>
	
	[liste_errors]

[onshow;block=end]

[onshow;block=begin;when [view.mode]=='new']
[import.titre_fiche]
<br />
<form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER["PHP_SELF"] ?>">
	<input type="hidden" name="mode" value="new" />
	
	<table class="border" width="100%">
		<tr>
			<td>Type d'import</td>
			<td>[import.type_import]</td>
			<td><?php
			$html=new Form($db);
			print $html->select_company('','socid','fournisseur=1',0, 0,1);
			?></td>
			<td><?php echo $langs->trans('FileToImport') ?></td>
			<td><input type="file" name="fileToImport" class="flat" /></td>
			<td><input type="submit" name="import" class="button" value="<?php echo $langs->trans("Import") ?>"></td>
		</tr>
	</table>
</form>

[onshow;block=end]