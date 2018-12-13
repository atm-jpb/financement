<?php

require 'config.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');

require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

if (empty($user->rights->financement->admin->write)) accessforbidden();

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

$n = GETPOST('n'); // exemple de valeur : -1 / -2 / +1 (une valeur positive n'a pas d'intéret mais c'est possible)
$TContrat_filter = GETPOST('TContrat', 'array'); // GETPOST
$TStatut_filter = GETPOST('TStatut', 'array'); // GETPOST

if (GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha'))
{
	$n=0;
	$TEntity=array();
	$TContrat_filter=array();
	$TStatut_filter=array();
}

// Le calcul des dates doit ce faire après le test "remove_filter" car dépendant de "$n"
$date_fiscal_start = date('Y-'.$conf->global->SOCIETE_FISCAL_MONTH_START.'-01');
$date_fiscal_end = date('Y-'.($conf->global->SOCIETE_FISCAL_MONTH_START-1).'-t');
if ((int) $conf->global->SOCIETE_FISCAL_MONTH_START > (int) date('m')) $date_fiscal_start = date('Y-m-d', strtotime($date_fiscal_start.' -1 year'));
else $date_fiscal_end = date('Y-m-d', strtotime($date_fiscal_end.' +1 year'));
$time_fiscal_start = strtotime($date_fiscal_start.(!empty($n) ? ' '.$n.' year' : ''));
$time_fiscal_end = strtotime($date_fiscal_end.' 23:59:59 '.(!empty($n) ? ' '.$n.' year' : ''));

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

asort($TEntityAvailable);

$form = new Form($db);


/*
 * View
 */

// Permet de faire l'inclusion de la librairie via l'appel à llxHeader
$conf->global->MAIN_USE_JQUERY_MULTISELECT = 'select2';

llxHeader('',$title,'','');

$head_search = '';

$head_search.= '<div class="divsearchfield">';
$head_search.= $langs->trans('Période').' '.$form->selectarray('n', array('0' => 'n', '-1'=>'n-1'), $n, 0, 0, 0, ' style="min-width:75px"', '', 0, 0, '', ' minwidth75');
$head_search.= '</div>';
	
$head_search.= '<div class="divsearchfield">';
$head_search.= $langs->trans('FinancementEntitySearch').' '.$form->multiselectarray('TEntity', $TEntityAvailable, $TEntity, 0, 0, '', 0, '600');
$head_search.= '</div>';

$formcore = new TFormCore($_SERVER['PHP_SELF'], 'form_list_financement', 'GET');
print $formcore->hidden('view', $view);

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
		$sql = 'SELECT DISTINCT fk_type_contrat FROM '.MAIN_DB_PREFIX.'fin_simulation ORDER BY fk_type_contrat';
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

	if (isset($TContrat[0])) unset($TContrat[0]);
	if (isset($TContrat[''])) unset($TContrat['']);
	
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
	
//	if ($accord == 'WAIT')
//	{
//		echo $sql;
//		exit;
//	}
	
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
	global $db,$langs,$time_fiscal_start,$time_fiscal_end,$form
			,$TContrat_filter,$TStatut_filter;
	
	$TContrat = getTContrat();
	$TStatut = getTStatut();
	
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
		$end_time_periode = strtotime(date('Y-m-t 23:59:59', $start_time));
		$periode = date('d/m/Y', $start_time).' '.date('d/m/Y', $end_time_periode);
		
		$TData[$i]['periode'] = $periode;
		
		foreach ($TStatut_filter as $statut)
		{
			if (empty($TStatut[$statut])) continue;
			
			$val = _getNbSimulation($start_time, $end_time_periode, '', $statut, $TEntity);
			$TData[$i][$statut] = $val;
			// TODO voir si on ajoute pas les statuts wait
			if (in_array($statut, array('OK', 'KO', 'SS'))) $TData[$i]['total'] += $val;
			$TTotal[$statut] += $val;
		}
		
		foreach ($TContrat_filter as $type)
		{
			if (empty($TContrat[$type])) continue;
			
			$val = _getNbSimulation($start_time, $end_time_periode, $type, 'OK', $TEntity);
			$TData[$i][$type] = $val;
//			$TData[$i]['total'] += $val; // Il ne faut pas compter cette partie dans le total, c'est l'eclatement des Statuts OK, KO, SS
			$TTotal[$type] += $val;
			
		}
		
		$TTotal['total'] += $TData[$i]['total'];
		
		$i++;
		$start_time = strtotime('+1 month', $start_time);
	}
	
	// Récupération des valeurs dans un autre tableau avant conversion pour un graph
	$TSum = array();
	// TODO voir s'il ne faut pas exclure les statuts waits des totaux car actuellement non pris en compte dans le total
	foreach ($TTotal as $k => &$v)
	{
		if (in_array($k, $TContrat_filter) || in_array($k, $TStatut_filter))
		{
			$TSum[] = array($k, $v);
			if ($v == 0 || $TTotal['total'] == 0) $v = '0 %';
			else $v = number_format($v * 100 / $TTotal['total'], 2).' %';
		}
	}
	if (empty($TTotal['total'])) $TTotal['total'] = '0 %';
	else $TTotal['total'] = '100 %';
	
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
				'periode'=>'10%'
				,'total'=>'5%'
			)
		)
		,'position'=>array(
			'text-align'=>array(
				'total'=>'right'
			)
			,'rank'=>array(
				'periode'=> -100
				,'WAIT'=> -90
				,'WAIT_LEASER'=> -80
				,'WAIT_SELLER'=> -70
				,'WAIT_MODIF'=> -60
				,'SS'=> -50
				,'KO'=> -40
				,'OK'=> -30
				/*,'LOCSIMPLE'=>45
				,'LOCSIMPLE2'=>48
				,'INTEGRAL'=>50
				,'INTEGRAL2'=>51
				,'INTEGRAL ECO PRINT'=>52
				,'FORFAITGLOBAL'=>55
				,'GRANDCOMPTE'=>60
				,'BAREME_AVOCAT'=>65
				,'PROSPECTINTEGRAL'=>70
				,'PROSPECTFORFAITGLOBA'=>75
				,'CPRO NETWORKS'=>80
				,'ABONNEMENTINFO'=>85
				,'aboinfo'=>88*/
				,'total'=>100
			)
		)
	));
	
	
	if (!empty($TSum))
	{
		$listeview = new TListviewTBS('');
		$PDOdb = new TPDOdb;

		print $listeview->renderArray($PDOdb, $TSum
			,array(
				'type' => 'chart'
				,'chartType' => 'PieChart'
				,'liste'=>array(
					'titre'=>''
				)
			)
		);
	}
	
}

