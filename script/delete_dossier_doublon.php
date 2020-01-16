<?php
/**
 * Script permettant de supprimer les dossiers de l'entité TELECOM,
 * étant en doublons avec ceux de l'entité TDP
 */

require_once '../config.php';
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossierRachete.class.php');

$debug = GETPOST('debug', 'int');
$limit = GETPOST('limit', 'int');
$commit = GETPOST('commit', 'int');

// On va chercher toutes les références de dossiers en doublons dans les entités 20, 21, 22 et 24
$sql = 'SELECT GROUP_CONCAT(DISTINCT fk_fin_dossier) as TRowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (d.rowid = df.fk_fin_dossier)';
$sql.= ' WHERE d.entity IN (20,21,22,24)';
$sql.= " AND df.type = 'LEASER'";
$sql.= " AND df.reference <> ''";
$sql.= ' GROUP BY df.reference';
$sql.= ' HAVING COUNT(*) > 1';
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

    $TRowid = explode(',', $obj->TRowid);

    foreach($TRowid as $fk_fin_dossier) {
        $dossier = new TFin_dossier;
        $dossier->load($PDOdb, $obj->fk_fin_dossier, true, false);

        // On ne veut supprimer les dossiers uniquement s'ils ne sont pas sur l'entité 20 et s'ils ne sont pas sélectionnés dans une simul
        if($dossier->entity != 20 && ! DossierRachete::isDossierSelected($dossier->rowid)) {
            $dossier->delete($PDOdb, true, false, false);
        }
    }
}
$db->free($resql);

print '<br/><br/>';
print '<span>OK !</span>';
