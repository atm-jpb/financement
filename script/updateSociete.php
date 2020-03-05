<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

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
            break;
        }
    }

    updateDossier($TData);
    fclose($f);

    setEventMessage(($i-1 - count($TError)).' mise à jour effectuées sur '.($i-1).' lignes dans le fichier', 'warnings');
}
elseif(! empty($action)) {
    setEventMessage('AnErrorOccured', 'errors');
}

?>
<h3>Mise à jour des Tiers Equinoxe</h3>
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
        'fk_soc' => 1
    );

    return $TLine[$TIndex['fk_soc']];
}

/**
 * @param   array     $TData
 * @return  bool
 */
function updateDossier($TData) {
    global $db, $user;

    $TData = array_unique($TData);

    foreach($TData as $fk_soc) {
        var_dump($fk_soc);
        $db->begin();

        $soc = new Societe($db);
        $res = $soc->fetch($fk_soc);
        if($res > 0) {
            $TComm = $soc->getSalesRepresentatives($user, 1);
            unset($soc->id, $soc->rowid);

            $soc->entity = 27;
            $lastInsertId = $soc->create($user);
            if($lastInsertId <= 0) {
                dol_print_error($db);
                $db->rollback();
            }
            else {
                foreach($TComm as $fk_commercial) $soc->add_commercial($user, $fk_commercial);
                $db->commit();
            }
        }
        var_dump($lastInsertId);
    }

    return true;
}
