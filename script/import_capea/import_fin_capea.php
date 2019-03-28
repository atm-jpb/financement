<?php

require('../../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$PDOdb = new TPDOdb;

$TData = array();
$f = fopen(__DIR__.'/financements_capea_client.csv', 'r');
while($TLine = fgetcsv($f, 2048, ';', '"')) {
    $TData[] = getUsefulData($TLine);
}
unset($TData[0]);   // Unset header line

$upd = 0;
foreach($TData as $datadoss) {
    $upd += updateDossier($PDOdb, $datadoss);
}

echo '<hr>'.$upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier';

function getUsefulData($TLine) {
    $TIndex = array(
        'num_contrat' => 8,
        'num_leaser' => 9,
        'date_debut' => 10,
        'date_fin' => 11,
        'montant' => 14,
        'loyer_intercalaire' => 16
    );

    $TData = array(
        'financement' => array(
            'reference' => $TLine[$TIndex['num_contrat']],
            'montant' => price2num($TLine[$TIndex['montant']]),
            'date_debut' => $TLine[$TIndex['date_debut']],
            'date_fin' => $TLine[$TIndex['date_fin']],
            'loyer_intercalaire' => $TLine[$TIndex['loyer_intercalaire']],
            'fk_soc' => 1
        ),
        'financementLeaser' => array(
            'reference' => $TLine[$TIndex['num_leaser']],
            'fk_soc' => 207847
        )
    );

    return $TData;
}

// Chargement du dossier, modification pour passer en interne + remplir les données
function updateDossier(&$PDOdb, $data) {
    echo '<hr>';

    if(empty($data['financementLeaser']['reference'])) {
        echo $data['financementLeaser']['reference'].' - Référence vide';
        return 0;
    }

    $fin = new TFin_financement();
    if($fin->loadReference($PDOdb, $data['financementLeaser']['reference']) > 0) {
        $doss = new TFin_dossier();
        $doss->load($PDOdb, $fin->fk_fin_dossier, false, true);

        $doss->load_affaire($PDOdb);
        $doss->TLien[0]->affaire->nature_financement = 'INTERNE';
        $doss->TLien[0]->affaire->save($PDOdb);

        $doss->nature_financement = 'INTERNE';
        $doss->financement->date_debut = $doss->financementLeaser->date_debut;
        $doss->financement->date_fin = $doss->financementLeaser->date_fin;
        $doss->financement->duree = $doss->financementLeaser->duree;
        $doss->financement->date_prochaine_echeance = $doss->financementLeaser->date_prochaine_echeance;
        $doss->financement->numero_prochaine_echeance = $doss->financementLeaser->numero_prochaine_echeance;
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
        $doss->financement->incident_paiement = $doss->financementLeaser->incident_paiement;
        $doss->financement->reloc = $doss->financementLeaser->reloc;

        // Override values
        foreach($data['financement'] as $field => $value) $doss->financement->$field = $value;

        // Modification Leaser
        $doss->financementLeaser->fk_soc = $data['financementLeaser']['fk_soc'];

        $doss->save($PDOdb);

        $doss->financement->setProchaineEcheanceClient($PDOdb, $doss);

        echo $data['financementLeaser']['reference'].' - Dossier mis à jour<br>';
        return 1;
    }
    else {
        echo $data['financementLeaser']['reference'].' - Dossier leaser non trouvé<br>';
        return 0;
    }
}
