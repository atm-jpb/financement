<?php

require('../../config.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$action = GETPOST('action', 'alpha');
$fk_leaser = GETPOST('fk_leaser', 'int');
$entity = GETPOST('entity', 'int');

$form = new Form($db);
$PDOdb = new TPDOdb;
$dao = new DaoMulticompany($db);
$dao->getEntities();
$TEntity = array(0 => '');
foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

$THexaLeaser = getHexaLeaser();

llxHeader();

if($action == 'import' && ! empty($fk_leaser) && in_array($fk_leaser, array_keys($THexaLeaser)) && substr($_FILES['fileToImport']['name'], -4) === '.csv' && ! empty($entity)) {
    $TData = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData[] = getUsefulData($PDOdb, $TLine, $fk_leaser);
        }
    }
//    unset($TData[0]);   // Unset header line

    $upd = 0;
    foreach($TData as $datadoss) {
        $upd += updateDossier($PDOdb, $datadoss, $entity);
        $upd += updateDossier($PDOdb, $datadoss, $entity, 'CLIENT');    // On est obligé de faire ça sinon la prochaine échéance n'est pas bonne
    }

    setEventMessage($upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier');
//    echo '<hr>'.$upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier';
}
elseif(! empty($action)) {
    setEventMessage('AnErrorOccured', 'errors');
}

?>
<h3>Import Hexapage</h3>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="import" />
    <table>
        <tr>
            <td style="width: 130px;"><span>Fichier CSV : </span></td>
            <td><input type="file" name="fileToImport" /></td>
        </tr>
        <tr>
            <td><span>Leaser Hexapage : </span></td>
            <td>
                <?php
                print Form::selectarray('fk_leaser', $THexaLeaser, $fk_leaser, 1, 0, 0, 'style="width: 400px;"');
                ?>
            </td>
        </tr>
        <tr>
            <td><span>Entité : </span></td>
            <td>
                <?php
                print Form::selectarray('entity', $TEntity, $entity, 0, 0, 0, 'style="width: 175px;"');
                ?>
            </td>
        </tr>
    </table>
    <br/><br/>
    <input class="butAction" type="submit" name="submit" value="Importer" />
    <input class="butAction" type="reset" name="reset" value="Annuler" />
</form>
<br/>
<div id="retours">
</div>

<?php

llxFooter();

function getUsefulData(&$PDOdb, $TLine, $fk_leaser) {
    $TIndex = array(
        'siren_client' => 0,
        'ref_client' => 1,
        'date_debut' => 2,
        'montant_client' => 3,
        'duree' => 4,
        'terme' => 5,
        'echeance' => 6,
        'loyer_intercalaire' => 7,
        'reste' => 8,
        'montant_prestation' => 9,
        'periodicite' => 10,
        'incident_paiement' => 11,
        'assurance' => 13,
        'matricule' => 14,
        'ref_leaser' => 19,
        'ref_contrat_client' => 20,
        'montant_finance_leaser' => 21
    );

    // Format date
    $TDateDebut = explode('/', $TLine[$TIndex['date_debut']]);
    $date_debut = mktime(null, null, null, $TDateDebut[1], $TDateDebut[0], $TDateDebut[2]);

    $iPeriode = getiPeriode($TLine[$TIndex['periodicite']]);

    $TData = array(
        'financement' => array(
            'fk_soc' => getSocieteBySIREN($PDOdb, $TLine[$TIndex['siren_client']]),
            'reference' => $TLine[$TIndex['ref_client']],
            'date_debut' => $date_debut,
            'montant' => str_replace(' ', '', $TLine[$TIndex['montant_client']]),
            'duree' => intval($TLine[$TIndex['duree']] / $iPeriode),
            'terme' => $TLine[$TIndex['terme']],
            'echeance' => $TLine[$TIndex['echeance']],
            'loyer_intercalaire' => 0,
            'reste' => $TLine[$TIndex['reste']],    // Valeur résiduelle
            'montant_prestation' => $TLine[$TIndex['montant_prestation']],
            'periodicite' => $TLine[$TIndex['periodicite']],
            'incident_paiement' => $TLine[$TIndex['incident_paiement']],
            'assurance' => $TLine[$TIndex['assurance']],
        ),
        'financementLeaser' => array(
            'reference' => $TLine[$TIndex['ref_leaser']],
            'montant' => price2num(str_replace(' ', '', $TLine[$TIndex['montant_finance_leaser']])),
            'fk_soc' => $fk_leaser
        )
    );

    return $TData;
}

// Chargement du dossier, modification pour passer en interne + remplir les données
function updateDossier(&$PDOdb, $data, $entity, $type = 'LEASER') {
    if(empty($data['financement']['reference'])) {
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $('div#retours').append('<p><?php echo $data['financement']['reference']; ?> - Référence vide</p>');
            });
        </script>
        <?php
