<?php

/*
 * Script de mise à jour quotidienne des dossiers en relocation 
 */


set_time_limit(0);


if(! defined('INC_FROM_DOLIBARR')) {
	require_once __DIR__.'/../../config.php';
}


dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

$PDOdb = new TPDOdb;

// On remet tous les dossiers hors relocation par défaut

$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_dossier d
		LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfc ON (dfc.fk_fin_dossier = d.rowid AND dfc.type="CLIENT")
		LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type="LEASER")
		SET dfc.reloc = "NON", dfl.reloc = "NON", dfc.encours_reloc = 0, dfl.encours_reloc = 0';

$PDOdb->Execute($sql);


// Passage en relocation des dossiers internes échus mais sans date de solde

$sql = 'SELECT d.rowid
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfc ON (dfc.fk_fin_dossier = d.rowid AND dfc.type="CLIENT")
		WHERE d.nature_financement = "INTERNE"
		AND dfc.reloc = "NON"
		AND dfc.date_fin < NOW()
		AND COALESCE(dfc.date_solde, "1001-01-01 00:00:00") <= "1970-01-01"
		GROUP BY d.rowid';

$TDossiersInternesReloc = $PDOdb->ExecuteAsArray($sql);

$nbInvoiceMissing = 0;


foreach($TDossiersInternesReloc as $dossierStatic) {
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $dossierStatic->rowid);

	$dossier->financement->reloc = 'OUI';
	$dossier->financementLeaser->reloc = 'OUI';

	$relocOK = true;

	$numLastEcheance = $dossier->financement->numero_prochaine_echeance - 1;

	if($dossier->financement->duree > 0 && $numLastEcheance > $dossier->financement->duree) {
		for($i = $dossier->financement->duree; $i < $numLastEcheance; $i++) {
			if(empty($dossier->TFacture[$i])) {
				$relocOK = false;
				$nbInvoiceMissing++;
				break;
			}
		}
	}

	// echo '<p>Dossier n°'.$dossier->reference.' : relocOK '.($relocOK ? 'OUI': 'NON').'</p>';

	$dossier->financement->relocOK = $relocOK ? 'OUI' : 'NON';

	$dossier->save($PDOdb); // Inclut le calcul de l'encours de relocation
}

echo '<p>'.count($TDossiersInternesReloc).' dossier(s) internes en relocation, '.$nbInvoiceMissing.' à traiter</p>';

