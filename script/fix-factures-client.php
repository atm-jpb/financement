<?php

require('../config.php');

@set_time_limit(0);					// No timeout for this script
@ini_set('memory_limit', '256M');

// Actions possibles : del_draft, del_errlink, add_links
$action = GETPOST('action');

$PDOdb=new TPDOdb();

// Récupération des factures / contrat de LeaseBoard
$sql = "SELECT f.rowid as id_facture, f.facnumber, d.rowid as id_dossier, d.reference";
$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
$sql.= " WHERE f.fk_user_author IS NULL";
$sql.= " AND f.datef BETWEEN '2016-01-01' AND '2018-06-21'";
$sql.= " ORDER BY f.facnumber";

$TData = $PDOdb->ExecuteAsArray($sql);

$ToDel = $ToLink = $ToCheck = array();
foreach ($TData as $data) {
	// Facture brouillon, on supprime
	if(strpos($data->facnumber, '(PROV') !== false) {
		$ToDel[] = $data->facnumber;
	}
	// Facture sans lien, on vérifiera si on peut lier grâce au fichier
	else if(empty($data->reference)) {
		$ToLink[] = $data->facnumber;
	}
	// Factures avec lien, on vérifier avec le fichier si le lien est bon
	else {
		$ToCheck[$data->facnumber] = $data->reference;
	}
}
echo 'Analyse des factures LeaseBoard';
echo '<hr>Factures à supprimer : ' . count($ToDel);
echo '<br>Factures sans liens : ' . count($ToLink);
echo '<br>Factures avec liens à vérifier : ' . count($ToCheck);
//exit;

/**
 * ACTION
 */
// Suppression des factures brouillons
if($action == 'del_draft') {
	foreach ($ToDel as $facnumber) {
		$f = new Facture($db);
		$f->fetch(0,$facnumber);
		$f->delete();
	}
}

// Comparaison avec les données du fichier
$file = dol_buildpath('/financement/script/fix-factures-client/').'factures-contrat.csv';
$fileHandler = fopen($file, 'r');

$TFac = array();

while($dataline = fgetcsv($fileHandler, 4096)) {
	$refFacture = trim($dataline[0]);
	$refContrat = trim($dataline[1]);
	$TFac[$refFacture] = $refContrat;
}
fclose($fileHandler);

$ToDel2 = $ToCheck2 = array();
foreach($ToCheck as $facture => $contrat) {
	if($TFac[$facture] == $contrat) $ok++; // Facture liée correctement
	else {
		$ko++;
		if(empty($TFac[$facture])) { // Facture inexistante chez CPRO
			$ToDel2[] = $facture;
		} else {
			$ToCheck2[] = $facture;
		}
	}
}

echo '<hr>Analyse des factures Artis à partir du fichier';
echo '<hr>Factures à supprimer (existante dans LB pas dans Artis): ' . count($ToDel2);
echo '<br>Factures avec liens à vérifier (différence de contrat) : ' . count($ToCheck2);

/**
 * ACTION
 * Suppression des factures n'existant pas chez CPRO
 */
if($action == 'del_errlink') {
	foreach ($ToDel2 as $facnumber) {
		$f = new Facture($db);
		$f->fetch(0,$facnumber);
		$f->delete();
	}
}
