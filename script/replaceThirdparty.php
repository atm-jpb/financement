<?php

require '../config.php';
$entity = GETPOST('entity', 'int');

$sql = "SELECT siren, group_concat(concat(rowid, '-', code_client)) as data";
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
    $max = 0;   // Réprésente le rowid de la société à garder

    foreach($TData as $k => &$v) {
        $v = explode('-', $v);
        if($v[0] > $max) $max = $v[0];
    }
    $s = new Societe($db);
    $s->fetch($max);

    $TCustomerCode = array();
    foreach($TData as $data) {
        if($data[0] != $max) $TCustomerCode[] = $data[1];
    }

    if(empty($s->array_options['options_other_customer_code'])) $s->array_options['options_other_customer_code'] = implode(';', $TCustomerCode);
    else $s->array_options['options_other_customer_code'] .= ';'.implode(';', $TCustomerCode);

    $s->updateExtraField('other_customer_code');
}