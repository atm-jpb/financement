<?php
require '../config.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/conformite.class.php');

$action = GETPOST('action', 'alpha');

switch($action) {
    case 'updateDateReception':
        $strDate = GETPOST('strDate');
        $strAllConformite = GETPOST('allSelectedConformite');

        print json_encode(updateDateReception($strDate, $strAllConformite));
        break;
}

/**
 * @param   string  $strDate
 * @param   string  $strAllConformite
 * @return  bool
 */
function updateDateReception($strDate, $strAllConformite) {
    if(empty($strDate) || empty($strAllConformite)) return false;

    $PDOdb = new TPDOdb;
    $date = strtotime($strDate);
    $TFkConformite = explode(',', $strAllConformite);

    foreach($TFkConformite as $fk_conformite) {
        $c = new Conformite;
        $c->fetch($fk_conformite);

        $s = new TSimulation;
        $s->load($PDOdb, $c->fk_simulation, false);
        if(empty($s->fk_fin_dossier)) continue; // On ne peut pas modifier si la simul n'a pas de dossier

        $d = new TFin_dossier;
        $d->load($PDOdb, $s->fk_fin_dossier, false, false);

        $d->date_reception_papier = $date;
        $d->save($PDOdb);
    }

    return true;
}