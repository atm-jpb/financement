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

$sqlSearch = "SELECT rowid FROM llx_societe WHERE code_client = '%s'";

while($dataline = fgetcsv($fileHandler, 1024, $delimiter, $enclosure)) {
	$imp->nb_lines++;
	
	$rowid = 0;
	$data = array();
	
	// Vérification cohérence des données
	if(count($mapping) != count($dataline)) {
		$imp->addError('ErrorNbColsNotMatchingMapping', $dataline);
		continue;
	}
	
	// Recherche si enregistrement existant dans la base
	$sql = sprintf($sqlSearch, $data['code_client']);
	$resql = $db->query($sql);
	if($resql) {
		$num = $db->num_rows($result);
		if($num == 1) { // Client trouvé, mise à jour
			$obj = $db->fetch_object($result);
			$rowid = $obj->rowid;
		} else if($num > 1) { // Plusieurs trouvés, erreur
			$imp->addError('ErrorMultipleCustomerFound', $dataline);
			continue;
		}
	} else {
		$imp->addError('ErrorWhileSearchingCustomer', $dataline);
		continue;
	}
	
	// Construction du tableau de données et de l'objet correspondant
	array_walk($dataline, 'trim');
	$data = array_combine($mapping, $dataline);
	$data['client'] = 1;

	$imp->importLine($data, 'Societe', $rowid);
}
?>
