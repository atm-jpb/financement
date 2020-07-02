<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/affaire.class.php');

$entity = GETPOST('entity', 'int');
$workWithEntityGroups = GETPOST('workWithEntityGroups', 'int');
$siren = GETPOST('siren');

if(empty($entity)) exit('Empty entity !');

if(! empty($workWithEntityGroups)) {
    $TEntity = getOneEntityGroup($entity, 'thirdparty', array(4, 17));

    print 'Entity : '.$entity.'<br/>';
    print 'Thirdparty entity group : '.implode(',', $TEntity);
}

$sql = "SELECT siren, group_concat(rowid) as data";
$sql.= ' FROM '.MAIN_DB_PREFIX.'societe';
$sql.= ' WHERE entity = '.$db->escape($entity);
$sql.= ' AND LENGTH(siren) = 9';
$sql.= " AND siren <> '000000000'";
if(! empty($siren)) $sql.= " AND siren = '".$db->escape($siren)."'";
if(! empty($workWithEntityGroups) && ! empty($TEntity)) $sql.= ' AND entity IN ('.implode(',', $TEntity).')';
$sql.= ' GROUP BY siren';
$sql.= ' HAVING count(*) > 1';

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

while($obj = $db->fetch_object($resql)) {
    $TData = explode(',', $obj->data);
    sort($TData);
    $max = array_pop($TData);   // Réprésente le rowid de la société à garder
    // On a maintenant dans $TData tous les rowid à supprimer

    $s = new Societe($db);
    $s->fetch($max);

    foreach($TData as $fkSoc) {
        $soc = new Societe($db);
        $soc->fetch($fkSoc);

        mergeThirdparty($s, $soc);
    }
}

/**
 * @param Societe $object       Thirdparty to keep
 * @param Societe $soc_origin   Thirdparty to remove
 */
