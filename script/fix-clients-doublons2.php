<?php

require('../config.php');

@set_time_limit(0);					// No timeout for this script

$PDOdb=new TPDOdb();

$TExclude = array(
    '025607'
    ,'025666'
    ,'025689'
    ,'025724'
    ,'025811'
    ,'025953'
    ,'025964'
    ,'030639'
    ,'031079'
    ,'034120'
    ,'036576'
);

// Pour COPEM les clients sont en double dans COPEM et dans IMPRESSION
// On récupère les clients COPEM et on fusionne ceux d'impression dedans
$sql = 'SELECT rowid, code_client FROM '.MAIN_DB_PREFIX.'societe WHERE entity = 6 AND code_client IS NOT NULL';
$TRes = TRequeteCore::get_keyval_by_sql($PDOdb, $sql, 'rowid', 'code_client');
//$TRes = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX.'societe', array('entity' => 6), 'code_client');
echo count($TRes).'<hr>';
$res = 0;
foreach($TRes as $idClient => $codeClient) {
    echo $idClient.' => '.$codeClient.'<hr>';
    if(in_array($codeClient, $TExclude)) {
        echo 'EXCLUDE<hr>';
        continue;
    }
    $sql = 'SELECT rowid, code_client FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN (1,2,3,10) AND code_client = \''.$codeClient.'\'';
    $TRes = TRequeteCore::_get_id_by_sql($PDOdb, $sql, 'rowid');
    $res += fusion_client_financement($PDOdb, $idClient, $TRes[0], true);

    if($res > 5) break;
}
echo $res;

function fusion_client_financement(&$PDOdb, $idClient, $idDoublon, $real=false) {
    global $db;

    if(empty($idClient) || empty($idDoublon)) return 0;

    $tables = array(
        MAIN_DB_PREFIX.'fin_simulation'
        ,MAIN_DB_PREFIX.'fin_affaire'
        ,MAIN_DB_PREFIX.'asset'
        ,MAIN_DB_PREFIX.'fin_score'
        ,MAIN_DB_PREFIX.'societe_commerciaux'
    );

    echo $idClient . ' => '.$idDoublon;
    echo '<hr>';

    foreach ($tables as $table) {
        $sql = 'UPDATE '.$table;
        $sql.= ' SET fk_soc = '.$idClient;
        $sql.= ' WHERE fk_soc = '.$idDoublon;

        if($real) $PDOdb->Execute($sql);
        else echo $sql . '<hr>';
    }

    if($real) {
        $dbl = new Societe($db);
        $dbl->id = $idDoublon;
        $dbl->delete($dbl->id);
    }
    echo '<br>';

    return 1;
}