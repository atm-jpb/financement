<?php

require 'config.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

if (empty($user->admin)) accessforbidden();

$langs->load('abricot@abricot');
$langs->load('financement@financement');

$PDOdb = new TPDOdb;
$object = null;

$TEntity = GETPOST('TEntity');
if (empty($TEntity)) $TEntity = array();

$view = GETPOST('view', 'alpha');
switch ($view) {
	case 'facturation_par_leaser':
		$title = $langs->trans('ReportFinancementFacturationParLeaser');
		break;
	case 'types_contrats_et_financements_actifs':
		$title = $langs->trans('ReportFinancementTypesContratsEtFinancesmentActifs');
		break;
	case 'encours_leaser':
		$title = $langs->trans('ReportFinancementEncoursLeaser');
		break;
	case 'recurrent_financement':
		$title = $langs->trans('ReportFinancementRecurrentFinancement');
		break;
	case 'renta_neg':
		$title = $langs->trans('ReportFinancementRentaNeg');
		break;
	default:
		$view = 'demandes_de_financement';
		$title = $langs->trans('ReportFinancementDemandesDeFinancement');
		break;
}

$hookmanager->initHooks(array('financementreport'));


$date_fiscal_start = date('Y-'.$conf->global->SOCIETE_FISCAL_MONTH_START.'-01');
$date_fiscal_end = date('Y-'.($conf->global->SOCIETE_FISCAL_MONTH_START-1).'-t');
if ((int) $conf->global->SOCIETE_FISCAL_MONTH_START > (int) date('m')) $date_fiscal_start = date('Y-m-d', strtotime($date_fiscal_start.' -1 year'));
else $date_fiscal_end = date('Y-m-d', strtotime($date_fiscal_end.' +1 year'));
$time_fiscal_start = strtotime($date_fiscal_start);
$time_fiscal_end = strtotime($date_fiscal_end);

/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	// do action from GETPOST ... 
}


$dao = new DaoMulticompany($db);
$dao->getEntities();

$TEntityAvailable = array();
foreach ($dao->entities as &$e)
{
	$TEntityAvailable[$e->id] = $e->label;
}

$form = new Form($db);


/*
 * View
 */

// Permet de faire l'inclusion de la librairie via l'appel à llxHeader
$conf->global->MAIN_USE_JQUERY_MULTISELECT = 'select2';

llxHeader('',$title,'','');

$head_search = '<div class="divsearchfield">';
$head_search.= $langs->trans('FinancementEntitySearch').' '.$form->multiselectarray('TEntity', $TEntityAvailable, $TEntity, 0, 0, '', 0, '600');
$head_search.= '</div>';

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_financement', 'GET');

// Print le contenu de la page
call_user_func($view, $title, $head_search, $TEntity);



$parameters=array('view'=>$view);
$reshook=$hookmanager->executeHooks('printFieldListFooter', $parameters, $object);    // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

$formcore->end_form();

// Css d'une version Dolibarr plus recente pour appliquer le style sur la classe .oddeven plutôt que pair/impair
print '<style type="text/css">
	.noborder > tbody > tr:nth-child(even):not(.liste_titre), .liste > tbody > tr:nth-child(even):not(.liste_titre) {
		background: linear-gradient(bottom, rgb(255,255,255) 85%, rgb(255,255,255) 100%);
		background: -o-linear-gradient(bottom, rgb(255,255,255) 85%, rgb(255,255,255) 100%);
		background: -moz-linear-gradient(bottom, rgb(255,255,255) 85%, rgb(255,255,255) 100%);
		background: -webkit-linear-gradient(bottom, rgb(255,255,255) 85%, rgb(255,255,255) 100%);
		background: -ms-linear-gradient(bottom, rgb(255,255,255) 85%, rgb(255,255,255) 100%);
	}
	.noborder > tbody > tr:nth-child(even):not(:last-child) td:not(.liste_titre), .liste > tbody > tr:nth-child(even):not(:last-child) td:not(.liste_titre) {
		border-bottom: 1px solid #ddd;
	}

	.noborder > tbody > tr:nth-child(odd):not(.liste_titre), .liste > tbody > tr:nth-child(odd):not(.liste_titre) {
		background: linear-gradient(bottom, rgb(248,248,248) 85%, rgb(248,248,248) 100%);
		background: -o-linear-gradient(bottom, rgb(248,248,248) 85%, rgb(248,248,248) 100%);
		background: -moz-linear-gradient(bottom, rgb(248,248,248) 85%, rgb(248,248,248) 100%);
		background: -webkit-linear-gradient(bottom, rgb(248,248,248) 85%, rgb(248,248,248) 100%);
		background: -ms-linear-gradient(bottom, rgb(248,248,248) 85%, rgb(248,248,248) 100%);
	}
	.noborder > tbody > tr:nth-child(odd):not(:last-child) td:not(.liste_titre), .liste > tbody > tr:nth-child(odd):not(:last-child) td:not(.liste_titre) {
		border-bottom: 1px solid #ddd;
	}
</style>';

llxFooter('');
$db->close();


function getTContrat($force=false)
{
	global $db, $TContrat;
	
	if (empty($TContrat) || $force)
	{
		$TContrat = array();
		// Récupération des types de contrat possible (LOCSIMPLE, INTEGRAL, ...)
		$sql = 'SELECT DISTINCT fk_type_contrat FROM '.MAIN_DB_PREFIX.'fin_simulation';
		$resql = $db->query($sql);
		if ($resql)
		{
			while ($arr = $db->fetch_array($resql)) $TContrat[$arr['fk_type_contrat']] = $arr['fk_type_contrat'];
		}
		else
		{
			dol_print_error($db);
			exit;
		}
	}
	
	
	return $TContrat;
}