function mergeThirdparty(Societe $object, Societe $soc_origin) {
    global $db, $user, $hookmanager, $langs;

    $error = 0;
    $db->begin();

    // Recopy some data
    $object->client = $object->client | $soc_origin->client;
    $object->fournisseur = $object->fournisseur | $soc_origin->fournisseur;
    $listofproperties=array(
        'address', 'zip', 'town', 'state_id', 'country_id', 'phone', 'phone_pro', 'fax', 'email', 'skype', 'twitter', 'facebook', 'linkedin', 'url', 'barcode',
        'idprof1', 'idprof2', 'idprof3', 'idprof4', 'idprof5', 'idprof6',
        'tva_intra', 'effectif_id', 'forme_juridique', 'remise_percent', 'remise_supplier_percent', 'mode_reglement_supplier_id', 'cond_reglement_supplier_id', 'name_bis',
        'stcomm_id', 'outstanding_limit', 'price_level', 'parent', 'default_lang', 'ref', 'ref_ext', 'import_key', 'fk_incoterms', 'fk_multicurrency',
        'code_client', 'code_fournisseur', 'code_compta', 'code_compta_fournisseur',
        'model_pdf', 'fk_projet'
    );
    foreach ($listofproperties as $property)
    {
        if (empty($object->$property)) $object->$property = $soc_origin->$property;
    }

    // Concat some data
    $listofproperties=array(
        'note_public', 'note_private'
    );
    foreach ($listofproperties as $property)
    {
        $object->$property = dol_concatdesc($object->$property, $soc_origin->$property);
    }

    // Merge extrafields
    if (is_array($soc_origin->array_options))
    {
        foreach ($soc_origin->array_options as $key => $val)
        {
            if (empty($object->array_options[$key])) $object->array_options[$key] = $val;
        }
    }

    // Merge categories
    $static_cat = new Categorie($db);

    $custcats_ori = $static_cat->containing($soc_origin->id, 'customer', 'id');
    $custcats = $static_cat->containing($object->id, 'customer', 'id');
    $custcats = array_merge($custcats, $custcats_ori);
    $object->setCategories($custcats, 'customer');

    $suppcats_ori = $static_cat->containing($soc_origin->id, 'supplier', 'id');
    $suppcats = $static_cat->containing($object->id, 'supplier', 'id');
    $suppcats = array_merge($suppcats, $suppcats_ori);
    $object->setCategories($suppcats, 'supplier');

    // If thirdparty has a new code that is same than origin, we clean origin code to avoid duplicate key from database unique keys.
    if (! empty($soc_origin->code_client) && ! empty($object->code_client) && $soc_origin->code_client == $object->code_client
        || ! empty($soc_origin->code_fournisseur) && ! empty($object->code_fournisseur) && $soc_origin->code_fournisseur == $object->code_fournisseur
        || ! empty($soc_origin->barcode) && ! empty($object->barcode) && $soc_origin->barcode == $object->barcode)
    {
        dol_syslog("We clean customer and supplier code so we will be able to make the update of target");
        $soc_origin->code_client = '';
        $soc_origin->code_fournisseur = '';
        $soc_origin->barcode = '';
        $soc_origin->update($soc_origin->id, $user, 0, 1, 1, 'merge');
    }

    // Update
    $result = $object->update($object->id, $user, 0, 1, 1, 'merge');
    if ($result < 0)
    {
        $error++;
    }

    // Move links
    if (! $error)
    {
        $objects = array(
            'Adherent' => '/adherents/class/adherent.class.php',
            'Societe' => '/societe/class/societe.class.php',
            //'Categorie' => '/categories/class/categorie.class.php',
            'ActionComm' => '/comm/action/class/actioncomm.class.php',
            'Propal' => '/comm/propal/class/propal.class.php',
            'Commande' => '/commande/class/commande.class.php',
            'Facture' => '/compta/facture/class/facture.class.php',
            'FactureRec' => '/compta/facture/class/facture-rec.class.php',
            'LignePrelevement' => '/compta/prelevement/class/ligneprelevement.class.php',
            'Contact' => '/contact/class/contact.class.php',
            'Contrat' => '/contrat/class/contrat.class.php',
            'Expedition' => '/expedition/class/expedition.class.php',
            'Fichinter' => '/fichinter/class/fichinter.class.php',
            'CommandeFournisseur' => '/fourn/class/fournisseur.commande.class.php',
            'FactureFournisseur' => '/fourn/class/fournisseur.facture.class.php',
            'SupplierProposal' => '/supplier_proposal/class/supplier_proposal.class.php',
            'ProductFournisseur' => '/fourn/class/fournisseur.product.class.php',
            'Livraison' => '/livraison/class/livraison.class.php',
            'Product' => '/product/class/product.class.php',
            'Project' => '/projet/class/project.class.php',
            'User' => '/user/class/user.class.php',
        );

        //First, all core objects must update their tables
        foreach ($objects as $object_name => $object_file)
        {
            require_once DOL_DOCUMENT_ROOT.$object_file;

            if (!$error && !$object_name::replaceThirdparty($db, $soc_origin->id, $object->id))
            {
                $error++;
                setEventMessages($db->lasterror(), null, 'errors');
            }
        }
    }

    // External modules should update their ones too
    if (! $error)
    {
        // Traitement du hook de financement
        $TEntityGroup = getOneEntityGroup($object->entity, 'fin_simulation', array(4, 17));
        // Si les 2 sociétés ne sont pas dans la même groupe, on évite de merge
        if(! in_array($soc_origin->entity, $TEntityGroup)) {
            $object->errors[] = $langs->load('FinancementReplaceThirdpartyError');
            return -1;
        }

        if(! empty($soc_origin->code_client)) {
            if(empty($object->array_options['options_other_customer_code'])) $object->array_options['options_other_customer_code'] = $soc_origin->code_client;
            else $object->array_options['options_other_customer_code'] .= ';'.$soc_origin->code_client;

            $res = $object->updateExtraField('other_customer_code');
        }

        TSimulation::replaceThirdparty($soc_origin->id, $object->id, $TEntityGroup);
        TFin_affaire::replaceThirdparty($soc_origin->id, $object->id, $TEntityGroup);
    }


    if (! $error)
    {
        $object->context=array('merge'=>1, 'mergefromid'=>$soc_origin->id);

        // Call trigger
        $result=$object->call_trigger('COMPANY_MODIFY', $user);
        if ($result < 0)
        {
            setEventMessages($object->error, $object->errors, 'errors');
            $error++;
        }
        // End call triggers
    }

    if (!$error)
    {
        //We finally remove the old thirdparty
        if ($soc_origin->delete($soc_origin->id, $user) < 1)
        {
            $error++;
        }
    }

    if (!$error)
    {
        setEventMessages($langs->trans('ThirdpartiesMergeSuccess'), null, 'mesgs');
        $db->commit();
    }
    else
    {
        $langs->load("errors");
        setEventMessages($langs->trans('ErrorsThirdpartyMerge'), null, 'errors');
        $db->rollback();
    }
}