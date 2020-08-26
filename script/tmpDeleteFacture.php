<?php
/*
 * Fichier destiné à n'être utilisé qu'une fois pour supprimer les factures fournisseur générées par erreur le 12/08/2020
 */
define('INC_FROM_CRON_SCRIPT', true);

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';

global $user, $db;

set_time_limit(0);					// No timeout for this script

// Load user and its permissions
if(empty($user->id)) {
	$result = $user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
	if (! $result > 0) { dol_print_error('', $user->error); exit; }
	$user->getrights();
}

$fk_dossier = GETPOST('fk_dossier', 'int');
$limit = GETPOST('limit', 'int');
$commit = GETPOST('commit', 'int');
$debug = GETPOST('debug', 'int');

$sql = 'SELECT f.rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX."element_element ee on (df.type = 'LEASER' and ee.sourcetype = 'dossier' and ee.fk_source = df.fk_fin_dossier)";
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX."facture_fourn f on (ee.targettype = 'invoice_supplier' and ee.fk_target = f.rowid and f.type = 0)";
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX."facture_fourn_extrafields fe on (fe.fk_object = f.rowid)";
$sql.= " WHERE df.date_solde > '1970-01-01' AND df.date_solde is not null"; // Dossiers soldés
$sql.= ' AND df.montant_solde > 0.00 AND df.montant_solde is not null';     // Dossiers soldés
//$sql.= ' AND fe.date_debut_periode > df.date_solde';
$sql.= " AND date_format(f.datec, '%Y-%m-%d') = '2020-08-12'";
if(! empty($fk_dossier)) $sql.= ' AND fk_fin_dossier = '.$fk_dossier;

if(! empty($debug)) print $sql.'<br/>';

$resql = $db->query($sql);

if(! $resql) {
    dol_print_error($db);
    exit;
}

$nbRow = $db->num_rows($resql);
$i = 0;

$db->begin();

while($obj = $db->fetch_object($resql)) {
    $facture = new FactureFournisseur($db);
    $facture->fetch($obj->rowid);

    // On supprime tous les potentiels avoirs de la facture leaser
    $TAvoir = $facture->getListIdAvoirFromInvoice();
    foreach($TAvoir as $fk_creditNote) {
        $a = new FactureFournisseur($db);
        $a->fetch($fk_creditNote);

        $a->delete($user);
    }

    $facture->deleteObjectLinked();
    $res = $facture->delete($user);

    if($res < 0) dol_print_error($db);
    else $i++;
}

print '<br/';
print '<span>Nb row : '.$nbRow.'</span><br/>';
print '<span>Nb deleted : '.$i.'</span>';

if(! empty($commit)) {
    $db->commit();
    print '<p><span style="background-color: limegreen">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>&nbsp;COMMIT !</p>';
}
else {
    $db->rollback();
    print '<p><span style="background-color: #FF0000">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>&nbsp;ROLLBACK !</p>';
}