function facturation_par_leaser($title, $head_search, $TEntity)
{
	global $db,$time_fiscal_start,$time_fiscal_end;
	
	$sql = 'SELECT s.nom as nom, SUM(f.total) as "Total HT",  SUM(f.total) as annotation';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'societe s';
	$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'facture f ON (f.fk_soc = s.rowid)';
	$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'categorie_fournisseur cf ON (cf.fk_societe = s.rowid)';
	$sql.= ' INNER JOIN (
			SELECT rowid as fk_cat FROM '.MAIN_DB_PREFIX.'categorie WHERE label = "Leaser"
			UNION
			SELECT rowid as fk_cat FROM '.MAIN_DB_PREFIX.'categorie WHERE fk_parent IN (SELECT rowid as fk_cat FROM '.MAIN_DB_PREFIX.'categorie WHERE label = "Leaser")
		) c ON (c.fk_cat = cf.fk_categorie)';
	$sql.= ' WHERE s.fournisseur = 1';
	if (!empty($TEntity)) $sql.= ' AND f.entity IN ('.implode(',', $TEntity).')';
	$sql.= ' AND f.datef >= "'.$db->idate($time_fiscal_start).'" AND f.datef <= "'.$db->idate($time_fiscal_end).'"';
	$sql.= ' GROUP BY s.nom';
	
	print_barre_liste($title, 0, $_SERVER["PHP_SELF"], '', '', '', '', -1, 0, 'object_accounting.png');
	
	$search_button = '<div style="position:absolute;top:0;right:0" class="nowrap">';
	$search_button.= img_search();
	$search_button.= '&nbsp;'.img_searchclear();
	$search_button.= '</div>';
	
	print '<div style="position:relative;" class="liste_titre liste_titre_bydiv centpercent">'.$head_search.$search_button.'</div>';
	
	$listeview = new TListviewTBS('facturation_par_leaser');
	$PDOdb = new TPDOdb;

	print $listeview->render($PDOdb, $sql
		,array(
			'type' => 'chart'
			,'chartType' => 'BarChart'
			,'liste'=>array(
				'titre'=>''
			)
			,'search'=>array(
				
			)
//			,'height' => '800'
			,'chart'=>array(
				'role'=>array(
					'annotation'=>'annotation'
				)
				,'options' => array(
					//'bar' => 'groupWidth: "90%"'
				)
			)
		)
	);
}

