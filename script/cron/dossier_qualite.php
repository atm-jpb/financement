<?php

/**
 * Script de contrôle qualité : pour chaque règle définie par l'utilisateur, sélectionne un dossier au hasard et crée un test qui apparaîtra en page d'accueil
 */


if(! defined('INC_FROM_DOLIBARR')) {
	require_once __DIR__.'/../../config.php';
}

dol_include_once('/financement/class/quality.class.php');

$PDOdb = new TPDOdb;

$ruleStatic = new TFin_DossierQualityRule;
$TRules = $ruleStatic->LoadAllBy($PDOdb);

foreach($TRules as &$rule)
{
	$test = new TFin_DossierQualityTest;

	$sql = 'SELECT COUNT(*) as count
			FROM ' . $test->get_table() . '
			WHERE fk_fin_dossier_quality_rule = ' . $rule->getId() . '
			AND DATE_FORMAT(DATE_ADD(date_cre, INTERVAL ' . $rule->frequency_days . ' DAY), "%Y-%m-%d") > DATE_FORMAT(NOW(), "%Y-%m-%d")';

	$PDOdb->Execute($sql);

	$result = $PDOdb->Get_line();
	$count = intval($result->count);

	// Si on trouve des tests, l'intervalle entre deux séries de tests de la règle courante n'est pas écoulé => on passe
	if($count > 0)
	{
		continue;
	}

	for($i = 0; $i < $rule->nb_tests; $i++)
	{
		$test = new TFin_DossierQualityTest;
		$test->fk_fin_dossier_quality_rule = $rule->getId();
		$test->save($PDOdb);
	}
}