function getTStatut($force=false)
{
	global $TStatut;
	
	if (empty($TStatut) || $force)
	{
		$simulation = new TSimulation;
		$TStatut = $simulation->TStatut;
	}
	
	return $TStatut;
}

function _getNbSimulation($date_simul_start, $date_simul_end, $fk_type_contrat='', $accord='', $TEntity=array())
{
	global $db;
	
	$sql = 'SELECT count(*) as nb FROM '.MAIN_DB_PREFIX.'fin_simulation WHERE date_simul >= "'.$db->idate($date_simul_start).'" AND date_simul <= "'.$db->idate($date_simul_end).'"';
	if (!empty($fk_type_contrat)) $sql.= ' AND fk_type_contrat = "'.$fk_type_contrat.'"';
	if (!empty($accord)) $sql.= ' AND accord = "'.$accord.'"';
	if (!empty($TEntity)) $sql.= ' AND entity IN ('.implode(',', $TEntity).')';
	
	$resql = $db->query($sql);
	if ($resql)
	{
		if (($obj = $db->fetch_object($resql))) return $obj->nb;
	}
	else
	{
		dol_print_error($db);
		exit;
	}
	
	return 0;
}

function demandes_de_financement($title, $head_search, $TEntity)
{
	global $db,$langs,$time_fiscal_start,$time_fiscal_end,$form;
	
	$TContrat = getTContrat();
	$TContrat_filter = GETPOST('TContrat', 'array'); // GETPOST
	
	$TStatut = getTStatut();
	$TStatut_filter = GETPOST('TStatut', 'array'); // GETPOST
	
	$head_search.= '<div class="divsearchfield">';
	$head_search.= $langs->trans('TypeContrat').' '.$form->multiselectarray('TContrat', $TContrat, $TContrat_filter, 0, 0, '', 0, '200');
	$head_search.= '</div>';
	$head_search.= '<div class="divsearchfield">';
	$head_search.= $langs->trans('FinancementStatus').' '.$form->multiselectarray('TStatut', $TStatut, $TStatut_filter, 0, 0, '', 0, '200');
	$head_search.= '</div>';
	
	// Par défaut, si rien de selectionné, alors j'affiche tout
	if (empty($TContrat_filter)) $TContrat_filter = array_keys($TContrat);
	if (empty($TStatut_filter)) $TStatut_filter = array_keys($TStatut);
	
	$TData = array();
//	var_dump(
//		date('Y-m-d', $time_fiscal_start)
//		,date('Y-m-d', $time_fiscal_end)
//	);
	
	$start_time = $time_fiscal_start;
	$TTotal = array('periode' => 'Cumulé sur l\'exercice');
	$i=0;
	while ($start_time < $time_fiscal_end)
	{
		$end_time_periode = strtotime(date('Y-m-t', $start_time));
		$periode = date('d/m/Y', $start_time).' '.date('d/m/Y', $end_time_periode);
		
		$TData[$i]['periode'] = $periode;
		
		foreach ($TStatut_filter as $statut)
		{
			if (empty($TStatut[$statut])) continue;
			
			$val = _getNbSimulation($start_time, $end_time_periode, '', $statut, $TEntity);
			$TData[$i][$statut] = $val;
			$TData[$i]['total'] += $val;
			$TTotal[$statut] += $val;
		}
		
		foreach ($TContrat_filter as $type)
		{
			if (empty($TContrat[$type])) continue;
			
			$val = _getNbSimulation($start_time, $end_time_periode, $type, 'OK', $TEntity);
			$TData[$i][$type] = $val;
			$TData[$i]['total'] += $val;
			$TTotal[$type] += $val;
			
		}
		
		$TTotal['total'] += $TData[$i]['total'];
		
		$i++;
		$start_time = strtotime('+1 month', $start_time);
	}
	
	// 
	foreach ($TTotal as $k => &$v)
	{
		if (in_array($k, array('periode', 'total'))) continue;
		$v = number_format($v * 100 / $TTotal['total'], 2).' %';
	}
	$TTotal['total'] = '100 %';
	
	$TData[] = $TTotal;
	
	$TTitle = array();
	$TTitle['periode'] = $langs->trans('Periode');
	foreach ($TStatut_filter as $statut) $TTitle[$statut] = $TStatut[$statut];
	foreach ($TContrat_filter as $type) $TTitle[$type] = $type;
	$TTitle['total'] = $langs->trans('TotalDemande');

	$r = new Listview($db, 'financement');
	print $r->renderArray($db, $TData, array(
		'view_type' => 'list' // default = [list], [raw], [chart]
		,'list' => array(
			'title' => $title
			,'image'=>'object_accounting.png'
			,'head_search' => $head_search
		)
		,'title'=>$TTitle
		,'size'=>array(
			'width'=>array(
				'periode'=>'20%'
				,'total'=>'10%'
			)
		)
		,'position'=>array(
			'text-align'=>array(
				'total'=>'right'
			)
		)
		,'search'=>array(
			'periode'=>''
		)
	));
	
}

function facturation_par_leaser($title, $head_search, $TEntity)
{
	
}

function types_contrats_et_financements_actifs($title, $head_search, $TEntity)
{
	
}

function encours_leaser($title, $head_search, $TEntity)
{
	
}

function recurrent_financement($title, $head_search, $TEntity)
{
	
}

function renta_neg($title, $head_search, $TEntity)
{
	
}