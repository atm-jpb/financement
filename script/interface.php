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

    $date = strtotime($strDate);
    $TFkConformite = explode(',', $strAllConformite);

    foreach($TFkConformite as $fk_conformite) {
        $c = new Conformite;
        $c->fetch($fk_conformite);

        $c->date_reception_papier = $date;
        $c->update();
    }

    return true;
}