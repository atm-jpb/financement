<?php

require '../config.php';
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

set_time_limit(0);

$action = GETPOST('action', 'alpha');

$PDOdb = new TPDOdb;

llxHeader();

if($action == 'import' && substr($_FILES['fileToImport']['name'], -4) === '.csv') {
    $TData = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData[] = getUsefulData($TLine);
        }
    }

    $upd = process($TData);

    setEventMessage($upd.' mise à jour effectuées sur '.count($TData).' lignes dans le fichier');
    ?>
    <script type="text/javascript">
        $(document).ready(function() {
            $('div#retours').append('<p>Fichier "<?php echo $_FILES['fileToImport']['name']; ?>" : <?php echo $upd.'/'.count($TData); ?></p>');
        });
    </script>
    <?php
}
elseif(! empty($action)) {
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
        'name' => 0,
        'customerCode' => 2,
        'siret' => 3,
        'siren' => 4,
    );

    $TRes = array();
    foreach($TIndex as $k => $v) $TRes[$k] = trim($TLine[$v]);

    return $TRes;
}

/**
 * @param   array     $TData
 * @return  int
 */
function process($TData) {
    global $db;

    $nbUpdated = 0;

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= " WHERE code_client = '".$db->escape($TData['customerCode'])."'";
//    $sql.= " AND siren = '".$db->escape($TData['siren'])."'";
    $sql.= " AND siret = '".$db->escape($TData['siret'])."'";
    $sql.= " AND nom LIKE '".$db->escape($TData['name'])."'";

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    $nbRow = $db->num_rows($resql);
    if($nbRow != 1) {
        var_dump($nbRow);
        exit('Please help !');
    }

    if($obj = $db->fetch_object($resql)) {
        $soc = new Societe($db);
        $soc->fetch($obj->rowid);

        $soc->delete($soc->id);
    }
    $db->free($resql);

    return $nbUpdated;
}

