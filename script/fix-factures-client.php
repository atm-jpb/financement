<?php

require('../config.php');
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/financement/class/dossier.class.php');

@set_time_limit(0);					// No timeout for this script
@ini_set('memory_limit', '256M');

// Actions possibles : del_draft, del_errlink, add_links
$action = GETPOST('action');

$PDOdb=new TPDOdb();

// Récupération des factures / contrat de LeaseBoard
$sql = "SELECT f.rowid as id_facture, f.ref, d.rowid as id_dossier, dfcli.reference";
$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type = 'CLIENT')";
$sql.= " WHERE (f.fk_user_author IS NULL OR f.fk_user_author = 1)";
$sql.= " AND f.datef BETWEEN '2016-01-01' AND '2018-06-31'";
//$sql.= " AND f.facnumber LIKE '12011814%'";
$sql.= " ORDER BY f.ref";
//echo $sql;
$TData = $PDOdb->ExecuteAsArray($sql);

$TAll = $ToDel = $ToLink = $ToCheck = $ToRenum = array();
foreach ($TData as $data) {
	// Facture brouillon, on supprime
	if(strpos($data->ref, '(PROV') !== false) {
		$ToDel[] = $data->ref;
	}
	// Facture avec un tiret dans le numéro
	else if(strpos($data->ref, '-') !== false) {
		$ToRenum[] = $data->ref;
	}
	// Facture sans lien, on vérifiera si on peut lier grâce au fichier
	else if(empty($data->reference)) {
		$ToLink[] = $data->ref;
	}
	// Factures avec lien, on vérifiera avec le fichier si le lien est bon
	else {
		$ToCheck[$data->ref] = $data->reference;
	}
	
	$TAll[$data->ref] = $data->reference;
}
echo 'Analyse des factures LeaseBoard';
echo '<hr>A) Factures brouillon à supprimer : ' . count($ToDel);
echo '<br>B) Factures sans liens : ' . count($ToLink);
echo '<br>C) Factures avec liens à vérifier : ' . count($ToCheck);
echo '<br>D) Factures avec tiret (potentiel doublon) : ' . count($ToRenum);
//exit;

// Récupération des factures / contrat provenant du fichier
$file = dol_buildpath('/financement/script/fix-factures-client/').'factures-contrat2.csv';
$fileHandler = fopen($file, 'r');

$TFac = array();

while($dataline = fgetcsv($fileHandler, 4096)) {
	$refFacture = trim($dataline[0]);
	$refContrat = trim($dataline[1]);
	$TFac[$refFacture] = $refContrat;
}
fclose($fileHandler);

///// Gestion des factures brouillon /////
/**
 * ACTION
 */
// Suppression des factures brouillons
if($action == 'del_draft') {
	echo '<br>***ACTION DEL DRAFT***<br>';
	foreach ($ToDel as $facnumber) {
		$f = new Facture($db);
		$f->fetch(0,$facnumber);
		$f->delete();
	}
}

///// Gestion des factures LB sans liens /////
// Vérification des liens à créer
$ToLinkOK = array();
foreach($ToLink as $facnumber) {
	if(isset($TFac[$facnumber])) {
		$ToLinkOK[$facnumber] = $TFac[$facnumber];
	}
}

echo '<hr>B) Liens créables : ' . count($ToLinkOK);

/**
 * ACTION
 * Ajout des liens entre factures et contrat
 */
if($action == 'add_links') {
	echo '<br>***ACTION ADD LINKS***<br>';
	foreach ($ToLinkOK as $facnumber => $contratref) {
		$f = new Facture($db);
		$f->fetch(0,$facnumber);
		$fin = new TFin_financement();
		$fin->loadBy($PDOdb, $contratref, 'reference', false);
		if($f->id > 0 && $fin->getId() > 0) $f->add_object_linked('dossier', $fin->fk_fin_dossier);
	}
}

///// Comparaison des liens /////
$ok = 0;
$ToDel2 = $ToCheck2 = array();
foreach($ToCheck as $facnumber => $contratref) {
	if($TFac[$facnumber] == $contratref) $ok++; // Facture liée correctement
	elseif(strpos($contratref,$TFac[$facnumber]) !== false) $ok++; // Lien correct, numéro de contrat LB modifié (-old, -adj, -solde, ...)
	else {
		$ko++;
		if(empty($TFac[$facnumber])) { // Facture inexistante chez CPRO
			$ToDel2[] = $facnumber;
		} else {
			$ToCheck2[] = $facnumber;
		}
	}
}

echo '<hr>C) Analyse des liens factures à partir du fichier Artis '.count($ToCheck);
echo '<hr>Factures OK : ' . $ok;
echo '<br>Factures à supprimer (existante dans LB pas dans Artis): ' . count($ToDel2);
echo '<br>Factures avec liens à vérifier (différence de contrat) : ' . count($ToCheck2);

/*foreach ($ToCheck2 as $facture) {
	echo '<hr>'.$facture.' - LB : '.$ToCheck[$facture].' - ARTIS : '.$TFac[$facture];
}*/

/**
 * ACTION
 * Suppression des factures n'existant pas chez CPRO
 */
if($action == 'del_errlink') {
	echo '<br>***ACTION DEL LINK***<br>';
	foreach ($ToDel2 as $facnumber) {
		$f = new Facture($db);
		$f->fetch(0,$facnumber);
		$f->delete();
		echo $facnumber.'<br>';
	}
}

///// Numérotation avec tirets / doublons /////
$ToDel3 = array();
foreach ($ToRenum as $facnumber) {
	$fshort = substr($facnumber, 0, 8);
	if(array_key_exists($fshort, $TAll)) {
		$ToDel3[] = $facnumber;
	}
}

echo '<hr>D) => Analyse des factures avec tiret';
echo '<hr>Factures avec tiret : ' . count($ToRenum);
echo '<br>Factures existantes sans tiret : ' . count($ToDel3);

/**
 * ACTION
 * Suppression des factures en doublon
 */
if($action == 'del_dbl') {
	echo '<br>***ACTION DEL DBL***<br>';
	foreach ($ToDel3 as $facnumber) {
		$f = new Facture($db);
		$f->fetch(0,$facnumber);
		$f->delete();
	}
}
