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


print_barre_liste($langs->trans('ImportListError'), $page,'import.php?mode=list_error',$param,$sortfield,$sortorder,'',$num,0,'import32.png@financement');
?>
<br />
<?php if($import_error_list) { ?>
<table class="liste" width="100%">
	<tr class="liste_titre">
		<?php print_liste_field_titre($langs->trans('NumLine'),$_SERVER["PHP_SELF"],'ie.num_line','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('ErrorMessage'),$_SERVER["PHP_SELF"],'ie.error_msg','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('ContentLine'),$_SERVER["PHP_SELF"],'ie.content_line','',$param,'',$sortfield,$sortorder) ?>
		<?php print_liste_field_titre($langs->trans('SQLErrno'),$_SERVER["PHP_SELF"],'ie.sql_errno','',$param,'',$sortfield,$sortorder) ?>
	</tr>
	
<?php
		$num = $db->num_rows($import_error_list);
		$i = 0;
		
		while ($i < min($num,$limit)) {
			$var=!$var;

			$obj = $db->fetch_object($import_error_list);
			
			?>
			<tr <?php echo $bc[$var] ?>>
				<td><?php echo $obj->num_line ?></td>
				<td><?php echo $obj->error_msg ?></td>
				<td><?php echo implode(";", unserialize($obj->content_line)) ?></td>
				<td><?php echo $obj->sql_errno ?></td>
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
