<?php

/* Copyright (C) 2012	  Maxime Kohlhaas		<maxime.kohlhaas@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**		\file		tpl/import_new.tpl.php
 *		\ingroup	financement
 *		\brief		Liste des imports réalisés das le système
 */

print_fiche_titre($langs->trans("NewImport"),'','import32.png@financement');
?>
<br />
<form method="post" enctype="multipart/form-data" action="<?php echo $_SERVER["PHP_SELF"] ?>">
	<input type="hidden" name="mode" value="new" />
	
	<table class="border" width="100%">
		<tr>
			<td><?php echo $langs->trans('ImportType') ?></td>
			<td><?php echo $formfin->select_financement('type_import', $typeImport, 'type_import', false) ?></td>
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
