<?php

require '../config.php';
dol_include_once('/multicompany/class/dao_multicompany.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$action = GETPOST('action', 'alpha');
$delimiter = GETPOST('delimiter');

$PDOdb = new TPDOdb;

llxHeader();

if($action == 'import' && substr($_FILES['fileToImport']['name'], -4) === '.csv' && ! empty($user->rights->financement->alldossier->solde)) {
    $TData = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, $delimiter, '"')) {
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
        <tr>
            <td><span>Délimiteur : </span></td>
            <td>
                <select name="delimiter">
                    <option value="," selected="selected">Virgule : ","</option>
                    <option value=";">Point-virgule : ";"</option>
                </select>
            </td>
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
        'ref_contrat' => 0,
        'montant' => 1,
        'date' => 2,
    );

    $reference = trim($TLine[$TIndex['ref_contrat']]);

    $montant = $TLine[$TIndex['montant']];
    if(! is_null($montant)) $montant = str_replace(array(',', ' '), array('.', ''), $TLine[$TIndex['montant']]);

    // Format date
    $date_debut = $TLine[$TIndex['date']];
    if(! is_null($date_debut)) {
        $TDateDebut = explode('/', $TLine[$TIndex['date']]);
        $date_debut = mktime(null, null, null, $TDateDebut[1], $TDateDebut[0], $TDateDebut[2]);
        $date_debut = date('Y-m-d', $date_debut);
    }

    return array(
        'ref_contrat' => $reference,
        'montant' => $montant,
        'date' => $date_debut
    );
}

/**
 * @param   TPDOdb    $PDOdb
 * @param   array     $TData
 * @return  int
 */
function updateDossierSolde(TPDOdb &$PDOdb, $TData) {
    $nbUpdated = 0;

    $sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_dossier_financement df';
    $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df2 USING (fk_fin_dossier)';
    $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (df.fk_fin_dossier = d.rowid)';
    $sql.= " SET df2.date_solde = :date, df2.montant_solde = :montant";
    $sql.= " WHERE df.reference = :reference";
    $sql.= ' AND d.entity IN ('.getEntity('fin_dossier').')';

    $stmt = $PDOdb->db->prepare($sql);

    foreach($TData as $data) {
        if(is_null($data['montant']) || is_null($data['date'])) continue;

        $stmt->bindParam(':date', $data['date']);
        $stmt->bindParam(':montant', $data['montant']);
        $stmt->bindParam(':reference', $data['ref_contrat']);

        $res = $stmt->execute();
        $nbUpdated += intval($res);
    }

    return $nbUpdated;
}

