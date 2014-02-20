<?php

require('../../config.php');
require('../../class/affaire.class.php');
require('../../class/dossier.class.php');
require('../../class/grille.class.php');
dol_include_once('/asset/class/asset.class.php');

@set_time_limit(0);					// No timeout for this script

$ATMdb = new TPDOdb;
$ATMdb2 = new TPDOdb;

//Chargement de l'ensemble des affaire :
//	celles commençant par EXT on supprime l'affaire, le dossier, le financement et le lien affaire_dossier
//	celles avec nature externe on supprime uniquement le dossier et le financement

$sql = "SELECT a.rowid as idAffaire, da.rowid as idDossierAffaire, d.rowid as idDossier, df.rowid as idDossierFinancement
		FROM ".MAIN_DB_PREFIX."fin_affaire as a 
			LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire as da ON (a.rowid = da.fk_fin_affaire)
			LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier as d ON (d.rowid = da.fk_fin_dossier)
			LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as df ON (df.fk_fin_dossier = d.rowid)
		WHERE 1";

$nbAffaire = 0;

$ATMdb->Execute($sql);

$force = __get('force',0,'integer');


while($ATMdb->Get_line()){
	
	$affaire = new TFin_affaire;
	$dossier = new TFin_dossier;
	$dossierAffaire = new TFin_dossier_affaire;
	$dossierFinancement = new TFin_financement;
	
	$affaire->load($ATMdb2, $ATMdb->Get_field('idAffaire'),false);
	echo "Affaire : ".$affaire->rowid.'<br>';
	
	if($affaire->nature_financement == 'EXTERNE'){
		
		$dossier->load($ATMdb2,$ATMdb->Get_field('idDossier'),false);
		echo "Dossier : ".$dossier->rowid.'<br>';
		
		if($dossier->financementLeaser->fk_soc == 3306){ //LOCAM
		
			if($force)	$dossier->delete($ATMdb2);
			
			$dossierAffaire->load($ATMdb2, $ATMdb->Get_field('idDossierAffaire'),false);
			echo "DossierAffaire : ".$dossierAffaire->rowid.'<br>';
			if($force)$dossierAffaire->delete($ATMdb2);
			
			$dossierFinancement->load($ATMdb2, $ATMdb->Get_field('idDossierFinancement'));
			echo "DossierFinancement : ".$dossierFinancement->rowid.'<br>';
			if($force)$dossierFinancement->delete($ATMdb2);
			
			if((int)strpos($affaire->reference,"EXT-") === 0){
				if($force)$affaire->delete($ATMdb2);
				
				$nbAffaire ++;
				
				echo $affaire->reference."<br>";
			}
		}
	}
	
	echo '<br><br>';
}

echo $nbAffaire.' ****** ';