function _getNbDossier($TEntity, $date_simul_start, $date_simul_end)
{
	global $db;
	
	$TRes = array();
	
	$sql = 'SELECT a.entity, a.contrat, count(*) as nb';
	$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_affaire a';
	$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (da.fk_fin_affaire = a.rowid)';
	$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (d.rowid = da.fk_fin_dossier)';
	$sql.= ' WHERE a.contrat IS NOT NULL AND a.contrat <> "" AND a.entity IN ('.implode(',', $TEntity).')';
	$sql.= ' AND a.date_affaire >= "'.$db->idate($date_simul_start).'" AND a.date_affaire <= "'.$db->idate($date_simul_end).'"';
	$sql.= ' AND (d.date_solde IS NULL OR d.date_solde <= \'1000-01-01 00:00:00\')';
	$sql.= ' GROUP BY a.entity, a.contrat';
	
	$resql = $db->query($sql);
	if ($resql)
	{
		while ($arr = $db->fetch_array($resql))
		{
			$TRes[$arr['entity']][$arr['contrat']] = $arr['nb'];
		}
	}
	else
	{
		dol_print_error($db);
		exit;
	}
	
	return $TRes;
}

function types_contrats_et_financements_actifs($title, $head_search, $TEntity)
{
	global $db,$langs,$TEntityAvailable,$time_fiscal_start,$time_fiscal_end
			,$TContrat_filter;
	
	$TData = array();
	
	$TContrat = getTContrat();
	if (empty($TContrat_filter)) $TContrat_filter = array_keys($TContrat);
	
	if (empty($TEntity)) $TEntity = array_keys($TEntityAvailable);
	
	$TTitle = array(
		'type_contrat' => $langs->trans('TypeContrat')
	);
	
	foreach ($TEntity as $fk_entity) $TTitle[$TEntityAvailable[$fk_entity]] = $TEntityAvailable[$fk_entity];
	$TTitle['total'] = $langs->trans('Total');

	$TNbDossier = _getNbDossier($TEntity, $time_fiscal_start, $time_fiscal_end);
	
	$i=0;
	foreach ($TContrat as $type)
	{
		foreach ($TEntity as $fk_entity)
		{
			$entity_name = $TEntityAvailable[$fk_entity];
			$TData[$i]['type_contrat'] = $type;
			$TData[$i][$entity_name] = !empty($TNbDossier[$fk_entity][$type]) ? $TNbDossier[$fk_entity][$type] : 0;
			$TData[$i]['total'] += $TData[$i][$entity_name];
		}
		
		$i++;
	}
	

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
				'type_contrat'=>'15%'
				,'total'=>'10%'
			)
		)
		,'position'=>array(
			'text-align'=>array(
				'total'=>'right'
			)
			,'rank'=>array(
				'type_contrat' => -100
				/*,'ABG'=>10
				,'Bourgogne Copie'=>15
				,'Copem'=>20
				,'Copy Concept'=>25
				,'EBM'=>30
				,'Impression'=>35
				,'Informatique'=>40
				,'QSIGD'=>45
				,'QUADRA'=>50
				,'TDP IP / SADOUX'=>55
				,'Télécom'=>60
				,'BASE COMMUNE'=>70
				,'BCMP'=>80
				,'Télécom'=>60*/
				,'total' => 100
			)
		)
	));
	
	
}