//        print '<script type="text/javascript"> $(document).ready(function() { $("div#retours").append("<p>'.$data['financement']['reference'].' - Référence vide</p>"); }); </script>';
//        echo $data['financement']['reference'].' - Référence vide';
        return 0;
    }

    $fin = new TFin_financement();
    if($fin->loadReference($PDOdb, $data['financement']['reference'], $type, $entity) > 0) {
        $doss = new TFin_dossier();
        $doss->load($PDOdb, $fin->fk_fin_dossier, false, true);

        if($doss->entity != $entity) {
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    $('div#retours').append('<p><?php echo $data['financement']['reference']; ?> - Dossier trouvé mais pas dans la bonne entité</p>');
                });
            </script>
            <?php
//            print '<script type="text/javascript"> $(document).ready(function() { $("div#retours").append("<p>'.$data['financement']['reference'].' - Dossier trouvé mais pas dans la bonne entité<br></p>"); }); </script>';
//            echo $data['financement']['reference'].' - Dossier trouvé mais pas dans la bonne entité<br>';
            return 0;       // Si on est pas sur la bonne entité, on a rien à faire ici !
        }

        $doss->load_affaire($PDOdb);
        $doss->TLien[0]->affaire->nature_financement = 'INTERNE';
        $doss->TLien[0]->affaire->save($PDOdb);

        $doss->nature_financement = 'INTERNE';

        foreach($data['financement'] as $field => $value) $doss->financement->$field = $value;

        $doss->financementLeaser->date_debut = $doss->financement->date_debut;
        $doss->financementLeaser->date_fin = $doss->financement->date_fin;
        $doss->financementLeaser->duree = $doss->financement->duree;
        $doss->financementLeaser->date_prochaine_echeance = $doss->financement->date_prochaine_echeance;
        $doss->financementLeaser->numero_prochaine_echeance = $doss->financement->numero_prochaine_echeance;
        $doss->financementLeaser->terme = $doss->financement->terme;
        $doss->financementLeaser->montant_prestation = $doss->financement->montant_prestation;
        $doss->financementLeaser->echeance = $doss->financement->echeance;
        $doss->financementLeaser->loyer_intercalaire = $doss->financement->loyer_intercalaire;
        $doss->financementLeaser->reste = $doss->financement->reste;
        $doss->financementLeaser->assurance = $doss->financement->assurance;
        $doss->financementLeaser->periodicite = $doss->financement->periodicite;
        $doss->financementLeaser->reglement = $doss->financement->reglement;
        $doss->financementLeaser->penalite_reprise = $doss->financement->penalite_reprise;
        $doss->financementLeaser->taux_commission = $doss->financement->taux_commission;
        $doss->financementLeaser->frais_dossier = $doss->financement->frais_dossier;
        $doss->financementLeaser->loyer_actualise = $doss->financement->loyer_actualise;
        $doss->financementLeaser->assurance_actualise = $doss->financement->assurance_actualise;
        $doss->financementLeaser->incident_paiement = $doss->financement->incident_paiement;
        $doss->financementLeaser->reloc = $doss->financement->reloc;

        // Override values
        foreach($data['financementLeaser'] as $field => $value) $doss->financementLeaser->$field = $value;

        // Modification Leaser
        $doss->financementLeaser->fk_soc = $data['financementLeaser']['fk_soc'];
        $doss->financement->fk_soc = $data['financement']['fk_soc'];
        unset($doss->financementLeaser->date_solde);

        $doss->save($PDOdb);

        $doss->financement->setProchaineEcheanceClient($PDOdb, $doss);
        $doss->financementLeaser->setEcheanceExterne();

        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $('div#retours').append('<p><?php echo $data['financement']['reference']; ?> - Dossier mis à jour</p>');
            });
        </script>
        <?php
//        echo $data['financement']['reference'].' - Dossier mis à jour<br>';
        return 1;
    }
    else {
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $('div#retours').append('<p><?php echo $data['financement']['reference']; ?> - Dossier leaser non trouvé</p>');
            });
        </script>
        <?php
//        echo $data['financement']['reference'].' - Dossier leaser non trouvé<br>';
        return 0;
    }
}

// Recherche du client par SIREN
function getSocieteBySIREN(&$PDOdb, $siren) {
    if(empty($siren)) return 0;

    $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN ('.getEntity('societe', true).') AND siren = \''.substr($siren, 0,9).'\'';
    $Tab = $PDOdb->ExecuteAsArray($sql);
    if(!empty($Tab)) return $Tab[0]->rowid;

    return 0;
}

function getiPeriode($periodicite = 'TRIMESTRE') {
    if($periodicite == 'TRIMESTRE') $iPeriode = 3;
    else if($periodicite == 'SEMESTRE') $iPeriode = 6;
    else if($periodicite == 'ANNEE') $iPeriode = 12;
    else $iPeriode = 1;

    return $iPeriode;
}

function getHexaLeaser() {
    global $db;

    $TRes = array();

    $sql = 'SELECT rowid, nom';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= ' WHERE fournisseur = 1';
    $sql.= ' AND status = 1';
    $sql.= " AND nom LIKE 'hexapage%'";

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    while($obj = $db->fetch_object($resql)) {
        $TRes[$obj->rowid] = $obj->nom;
    }

    return $TRes;
}
