<?php

	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/dossier.class.php');

	$PDOdb = new TPDOdb;
	
	$type = (GETPOST('type')) ? GETPOST('type') : 'CLIENT';
	
	$sql = "SELECT reference, count(*)
			FROM `llx_fin_dossier_financement` 
			WHERE type = 'CLIENT' AND reference != ''
			GROUP BY reference
			HAVING count(*) > 1
			ORDER BY reference ASC";

	$TRes = $PDOdb->ExecuteAsArray($sql);

	foreach ($TRes as $res) {
		
		$sql = "SELECT d.rowid
				FROM llx_fin_dossier_financement as fdf
				LEFT JOIN llx_fin_dossier as d ON (fdf.fk_fin_dossier = d.rowid) 
				WHERE fdf.reference = '".$res->reference."' 
				AND fdf.type = '".$type."'";

		$PDOdb->Execute($sql);
		while ($PDOdb->Get_line()) {
			$cpt ++;
			echo $PDOdb->Get_field('rowid').'<br>';
		}
	}
	
	echo ' TOTAL : '.$cpt;
	