function encours_leaser($title, $head_search, $TEntity)
{
	global $db,$langs,$time_fiscal_start,$time_fiscal_end;
	
	$TData = array();
	$TTitle = array('nom' => $langs->trans('Leaser'));
	$resql = $db->query('SELECT DISTINCT type_financement FROM '.MAIN_DB_PREFIX.'fin_affaire WHERE type_financement <> "" ORDER BY type_financement');
	if ($resql)
	{
		while ($obj = $db->fetch_object($resql))
		{
			$TTitle[$obj->type_financement] = $obj->type_financement;
		}
	}
	else
	{
		dol_print_error($db);
		exit;
	}
	$TTitle['action'] = '';


	$sql = 'SELECT s.nom, s.rowid
			FROM llx_societe s
			INNER JOIN llx_categorie_fournisseur cf ON (cf.fk_societe = s.rowid) 
			INNER JOIN (
				SELECT rowid as fk_cat FROM llx_categorie WHERE label = "Leaser" 
				UNION
				SELECT rowid as fk_cat FROM llx_categorie WHERE fk_parent IN (SELECT rowid as fk_cat FROM llx_categorie WHERE label = "Leaser")
			) c ON (c.fk_cat = cf.fk_categorie) 
			WHERE s.fournisseur = 1';

	$TLeaser = array();
	$resql = $db->query($sql);
	if ($resql)
	{
		while ($row = $db->fetch_object($resql))
		{
			$TLeaser[$row->rowid] = $row->nom;
		}
	}
	else
	{
		dol_print_error($db);
		exit;
	}

	$sql = 'SELECT a.type_financement, s.nom, SUM(df.montant) as amount
			FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df
			INNER JOIN '.MAIN_DB_PREFIX.'societe s ON (s.rowid = df.fk_soc)
			INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (d.rowid = df.fk_fin_dossier)
			INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (da.fk_fin_dossier = d.rowid)
			INNER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
			
			WHERE df.type = \'LEASER\'
			AND df.fk_soc IN ('.implode(',', array_keys($TLeaser)).')
			AND a.date_affaire >= \''.$db->idate($time_fiscal_start).'\' AND a.date_affaire <= \''.$db->idate($time_fiscal_end).'\'
			AND (d.date_solde IS NULL OR d.date_solde <= \'1000-01-01 00:00:00\')
			GROUP BY a.type_financement, s.nom';

	$resql = $db->query($sql);
	if ($resql)
	{
		while ($arr = $db->fetch_array($resql))
		{
			if (!isset($TData[$arr['nom']])) $TData[$arr['nom']] = array();
			$TData[$arr['nom']]['nom'] = $arr['nom'];
			$TData[$arr['nom']][$arr['type_financement']] = price($arr['amount'], 0, '', 1, -1, 2);
		}
	}
	else
	{
		dol_print_error($db);
		exit;
	}

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
				'nom'=>'15%'
				,'action'=>'5%'
			)
		)
		,'position'=>array(
			'rank'=>array(
				'nom' => -100
			)
		)
	));
}

