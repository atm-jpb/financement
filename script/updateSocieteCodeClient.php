<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('/multicompany/class/dao_multicompany.class.php');
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');

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
        'code_client' => 2,
        'entity' => 3
    );

    $siren = trim($TLine[$TIndex['siren']]);
    $code_client = trim($TLine[$TIndex['code_client']]);
    $entity = trim($TLine[$TIndex['entity']]);

    return array(
        'siren' => $siren,
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
        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
        $sql.= ' WHERE entity = '.$db->escape($v['entity']);
        $sql.= " AND siren = '".$db->escape($v['siren'])."'";

        $resql = $db->query($sql);
        if($resql) {
            if($obj = $db->fetch_object($resql)) {
                $soc = new Societe($db);
                $soc->fetch($obj->rowid);
                $oldCodeClient = $soc->code_client;

                $firstCustomerCode = array_shift($v['code_client']);
                if(empty($soc->code_client)) $soc->code_client = $firstCustomerCode;
                else if($soc->code_client != $firstCustomerCode) {
                    $tmp = $soc->code_client;
                    $soc->code_client = $firstCustomerCode;
                }

                if($soc->code_client != $oldCodeClient) { // Ce test permet de ne pas faire un update pour rien
                    $db->begin();
                    $sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX."societe SET code_client = '".$db->escape($soc->code_client)."' WHERE rowid = ".$db->escape($soc->id);
                    $res = $db->query($sqlUpdate);
                    if($res) {
                        $db->commit();
                        $nbUpdated++;
                    }
                    else $db->rollback();
                }

                if(isset($tmp)) $v['code_client'][] = $tmp;

                if(! empty($v['code_client'])) {
                    $TCustomerCode = array_unique($v['code_client']);
                    updateSocieteOtherCustomerCode($soc, $TCustomerCode);
                }
            }
        }

        unset($tmp, $firstCustomerCode, $oldCodeClient, $TCustomerCode, $TExistingCustomerCode);
        $db->free($resql);
    }

    return $nbUpdated;
}

