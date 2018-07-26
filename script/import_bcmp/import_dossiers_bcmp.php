<?php

require('../../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$PDOdb = new TPDOdb;

$TData = array();
$f = fopen(__DIR__.'/dossiers_bcmp.csv', 'r');
while($line = fgetcsv($f,2048,';','"')) {
	//pre($line,true);
	
	$TData[] = parseline($PDOdb, $line);
}
unset($TData[0]);

$i = $upd = 0;
foreach ($TData as $datadoss) {
	$upd += updateDossier($PDOdb, $datadoss);
	$i++;
	if($i > 0) break;
}

echo '<hr>'.$upd.' mise à jour effectuées sur '.$i.' lignes dans le fichier';


function parseline(&$PDOdb, $line) {
	$data = array(
		'financement' => array(
			'reference' => $line[0]
			,'montant' => price2num($line[10])
		)
		,'financementLeaser' => array(
			'reference' => $line[9]
		)
	);
	
	return $data;
}

// Chargement du dossier, modification pour passer en interne + remplir les données
function updateDossier(&$PDOdb, $data) {
	echo '<hr>';
	
	if(empty($data['financementLeaser']['reference'])) {
		echo $data['financementLeaser']['reference'].' - Référence vide';
		return 0;
	}
	pre($data,true);
	$fin = new TFin_financement();
	if($fin->loadReference($PDOdb, $data['financementLeaser']['reference']) > 0) {
		$doss = new TFin_dossier();
		$doss->load($PDOdb, $fin->fk_fin_dossier, false, true);
		
		$doss->load_affaire($PDOdb);
		$doss->TLien[0]->affaire->nature_financement = 'INTERNE';
		$doss->TLien[0]->affaire->save($PDOdb);
		pre((array)$doss->financementLeaser,true);
		
		$doss->nature_financement = 'INTERNE';
		$doss->financement->date_debut = $doss->financementLeaser->date_debut;
		$doss->financement->date_fin = $doss->financementLeaser->date_fin;
		$doss->financement->duree = $doss->financementLeaser->duree;
		$doss->financement->terme = $doss->financementLeaser->terme;
		$doss->financement->montant_prestation = $doss->financementLeaser->montant_prestation;
		$doss->financement->echeance = $doss->financementLeaser->echeance;
		$doss->financement->loyer_intercalaire = $doss->financementLeaser->loyer_intercalaire;
		$doss->financement->reste = $doss->financementLeaser->reste;
		$doss->financement->assurance = $doss->financementLeaser->assurance;
		$doss->financement->periodicite = $doss->financementLeaser->periodicite;
		$doss->financement->reglement = $doss->financementLeaser->reglement;
		$doss->financement->penalite_reprise = $doss->financementLeaser->penalite_reprise;
		$doss->financement->taux_commission = $doss->financementLeaser->taux_commission;
		$doss->financement->frais_dossier = $doss->financementLeaser->frais_dossier;
		$doss->financement->loyer_actualise = $doss->financementLeaser->loyer_actualise;
		$doss->financement->assurance_actualise = $doss->financementLeaser->assurance_actualise;
		
		$doss->financement->reference = $data['financement']['reference'];
		$doss->financement->montant = $data['financement']['montant'];
		
		$doss->save($PDOdb);
		
		$doss->financement->setProchaineEcheanceClient($PDOdb, $doss);
		
		echo $data['financementLeaser']['reference'].' - Dossier mis à jour<br>';
		return 1;
	} else {
		echo $data['financementLeaser']['reference'].' - Dossier leaser non trouvé<br>';
		return 0;
	}
}