function recurrent_financement($title, $head_search, $TEntity)
{
	global $db,$langs,$time_fiscal_start,$time_fiscal_end,$TEntityAvailable;
	
	$TTitle = array('type' => '');
	$TType = $TPosition = array();
	if (empty($TEntity)) $TEntity = array_keys($TEntityAvailable);
	foreach ($TEntity as $fk_entity)
	{
		$TTitle[$TEntityAvailable[$fk_entity]] = $TEntityAvailable[$fk_entity];
		$TType[$TEntityAvailable[$fk_entity]] = 'money';
		$TPosition['text-align'][$TEntityAvailable[$fk_entity]] = 'right';
	}
	
	
	$sql = '
		SELECT d.entity, df.type, SUM(ff.total_ht) as total_ht
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)
		INNER JOIN '.MAIN_DB_PREFIX.'element_element eel ON (eel.fk_source = df.rowid )
		INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn ff ON (ff.rowid = eel.fk_target)
		WHERE d.entity IN ('.implode(',', $TEntity).')
		AND df.type = \'LEASER\'
		AND d.nature_financement = \'INTERNE\'
		AND eel.sourcetype="dossier" AND eel.targettype="invoice_supplier"
		AND ff.datef >= \''.$db->idate($time_fiscal_start).'\' AND ff.datef <= \''.$db->idate($time_fiscal_end).'\'
		GROUP BY d.entity

		UNION

		SELECT d.entity, df.type, SUM(f.total) as total_ht
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)
		INNER JOIN '.MAIN_DB_PREFIX.'element_element eec ON (eec.fk_source = df.rowid)
		INNER JOIN '.MAIN_DB_PREFIX.'facture f ON (f.rowid = eec.fk_target)
		WHERE d.entity IN ('.implode(',', $TEntity).')
		AND df.type = \'CLIENT\'
		AND d.nature_financement = \'INTERNE\'
		AND eec.sourcetype="dossier" AND eec.targettype="facture"
		AND f.datef >= \''.$db->idate($time_fiscal_start).'\' AND f.datef <= \''.$db->idate($time_fiscal_end).'\'
		GROUP BY d.entity
	';
	
	$resql = $db->query($sql);
	if ($resql)
	{
		$TData = array('ca_client' => array('type' => $langs->trans('FinCA_Client')), 'ha_leaser' => array('type' => $langs->trans('FinCA_Leaser')), 'recurrent' => array('type' => $langs->trans('FinRecurrent')));
		while ($arr = $db->fetch_array($sql))
		{
			if ($arr['type'] == 'CLIENT') $TData['ca_client'][$TEntityAvailable[$arr['entity']]] = $arr['total_ht'];
			else if ($arr['type'] == 'LEASER') $TData['ha_leaser'][$TEntityAvailable[$arr['entity']]] = $arr['total_ht'];
		}
		
		foreach ($TEntity as $fk_entity)
		{
			$delta = $TData['ca_client'][$TEntityAvailable[$fk_entity]] - $TData['ha_leaser'][$TEntityAvailable[$fk_entity]];
			$TData['recurrent'][$TEntityAvailable[$fk_entity]] = $delta;
		}
	}
	else
	{
		dol_print_error($db);
		exit;
	}
	
	$TPosition['rank'] = array(
		'type'=> -100
		/*,'ABG'=>10
		,'Bourgogne Copie'=>15
		,'Copem'=>20
		,'Copy Concept'=>25
		,'EBM'=>30
		,'Impression'=>35
		,'Informatique'=>40
		,'QSIGD'=>45
		,'QUADRA'=>50
		,'TDP IP / SADOUX'=>55
		,'Télécom'=>60*/
	);
	
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
				'nom'=>'15%'
				,'action'=>'5%'
			)
		)
		,'type'=>$TType
		,'position'=>$TPosition
	));
	
}

