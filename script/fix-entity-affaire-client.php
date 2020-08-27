<?php
set_time_limit(0);					// No timeout for this script

require '../config.php';
dol_include_once('/financement/lib/financement.lib.php');

global $db, $user;

$fk_affaire = GETPOST('fk_affaire', 'int');
$limit = GETPOST('limit', 'int');

// Récupération des affaires commençant par EXT dont l'entité n'est pas la même que celle du client
$sql = 'SELECT a.rowid, a.entity, a.reference, s.rowid as socid, s.entity as entity_soc, s.siren, s.nom, s.address, s.zip, s.town, s.siren, s.siret, s.fk_pays';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_affaire a';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid = a.fk_soc';
$sql.= " WHERE a.reference LIKE 'EXT%'";
$sql.= ' AND a.entity <> s.entity';
$sql.= ' AND (a.entity not in (1, 2, 3, 30) or s.entity not in (1, 2, 3, 30))';
$sql.= ' AND (a.entity not in (5, 16) or s.entity not in (5, 16))';
$sql.= ' AND (a.entity not in (20, 23) or s.entity not in (20, 23))';
if(! empty($fk_affaire)) $sql.= ' AND a.rowid = '.$db->escape($fk_affaire);
if(! empty($limit)) $sql.= ' LIMIT '.$db->escape($limit);

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$i = $j = 0;
while($obj = $db->fetch_object($resql)) {
    if(in_array($obj->entity, array(1, 2, 3, 30)) && in_array($obj->entity_soc, array(1, 2, 3, 30))) continue;  // FIXME: Useless with new constraints in SQL
    if(in_array($obj->entity, array(5, 16)) && in_array($obj->entity_soc, array(5, 16))) continue;      // FIXME: Useless with new constraints in SQL
    if(in_array($obj->entity, array(20, 23)) && in_array($obj->entity_soc, array(20, 23))) continue;    // FIXME: Useless with new constraints in SQL
    $i++;

    $TEntityGroup = getOneEntityGroup($obj->entity, 'thirdparty', array(4, 17));
    $TKey = array_keys($TEntityGroup, 17);   // On enlève l'entité 17 du tableau car aussi surprenant que cela puisse paraître, des clients existent dans cette entité !!
    foreach($TKey as $key) unset($TEntityGroup[$key]);

    echo '<hr>AFFAIRE '.$obj->reference.' ('.$obj->rowid.') : ';
    if(strlen($obj->siren) != 9) {
        echo 'SIREN INCORRECT';
        continue;
    }

    $sql2 = 'SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.entity IN ('.implode(',', $TEntityGroup).") AND s.siren = '".$obj->siren."'";
    $resql2 = $db->query($sql2);
    if($db->num_rows($resql2) == 0) {
        $db->begin();

        echo 'SIREN NON TROUVÉ ('.$sql2.') => ';
        $soc = new Societe($db);
        $soc->name = $obj->nom;
        $soc->address = $obj->address;
        $soc->zip = $obj->zip;
        $soc->town = $obj->town;
        $soc->country_id = $obj->fk_pays;
        $soc->idprof1 = $obj->siren;
        $soc->idprof2 = $obj->siret;
        $soc->entity = $obj->entity;
        $soc->client = 1;
        $soc->status = 1;
        $socid = $soc->create($user);
        if($socid < 0) {
            echo 'ERREUR CREATION '.$socid;
            continue;
        }

        $db->commit();
    }
    else {
        $obj2 = $db->fetch_object($resql2);
        $socid = $obj2->rowid;
    }

    $sql3 = 'UPDATE '.MAIN_DB_PREFIX.'fin_affaire a SET a.fk_soc = '.$socid.' WHERE a.rowid = '.$obj->rowid;
    echo $sql3;
    $resql3 = $db->query($sql3);
    if(! $resql3) {
        dol_print_error($db);
    }
    $j++;
}

echo '<hr>'.$j.' / '.$i;
