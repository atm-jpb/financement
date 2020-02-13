<?php

set_time_limit(0);

require_once '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossierRachete.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$action = GETPOST('action', 'alpha');

$PDOdb = new TPDOdb;

llxHeader();

if($action == 'import' && substr($_FILES['fileToImport']['name'], -4) === '.csv') {
    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = $nbDeleted = 0;

    while ($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if ($i > 1) {
            $nbDeleted += deleteContrat($PDOdb, $TLine);
        }
    }
    fclose($f);
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

function deleteContrat(TPDOdb &$PDOdb, $TLine) {
    $TIndex = array(
        'ref_contrat' => 1
    );

    $reference = trim($TLine[$TIndex['ref_contrat']]);

    $d = new TFin_dossier;
    $res = $d->loadReference($PDOdb, $reference, false, 1);

    if($res !== false) {
        if(! DossierRachete::isDossierSelected($d->rowid)) {
//            $d->delete($PDOdb, true, false, false);
            return 1;
        }
        else {
            ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    $('div#retours').append('<p><?php echo $reference; ?></p>');
                });
            </script>
            <?php
            return 0;
        }
    }

    return 0;
}