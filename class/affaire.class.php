<?php

class TFin_affaire extends TObjetStd
{
    function __construct() { /* declaration */
        parent::set_table(MAIN_DB_PREFIX.'fin_affaire');
        parent::add_champs('reference,nature_financement,contrat,type_financement,type_materiel,xml_fic_transfert', 'type=chaine;');
        parent::add_champs('date_affaire,xml_date_transfert', 'type=date;');
        parent::add_champs('fk_soc,entity', 'type=entier;index;');
        parent::add_champs('montant,solde', 'type=float;');

        parent::_init_vars();
        parent::start();

        $this->TLien = array();
        $this->TCommercial = array();
        $this->TAsset = array();
        $this->contrat = 'LOCSIMPLE';

        $this->TContrat = $this->load_c_type_contrat();
        $this->TBaseSolde = array(
            'MF' => 'Montant financé'
            , 'CRD' => 'CRD'
            , 'LRD' => 'LRD'
            , 'SPE' => 'Spécifique'
        );

        $this->TTypeFinancement = array(
            'PURE' => 'Location Pure'
            , 'ADOSSEE' => 'Location adossée'
            , 'MANDATEE' => 'Location mandatée'
            , 'FINANCIERE' => 'Location financière'
        );
        $this->TTypeFinancementShort = array(
            'PURE' => 'Loc. Pure'
            , 'ADOSSEE' => 'Loc. adossée'
            , 'MANDATEE' => 'Loc. mandatée'
            , 'FINANCIERE' => 'Loc. financière'
        );

        $this->TTypeMateriel = array();
        $this->TNatureFinancement = array(
            'INTERNE' => 'Interne'
            , 'EXTERNE' => 'Externe'
        );

        $this->somme_dossiers = 0;

        // Obligé d'init à null vu que la fonction parent::_init_vars() met des valeurs dedans
        $this->date_affaire = null;
        $this->xml_date_transfert = null;
    }

    function load_c_type_contrat() {
        global $db, $conf, $user;

        $res = array();
        $sql = "SELECT tc.code, tc.label FROM ".MAIN_DB_PREFIX."c_financement_type_contrat tc";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_financement_type_contrat tc2 ON tc.code = tc2.code";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON tc2.rowid = ee.fk_source AND ee.sourcetype = 'type_contrat'";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."usergroup ug ON ug.rowid = ee.fk_target AND ee.targettype = 'usergroup'";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user uu ON ug.rowid = uu.fk_usergroup";
        $sql.= " WHERE tc.entity = ".$conf->entity." AND tc.active = 1 AND uu.fk_user = ".$user->id;

        $resql = $db->query($sql);

        if($resql) {
            while($line = $db->fetch_object($resql)) {
                $res[$line->code] = $line->label;
            }
        }

        return $res;
    }

    function load(&$ATMdb, $id, $annexe = true) {
        global $db;

        $res = parent::load($ATMdb, $id);

        // Chargement du client
        $this->societe = new Societe($db);
        $this->societe->fetch($this->fk_soc);

        if($annexe) {
            $this->loadDossier($ATMdb);
            $this->loadCommerciaux($ATMdb);
            $this->loadEquipement($ATMdb);
        }

        $this->calculSolde();

        return $res;
    }

    function loadCommerciaux(&$db) {
        $Tab = TRequeteCore::get_id_from_what_you_want($db, MAIN_DB_PREFIX.'fin_affaire_commercial', array('fk_fin_affaire' => $this->getId()));

        foreach($Tab as $i => $id) {
            $this->TCommercial[$i] = new TFin_affaire_commercial;
            $this->TCommercial[$i]->load($db, $id);
        }
    }

    function loadEquipement(&$db) {
        if(! class_exists('TAssetLink')) dol_include_once('/assetatm/class/asset.class.php');

        $Tab = TRequeteCore::get_id_from_what_you_want($db, MAIN_DB_PREFIX.'assetatm_link', array('fk_document' => $this->getId(), 'type_document' => 'affaire'));
        $this->TAsset = array();

        foreach($Tab as $i => $id) {
            $this->TAsset[$i] = new TAssetLink;
            $this->TAsset[$i]->load($db, $id, true);
        }
    }

    function calculSolde() {
        $this->somme_dossiers = 0;
        foreach($this->TLien as &$lien) {
            $this->somme_dossiers += $lien->dossier->montant;
        }

        $this->solde = $this->montant - $this->somme_dossiers;
    }

