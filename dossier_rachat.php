<?php

set_time_limit(0);
require('config.php');
require('./class/simulation.class.php');

$PDOdb = new TPDOdb();
$PDOdb2 = new TPDOdb();
$simu = new TSimulation();

$sql = "SELECT rowid ";
$sql.= "FROM ".MAIN_DB_PREFIX."fin_simulation ";
//$sql.= "WHERE rowid = 3920";

$resql = $PDOdb->Execute($sql);
$i = $j = 0;
$line = '';
while($PDOdb->Get_line()) {
	$id_simu = $PDOdb->Get_field('rowid');
	$simu->load($PDOdb2, $db, $id_simu, false);
	if(!empty($simu->dossiers_rachetes)
		|| !empty($simu->dossiers_rachetes_p1)
		|| !empty($simu->dossiers_rachetes_nr)
		|| !empty($simu->dossiers_rachetes_nr_p1)
		|| !empty($simu->dossiers_rachetes_perso)
		|| !empty($simu->dossiers_rachetes_m1)
		|| !empty($simu->dossiers_rachetes_nr_m1)
	) {
		$i++;
		$TDossier = $simu->_getDossierSelected();
		//pre($TDossier,true);
		//var_dump($simu->dossiers);
		
		foreach($TDossier as $id_dossier) {
			
			if(!empty($simu->dossiers[$id_dossier])) {
				$details = $simu->dossiers[$id_dossier];
				$TLine = array();
				$TLine['ref_simulation'] = $simu->reference;
				$TLine['num_contrat'] = $details['num_contrat'];
				$TLine['num_contrat_leaser'] = $details['num_contrat_leaser'];
				$TLine['date_debut_periode_client'] = $details['date_debut_periode_client'];
				$TLine['date_fin_periode_client'] = $details['date_fin_periode_client'];
				$TLine['date_debut_periode_leaser'] = $details['date_debut_periode_leaser'];
				$TLine['date_fin_periode_leaser'] = $details['date_fin_periode_leaser'];
				$TLine['decompte_copies_sup'] = $details['decompte_copies_sup'];
				$TLine['solde_banque_a_periode_identique'] = $details['solde_banque_a_periode_identique'];
				$TLine['type_contrat'] = $details['type_contrat'];
				$TLine['duree'] = $details['duree'];
				$TLine['echeance'] = $details['echeance'];
				$TLine['loyer_actualise'] = $details['loyer_actualise'];
				$TLine['date_debut'] = $details['date_debut'];
				$TLine['date_fin'] = $details['date_fin'];
				$TLine['date_prochaine_echeance'] = $details['date_prochaine_echeance'];
				$TLine['numero_prochaine_echeance'] = $details['numero_prochaine_echeance'];
				$TLine['terme'] = $details['terme'];
				$TLine['reloc'] = $details['reloc'];
				$TLine['maintenance'] = $details['maintenance'];
				$TLine['assurance'] = $details['assurance'];
				$TLine['assurance_actualise'] = $details['assurance_actualise'];
				$TLine['montant'] = $details['montant'];

				if(empty($line)) $line = implode(';', array_keys($TLine)) . "<br>\r\n";
				$line.= implode(';', $TLine) . "<br>\r\n";
				
			}
			
		}
		
	}
	$j++;
}
echo $line . '<br>';
echo $i . ' / ' .$j;