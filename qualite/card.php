<?php

require_once __DIR__.'/../config.php';
dol_include_once('/financement/class/quality.class.php');

$PDOdb = new TPDOdb;

$action = GETPOST('action');
$id = GETPOST('id', 'int');

if($id <= 0) accessforbidden();

$test = new TFin_QualityTest;
$test->load($PDOdb, $id);

if(! $test->quality_rule->userHasRightToRead()) accessforbidden();

$langs->load('financement@financement');

/*
 * Actions
 */

switch($action)
{
	case 'confirm_validate':
		$test->setResult($PDOdb, 'OK', GETPOST('comment'));

		break;

	case 'confirm_refuse':
		$test->setResult($PDOdb, 'KO', GETPOST('comment'));

		break;
}


/*
 * View
 */

$title = 'Test de contrôle qualité';
$linkback = '<a href="' . dol_buildpath('/financement/qualite/list.php', 1) . '">Retour liste</a>';

llxHeader('', $title);

print_fiche_titre($title, $linkback, 'financement32@financement');

if($action == 'validate' || $action == 'refuse')
{
	$formCore = new TFormCore($_SERVER['PHP_SELF'] . '?id=' . $test->getId(), 'setQualityTestStatus', 'POST');
}

$buttons = '';
$displayForm = $action == 'validate' || $action == 'refuse';

if($displayForm)
{
	$buttons = '
		<div class="center">
			<button type="submit" name="action" value="confirm_' . $action . '">' . $langs->trans('Save'). '</button>
			<button type="submit" name="action" value="cancel">' . $langs->trans('Cancel'). '</button>
		</div>';
}
elseif($test->result === 'TODO')
{
	$buttons = '
		<div class="tabsAction">
			<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $test->getid() . '&action=validate">' . $langs->trans('Validate') . '</a>
			<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $test->getid() . '&action=refuse">' . $langs->trans('Refuse') . '</a>
		</div>';
}


$TBS = new TTemplateTBS;
echo $TBS->render(
	__DIR__.'/../tpl/qualite_card.tpl.php'
	, array()
	, array(
		'quality_rule' => $test->quality_rule->name
		, 'element' => $test->element->getNomUrl()
		, 'date_cre' => dol_print_date($test->date_cre, '%d/%m/%Y %H:%M:%S')
		, 'result' => $test->getLibResult(true)
		, 'comment' => $displayForm ? $formCore->zonetexte('', 'comment', '', 9999, 6, 'style="width:99%; min-height:70px"') : $test->comment
		, 'buttons' => $buttons
	)
);



if($displayForm)
{
	$formCore->end();
}


llxFooter();