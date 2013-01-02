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
 *      \file       script/import_facture_materiel.script.php
 *		\ingroup    financement
 *      \brief      This file is an example for a command line script
 *					Initialy built by build_class_from_table on 2012-12-20 10:36
 */


dol_include_once("/compta/facture/class/facture.class.php");
$sqlSearchFacture = "SELECT rowid FROM llx_facture WHERE facnumber = '%s'";
$sqlSearchClient = "SELECT rowid FROM llx_societe WHERE code_client = '%s'";

$oldfacnumber = '';

while($dataline = fgetcsv($fileHandler, 1024, $delimiter, $enclosure)) {
	// Compteur du nombre de lignes
	$imp->nb_lines++;
	
	echo '<pre>';
	print_r($dataline);
	echo '</pre>';

	if($imp->checkData($dataline)) {
		$data = $imp->contructDataTab($dataline);
		$facnumber = $data[$imp->mapping['search_key']];
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $facnumber);
		$resql = $db->query($sql);
		if($resql) {
			$num = $db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$imp->addError('ErrorMultipleFactureFound', $dataline);
				continue;
			}
		} else {
			$imp->addError('ErrorWhileSearchingFacture', $dataline);
			continue;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $data[$imp->mapping['search_key_client']]);
		$resql = $db->query($sql);
		if($resql) {
			$num = $db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$imp->addError('ErrorMultipleClientFound', $dataline);
				continue;
			} else {
				$imp->addError('ErrorNoClientFound', $dataline);
				continue;
			}
		} else {
			$imp->addError('ErrorWhileSearchingClient', $dataline);
			continue;
		}
		
		$data['socid'] = $fk_soc;
		$data['lines']= array(array(
			'desc' => 'Matricule '.$data['matricule'],
			'subprice' => $data['total_ttc'],
			'qty' => 1,
			'tva_tx' => 19.6,
			'product_type' => 0
		));
		
		$fact = $imp->importLine($data, 'Facture', $rowid); // Création de la facture
		$fact->validate($user, $facnumber); // Force la validation avec numéro de facture
	}
}
?>
