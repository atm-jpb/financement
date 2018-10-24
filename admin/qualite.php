<?php
/* Copyright (C) 2018 Marc de Lima Lucio      <marc@atm-consulting.fr>
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
 * 	\defgroup   financement     Module Financement
 *  \brief      Module financement pour C'PRO
 *  \file       /financement/admin/qualite.php
 *  \ingroup    Financement
 *  \brief      Configuration du contrôle qualité des dossiers de financement
 */

require_once __DIR__ . '/../config.php';
dol_include_once('/financement/class/quality.class.php');
dol_include_once('/financement/lib/admin.lib.php');

if (empty($user->rights->financement->admin->write))
{
	accessforbidden();
}

$PDOdb = new TPDOdb();

$action = GETPOST('action');

if(! empty($action))
{
	switch($action)
	{
		case 'save':
			$TRules = $_POST['TRules'];

			$TRulesKO = array();

			if(is_array($TRules))
			{
				foreach($TRules as $id => $TRule)
				{
					if($id < -1)
					{
						continue;
					}

					$rule = new TFin_QualityRule;

					$msgPrefix = 'Nouvelle règle';

					if($id > 0)
					{
						$msgPrefix = 'ID ' . $id;
						$ruleLoaded = $rule->load($PDOdb, $id);

						if(! $ruleLoaded)
						{
							$TRulesKO[] = $msgPrefix . ' : Règle introuvable';
							continue;
						}
					}

					if(empty($TRule['name']) || empty($TRule['sql_filter']))
					{
						$TRulesKO[] = $msgPrefix . ' : Un des champs est vide';
						continue;
					}

					$rule->set_values($TRule);

					if(! $rule->testIfValid($PDOdb))
					{
						$TRulesKO[] = $msgPrefix . ' : Filtre SQL invalide';
						continue;
					}

					$ruleID = $rule->save($PDOdb);

					if($ruleID <= 0)
					{
						$TRulesKO[] = $msgPrefix . ' : Erreur inconnue';
					}
				}
			}

			if(empty($TRulesKO))
			{
				setEventMessage('Toutes les règles ont été correctement enregistrées');

				header('Location: '.$_SERVER['PHP_SELF']);
				exit;
			}

			$nbRulesOK = count($TRules) - count($TRulesKO);

			if($nbRulesOK)
			{
				setEventMessage($nbRulesOK . ' règle(s) enregistrée(s) correctement');
			}

			$msg = 'Les règles suivantes n\'ont pas pu être enregistrées : <br />' . implode('<br />', $TRulesKO);

			setEventMessage($msg, 'errors');

			break;


		case 'deleteline':
			$id = GETPOST('lineid');

			if($id > 0)
			{
				$rule = new TFin_QualityRule;
				$rule->load($PDOdb, $id);
				$rule->delete($PDOdb);

				setEventMessage('Règle correctement supprimée.');

				header('Location: '.$_SERVER['PHP_SELF']);
				exit;
			}

			setEventMessage('Impossible de supprimer la règle spécifiée.', 'errors');

			break;
	}
}

if(! is_object($form))
{
	$form = new Form($db);
}

llxHeader('',$langs->trans("FinancementSetup"));
$head = financement_admin_prepare_head(null);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("QualityControlRules"), $linkback);

dol_fiche_head($head, 'quality', $langs->trans("Financement"), 0, 'financementico@financement');

$formCore = new TFormCore($_SERVER['PHP_SELF'], 'editQualityControl', 'POST');


$rulesStatic = new TFin_QualityRule;

$TRules = $rulesStatic->LoadAllBy($PDOdb);
$TRulesForm = array();

