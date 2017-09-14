<?php

set_time_limit(0);
require('config.php');
require('./class/simulation.class.php');
require('./lib/financement.lib.php');

$PDOdb = new TPDOdb();
$PDOdb2 = new TPDOdb();
$simu = new TSimulation();

$sql = "SELECT rowid ";
$sql.= "FROM ".MAIN_DB_PREFIX."fin_simulation ";
$sql.= "WHERE 1";
// Filtrer par entité sauf admin
$sql.= ' AND entity IN('.getEntity('fin_simulation', TFinancementTools::user_courant_est_admin_financement()).')';

$resql = $PDOdb->Execute($sql);
$i = $j = 0;
$fileContent = '';
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
		//pre($simu,true);exit;
		
		foreach($TDossier as $id_dossier) {
			if(!empty($simu->dossiers[$id_dossier])) {
				$details = $simu->dossiers[$id_dossier];
				$line = array();
				$line['ref_simulation'] = $simu->reference;
				$line['num_contrat'] = $details['num_contrat'];
				$line['num_contrat_leaser'] = $details['num_contrat_leaser'];
				$line['leaser'] = $details['leaser'];
				$line['date_debut_periode_client'] = $details['date_debut_periode_client'];
				$line['date_fin_periode_client'] = $details['date_fin_periode_client'];
				$line['date_debut_periode_leaser'] = $details['date_debut_periode_leaser'];
				$line['date_fin_periode_leaser'] = $details['date_fin_periode_leaser'];
				$line['decompte_copies_sup'] = $details['decompte_copies_sup'];
				$line['solde_banque_a_periode_identique'] = $details['solde_banque_a_periode_identique'];
				$line['solde_vendeur'] = $details['solde_vendeur'];
				$line['type_contrat'] = $details['type_contrat'];
				$line['duree'] = $details['duree'];
				$line['echeance'] = $details['echeance'];
				$line['loyer_actualise'] = $details['loyer_actualise'];
				$line['date_debut'] = !empty($details['date_debut']) ? date('Y-m-d',$details['date_debut']) : '';
				$line['date_fin'] = !empty($details['date_fin']) ? date('Y-m-d',$details['date_fin']) : '';
				$line['date_prochaine_echeance'] = !empty($details['date_prochaine_echeance']) ? date('Y-m-d',$details['date_prochaine_echeance'])	 : '';
				$line['numero_prochaine_echeance'] = $details['numero_prochaine_echeance'];
				$line['terme'] = $details['terme'];
				$line['reloc'] = $details['reloc'];
				$line['maintenance'] = $details['maintenance'];
				$line['assurance'] = $details['assurance'];
				$line['assurance_actualise'] = $details['assurance_actualise'];
				$line['montant'] = $details['montant'];
				
				$TEch = explode('/', $details['numero_prochaine_echeance']);
				$line['numero_echeance'] = !empty($TEch[0]) ? (int)$TEch[0] - 1 : 0;
				
				$line['retrait_copie_supp'] = $details['retrait_copie_supp'];
				
				//pre($line,true);

				if(empty($fileContent)) $fileContent = implode(';', array_keys($line)) . "\r\n";
				$fileContent.= implode(';', $line) . "\r\n";
				
			}
			
		}
		
	}
	$j++;
}
//echo nl2br($fileContent) . '<br>';
//echo $i . ' / ' .$j;

$fileName = 'dossier_rachetes_'.date('ymd').'.csv';

$size = strlen($fileContent);
			
header("Content-Type: application/csv; name=\"$fileName\"");
header("Content-Transfer-Encoding: csv");
header("Content-Length: $size");
header("Content-Disposition: attachment; filename=\"$fileName\"");
header("Expires: 0");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

print $fileContent;

exit();