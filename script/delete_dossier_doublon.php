<?php
/**
 * Script permettant de supprimer les dossiers de l'entité TELECOM,
 * étant en doublons avec ceux de l'entité TDP
 */

require_once '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$debug = GETPOST('debug', 'int');
$limit = GETPOST('limit', 'int');
$commit = GETPOST('commit', 'int');

// On va chercher toutes les références de dossiers en doublons dans les entités 20, 21, 22 et 24
$subquery = 'SELECT df.reference';
$subquery.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
$subquery.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (d.rowid = df.fk_fin_dossier)';
$subquery.= ' WHERE d.entity IN (20,21,22,24)';
$subquery.= " AND df.type = 'LEASER'";
$subquery.= " AND df.reference <> ''";
$subquery.= ' GROUP BY df.reference';
$subquery.= ' HAVING COUNT(*) > 1';

// On récupère que les 'fk_fin_dossier' des dossiers des entités 21, 22 et 24 pour les delete
$sql = 'SELECT df.fk_fin_dossier';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (d.rowid = df.fk_fin_dossier)';
$sql.= ' WHERE d.entity <> 20';
$sql.= ' AND df.reference IN ('.$subquery.')';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$PDOdb = new TPDOdb;
$nb = $db->num_rows($resql);
print '<span>Nb to be deleted : '.$nb.'</span>';

if(empty($commit)) exit;    // Petite sécurité

while($obj = $db->fetch_object($resql)) {
    if(! empty($debug)) {
        var_dump($obj);
        print '<br/>';
    }

    $dossier = new TFin_dossier;
    $dossier->load($PDOdb, $obj->fk_fin_dossier);

    $dossier->delete($PDOdb, true, false, false);
}
$db->free($resql);
