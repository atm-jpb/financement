<?php

ini_set('max_execution_time', 0);
require_once '../config.php';

$commit = GETPOST('commit', 'int');
$limit = GETPOST('limit', 'int');
$fk_affaire = GETPOST('fk_affaire', 'int');
$fk_dossier = GETPOST('fk_dossier', 'int');

$sql = 'SELECT fk_fin_affaire, fk_fin_dossier, count(*) as nb';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_affaire';
$sql.= ' WHERE fk_fin_affaire IS NOT NULL';
$sql.= ' AND fk_fin_dossier IS NOT NULL';
if(! empty($fk_affaire)) $sql.= ' AND fk_fin_affaire = '.$db->escape($fk_affaire);
if(! empty($fk_dossier)) $sql.= ' AND fk_fin_dossier = '.$db->escape($fk_dossier);
$sql.= ' GROUP BY fk_fin_affaire, fk_fin_dossier';
$sql.= ' HAVING nb > 1';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;
//var_dump($sql);exit;
$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}
print '<span>Ready ?</span><br/>';
if(empty($commit)) exit;

while($obj = $db->fetch_object($resql)) {
    $error = 0;

    // On supprime tous les liens
    $sql_delete = 'DELETE FROM '.MAIN_DB_PREFIX.'fin_dossier_affaire WHERE fk_fin_affaire = '.$db->escape($obj->fk_fin_affaire).' AND fk_fin_dossier = '.$db->escape($obj->fk_fin_dossier).';';
    $res_delete = $db->query($sql_delete);
    if(! $res_delete) $error++;

    // On en insére juste un seul
    $sqlInsert = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_dossier_affaire(fk_fin_affaire, fk_fin_dossier) VALUES('.$db->escape($obj->fk_fin_affaire).', '.$db->escape($obj->fk_fin_dossier).')';
    $resInsert = $db->query($sqlInsert);
    if(! $resInsert) $error++;
}
$db->free($resql);
?>
<span>END</span>
