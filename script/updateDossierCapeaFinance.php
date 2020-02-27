<?php

require '../config.php';
//dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
//dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$action = GETPOST('action', 'alpha');

$PDOdb = new TPDOdb;

llxHeader();

if($action == 'import' && substr($_FILES['fileToImport']['name'], -4) === '.csv') {
    $TError = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData = getUsefulData($TLine);
            $res = updateDossier($PDOdb, $TData);
            if($res === false) $TError[] = $TData['ref_contrat'];
        }
    }
    fclose($f);

    setEventMessage(($i-1 - count($TError)).' mise à jour effectuées sur '.($i-1).' lignes dans le fichier', 'warnings');

    if(! empty($TError)) {
        ?>
        <script type="text/javascript">
            $(document).ready(function () {
                $('div#retours').append('<p><?php echo implode('<br/>', $TError); ?></p>');
            });
        </script>
        <?php
    }
}
elseif(! empty($action)) {
    setEventMessage('AnErrorOccured', 'errors');
}

?>
<h3>Mise à jour des dossiers CAPEA Finance</h3>
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

/**
 * @param   array   $TLine
 * @return  array
 */
function getUsefulData($TLine) {
    $TIndex = array(
        'ref_contrat' => 1,
        'ref_contrat_leaser' => 2,
        'date_debut' => 4,
        'montant' => 5,
        'duree' => 6,
        'terme' => 8,
        'echeance' => 9,
        'loyer_intercalaire' => 10,
        'reste' => 11,
        'periodicite' => 13,
        'incident' => 15,
        'reloc' => 16,
        'assurance' => 17,
        'entity' => 19,
        'leaserName' => 24
    );

    $reference = trim($TLine[$TIndex['ref_contrat']]);
    $referenceLeaser = trim($TLine[$TIndex['ref_contrat_leaser']]);

    $montant = str_replace(array(',', ' '), array('.', ''), $TLine[$TIndex['montant']]);
    $echeance = str_replace(array(',', ' '), array('.', ''), $TLine[$TIndex['echeance']]);
    $loyerInter = str_replace(array(',', ' '), array('.', ''), $TLine[$TIndex['loyer_intercalaire']]);
    $vr = str_replace(array(',', ' '), array('.', ''), $TLine[$TIndex['reste']]);
    $assurance = str_replace(array(',', ' '), array('.', ''), $TLine[$TIndex['assurance']]);

    // Format date
    $TDateDebut = explode('/', $TLine[$TIndex['date_debut']]);
    $date_debut = mktime(null, null, null, intval($TDateDebut[1]), intval($TDateDebut[0]), intval($TDateDebut[2]));

    $TPeriodicite = array(
        'Mensuelle' => 'MOIS',
        'Trimestrielle' => 'TRIMESTRE',
        'Semestrielle' => 'SEMESTRE',
        'Annuelle' => 'ANNEE'
    );

    $periodicite = $TPeriodicite[$TLine[$TIndex['periodicite']]];
    $fk_leaser = _getLeaserByName($TLine[$TIndex['date_debut']]);

    return array(
        'ref_contrat' => $reference,
        'entity' => $TLine[$TIndex['entity']],
        'financementLeaser' => array(
            'reference' => $referenceLeaser,
            'date_debut' => date('Y-m-d', $date_debut),
            'montant' => $montant,
            'duree' => $TLine[$TIndex['duree']],
            'terme' => $TLine[$TIndex['terme']],
            'echeance' => $echeance,
            'loyer_intercalaire' => $loyerInter,
            'reste' => $vr,
            'periodicite' => $periodicite,
            'incident_paiement' => $TLine[$TIndex['incident']],
            'reloc' => $TLine[$TIndex['reloc']],
            'assurance' => $assurance,
            'fk_soc' => $fk_leaser
        )
    );
}

/**
 * @param   TPDOdb    $PDOdb
 * @param   array     $TData
 * @return  bool
 */
function updateDossier(TPDOdb &$PDOdb, $TData) {
    $d = new TFin_dossier;
    $res = $d->loadReference($PDOdb, $TData['ref_contrat'], false, $TData['entity']);

    if($res === false) return false;

    /**
     * @var TFin_financement $f
     */
    $f = &$d->financementLeaser;

    foreach($TData['financementLeaser'] as $k => $v) $f->$k = $v;

    return true;//$f->save($PDOdb);
}

/**
 * @param   string $leaserName
 * @return  int
 */
function _getLeaserByName($leaserName) {
    global $db;

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= " WHERE nom LIKE '".$db->escape($leaserName)."'";
    $sql.= ' AND fournisseur = 1';

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    if($obj = $db->fetch_object($resql)) return $obj->rowid;

    return 0;
}
