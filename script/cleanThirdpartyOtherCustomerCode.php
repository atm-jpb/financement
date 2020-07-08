<?php

require '../config.php';

$fk_soc = GETPOST('fk_soc', 'int');
$debug = GETPOST('debug', 'int');
$commit = GETPOST('commit', 'int');

$sql = 'SELECT fk_object, other_customer_code';
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe_extrafields';
$sql.= ' WHERE other_customer_code is not null';
if(! empty($fk_soc)) $sql.= ' AND fk_object = '.$db->escape($fk_soc);

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$nbRow = $db->num_rows($resql);
$nbSetToNull = $NbUpdated = 0;

while($obj = $db->fetch_object($resql)) {
    if(! empty($debug)) {
        print '<pre>';
        var_dump($obj);
        print '</pre>';
    }
    // Ces valeurs n'aurait jamais du se mettre dans les autres codes clients
    $TWrongCustomerCode = array(
        '0',
//        '30',
//        '31',
//        '32'
    );
    if(strpos($obj->other_customer_code, ';') === false && in_array($obj->other_customer_code, $TWrongCustomerCode)) {
        $db->begin();

        $sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX.'societe_extrafields SET other_customer_code = null WHERE fk_object = '.$obj->fk_object;

        $resUpdate = $db->query($sqlUpdate);
        if(! $resUpdate || empty($commit)) $db->rollback();
        else {
            $db->commit();
            $nbSetToNull++;
        }
    }
    else {
        $db->begin();
        $TExistingCustomerCode = array_unique(array_filter(explode(';', $obj->other_customer_code)));
        $strCustomerCode = implode(';', $TExistingCustomerCode);

        if($strCustomerCode != $obj->other_customer_code) {
            $sqlUpdate = 'UPDATE '.MAIN_DB_PREFIX."societe_extrafields SET other_customer_code = '".$db->escape($strCustomerCode)."' WHERE fk_object = ".$obj->fk_object;

            $resUpdate = $db->query($sqlUpdate);
            if(! $resUpdate || empty($commit)) $db->rollback();
            else {
                $db->commit();
                $NbUpdated++;
            }
        }
    }
}

?>
<span>Total : <?php echo $nbRow; ?></span><br/>
<span>Nb update : <?php echo $NbUpdated; ?></span><br/>
<span>Nb set to null : <?php echo $nbSetToNull; ?></span>