function renta_neg($title, $head_search, $TEntity)
{
	global $db,$langs,$time_fiscal_start,$time_fiscal_end,$TEntityAvailable;
	
	if (empty($TEntity)) $TEntity = array_keys($TEntityAvailable);
	
	// Load statuts
	$sql = 'SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_financement_statut_dossier WHERE entity IN (0, '.implode(',', $TEntity).') ORDER BY label';
	$resql = $db->query($sql);
	$TStatutDossierById = array();
	$TStatutDossierByCode = array();
	if ($resql)
	{
		while ($row = $db->fetch_object($resql))
		{
			$TStatutDossierById[$row->rowid] = $row->label;
			$TStatutDossierByCode[$row->code] = $row->label;
		}
	}
	else
	{
		dol_print_error($db);
	}
		
	// Statut renta neg anomalie
	$sql = 'SELECT rowid, code, label FROM '.MAIN_DB_PREFIX.'c_financement_statut_renta_neg_ano WHERE entity IN (0, '.implode(',', $TEntity).') ORDER BY label';
	$resql = $db->query($sql);
	$TStatutRentaNegAnoById = array();
	$TStatutRentaNegAnoByCode = array();
	if ($resql)
	{
		while ($row = $db->fetch_object($resql))
		{
			$TStatutRentaNegAnoById[$row->rowid] = $row->label;
			$TStatutRentaNegAnoByCode[$row->code] = $row->label;
		}
	}
	else
	{
		dol_print_error($db);
	}
	// Fin load statuts
	
	// Liste du nombre de dossier par statut
	$sql = 'SELECT count(*) AS nb, d.fk_statut_dossier
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)
		
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (da.fk_fin_dossier = d.rowid)
		INNER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)

		WHERE d.nature_financement = \'INTERNE\' 
		AND df.type = \'LEASER\'
		AND d.fk_statut_dossier IS NOT NULL
		AND d.fk_statut_dossier != \'\'
		AND a.date_affaire >= "'.$db->idate($time_fiscal_start).'" AND a.date_affaire <= "'.$db->idate($time_fiscal_end).'"
		AND d.entity IN ('.implode(',', $TEntity).')
		
		GROUP BY d.fk_statut_dossier
	';
	
	$TStatut = array();
	$resql = $db->query($sql);
	if ($resql)
	{
		foreach ($TStatutDossierById as $label) $TStatut[$label] = 0;
		
		while ($row = $db->fetch_object($resql))
		{
			$label = '';
			if (!empty($TStatutDossierById[$row->fk_statut_dossier])) $label = $TStatutDossierById[$row->fk_statut_dossier];
			else if (!empty($TStatutRentaNegAnoByCode[$row->fk_statut_dossier])) $label = $TStatutRentaNegAnoByCode[$row->fk_statut_dossier];
			
			if (!empty($label)) $TStatut[$label] = $row->nb;
		}
	}
	else
	{
		dol_print_error($db);
	}
	
	// Liste du nombre de dossier par statut renta neg
	$sql = 'SELECT count(*) AS nb, d.fk_statut_renta_neg_ano
		FROM '.MAIN_DB_PREFIX.'fin_dossier d
		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)

		INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (da.fk_fin_dossier = d.rowid)
		INNER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
			
		WHERE d.nature_financement = \'INTERNE\' 
		AND df.type = \'LEASER\'
		AND d.fk_statut_renta_neg_ano IS NOT NULL
		AND d.fk_statut_renta_neg_ano != \'\'
		AND a.date_affaire >= "'.$db->idate($time_fiscal_start).'" AND a.date_affaire <= "'.$db->idate($time_fiscal_end).'"
		AND d.entity IN ('.implode(',', $TEntity).')
		
		GROUP BY d.fk_statut_renta_neg_ano
	';
	
	$TAnomalie = array();
	$resql = $db->query($sql);
	if ($resql)
	{
		foreach ($TStatutRentaNegAnoById as $label) $TAnomalie[$label] = 0;
		
		while ($row = $db->fetch_object($resql))
		{
			$label = '';
			if (!empty($TStatutRentaNegAnoById[$row->fk_statut_renta_neg_ano])) $label = $TStatutRentaNegAnoById[$row->fk_statut_renta_neg_ano];
			else if (!empty($TStatutRentaNegAnoByCode[$row->fk_statut_renta_neg_ano])) $label = $TStatutRentaNegAnoByCode[$row->fk_statut_renta_neg_ano];
			
			if (!empty($label)) $TAnomalie[$label] = $row->nb;
		}
	}
	else
	{
		dol_print_error($db);
	}
	
	print load_fiche_titre($title, '', 'object_accounting.png');
	
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $head_search;
	print '</div>';
	
	print '<div class="div-table-responsive liste_titre">';
	
	print '<div style="" class="nowrap">';
	print img_search();
	print '&nbsp;'.img_searchclear();
	print '</div>';
	
	print '</div>';
	
	
	print '<div class="fichethirdleft">';
	
	// TODO afficher le tableau renta neg
	
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><th colspan="2">Anomalies</th></tr>';
	if (empty($TAnomalie)) print '<tr><td colspan="2">Pas de stat sur les dossiers en anomalies</td></tr>';
	else
	{
		foreach ($TAnomalie as $label => $nb)
		{
			print '<tr class="">';

			print '<td>'.$label.'</td>';
			print '<td align="right">'.$nb.'</td>';

			print '</tr>';
		}
	}
	
	print '</table>';
	
	print '</div>';
	
	print '<div class="fichetwothirdright">';
	print '<div class="ficheaddleft">';
	
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre"><th colspan="2">Statuts</th></tr>';
	
	if (empty($TStatut)) print '<tr><td colspan="2">Pas de stat sur les dossiers en anomalies</td></tr>';
	else
	{
		foreach ($TStatut as $label => $nb)
		{
			print '<tr class="">';

			print '<td>'.$label.'</td>';
			print '<td align="right">'.$nb.'</td>';

			print '</tr>';
		}
	}
	
	print '</table>';
	
	
	print '</div>';
	print '</div>';
}