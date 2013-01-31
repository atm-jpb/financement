[onshow;block=begin;when [view.mode]=='view']
		
	<div class="tabs">
	<a class="tabTitle">[import.titre_view; strconv=no]</a>
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
	<br />
	[liste_errors; strconv=no]

[onshow;block=end]

[onshow;block=begin;when [view.mode]=='new']
[import.titre_new; strconv=no]
	<br />
	<table class="border" width="100%">
		<tr>
			<td>Type d'import</td>
			<td>[import.type_import; strconv=no]</td>
			<td>Leaser</td>
			<td>[import.leaser; strconv=no]</td>
			<td>Fichier à importer</td>
			<td>[import.fileToImport; strconv=no]</td>
			<td rowspan="2"><input type="submit" name="import" class="button" value="Importer"></td>
		</tr>
		<tr>
			<td>Ignorer la premi&egrave;re ligne</td>
			<td>[import.ignore_first_line; strconv=no]</td>
			<td>Valeurs d&eacute;limit&eacute;es par</td>
			<td>[import.delimiter; strconv=no]</td>
			<td>Encadrement des valeurs</td>
			<td>[import.enclosure; strconv=no]</td>
		</tr>
	</table>

[onshow;block=end]