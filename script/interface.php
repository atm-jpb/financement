<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
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
    case 'createMateriel':
        $libelleProduit = GETPOST('libelleProduit');
        $serialNumber = GETPOST('serialNumber');
        $refProduit = GETPOST('refProduit');
        $marque = GETPOST('marque');
        $entity = GETPOST('affaireEntity');

        print json_encode(createMateriel($libelleProduit, $serialNumber, $refProduit, $marque, $entity));
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

/**
 * @param string $libelleProduit
 * @param string $serialNumber
 * @param string $refProduit
 * @param string $marque
 * @param int $entity
 * @return bool
 */
function createMateriel($libelleProduit, $serialNumber, $refProduit, $marque, $entity) {
    if(empty($libelleProduit) || empty($serialNumber) || empty($refProduit) || empty($marque) || empty($entity)) return false;
    global $db, $user;

    $PDOdb = new TPDOdb;

    $p = new Product($db);
    $p->fetch('', $refProduit);

    // On en créé que si le produit n'existe pas
    if(empty($p->id)) {
        $p->ref = $refProduit;
        $p->label = $libelleProduit;
        $p->type = Product::TYPE_PRODUCT;

        $p->price_base_type = 'TTC';
        $p->price_ttc = 0;
        $p->price_min_ttc = 0;

        $p->tva_tx = 20;
        $p->tva_npr = 0;

        $p->localtax1_tx = get_localtax($p->tva_tx, 1);
        $p->localtax2_tx = get_localtax($p->tva_tx, 2);

        $p->status = 1;
        $p->status_buy = 1;
        $p->description = $marque;
        $p->customcode = '';
        $p->country_id = 1;
        $p->duration_value = 0;
        $p->duration_unit = 0;
        $p->seuil_stock_alerte = 0;
        $p->weight = 0;
        $p->weight_units = 0;
        $p->length = 0;
        $p->length_units = 0;
        $p->surface = 0;
        $p->surface_units = 0;
        $p->volume = 0;
        $p->volume_units = 0;
        $p->finished = 1;

        $p->create($user);
    }

    // Et comme ça s'il existe, on l'utilise
    if(! empty($p->id)) {
        $TSerial = explode(' - ', $serialNumber);

        foreach($TSerial as $serial) {
            $asset = new TAsset;
            $asset->loadReference($PDOdb, $serial);

            $asset->fk_product = $p->id;
            $asset->serial_number = $serial;

            $asset->set_date('date_achat', date('Y-m-d'));
            $asset->copy_color = 0;
            $asset->entity = $entity;

            $asset->save($PDOdb);
        }

        return true;
    }

    return false;
}