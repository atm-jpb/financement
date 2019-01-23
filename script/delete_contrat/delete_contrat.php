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

$debug = array_key_exists('debug', $_GET);
$force_rollback = array_key_exists('force_rollback', $_GET);
$PDOdb = new TPDOdb;

$TData = array();   // This will be set in following php file
include_once 'data.php';

print '<pre>';
var_dump(count($TData));
print '<br />';

/**
 * Exemple of content:
 * $TSimulationSuivi[0] => array('rowid' => 4, 'fk_simulation' => 15, 'fk_leaser' => 16, 'date_historization' => '1000-01-01 00:00:00')
 */
// Ici on parcours les enregistrements de la base de TEST de Financement
// qui ont une date_histo > '1000-01-01 00:00:00' (donc une date correcte)
foreach($TData as $refContrat) {

    $sql = 'SELECT rowid';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_affaire';
    $sql.= " WHERE reference = '".$refContrat."'";

    $resql = $db->query($sql);
    if(! $resql) {
        print '<span style="font-weight: bold;">';
        dol_print_error($db);
        print '</span>';
        exit('Please Fix this !');
    }

    if($obj = $db->fetch_object($resql)) {
        if($debug) {
            print '<span style="font-weight: bold;">';
            var_dump($obj->rowid);
            print '</span>';
            var_dump($refContrat);
            print '-----------------------------------<br />';
        }

        $affaire = new TFin_affaire;
        $affaire->load($PDOdb, $obj->rowid);

        // Check if exists into dossier_affaire
        if(! empty($affaire->TLien)) {
            // At least one 'Dossier'
            foreach($affaire->TLien as $dossier_affaire) $dossier_affaire->dossier->delete($PDOdb);
        }

        $affaire->delete($PDOdb);
    }
    else {
        if($debug) {
            var_dump($sql);
            print 'Object Not Set !<br />';
            print '-----------------------------------<br />';
        }
    }
    if($debug) break;
}

$db->free($resql);
