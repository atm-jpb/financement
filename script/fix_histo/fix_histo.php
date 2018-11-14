<?php

require('../../config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once("/fourn/class/fournisseur.facture.class.php");

$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();

$debug = array_key_exists('DEBUG', $_GET);
$force_rollback = array_key_exists('force_rollback', $_GET);
$PDOdb=new TPDOdb;

$TSimulationSuivi = array();   // This will be set in following php file
include_once 'simulation_suivi_data.php';

print '<pre>';
var_dump(count($TSimulationSuivi));
print '<br />';

/**
 * Exemple of content:
 * $TSimulationSuivi[0] => array('rowid' => 4, 'fk_simulation' => 15, 'fk_leaser' => 16, 'date_historization' => '1000-01-01 00:00:00')
 */
// Ici on parcours les enregistrements de la base de TEST de Financement
// qui ont une date_histo > '1000-01-01 00:00:00' (donc une date correcte)
foreach($TSimulationSuivi as $TContent) {
    $isObjectSet = false;

    $sql = 'SELECT date_historization';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation_suivi';
    $sql.= ' WHERE rowid='.$TContent['rowid'];
    $sql.= ' AND fk_simulation='.$TContent['fk_simulation'];
    $sql.= ' AND fk_leaser='.$TContent['fk_leaser'];
    $sql.= " AND date_historization<>'".$TContent['date_historization']."'";

    $resql = $db->query($sql);
    if(! $resql) {
        print '<span style="font-weight: bold;">';
        dol_print_error($db);
        print '</span>';
        exit('Please Fix this !');
    }

    while($obj = $db->fetch_object($resql)) {
        $isObjectSet = true;

        if($debug) {
            print '<span style="font-weight: bold;">';
            var_dump($obj->date_historization);
            print '</span>';
            var_dump($TContent['date_historization']);
            print '-----------------------------------<br />';
        }

        $db->begin();

        $sql_update = 'UPDATE '.MAIN_DB_PREFIX.'fin_simulation_suivi';
        $sql_update.= " SET date_historization='".$TContent['date_historization']."'";
        $sql_update.= ' WHERE rowid='.$TContent['rowid'];
        $sql_update.= ' AND fk_simulation='.$TContent['fk_simulation'];
        $sql_update.= ' AND fk_leaser='.$TContent['fk_leaser'];

        $res_update = $db->query($sql_update);
        if(! $res_update || ! empty($force_rollback)) {
            $db->rollback();
            dol_print_error($db);
            exit('Error with update query... ROLLBACK !');
        }

        $db->commit();
        $db->free($res_update);
    }
    if(! $isObjectSet) {
        // Enregistrement non présent dans la base courante
        // OU Date correcte déjà présente
        if($debug) {
            print 'Object Not Set !<br />';
            print '-----------------------------------<br />';
        }
    }
}

$db->free($resql);

const DEFAULT_DATE_VALUE = '1000-01-01 00:00:00';

$PDOdb->beginTransaction();

$sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_simulation_suivi';
$sql.= " SET date_historization='".DEFAULT_DATE_VALUE."'";
$sql.= " WHERE date_historization='0000-00-00 00:00:00' OR date_historization='1000-00-00 00:00:00'";

$resql = $PDOdb->Execute($sql);
if(! $resql || $force_rollback) {
    $PDOdb->rollBack();
    exit('Error with default date update... ROLLBACK !');
}

$PDOdb->commit();
