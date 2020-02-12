<?php
/*
 * Script permettant de copier les confs de l'entité 1 dans l'entité 0 suite à la MDV en 10.0
 * car les confs sont load avec la condition "entity in (0, $conf->entity)" au lieu de "entity in (0, 1, $conf->entity)"
 */

require '../../config.php';

$commit = GETPOST('commit', 'int');
$debug = GETPOST('debug', 'int');
$limit = GETPOST('limit', 'int');

$subquery = 'SELECT name';
$subquery.= ' FROM '.MAIN_DB_PREFIX.'const';
$subquery.= " WHERE name like 'financement_%'";
$subquery.= ' GROUP BY name';
$subquery.= ' HAVING LEFT(GROUP_CONCAT(entity), 1) = 1';    // Toutes les confs qui ne sont pas définies dans en entité 0 et définies dans l'entité 1

$sql = 'SELECT rowid, name';
$sql.= ' FROM '.MAIN_DB_PREFIX.'const';
$sql.= ' WHERE name IN ('.$subquery.')';
$sql.= ' AND entity = 1';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$nbRow = $db->num_rows($resql);
print 'Nb const : '.$nbRow.'</br>';

if(empty($commit)) exit;

while($obj = $db->fetch_object($resql)) {
    if(! empty($debug)) var_dump($obj);

    $db->begin();

    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'const(name, entity, value, type, visible, note)';
    $sql.= ' SELECT name, 0, value, type, visible, note';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'const';
    $sql.= ' WHERE rowid = '.$obj->rowid;

    $res = $db->query($sql);
    if(! $res) $db->rollback();
    else $db->commit();

    $db->free($res);
}
$db->free($resql);

print 'OK';