    function loadDossier(&$db) {
        $Tab = TRequeteCore::get_id_from_what_you_want($db, MAIN_DB_PREFIX.'fin_dossier_affaire', array('fk_fin_affaire' => $this->getId()));

        foreach($Tab as $i => $id) {
            $this->TLien[$i] = new TFin_dossier_affaire;
            $this->TLien[$i]->load($db, $id);
            $this->TLien[$i]->dossier->load($db, $this->TLien[$i]->fk_fin_dossier, false);
        }

        $this->calculSolde();
    }

    function getSolde(&$ATMdb, $type = 'SRBANK') {
        $solde = 0;
        foreach($this->TLien as $link) {
            $solde += $link->dossier->getSolde($ATMdb, $type);
        }

        return $solde;
    }

    function delete(&$db) {
        // Delete into llx_affaire_commercial
        $db->dbdelete(MAIN_DB_PREFIX.'fin_affaire_commercial', $this->getId(), 'fk_fin_affaire');

        // Delete into llx_dossier_affaire
        $db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $this->getId(), 'fk_fin_affaire');

        // Delete affaire
        parent::delete($db);
    }

    function save(&$db) {
        global $user;

        if(! $user->rights->financement->affaire->write) return false;

        $this->calculSolde();

        //Si dossier financement verrouillé, seule une action humaine doit permettre la modification de la classif
        if($this->TLien[0]->dossier->financementLeaser->okPourFacturation === 'AUTO') {
            $force_update = GETPOST('force_update');

            if(! $force_update) {
                $liste_champ_to_unsave = 'reference,nature_financement,contrat,type_financement,type_materiel,date_affaire,montant,solde';

                $this->_no_save_vars($liste_champ_to_unsave);
            }
        }

        parent::save($db);

        foreach($this->TLien as &$lien) {
            $lien->fk_fin_affaire = $this->getId();
            $lien->save($db);
            // Sauvegarde du dossier pour mise à jour si changement de classification
            $lien->dossier->save($db);
        }

        foreach($this->TAsset as &$lien) {
            if(is_object($lien)) {
                $lien->fk_document = $this->getId();
                $lien->save($db);
            }
        }

        foreach($this->TCommercial as &$lien) {
            $lien->fk_fin_affaire = $this->getId();
            $lien->save($db);
        }
    }

    function deleteDossier(&$db, $id) {
        foreach($this->TLien as $k => &$lien) {
            if($lien->fk_fin_dossier == $id) {
                $db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $lien->getId(), 'rowid');
                unset($this->TLien[$k]);
                $this->calculSolde();

                return true;
            }
        }

        return false;
    }

    function addDossier(&$db, $id = null, $reference = null) {
        $dossier = new TFin_dossier;

        if((! is_null($id) && $dossier->load($db, $id))
            || (! is_null($reference) && $dossier->loadReference($db, $reference))) {
            /*
             * Le dossier existe liaison
             */
            foreach($this->TLien as $k => $lien) {
                if($lien->fk_fin_dossier == $dossier->getId()) {
                    return false;
                }
            }

            $i = count($this->TLien);
            $this->TLien[$i] = new TFin_dossier_affaire;
            $this->TLien[$i]->fk_fin_affaire = $this->rowid;
            $this->TLien[$i]->fk_fin_dossier = $dossier->rowid;

            $this->TLien[$i]->dossier = $dossier;

            $this->calculSolde();
            return true;
        }
        else {
            return false;
        }
    }

    function addFactureMat(&$ATMdb, $facnumber) {
        global $db;

        $facture = new Facture($db);
        $facture->fetch('', $facnumber);
        $facture->fetch_lines();

        foreach($facture->lines as $line) {
            if(strpos($line->desc, 'Matricule(s)') !== false) {
                // Création des liens entre affaire et matériel
                $TSerial = explode(' - ', strtr($line->desc, array('Matricule(s) ' => '')));

                foreach($TSerial as $serial) {
                    $serial = trim($serial);

                    $asset = new TAsset;
                    if($asset->loadReference($ATMdb, $serial)) {
                        $asset->fk_soc = $this->fk_soc;

                        $asset->add_link($this->getId(), 'affaire');
                        $asset->add_link($facture_mat->id, 'facture');   // FIXME Undefined variable $facture_mat

                        $asset->save($ATMdb);
                    }
                }

                $facture->add_object_linked('affaire', $this->getId());
            }
        }

        return true;
    }

    function deleteEquipement(&$db, $id) {
        foreach($this->TAsset as $k => &$lien) {
            if($lien->asset->getId() == $id && $lien->fk_document == $this->getId() && $lien->type_document == 'affaire') {
                $lien->delete($db);
                unset($this->TAsset[$k]);
                return true;
            }
        }

        return false;
    }

    function addEquipement(&$db, $id) {
        foreach($this->TAsset as $k => &$asset) {
            if($asset->getId() == $id) {
                return false;
            }
        }

        $asset = new TAsset;
        $asset->load($db, $id);
        $i = $asset->add_link($this->getId(), 'affaire');
        $asset->save($db);

        $asset->TLink[$i]->asset = $asset;

        $this->TAsset[] = $asset->TLink[$i];

        return true;
    }

    function deleteCommercial(&$db, $id) {
        foreach($this->TLien as $k => &$lien) {
            if($lien->fk_fin_dossier == $id) {
                $db->dbdelete(MAIN_DB_PREFIX.'fin_affaire_commercial', $lien->getId(), 'rowid');
                unset($this->TLien[$k]);
                return true;
            }
        }

        return false;
    }

    function addCommercial(&$db, $id) {
        foreach($this->TCommercial as $k => $lien) {
            if($lien->fk_user == $id) {
                return false;
            }
        }

        $i = count($this->TCommercial);
        $this->TCommercial[$i] = new TFin_affaire_commercial;
        $this->TCommercial[$i]->fk_fin_affaire = $this->getId();
        $this->TCommercial[$i]->fk_user = $id;

        return true;
    }

    function loadReference(&$db, $reference, $annexe = false, $entity = null) {
        $checkEntity = '';
        if(! is_null($entity) && is_numeric($entity)) $checkEntity .= ' AND entity = '.$entity;

        $db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference='".$reference."'".$checkEntity);

        if($db->Get_line()) {
            return $this->load($db, $db->Get_field('rowid'), $annexe);
        }
        else {
            return false;
        }
    }

    function loadFactureMat($fetchFacture = true) {
        if(empty($this->rowid)) return;
        if(! class_exists('Facture')) dol_include_once('/compta/facture/class/facture.class.php');

        global $db;

        $sql = 'SELECT fk_target';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
        $sql .= " WHERE sourcetype='affaire'";
        $sql .= ' AND fk_source='.$this->rowid;
        $sql .= " AND targettype='facture'";

        $resql = $db->query($sql);
        if($resql) {
            if($obj = $db->fetch_object($resql)) {
                if($fetchFacture) {
                    $facture = new Facture($db);
                    $facture->fetch($obj->fk_target);

                    return $facture;
                }

                return $obj->tk_target;
            }
        }
        else {
            dol_print_error($db);
            return -2;
        }

        return -1;
    }



    /**
     * @param   $source     integer
     * @param   $target     integer
     * @param   $TEntity    array
     * @return              bool
     */
    public static function replaceThirdparty($source, $target, $TEntity = array()) {
        if(empty($source) || empty($target)) return false;

        global $db, $conf;
        if(empty($TEntity)) $TEntity[] = $conf->entity;

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_affaire';
        $sql.= ' SET fk_soc = '.intval($target);
        $sql.= ' WHERE fk_soc = '.intval($source);
        $sql.= ' AND entity IN ('.implode(',', $TEntity).')';

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        return true;
    }

    public static function isExistingObject($id = null, $fkSoc = null) {
        global $db;

        $sql = 'SELECT count(*) as nb';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_affaire';
        if(! is_null($fkSoc)) $sql.= ' WHERE fk_soc = '.$db->escape($fkSoc);
        else if(! is_null($id)) $sql .= ' WHERE rowid = '.$db->escape($id);

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        if($obj = $db->fetch_object($resql)) return $obj->nb > 0;

        return true;
    }
}

class TFin_affaire_commercial extends TObjetStd
{
    function __construct() { /* declaration */
        parent::set_table(MAIN_DB_PREFIX.'fin_affaire_commercial');
        parent::add_champs('fk_user,fk_fin_affaire', 'type=entier;index;');

        parent::_init_vars();
        parent::start();
    }
}
