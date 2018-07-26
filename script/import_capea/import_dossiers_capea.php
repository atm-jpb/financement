<?php

require('../../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$PDOdb = new TPDOdb;

$TPeriodicite = array(
	'Trimestrielle' => 'TRIMESTRE'
	,'Mensuelle' => 'MOIS'
	,'Annuelle' => 'ANNEE'
	,'Semestrielle' => 'SEMESTRE'
);

$TData = array();
$f = fopen(__DIR__.'/dossiers_capea.csv', 'r');
$i = 0;
while($line = fgetcsv($f,2048,';','"')) {
	//pre($line,true);
	
	parseline($PDOdb, $TData, $line);
	$i++;
	if($i>5) break;
}
unset($TData[0]);
pre($TData,true);
exit;
$i = $crea = 0;
foreach ($TData as $datadoss) {
	$crea += createDossier($PDOdb, $datadoss);
	$i++;
	if($i > 0) break;
}

echo '<hr>'.$crea.' créations effectuées sur '.$i.' lignes dans le fichier';


function parseline(&$PDOdb, &$TData, $line) {
	global $TPeriodicite;
	
	// Pas de référence => ligne à ignorer
	if(empty($line[6])) return 0;
	
	// Récupération de la ligne précédente pour garder les données non répétées dans le fichier
	if(!empty($TData)) $data = $TData[count($TData) - 1];
	
	$data['financementLeaser']['reference'] = $line[6];
	if(!empty($line[4])) $data['financement']['fk_soc_client'] = getSocieteBySIREN($PDOdb, $line[4]);
	
	if(empty($data['financement']['fk_soc_client'])) {
		echo $data['financementLeaser']['reference'].' - SIREN non trouvé : '.$line[4].'<br>';
		return 0;
	}
	
	if(!empty($line[8])) $data['financementLeaser']['date_debut'] = $line[8];
	if(!empty($line[9])) $data['financementLeaser']['date_fin'] = $line[9];
	if(!empty($line[10])) $data['financementLeaser']['periodicite'] = $TPeriodicite[$line[10]];
	if(!empty($line[11])) $data['financementLeaser']['duree'] = $line[11];
	if(!empty($line[12])) $data['financementLeaser']['montant'] = $line[12];
	if(!empty($line[13])) $data['financementLeaser']['echeance'] = $line[13];
	if(!empty($line[14])) $data['financementLeaser']['loyer_intercalaire'] = $line[14];
	
	return $data;
}

// Création de l'affaire, du dossier et des financements
function createDossier(&$PDOdb, $data) {
	echo '<hr>';
	
	if(empty($data['financementLeaser']['reference'])) {
		echo $data['financementLeaser']['reference'].' - Référence vide';
		return 0;
	}
	pre($data,true);
	return;
	
	$aff = new TFin_affaire();
	$aff->reference = '';
	$aff->date_affaire = '';
	$aff->save($PDOdb);
	
	$doss = new TFin_dossier();
	$doss->financement->set_values($data['financement']);
	$doss->financementLeaser->set_values($data['financementLeaser']);
	$doss->save($PDOdb);
	
	$doss->addAffaire($PDOdb, $aff->getId());
	
	echo $data['financementLeaser']['reference'].' - Dossier créé<br>';
	
	return 1;
}

// Recherche du client par SIREN
function getSocieteBySIREN(&$PDOdb, $siren) {
	if(empty($siren)) return 0;
	
	$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity = 12 AND siren = \''.substr($siren, 0,9).'\'';
	echo $sql;
	$Tab = $PDOdb->ExecuteAsArray($sql);
	if(!empty($Tab)) return $Tab[0];
	
	return 0;
}
