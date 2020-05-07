<?php

$a = microtime(true);

require '../config.php';
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/simulation.class.php');

set_time_limit(0);

$action = GETPOST('action', 'alpha');
$commit = GETPOST('commit');

$PDOdb = new TPDOdb;

llxHeader();

if($action == 'import' && substr($_FILES['fileToImport']['name'], -4) === '.csv') {
    $TData = $TError = array();

    $f = fopen($_FILES['fileToImport']['tmp_name'], 'r');
    $i = 0;
    while($TLine = fgetcsv($f, 2048, ';', '"')) {
        $i++;
        if($i > 1) {
            $TData = getUsefulData($TLine);
            process($TData, $commit, $TError);
        }
    }

    $nbLine = $i-1;
    $nbErr = count($TError);
    $nbUpd = $nbLine - $nbErr;

    $style = 'mesgs';
    if($nbErr > $nbUpd) $style = 'errors';
    else if($nbErr > 0) $style = 'warnings';

    setEventMessage($langs->trans('CSVScriptFileOutput', $nbLine, $nbUpd, $nbErr), $style);
    ?>
    <script type="text/javascript">
        $(document).ready(function() {
            $('div#retours').append('<p>Fichier "<?php echo $_FILES['fileToImport']['name']; ?>" : <?php echo $langs->trans('CSVScriptFileOutput', $nbLine, $nbUpd, $nbErr); ?></p>');
        });
    </script>
    <?php
}
elseif(! empty($action)) {
    setEventMessage('AnErrorOccured', 'errors');
}

?>
    <h3>Suppression de Tiers</h3>
    <form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import" />
        <table>
            <tr>
                <td style="width: 100px;"><span>Fichier CSV : </span></td>
                <td><input type="file" name="fileToImport" /></td>
            </tr>
            <tr>
                <td align="right"><input type="radio" id="forceRollback" name="commit" value="0"/></td>
                <td><label for="forceRollback"> Rollback</label></td>
            </tr>
            <tr>
                <td align="right"><input type="radio" id="commit" name="commit" value="1" /></td>
                <td><label for="commit"> Commit</label></td>
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
$b = microtime(true);
print 'Execution time : '.($b-$a).' s';

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
 * @param array $TData
 * @param int   $commit
 * @param array $TError
 * @return  int
 */
function process($TData, $commit, &$TError) {
    global $db, $i;
    $res = null;
    $customerCode = str_pad($TData['customerCode'], 6, 0, STR_PAD_LEFT);

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
    $sql.= " WHERE code_client = '".$db->escape($customerCode)."'";
//    $sql.= " AND siren = '".$db->escape($TData['siren'])."'";
//    $sql.= " AND siret = '".$db->escape($TData['siret'])."'";
    $sql.= " AND nom LIKE '".$db->escape($TData['name'])."'";

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    $nbRow = $db->num_rows($resql);
    if($nbRow != 1) {
        $TError[] = $i;

        print 'Please help ! : ';
        print $nbRow.' - '.$sql;
        print '<br/>';

        return 0;
    }

    if($obj = $db->fetch_object($resql)) {
        $db->begin();

        $soc = new Societe($db);
        $soc->fetch($obj->rowid);

        $res = $soc->delete($soc->id);
        if($res > 0 && ! empty($commit)) $db->commit();
        else $db->rollback();
    }
    $db->free($resql);

    return $res;
}

