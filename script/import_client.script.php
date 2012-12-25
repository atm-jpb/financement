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

/**
 *      \file       script/import_client.script.php
 *		\ingroup    financement
 *      \brief      This file is an example for a command line script
 *					Initialy built by build_class_from_table on 2012-12-20 10:36
 */


dol_include_once("/societe/class/societe.class.php");
$sqlSearchClient = "SELECT rowid FROM llx_societe WHERE code_client = '%s'";

while($dataline = fgetcsv($fileHandler, 1024, $delimiter, $enclosure)) {
	$imp->importLine($dataline, 'Societe', $sqlSearchClient);
}
?>
