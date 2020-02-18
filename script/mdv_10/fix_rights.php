<?php
/*
 * Script permettant de basculer les données de llx_fin_dossier.date_reception_papier vers llx_fin_conformite.date_reception_papier
 */
require_once '../../config.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';

$commit = GETPOST('commit', 'int');

// Chargement des groupes configurés dans multi entité
$TGroupEntity = unserialize($conf->global->MULTICOMPANY_USER_GROUP_ENTITY);
if(! is_array($TGroupEntity)) exit;

$TRes = array();

foreach($TGroupEntity as $TData) {
    if(is_null($TRes[$TData['group_id']])) $TRes[$TData['group_id']] = array($TData['entity_id']);
    else {
        if(! in_array($TData['entity_id'], $TRes[$TData['group_id']])) $TRes[$TData['group_id']][] = $TData['entity_id'];
    }
}

if(empty($commit)) exit;    // Petite sécurité

foreach($TRes as $fk_usergroup => $TEntity) {
    if(count($TEntity) === 1) {
        $db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'usergroup_rights';
        $sql.= ' SET entity = '.$TEntity[0];
        $sql.= ' WHERE fk_usergroup = '.$fk_usergroup;

        $resql = $db->query($sql);
        if(! $resql) $db->rollback();
        else $db->commit();

        $db->free($resql);
    }
    else {
        $db->begin();

        $sql.= 'INSERT IGNORE INTO '.MAIN_DB_PREFIX.'usergroup_rights(entity, fk_usergroup, fk_id)';
        $sql.= ' SELECT e.rowid, ur.fk_usergroup, ur.fk_id';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'usergroup_rights ur';
        $sql.= ' JOIN '.MAIN_DB_PREFIX.'entity e';
        $sql.= ' WHERE fk_usergroup = '.$fk_usergroup;
        $sql.= ' AND e.rowid IN ('.implode(',', $TEntity).')';

        $resql = $db->query($sql);
        if(! $resql) $db->rollback();
        else $db->commit();

        $db->free($resql);
    }
}
