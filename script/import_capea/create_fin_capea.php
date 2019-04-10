<?php

require('../../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/lib/financement.lib.php');

set_time_limit(0);

$PDOdb = new TPDOdb;

$TPeriodicite = array(
    'Trimestrielle' => 'TRIMESTRE'
    ,'Mensuelle' => 'MOIS'
    ,'Annuelle' => 'ANNEE'
    ,'Semestrielle' => 'SEMESTRE'
);

$TTranslate = array(
    'Adossé' => 'ADOSSEE',
    'location simple' => 'LOCSIMPLE',
    'INTEGRAL' => 'INTEGRAL'
);

switchEntity(15);   // 15 => CAPEA Bordeaux ; Utile pour le getEntity un peu plus loin

$TData = array();
$f = fopen(__DIR__.'/financements_capea_toAdd.csv', 'r');
while($TLine = fgetcsv($f, 2048, ';', '"')) {
    parseline($PDOdb, $TData, $TLine);
}
unset($TData[0]);   // Unset header line

$upd = 0;
foreach($TData as $datadoss) {
    $upd += createDossier($PDOdb, $datadoss);
}

echo '<hr>'.$upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier';

function parseline(&$PDOdb, &$TData, $line) {
    global $TPeriodicite, $TTranslate;

    $TIndex = array(
        'type_financement' => 1,
        'type_contrat' => 4,
        'siren' => 9,
        'num_contrat' => 11,
        'commentaire' => 14,
        'date_debut' => 15,
        'date_fin' => 16,
        'periodicite' => 17,
        'nb_loyer' => 18,
        'montant' => 19,
        'echeance' => 20,
        'loyer_intercalaire' => 21,
        'date_debut_leaser' => 22
    );

    // Pas de référence => ligne à ignorer
    if(empty($line[$TIndex['num_contrat']])) return 0;

    // Récupération de la ligne précédente pour garder les données non répétées dans le fichier
    if(!empty($TData)) $data = $TData[count($TData) - 1];

    $data['financementLeaser']['reference'] = $line[$TIndex['num_contrat']];
    if(!empty($line[$TIndex['siren']])) $data['financement']['fk_soc'] = getSocieteBySIREN($PDOdb, $line[$TIndex['siren']]);

    if(empty($data['financement']['fk_soc'])) {
        echo '<hr>'.$data['financementLeaser']['reference'].' - SIREN non trouvé : '.$line[$TIndex['siren']];
        return 0;
    }

    if($line[$TIndex['date_debut']] != '') $data['financement']['date_debut'] = $line[$TIndex['date_debut']];
    if($line[$TIndex['date_debut_leaser']] != '') $data['financementLeaser']['date_debut'] = $line[$TIndex['date_debut_leaser']];
    if($line[$TIndex['date_fin']] != '') $data['financementLeaser']['date_fin'] = $line[$TIndex['date_fin']];
    if($line[$TIndex['periodicite']] != '') $data['financementLeaser']['periodicite'] = $TPeriodicite[$line[$TIndex['periodicite']]];
    if($line[$TIndex['nb_loyer']] != '') $data['financementLeaser']['duree'] = price2num($line[$TIndex['nb_loyer']]);
    if($line[$TIndex['montant']] != '') $data['financementLeaser']['montant'] = price2num($line[$TIndex['montant']]);
    if($line[$TIndex['loyer_intercalaire']] != '') $data['financementLeaser']['loyer_intercalaire'] = price2num($line[$TIndex['loyer_intercalaire']]);
    if($line[$TIndex['echeance']] != '') $data['financementLeaser']['echeance'] = price2num($line[$TIndex['echeance']]);
    if($line[$TIndex['commentaire']] != '') $data['financementLeaser']['commentaire'] = $line[$TIndex['commentaire']];
    if($line[$TIndex['type_financement']] != '') $data['financementLeaser']['type_financement'] = $TTranslate[$line[$TIndex['type_financement']]];
    if($line[$TIndex['type_contrat']] != '') $data['financementLeaser']['type_contrat'] = $TTranslate[$line[$TIndex['type_contrat']]];

    $data['financementLeaser']['fk_soc'] = 207847; // Leaser CAPEA FINANCE

    $TData[] = $data;
}

function createDossier(&$PDOdb, $TData) {
    echo '<hr>';
    if(empty($TData['financementLeaser']['reference'])) {
        echo $TData['financementLeaser']['reference'].' - Référence vide';
        return 0;
    }
    pre($TData,true);

    $doss = new TFin_dossier();
    $doss->commentaire = $TData['financementLeaser']['commentaire'];
    $doss->financement->set_values($TData['financementLeaser']);
    $doss->financement->set_date('date_debut', $TData['financement']['date_debut']);    // Les date leaser & clients sont différentes

    unset($TData['financementLeaser']['loyer_intercalaire']);   // On ne garde pas le loyer_intercalaire du côté leaser
    $doss->financementLeaser->set_values($TData['financementLeaser']);
    $doss->entity = 12;
    $doss->save($PDOdb);

    if($doss->getId() > 1) {
        $aff = new TFin_affaire();
        $aff->nature_financement= 'INTERNE';
        $aff->reference = $TData['financementLeaser']['reference'];
        $aff->set_date('date_affaire', $TData['financementLeaser']['date_debut']);
        $aff->montant = $TData['financementLeaser']['montant'];
        $aff->contrat = $TData['financementLeaser']['type_contrat'];
        $aff->type_financement = $TData['financementLeaser']['type_financement'];
        $aff->fk_soc = $TData['financement']['fk_soc'];
        $aff->entity = 12;
        $aff->save($PDOdb);

        $doss->financement->setProchaineEcheanceClient($PDOdb, $doss);
        $doss->financementLeaser->setEcheanceExterne();

        $doss->addAffaire($PDOdb, $aff->getId());

        $doss->save($PDOdb);

        echo $TData['financementLeaser']['reference'].' - Dossier créé';
    } else {
        echo $TData['financementLeaser']['reference'].' - Erreur création dossier';
    }

    return 1;
}

// Recherche du client par SIREN
function getSocieteBySIREN(&$PDOdb, $siren) {
    if(empty($siren)) return 0;

    $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN ('.getEntity('societe', true).') AND siren = \''.substr($siren, 0,9).'\'';
    $Tab = $PDOdb->ExecuteAsArray($sql);
    if(!empty($Tab)) return $Tab[0]->rowid;

    return 0;
}
