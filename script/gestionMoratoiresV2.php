<?php

require '../config.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

use MathPHP\Finance as Finance;

set_time_limit(0);

$fk_dossier = GETPOST('fk_dossier', 'int');
$commit = GETPOST('commit', 'int');
$debug = GETPOST('debug', 'int');

$PDOdb = new TPDOdb;

$sql = "SELECT dflea.fk_fin_dossier";
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement dflea';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (dflea.fk_fin_dossier = d.rowid)';
$sql.= " WHERE dflea.type = 'LEASER'";
$sql.= " AND dflea.reference LIKE '%-covid'";
$sql.= ' AND d.entity IN (2, 3)';  // Informatique et Télécom
if(! empty($fk_dossier)) $sql.= ' AND fk_fin_dossier = '.$db->escape($fk_dossier);

if(! empty($debug)) {
    print '<pre>';
    var_dump($sql);
    print '</pre>';
}

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

while($obj = $db->fetch_object($resql)) {
    $dCovid = new TFin_dossier;
    $dCovid->load($PDOdb, $obj->fk_fin_dossier, false);
    $dCovid->load_affaire($PDOdb);

    $e = $dCovid->echeancier($PDOdb, 'LEASER', 1, true, false);

    // Permet de retrouver le bon numéro de période pour recalculer le CRD
    $periods = null;
    foreach($e['ligne'] as $iPeriode => $lineData) {
        if($lineData['date'] == '01/01/2020') {
            $periods = $iPeriode + 1;
        }
    }

    // On recalcule l'échéance avec la nouvelle durée
    /** @var TFin_financement $f */
    $f = $dCovid->financementLeaser;
    $taux = $f->taux/ (12 / $f->getiPeriode()) / 100;
    $beginning = ($f->terme == 1);
    $dureeRestante = ($f->duree_restante == 0) ? 1 : $f->duree_restante;    // Si le dossier se termine sur l'échéance non payée, il reste donc une période à payer

    $CRDCovid = $f->valeur_actuelle($f->duree-$periods); // On recalcule le CRD du 31/03/2020
    $montant = Finance::fv($taux, 1, 0, $CRDCovid, $beginning);
    $echeance = Finance::pmt($taux, $dureeRestante, $montant, $f->reste, $beginning);

    $d = new TFin_dossier;
    $d->loadReference($PDOdb, substr($f->reference, 0, -6), false, $dCovid->entity);

    if(! empty($debug)) {
        print '<pre>';
        var_dump($dCovid->rowid, $d->rowid, abs($montant), $echeance);
        print '</pre>';
    }

    // On solde temporairement le financement leaser pour générer les avoirs
    $d->financementLeaser->date_solde = strtotime('2020-06-30');
    if(! empty($commit)) $d->financementLeaser->save($PDOdb);

    $d->financementLeaser->date_solde = null;
    $d->financementLeaser->montant = abs($montant);
    $d->financementLeaser->echeance = $echeance;
    if(! empty($commit)) $d->financementLeaser->save($PDOdb);

}
