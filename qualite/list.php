<?php

require_once __DIR__.'/../config.php';
dol_include_once('/financement/class/quality.class.php');

$PDOdb = new TPDOdb;

$testStatic = new TFin_QualityTest;

$langs->load('financement@financement');



/*
 * View
 */

llxHeader('', 'Liste des tests');

$sql = 'SELECT qt.rowid, qt.date_cre, qr.name, qt.fk_element, qt.result, qt.fk_user_tester, qt.date_test
		FROM @table@ qt
		INNER JOIN ' . MAIN_DB_PREFIX . 'fin_quality_rule qr ON (qr.rowid = qt.fk_fin_quality_rule)';

$TCacheTests = array();

$formCore = new TFormCore($_SERVER['PHP_SELF'], 'qualityList', 'GET');


$list = new TSSRenderControler($testStatic);
echo $list->liste(
	$PDOdb
	, $sql
	, array(
		'link' => array(
			'rowid' => '<a href="' . dol_buildpath('/financement/qualite/card.php', 1) . '?id=@val@">@val@</a>'
		)
		, 'eval' => array(
			'fk_element' => '_getElementLink(@rowid@)'
			, 'fk_user_tester' => '_getUserLink(@val@);'
			, 'result' => '_getResult(@rowid@);'
		)
		, 'type' => array(
			'date_cre' => 'date'
			, 'date_test' => 'date'
		)
		, 'title' => array(
			'rowid' => 'ID'
			, 'date_cre' => 'Créé le'
			, 'name' => 'Règle'
			, 'fk_element' => 'Élément testé'
			, 'result' => 'Résultat'
			, 'fk_user_tester' => 'Testeur'
			, 'date_test' => 'Date résultat'
		)
		, 'search' => array(
			'result' => $testStatic->TResults
			, 'date_cre' => 'calendars'
			, 'date_test' => 'calendars'
		)
		, 'liste' => array(
			'titre' => 'Liste des tests'
			, 'image' => img_picto('', 'financement32@financement')
		)
	)
);

$formCore->end();


llxFooter();


function _getElementLink($testID)
{
	global $TCacheTests;

	if(empty($TCacheTests[$testID]))
	{
		_addTestToCache($testID);
	}
	
	return $TCacheTests[$testID]->element->getNomUrl();
}


function _getUserLink($fk_user)
{
	global $db;

	$user = new User($db);
	$userFetchReturn = $user->fetch($fk_user);

	if($userFetchReturn <= 0)
	{
		return '';
	}

	return $user->getNomUrl(1);
}


function _getResult($testID)
{
	global $TCacheTests;

	if(empty($TCacheTests[$testID]))
	{
		_addTestToCache($testID);
	}

	return $TCacheTests[$testID]->getLibResult();
}


function _addTestToCache($testID)
{
	global $TCacheTests;

	$PDOdb = new TPDOdb;

	$test = new TFin_QualityTest;
	$testLoaded = $test->load($PDOdb, $testID);

	if($testLoaded)
	{
		$TCacheTests[$testID] = $test;
	}

	unset($PDOdb);
}

