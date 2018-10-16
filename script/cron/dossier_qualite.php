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
	$test->fk_fin_dossier_quality_rule = $rule->getId();
	$test->save($PDOdb);
}

