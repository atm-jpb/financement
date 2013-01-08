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


print_barre_liste($langs->trans('ImportList'), $page,'import.php?mode=list',$param,$sortfield,$sortorder,'',$num,0,'import32.png@financement');
?>
<br />
<?php if($import_list) { ?>
<table class="liste" width="100%">
	<tr class="liste_titre">
		<?php print_liste_field_titre($langs->trans('Date'),$_SERVER["PHP_SELF"],'i.date','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('Type'),$_SERVER["PHP_SELF"],'i.type_import','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('FileName'),$_SERVER["PHP_SELF"],'i.filename','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('Author'),$_SERVER["PHP_SELF"],'u.login','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('NbLines'),$_SERVER["PHP_SELF"],'i.nb_lines','',$param, 'align="center"',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('NbErrors'),$_SERVER["PHP_SELF"],'i.nb_errors','',$param, 'align="center"',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('NbCreate'),$_SERVER["PHP_SELF"],'i.nb_create','',$param, 'align="center"',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('NbUpdate'),$_SERVER["PHP_SELF"],'i.nb_update','',$param, 'align="center"',$sortfield,$sortorder) ?>
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
		<td class="liste_titre">&nbsp;</td>
		<td class="liste_titre">&nbsp;</td>
		<td class="liste_titre" align="right">
			<input class="liste_titre" type="image" src="<?php echo DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png' ?>" value="<?php echo dol_escape_htmltag($langs->trans("Search")) ?>" title="<?php echo dol_escape_htmltag($langs->trans("Search")) ?>" />
		</td>
	</tr>
	</form>
	
<?php
		$userstatic=new User($db);
		$num = $db->num_rows($import_list);
		$i = 0;
		
		while ($i < min($num,$limit)) {
			$var=!$var;

			$obj = $db->fetch_object($import_list);
			$userstatic->id=$obj->fk_user_author;
			$userstatic->login=$obj->login;
			
			$y = dol_print_date($db->jdate($obj->date),'%Y');
			$m = dol_print_date($db->jdate($obj->date),'%m');
			$mt= dol_print_date($db->jdate($obj->date),'%b');
			$d = dol_print_date($db->jdate($obj->date),'%d');
			
			?>
			<tr <?php echo $bc[$var] ?>>
				<td align="center"><?php echo $d ?>
					<a href="<?php echo $_SERVER["PHP_SELF"].'?year='.$y.'&amp;month='.$m ?>"><?php echo $mt ?></a>
					<a href="<?php echo $_SERVER["PHP_SELF"].'?year='.$y ?>"><?php echo $y ?></a>
				</td>
				<td><?php echo $obj->type_import ?></td>
				<td><?php echo $obj->filename ?></td>
				<td><?php echo $userstatic->getLoginUrl(1) ?></td>
				<td align="center"><?php echo $obj->nb_lines ?></td>
				<td align="center"><a href="<?php echo $_SERVER["PHP_SELF"].'?mode=list_error&id='.$obj->rowid ?>"><?php echo $obj->nb_errors ?></a></td>
				<td align="center"><?php echo $obj->nb_create ?></td>
				<td align="center"><?php echo $obj->nb_update ?></td>
				<td>&nbsp;</td>
			</tr>
			<?php
			$i++;
		}
?>
</table>
<?php
} else {
	dol_print_error($db);
}
?>
