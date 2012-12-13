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

/**		\file		tpl/import_list.tpl.php
 *		\ingroup	financement
 *		\brief		Liste des imports réalisés das le système
 */

 
print_barre_liste($langs->trans('ListOfImport'), $page,'import.php?mode=list',$param,$sortfield,$sortorder,'',$num,0,'import32.png@financement');
?>
<br />
<table class="liste" width="100%">
	<tr class="liste_titre">
		<?php print_liste_field_titre($langs->trans('Date'),$_SERVER["PHP_SELF"],'i.date','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('Type'),$_SERVER["PHP_SELF"],'s.nom','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('FileName'),$_SERVER["PHP_SELF"],'s2.nom','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('Author'),$_SERVER["PHP_SELF"],'s2.nom','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('NbErrors'),$_SERVER["PHP_SELF"],'d.datedeb','',$param, 'align="center"',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('NbErrorsRemaining'),$_SERVER["PHP_SELF"],'d.datefin','',$param, 'align="center"',$sortfield,$sortorder) ?>
		<th class="liste_titre">&nbsp;</th>
	</tr>
	
	<form method="get" action="<?php echo $_SERVER["PHP_SELF"] ?>">
	<tr class="liste_titre">
		<td class="liste_titre">
			<?php echo $langs->trans('Month') ?> : <input class="flat" type="text" size="1" maxlength="2" name="month" value="<?php echo $month ?>">
			<?php echo $langs->trans('Year')?> : <?php echo $formother->select_year($year,'year',1, 20, 5) ?>
		</td>
		<td class="liste_titre">
			<?php echo $formfin->select_financement('type_import',$search_type_import,'search_type_import') ?>
		</td>
		<td class="liste_titre">
			<input type="text" name="search_filename" value="<?php echo $search_filename ?>" class="flat" />
		</td>
		<td class="liste_titre">
			<input type="text" name="search_author" value="<?php echo $search_author ?>" class="flat" />
		</td>
		<td class="liste_titre">&nbsp;</td>
		<td class="liste_titre">&nbsp;</td>
		<td class="liste_titre" align="right">
			<input class="liste_titre" type="image" src="<?php echo DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png' ?>" value="<?php echo dol_escape_htmltag($langs->trans("Search")) ?>" title="<?php echo dol_escape_htmltag($langs->trans("Search")) ?>" />
		</td>
	</tr>
	</form>
</table>