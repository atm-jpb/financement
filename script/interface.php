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
    case 'updateNextTerm':
        $commit = GETPOST('commit');

        if(! empty($commit)) {
            $fk_dossier = GETPOST('fk_dossier');
            $type = GETPOST('type');

            print json_encode(updateNextTerm($fk_dossier, $type));
        }
        break;
    case 'toggleDematCheckbox':
        $fk_dossier = GETPOST('fk_dossier');

        print json_encode(toggleDematCheckbox($fk_dossier));
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

/**
 * @param   int       $fk_dossier
 * @param   string    $type
 * @return  bool
 */
function updateNextTerm($fk_dossier, $type = 'leaser') {
    global $user;

    if(empty($fk_dossier) || empty($type) || $type != 'leaser' && $type != 'client' || $user->id != 1) return false;

    $PDOdb = new TPDOdb;
    $d = new TFin_dossier;
    $d->load($PDOdb, $fk_dossier, false);

    $f = new TFin_financement;
    if($type == 'leaser') $f = &$d->financementLeaser;
    else if($type == 'client') $f = &$d->financement;

    $f->setEcheanceExterne($f->date_debut);

    return $f->save($PDOdb);
}

function toggleDematCheckbox($fk_dossier) {
    if(empty($fk_dossier)) return false;
    global $db;

    $sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_dossier';
    $sql.= ' SET demat = NOT demat';
    $sql.= ' WHERE rowid = '.$fk_dossier;

    $resql = $db->query($sql);
    if(! $resql) return false;

    return true;
}