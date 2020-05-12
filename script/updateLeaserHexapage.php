<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$TLeaser = array(
    204904 => 3382,     // BNP
    204905 => 51443,    // Franfinance
    204906 => 4440      // Grenke
);

foreach($TLeaser as $fkHexaLeaser => $fkLeaser) {
    $db->begin();

    $sql = 'UPDATE llx_fin_dossier_financement';
    $sql.= ' SET fk_soc = '.$fkLeaser;
    $sql.= ' WHERE fk_soc = '.$fkHexaLeaser;
    $sql.= " and type = 'LEASER'";

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        $db->rollback();
    }
    else $db->commit();
}
