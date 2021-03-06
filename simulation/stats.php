<?php
require('../config.php');

$langs->load('financement@financement');

$PDOdb = new TPDOdb;
$TData = getDataSimulations($PDOdb);
$TDataTransfo = getDataSimulations($PDOdb, 12, true);

llxHeader('','Simulations - Statistiques');

print_fiche_titre('Statistiques - Simulations scorées par Leaser');

print_tab_simul_by_leaser_by_month($TData);

print '<br /><br />';

print_fiche_titre('Statistiques - Simulations financées par Leaser');

print_tab_simul_by_leaser_by_month($TDataTransfo);


llxFooter();
	
function getDataSimulations(&$PDOdb, $nb_month=12, $financee=false) {
	
	$TData = $TRes = array();
	
	$sql = 'SELECT c.label as leaser, ';
	$sql.= 'DATE_FORMAT(simu.date_simul, "%Y-%m") as mois, SUM(simu.montant) / 1000 as total ';
	$sql.= 'FROM '.MAIN_DB_PREFIX.'fin_simulation simu ';
	$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'societe lea ON (lea.rowid = simu.fk_leaser) ';
	$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'categorie_fournisseur cf ON (cf.fk_soc = lea.rowid) ';
	$sql.= 'LEFT JOIN '.MAIN_DB_PREFIX.'categorie c ON (cf.fk_categorie = c.rowid) ';
	$sql.= 'WHERE simu.accord = \'OK\' ';
	$sql.= 'AND simu.date_simul > DATE_SUB(NOW(), INTERVAL '.$nb_month.' MONTH) ';
	$sql.= 'AND c.fk_parent = 1 ';
	if($financee) $sql.= 'AND simu.fk_fin_dossier > 0 '; // On ne prend que les catégories enfant de la catégorie "Leaser"
	$sql.= 'GROUP BY c.label, mois ';
	$sql.= 'ORDER BY c.label, mois ';
	
	//echo $sql;
	
	$TData = $PDOdb->ExecuteAsArray($sql);
	//pre($TData,true);
	foreach ($TData as $data) {
		$total = round($data->total);
		$TRes['data'][$data->leaser][$data->mois] = $total;
		$TRes['total_leaser'][$data->leaser] += $total;
		$TRes['total_mois'][$data->mois] += $total;
	}
	
	ksort($TRes['data']);
	ksort($TRes['total_leaser']);
	ksort($TRes['total_mois']);
	
	return $TRes;
}

function print_tab_simul_by_leaser_by_month(&$TData) {
	global $bc;
	$var = true;
	
	$total_general = array_sum($TData['total_mois']);
	
	// Tableau avec montants
	print '<table class="liste">';
	print '<tr class="liste_titre">';
	print '<th>Leaser</th>';
	foreach ($TData['total_mois'] as $mois => $total) {
		print '<th align="center">'.$mois.'</th>';
	}
	print '<th align="center">TOTAL</th>';
	print '</tr>';
	
	foreach ($TData['data'] as $leaser => $totbymois) {
		$var = !$var;

		print '<tr '.$bc[$var].'>';
		print '<td>'.$leaser.'</td>';
		
		foreach ($TData['total_mois'] as $mois => $total) {
			print '<td align="right">'.price($totbymois[$mois],0,'',1,0).'</td>';
		}
		
		print '<td align="right">'.price($TData['total_leaser'][$leaser],0,'',1,0).'</td>';
		print '</tr>';
	}

	print '<tr class="liste_total">';
	print '<td>TOTAL</td>';
	foreach ($TData['total_mois'] as $mois => $total) {
		print '<td align="right">'.price($total,0,'',1,0).'</td>';
	}
	
	print '<td align="right">'.price($total_general,0,'',1,0).'</th>';
	print '</tr>';
	print '</table>';
	
	print '<br>';
	
	// Tableau avec %
	print '<table class="liste">';
	print '<tr class="liste_titre">';
	print '<th>Leaser</th>';
	foreach ($TData['total_mois'] as $mois => $total) {
		print '<th align="center">'.$mois.'</th>';
	}
	print '<th align="center">TOTAL</th>';
	print '</tr>';
	
	foreach ($TData['data'] as $leaser => $totbymois) {
		$var = !$var;

		print '<tr '.$bc[$var].'>';
		print '<td>'.$leaser.'</td>';
		
		foreach ($TData['total_mois'] as $mois => $total) {
			print '<td align="right">'.round($totbymois[$mois] / $TData['total_mois'][$mois] * 100).' %</td>';
		}
		
		print '<td align="right">'.round($TData['total_leaser'][$leaser] / $total_general * 100).' %</td>';
		print '</tr>';
	}

	print '<tr class="liste_total">';
	print '<td>TOTAL</td>';
	foreach ($TData['total_mois'] as $mois => $total) {
		print '<td align="right">'.price($total,0,'',1,0).'</td>';
	}
	
	print '<td align="right">'.price($total_general,0,'',1,0).'</th>';
	print '</tr>';
	print '</table>';
}
