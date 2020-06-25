<?php

require '../config.php';
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

use MathPHP\Finance as Finance;

set_time_limit(0);

$action = GETPOST('action', 'alpha');

$PDOdb = new TPDOdb;

llxHeader();

if($action == 'import' && substr($_FILES['fileToImport']['name'], -4) === '.csv' && ! empty($user->rights->financement->alldossier->solde)) {
    $TData = $TError = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData[] = getUsefulData($TLine);
        }
    }

    $upd = updateDossierSolde($PDOdb, $TData, $TError);

    setEventMessage($upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier');
    ?>
    <script type="text/javascript">
        $(document).ready(function() {
            $('div#retours').append('<p>Fichier "<?php echo $_FILES['fileToImport']['name']; ?>" : <?php echo $upd.'/'.count($TData); ?></p>');
            <?php
                if(! empty($TError)) {
                    print "$('div#retours').append('<p>Dossiers non modifiés :</p>');";
                    foreach($TError as $data) print "$('div#retours').append('<p>Ref : ".$data['reference']." ; Entité : ".$data['entity']."</p>');";
                }
            ?>
        });
    </script>
    <?php
}
elseif(! empty($action) || empty($user->rights->financement->alldossier->solde)) {
    setEventMessage('AnErrorOccured', 'errors');
}

?>
<h3>Solder des dossiers</h3>
<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="import" />
    <table>
        <tr>
            <td style="width: 130px;"><span>Fichier CSV : </span></td>
            <td><input type="file" name="fileToImport" /></td>
        </tr>
    </table>
    <br/><br/>
    <input class="butAction" type="submit" name="submit" value="Importer" />
    <input class="butActionDelete" type="reset" name="reset" value="Annuler" />
</form>
<br/>
<div id="retours">
</div>

<?php

llxFooter();

function getUsefulData($TLine) {
    $TIndex = array(
        'reference' => 0,
        'entity' => 1
    );

    $reference = trim($TLine[$TIndex['reference']]);
    $entity = trim($TLine[$TIndex['entity']]);

    return array(
        'reference' => $reference,
        'entity' => $entity
    );
}

/**
 * @param   TPDOdb $PDOdb
 * @param   array  $TData
 * @param   array  $TError
 * @return  int
 */
function updateDossierSolde(TPDOdb $PDOdb, $TData, &$TError) {
    global $db;

    $nbUpdated = 0;
//    $TData = array(array('reference' => '285114-H0', 'entity' => 3));

    foreach($TData as $data) {

        // On load les dossiers
        $sql = 'SELECT d.rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier d';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)';
        $sql.= ' WHERE d.entity IN ('.$db->escape($data['entity']).')';
        $sql.= " AND df.reference LIKE '".$db->escape($data['reference'])."%'"; // On veut les dossiers qui commencent par la référence fournie

        $resql = $db->query($sql);
        if(! $resql) dol_print_error($db);
        else {
            while($obj = $db->fetch_object($resql)) {
                $d = new TFin_dossier;
                $d->load($PDOdb, $obj->rowid, false);
                if(empty($d->rowid)) {  // Failed to load
                    $TError[] = $data;
                    continue;
                }
                if(! empty($d->financementLeaser->montant_solde) && $d->financementLeaser->date_solde > strtotime('1970-01-01')) continue;  // On ignore les dossiers déjà soldés
                $d->load_affaire($PDOdb);

                // On duplique le contrat
                $newDossier = new TFin_dossier;
                $newDossier->load($PDOdb, $d->rowid, false);
                $newDossier->load_affaire($PDOdb);

                // On unset les id pour en créer des nouveaux
                unset($newDossier->id, $newDossier->rowid);
                unset($newDossier->financement->id, $newDossier->financement->rowid, $newDossier->financement->reference);
                unset($newDossier->financementLeaser->id, $newDossier->financementLeaser->rowid, $newDossier->financementLeaser->reference);

                $newDossier->save($PDOdb);  // Saving to get Id

                // Update references
                $newDossier->financement->reference = $d->financement->reference.'-COVID';
                $newDossier->financementLeaser->reference = $d->financementLeaser->reference.'-COVID';

                // On solde le dossier
                $newDossier->financement->montant_solde = $newDossier->financement->reste;
                $newDossier->financement->date_solde = strtotime('2020-03-31');
                $newDossier->financementLeaser->montant_solde = $newDossier->financementLeaser->reste;
                $newDossier->financementLeaser->date_solde = strtotime('2020-03-31');

                $newDossier->save($PDOdb);

                // On garde les même affaires
                $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_dossier_affaire(fk_fin_dossier, fk_fin_affaire)';
                $sql.= ' SELECT '.$newDossier->rowid.', fk_fin_affaire';
                $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_affaire';
                $sql.= ' WHERE fk_fin_affaire = '.$d->TLien[0]->fk_fin_affaire;

                $resql2 = $db->query($sql);
                if(! $resql2) {
                    dol_print_error($db);
                }
                $db->free($resql2);

                // On déplace les factures fournisseurs vers le nouveau dossier
                $sql = 'UPDATE '.MAIN_DB_PREFIX.'element_element';
                $sql.= ' SET fk_source = '.$newDossier->rowid;
                $sql.= " WHERE sourcetype = 'dossier'";
                $sql.= ' AND fk_source = '.$d->rowid;
                $sql.= " AND targettype = 'invoice_supplier'";

                $resql3 = $db->query($sql);
                if(! $resql3) {
                    dol_print_error($db);
                }
                $db->free($resql3);

                // On recalcule l'échéance avec la nouvelle durée
                /** @var TFin_financement $f */
                $f = $d->financementLeaser;
                $beginning = ($f->terme == 1);
                $dureeRestante = ($f->duree_restante == 0) ? 1 : $f->duree_restante;    // Si le dossier se termine sur l'échéance non payée, il reste donc une période à payer

                $e = $d->echeancier($PDOdb, 'LEASER', 1, true, false);

                // Permet de retrouver le bon numéro de période pour recalculer le CRD
                $periods = null;
                foreach($e['ligne'] as $iPeriode => $lineData) {
                    if($lineData['date'] == '01/01/2020') {
                        $periods = $iPeriode + 1;
                    }
                }

                $montant = $f->valeur_actuelle($f->duree-$periods); // On recalcule le CRD du 31/03/2020
                $echeance = Finance::pmt($f->taux / (12 / $f->getiPeriode())/100, $dureeRestante, -$montant, $f->reste, $beginning);    // Calcul de la nouvelle échéance avec le nouveau montant financé

                $d->financementLeaser->duree = $f->duree_restante;
                $d->financementLeaser->montant = $montant;
                $d->financementLeaser->echeance = $echeance;
                $d->financementLeaser->date_debut = strtotime('2020-07-01');
                $d->financementLeaser->setEcheanceExterne($d->financementLeaser->date_debut);
                unset($f);

                /** @var TFin_financement $f */
                $f = $d->financement;
                $d->financement->reste += $f->echeance;

                $d->save($PDOdb);
                $nbUpdated++;
            }
        }


    }

    return $nbUpdated;
}

