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
    $TData = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData[] = getUsefulData($TLine);
        }
    }

    $upd = updateDossierSolde($PDOdb, $TData);

    setEventMessage($upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier');
    ?>
    <script type="text/javascript">
        $(document).ready(function() {
            $('div#retours').append('<p>Fichier "<?php echo $_FILES['fileToImport']['name']; ?>" : <?php echo $upd.'/'.count($TData); ?></p>');
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
 * @return  int
 */
function updateDossierSolde(TPDOdb $PDOdb, $TData) {
    global $db;

    $nbUpdated = 0;
//    $TData = array(array('reference' => '218420-K0/8'));

    foreach($TData as $data) {
        $d = new TFin_dossier;
        $d->loadReference($PDOdb, $data['reference'], $data['entity']);
        if(empty($d->rowid)) continue;  // Failed to load
        $d->load_affaire($PDOdb);

        // On duplique le contrat
        $newDossier = new TFin_dossier;
        $newDossier->load($PDOdb, $d->rowid, false);
        $newDossier->load_affaire($PDOdb);

        // On unset les id pour en créer des nouveaux
        unset($newDossier->id, $newDossier->rowid);
        unset($newDossier->financement->id, $newDossier->financement->rowid);
        unset($newDossier->financementLeaser->id, $newDossier->financementLeaser->rowid);

        // Update references
        $newDossier->financement->reference .= '-COVID';
        $newDossier->financementLeaser->reference .= '-COVID';

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

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
        }

        // On recalcule l'échéance avec la nouvelle durée
        /** @var TFin_financement $f */
        $f = $d->financementLeaser;
        $d->financementLeaser->duree = $f->duree_restante;
        $d->financementLeaser->echeance = Finance::pmt($f->taux / (12 / $f->getiPeriode())/100, $f->duree_restante, $f->montant, $f->reste, true);
        $d->financementLeaser->date_debut = strtotime('2020-07-01');
        unset($f);

        /** @var TFin_financement $f */
        $f = $d->financement;
        $d->financement->reste += $f->echeance;

        $d->save($PDOdb);
    }

    return $nbUpdated;
}

