<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$entity = GETPOST('entity', 'int');

$sql = "SELECT siren, group_concat(rowid) as data";
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
$sql.= ' WHERE entity = '.$db->escape($entity);
$sql.= ' AND LENGTH(siren) = 9';
$sql.= " AND siren <> '000000000'";
$sql.= ' GROUP BY siren';
$sql.= ' HAVING count(*) > 1';

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

while($obj = $db->fetch_object($resql)) {
    $TData = explode(',', $obj->data);
    $max = max($TData);   // Réprésente le rowid de la société à garder

    $s = new Societe($db);
    $s->fetch($max);

    foreach($TData as $k => $v) {
        if($v == $max) break;
    }
    unset($TData[$k], $k, $v);

    $TCustomerCode = array();
    foreach($TData as $fkSoc) {
        $soc = new Societe($db);
        $soc->fetch($fkSoc);

        $TCustomerCode[] = $soc->code_client;
    }

    if(empty($s->array_options['options_other_customer_code'])) $s->array_options['options_other_customer_code'] = implode(';', $TCustomerCode);
    else $s->array_options['options_other_customer_code'] .= ';'.implode(';', $TCustomerCode);

    $s->updateExtraField('other_customer_code');
}