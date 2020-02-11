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
$sql = "SELECT GROUP_CONCAT(concat(concat(fk_fin_dossier, '-'), d.entity)) as TRowid";
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

$TData = array();
for($i = 0 ; $obj = $db->fetch_object($resql) ; $i++) {
    if(! empty($debug)) {
        var_dump($obj);
        print '<br/>';
    }

    $TData[$i] = array();
    $TRowid = explode(',', $obj->TRowid);

    foreach($TRowid as $rowidEntity) {
        $TRes = explode('-', $rowidEntity);

        $TData[$i][] = array(
            'rowid' => $TRes[0],
            'entity' => $TRes[1],
            'selected' => DossierRachete::isDossierSelected($TRes[0])
        );
    }

    // On place le dossier de l'entité 20 en première position
    usort($TData[$i], function($a, $b) {
        if($a['entity'] > $b['entity']) return 1;
        if($a['entity'] < $b['entity']) return -1;
        return 0;
    });
}
$db->free($resql);

foreach($TData as $TDoublon) {
    // count($TDoublon) vaut toujours 2
    $dossier = new TFin_dossier;

    if(! $TDoublon[1]['selected']) {
        // Cas où le dossier de l'autre entité n'est pas sélectionné : On delete forcément celui de l'autre entité
        $dossier->load($PDOdb, $TDoublon[1]['rowid'], true, false);
        $dossier->delete($PDOdb);

        print '<br/><span>Deleted</span>';
    }
    else if(! $TDoublon[0]['selected']) {
        // Cas où le dossier de l'entité 20 n'est pas sélectionné alors que celui de l'autre entité l'est : On delete celui de l'entité 20
        $dossier->load($PDOdb, $TDoublon[0]['rowid'], true, false);
        $dossier->delete($PDOdb);

        print '<br/><span>Deleted</span>';
    }
    else {
        // Le 2 dossiers sont sélectionnés donc il faut solder celui des autres entités
        $db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_dossier_financement';
        $sql.= " SET date_solde = '1998-07-12', montant_solde = 1";
        $sql.= ' WHERE fk_fin_dossier = '.$TDoublon[1]['rowid'];

        $resql = $db->query($sql);
        if(! $resql) $db->rollback();
        else $db->commit();

        print '<br/><span>Solde</span>';
    }
}

print '<br/><br/>';
print '<span>End</span>';