foreach($TRules as $id => $rule)
{
	$name = ! empty($_POST['TRules'][$id]['name']) ? $_POST['TRules'][$id]['name'] : $rule->name;
	$element_type = ! empty($_POST['TRules'][$id]['element_type']) ? $_POST['TRules'][$id]['element_type'] : $rule->element_type;
	$sql_filter = ! empty($_POST['TRules'][$id]['sql_filter']) ? $_POST['TRules'][$id]['sql_filter'] : $rule->sql_filter;
	$frequency_days = ! empty($_POST['TRules'][$id]['frequency_days']) ? $_POST['TRules'][$id]['frequency_days'] : $rule->frequency_days;
	$nb_tests = ! empty($_POST['TRules'][$id]['nb_tests']) ? $_POST['TRules'][$id]['nb_tests'] : $rule->nb_tests;

	$TRulesForm[$id] = array(
		'id' => $id
		, 'name' => $formCore->texte('', 'TRules[' . $id . '][name]', $name, 64, 0, 'style="width:95%"')
		, 'element_type' => $formCore->combo('', 'TRules[' . $id . '][element_type]', $rule->TElementTypes, $element_type)
		, 'sql_filter' => $formCore->texte('', 'TRules[' . $id . '][sql_filter]', $sql_filter, 255, 0, 'style="width:99%"')
		, 'frequency_days' => $formCore->texte('', 'TRules[' . $id . '][frequency_days]', $frequency_days, 8, 0, 'style="width:25%" placeholder="14"')
		, 'nb_tests' => $formCore->texte('', 'TRules[' . $id . '][nb_tests]', $nb_tests, 8, 0, 'style="width:90%" placeholder="1"')
		, 'nbDossiers' => $rule->getNbElementsSelectable($PDOdb)
		, 'action' =>  '<a href="' . dol_buildpath('/financement/admin/qualite.php', 1) . '?action=deleteline&lineid=' . $id . '">' . img_delete() . '</a>'
	);
}

$newRuleName = ! empty($_POST['TRules'][-1]['name']) ? $_POST['TRules'][-1]['name'] : '';
$newRuleElementType = ! empty($_POST['TRules'][-1]['element_type']) ? $_POST['TRules'][-1]['element_type'] : 'fin_dossier';
$newRuleSQLFilter = ! empty($_POST['TRules'][-1]['sql_filter']) ? $_POST['TRules'][-1]['sql_filter'] : '';
$newRuleFrequencyDays = ! empty($_POST['TRules'][-1]['frequency_days']) ? $_POST['TRules'][-1]['frequency_days'] : '';
$newRuleNbTests = ! empty($_POST['TRules'][-1]['nb_tests']) ? $_POST['TRules'][-1]['nb_tests'] : '';

$TRulesForm[-1] = array(
	'id' => $langs->trans('New')
	, 'name' => $formCore->texte('', 'TRules[-1][name]', $newRuleName, 64, 0, 'style="width:95%"')
	, 'element_type' => $formCore->combo('', 'TRules[-1][element_type]', $rulesStatic->TElementTypes, $newRuleElementType)
	, 'sql_filter' => $formCore->texte('', 'TRules[-1][sql_filter]', $newRuleSQLFilter, 255, 0, 'style="width:99%"')
	, 'frequency_days' => $formCore->texte('', 'TRules[-1][frequency_days]', $newRuleFrequencyDays, 4, 0, 'style="width:25%" placeholder="14"')
	, 'nb_tests' => $formCore->texte('', 'TRules[-1][nb_tests]', $newRuleNbTests, 4, 0, 'style="width:90%" placeholder="1"')
	, 'nbDossiers' => ''
	, 'action' => '<button type="submit" name="action" value="save">' . $langs->trans('Save') . '</button>' // Impossible à faire avec le TFormCore...
);

$template = new TTemplateTBS;
echo $template->render(
	__DIR__ . '/../tpl/admin_qualite.tpl.php'
	, array(
		'TRules' => $TRulesForm
	)
	, array(
		'sqlFilterLabelWithTooltip' => $form->textwithpicto('Filtre SQL', $langs->trans('QualityControlSQLTooltip')) // TODO translate
	)
);

$formCore->end();

llxFooter();
