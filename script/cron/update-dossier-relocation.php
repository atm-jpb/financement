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
		SET dfc.reloc = "NON", dfl.reloc = "NON", dfc.relocOK = "OUI", dfl.relocOK = "OUI", dfc.encours_reloc = 0, dfl.encours_reloc = 0';

$PDOdb->Execute($sql);


// Passage en relocation des dossiers internes échus mais sans date de solde, avec un numéro de contrat et une échéance

$sql = 'SELECT d.rowid
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfc ON (dfc.fk_fin_dossier = d.rowid AND dfc.type="CLIENT")
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type="LEASER")
		LEFT JOIN '.MAIN_DB_PREFIX.'societe lea ON (lea.rowid = dfl.fk_soc)
		WHERE d.nature_financement = "INTERNE" 
		AND lea.nom NOT LIKE "HEXAPAGE%"
		AND dfc.reloc = "NON"
		AND dfc.date_fin < NOW()
		AND COALESCE(dfc.date_solde, "1001-01-01 00:00:00") <= "1970-01-01"
		AND dfc.reference IS NOT NULL
		AND CHAR_LENGTH(TRIM(dfc.reference)) > 0
		AND dfc.echeance > 0
		GROUP BY d.rowid';

$TDossiersInternesReloc = $PDOdb->ExecuteAsArray($sql);

$nbInvoiceMissing = 0;


foreach($TDossiersInternesReloc as $dossierStatic)
{
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $dossierStatic->rowid);

	$dossier->financement->reloc = 'OUI';

	$relocOK = true;

	$numLastEcheance = $dossier->financement->numero_prochaine_echeance - 1;

	if($dossier->financement->duree > 0 && $numLastEcheance >= $dossier->financement->duree)
	{
		// Décommenter tout ce bloc pour rechercher sur toutes les échéances suivant la date de fin
		// for($i = $dossier->financement->duree; $i < $numLastEcheance; $i++)
		// {
			if(empty($dossier->TFacture[$numLastEcheance - 1]))
			{
				$relocOK = false;
				$nbInvoiceMissing++;
				// break;
			}
		// }
	}

	$dossier->financement->relocOK = $relocOK ? 'OUI' : 'NON';

	$dossier->save($PDOdb); // Inclut le calcul de l'encours de relocation
}

echo '<p>'.count($TDossiersInternesReloc).' dossier(s) internes en relocation, '.$nbInvoiceMissing.' à traiter</p>';


// Passage en relocation des dossiers externes échus mais sans date de solde

$sql = 'SELECT d.rowid
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
                INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type="LEASER")
		LEFT JOIN '.MAIN_DB_PREFIX.'societe lea ON (lea.rowid = dfl.fk_soc)
		WHERE (d.nature_financement = "EXTERNE" OR lea.nom NOT LIKE "HEXAPAGE%")
		AND dfl.reloc = "NON"
		AND dfl.date_fin < NOW()
		AND COALESCE(dfl.date_solde, "1001-01-01 00:00:00") <= "1970-01-01"
		AND dfl.reference IS NOT NULL
		AND CHAR_LENGTH(TRIM(dfl.reference)) > 0
		AND dfl.echeance > 0
		GROUP BY d.rowid';

$TDossiersExternesReloc = $PDOdb->ExecuteAsArray($sql);

$nbInvoiceMissing = 0;


foreach($TDossiersExternesReloc as $dossierStatic)
{
	$dossier = new TFin_dossier;
	$dossier->load($PDOdb, $dossierStatic->rowid);


	$dossier->financementLeaser->reloc = 'OUI';

	$relocOK = true;

	$numLastEcheance = $dossier->financementLeaser->numero_prochaine_echeance - 1;

	if($dossier->financementLeaser->duree > 0)
	{
		// Décommenter tout ce bloc pour rechercher sur toutes les échéances suivant la date de fin
		// for($i = $dossier->financementLeaser->duree; $i < $numLastEcheance; $i++)
		// {
			if(empty($dossier->TFactureFournisseur[$numLastEcheance - 1]))
			{
				$relocOK = false;
				$nbInvoiceMissing++;
				// break;
			}
		// }
	}

	$dossier->financementLeaser->relocOK = $relocOK ? 'OUI' : 'NON';

	$dossier->save($PDOdb); // Inclut le calcul de l'encours de relocation
}

echo '<p>'.count($TDossiersExternesReloc).' dossier(s) externes en relocation, '.$nbInvoiceMissing.' à traiter</p>';

