<?php

require '../config.php';

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
            $Tmp = getUsefulData($TLine);
            if(empty($TData[$Tmp['siren']])) $TData[$Tmp['siren']] = $Tmp;
            else if(! in_array($Tmp['code_client'], $TData[$Tmp['siren']]['code_client'])) $TData[$Tmp['siren']]['code_client'][] = array_shift($Tmp['code_client']);
        }
    }

    $upd = updateDossierSolde($TData);

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
        'siren' => 0,
        'siret' => 1,
        'code_client' => 2,
        'entity' => 3
    );

    $siren = trim($TLine[$TIndex['siren']]);
    $siret = trim($TLine[$TIndex['siret']]);
    $code_client = trim($TLine[$TIndex['code_client']]);
    $entity = trim($TLine[$TIndex['entity']]);

    return array(
        'siren' => $siren,
        'siret' => $siret,
        'code_client' => array($code_client),
        'entity' => $entity
    );
}

/**
 * @param   array     $TData
 * @return  int
 */
function updateDossierSolde($TData) {
    global $db;
    $nbUpdated = 0;

    foreach($TData as $v) {
        $v['code_client'] = array_unique($v['code_client']);

        // Societe customer code
        $sql = 'SELECT s.rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'societe s';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields se ON (se.fk_object = s.rowid)';
        $sql.= ' WHERE s.entity = '.$db->escape($v['entity']);
        $sql.= " AND (s.siren = '' OR s.siren is null)";

        $str = "(s.code_client = '???' OR se.other_customer_code is not null AND ((locate(';???', se.other_customer_code) > 0 OR locate('???;', se.other_customer_code)) OR locate(';', se.other_customer_code) = 0 AND locate('???', se.other_customer_code) > 0))";
        foreach($v['code_client'] as $k => $cc) $v['code_client'][$k] = str_replace('???', $cc, $str);

        $sql.= ' AND ('.implode(' OR ', $v['code_client']).')';

        $resql = $db->query($sql);
        if($resql) {
            while($obj = $db->fetch_object($resql)) {
                $db->begin();
                $sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX."societe SET siren = '".$db->escape($v['siren'])."', siret = '".$db->escape($v['siret'])."' WHERE rowid = ".$db->escape($obj->rowid);
                $res = $db->query($sqlUpdate);
                if($res && false) {
                    $db->commit();
                    $nbUpdated++;
                }
                else $db->rollback();
            }
        }

        $db->free($resql);
    }

    return $nbUpdated;
}

