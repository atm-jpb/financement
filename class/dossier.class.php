<?php

/*
 * Dossier
 */

class TFin_dossier extends TObjetStd
{
    function __construct() { /* declaration */
        parent::set_table(MAIN_DB_PREFIX.'fin_dossier');
        parent::add_champs('solde,soldeperso,montant,montant_solde', 'type=float;');
        parent::add_champs('renta_previsionnelle,renta_attendue,renta_reelle,marge_previsionnelle,marge_attendue,marge_reelle,quote_part_couleur,quote_part_noir', 'type=float;');
        parent::add_champs('reference,nature_financement,commentaire,reference_contrat_interne,display_solde,visa_renta,visa_renta_ndossier,commentaire_visa,soldepersodispo,renta_anomalie', 'type=chaine;');
        parent::add_champs('date_relocation,date_solde,dateperso', 'type=date;');
        parent::add_champs('entity', array('type' => 'int', 'index' => true));
        parent::add_champs('type_regul,month_regul', array('type' => 'int'));
        parent::add_champs('fk_statut_renta_neg_ano,fk_statut_dossier,commentaire_conformite', array('type' => 'chaine'));
        parent::add_champs('date_reception_papier,date_paiement,date_facture_materiel', array('type' => 'date', 'index' => true));

        parent::start();
        parent::_init_vars();

        $this->somme_affaire = 0;
        $this->display_solde = 1;

        $this->date_relocation = 0;

        $this->Tvisa = array(0 => 'Non', 1 => 'Oui');

        $this->TLien = array();
        $this->financement = new TFin_financement;
        $this->financementLeaser = new TFin_financement;

        $this->nature_financement = 'EXTERNE';

        $this->TFacture = array();
        $this->TFactureFournisseur = array();

        // Dictionnaires
        $this->TStatutDossier = array();
        $this->TStatutRentaNegAno = array();
        $this->load_statut_dossier();

        $this->type_regul = 3;

        $this->date_reception_papier = null;
        $this->date_paiement = null;
        $this->date_facture_materiel = null;
    }

    function loadReference(&$db, $reference, $annexe = false, $entity = null) {
        $checkEntity = '';
        if(! is_null($entity) && is_numeric($entity) && ! empty($entity)) $checkEntity .= ' AND entity = '.$entity;

        $db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference='".$reference."'".$checkEntity);

        if($db->Get_line()) {
            return $this->load($db, $db->Get_field('rowid'), $annexe);
        }
        else {
            $sql = 'SELECT fk_fin_dossier';
            $sql.= ' FROM '.$this->get_table().'_financement df';
            $sql.= ' LEFT JOIN '.$this->get_table().' d ON (df.fk_fin_dossier = d.rowid)';
            $sql.= " WHERE df.reference = '".$reference."'";
            $sql.= $checkEntity;
            $db->Execute($sql);

            if($db->Get_line()) {
                return $this->load($db, $db->Get_field('fk_fin_dossier'), $annexe);
            }
            else {
                return false;
            }
        }
    }

    function loadReferenceContratDossier(&$db, $reference, $annexe = false, $entity = null) {
        $checkEntity = '';
        if(! is_null($entity) && is_numeric($entity)) $checkEntity .= ' AND entity = '.$entity;

        $db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference_contrat_interne='".$reference."'".$checkEntity);

        if($db->Get_line()) {
            return $this->load($db, $db->Get_field('rowid'), $annexe);
        }
        else {
            return false;
        }
    }

    function load(&$db, $id, $annexe = true, $annexe_fin = true) {
        $res = parent::load($db, $id);

        if($annexe_fin) {
            $this->load_financement($db);
            $this->load_categ();
        }
        $this->reference_contrat_interne = (! empty($this->financement)) ? $this->financement->reference : '';

        if($annexe) {
            $this->load_affaire($db);
            $this->load_facture($db);
            $this->load_factureFournisseur($db);
        }
        if($annexe_fin) {
            $this->calculSolde();
            $this->calculRenta($db);
        }

        return $res;
    }

    function load_categ() {
        global $db;

        dol_include_once('/categories/class/categorie.class.php');

        $cat = new Categorie($db);
        $cat->fetch(0, 'Acecom');
        $this->financementLeaser->is_acecom = $cat->containsObject('supplier', $this->financementLeaser->fk_soc);

        $cat = new Categorie($db);
        $cat->fetch(0, 'Locam');
        $this->financementLeaser->is_locam = $cat->containsObject('supplier', $this->financementLeaser->fk_soc);

        // Capé LRD sauf si ACECOM ou LOCAM
        $this->financementLeaser->cape_lrd = true;
        if($this->financementLeaser->is_acecom) $this->financementLeaser->cape_lrd = false;
        if($this->financementLeaser->is_locam) $this->financementLeaser->cape_lrd = false;
    }

    function load_financement(&$db) {
        $Tab = TRequeteCore::get_id_from_what_you_want($db, MAIN_DB_PREFIX.'fin_dossier_financement', array('fk_fin_dossier' => $this->getId()));

        foreach($Tab as $i => $id) {
            $f = new TFin_financement;
            $f->load($db, $id);
            if($f->type == 'LEASER') $this->financementLeaser = $f;
            else if($this->nature_financement == 'INTERNE') $this->financement = $f;
        }

        $this->calculSolde();
    }

    function load_affaire(&$db) {
        $Tab = TRequeteCore::get_id_from_what_you_want($db, MAIN_DB_PREFIX.'fin_dossier_affaire', array('fk_fin_dossier' => $this->getId()));

        $this->type_financement_affaire = array();

        foreach($Tab as $i => $id) {
            $this->TLien[$i] = new TFin_dossier_affaire;
            $this->TLien[$i]->load($db, $id);
            $this->TLien[$i]->affaire->load($db, $this->TLien[$i]->fk_fin_affaire, false);

            $this->somme_affaire += $this->TLien[$i]->affaire->montant;
            $this->contrat = $this->TLien[$i]->affaire->contrat;

            if($this->TLien[$i]->affaire->nature_financement == 'INTERNE') {
                $this->nature_financement = 'INTERNE';
            }

            $this->type_financement_affaire[$this->TLien[$i]->affaire->type_financement] = true;
        }

        if(count($Tab) == 0) $this->nature_financement = 'INTERNE';

        $this->solde = $this->montant - $this->somme_affaire;
    }

    function deleteAffaire(&$db, $id) {
        foreach($this->TLien as $k => &$lien) {
            if($lien->fk_fin_affaire == $id) {
                $db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $lien->getId(), 'rowid');
                unset($this->TLien[$k]);
                return true;
            }
        }

        return false;
    }

    function addAffaire(&$db, $id = null, $reference = null) {
        $affaire = new TFin_affaire;

        if((! is_null($id) && $affaire->load($db, $id))
            || (! is_null($reference) && $affaire->loadReference($db, $reference))) {
            /*
			 * Le dossier existe liaison
			 */

            foreach($this->TLien as $k => $lien) {
                if($lien->fk_fin_affaire == $affaire->getId()) {
                    return false;
                }
            }

            $i = count($this->TLien);
            $this->TLien[$i] = new TFin_dossier_affaire;
            $this->TLien[$i]->fk_fin_dossier = $this->getId();
            $this->TLien[$i]->fk_fin_affaire = $affaire->getId();

            $this->TLien[$i]->affaire = $affaire;

            // Très important car le dossier est créé dans la base et on présente à l'utilisateur le card en mode edit et l'entité n'est pas set à la création
            $this->entity = $affaire->entity;

            $this->calculSolde();

            return true;
        }
        else {
            return false;
        }
    }

    function delete(&$db, $affaire = true, $factures_fournisseur = true, $factures_client = true) {
        parent::delete($db);

        if($affaire) $db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $this->getId(), 'fk_fin_dossier');
        $db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_financement', $this->getId(), 'fk_fin_dossier');
        $db->dbdelete(MAIN_DB_PREFIX.'element_element', array('fk_source' => $this->getId(), 'sourcetype' => 'dossier'), array('fk_source', 'sourcetype'));

        if($factures_fournisseur) {
            foreach($this->TFactureFournisseur as $fact) {
                $fact->delete($fact->rowid);
                $fact->deleteObjectLinked();
            }
        }

        if($factures_client) {
            foreach($this->TFacture as $fact) {
                $fact->delete($fact->rowid);
                $fact->deleteObjectLinked();
            }
        }
    }

    private function setNatureFinancementOnSimpleLink(&$db) {
        /*
		 * Modifie la nature d'un dossier pour suivre l'affaire s'il n'y a qu'une affaire
		 */
        if(count($this->TLien) == 0) {
            $this->load_affaire($db);
        }
        if(count($this->TLien) == 1) {

            $lien = &$this->TLien[0];

            if(! empty($lien->affaire->nature_financement)) {
                $this->nature_financement = $lien->affaire->nature_financement;
            }
        }
    }

    function isSimilarRefExists() {
        global $db;

        if(empty($this->financementLeaser->reference)) return false;

        $sql = 'SELECT *';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement';
        $sql .= " WHERE reference LIKE '".$this->financementLeaser->reference."%'";
        $sql .= " AND type = 'LEASER'";
        $sql .= ' AND fk_fin_dossier <> '.$this->id;
        $sql .= " AND reference <> '' AND reference is not null";

        $resql = $db->query($sql);
        if($resql) {
            if($obj = $db->fetch_object($resql)) return true;
            return false;
        }
        else {
            dol_print_error($db);
            exit;
        }
    }

    function printOtherDossierLink(&$ref = '') {
        global $langs;

        if(! $this->isSimilarRefExists()) return '';

        if(empty($ref)) $ref = $this->financementLeaser->reference;

        $out = ' <a href="'.$_SERVER['PHP_SELF'].'?TListTBS[list_llx_fin_dossier][search][refDosLea]='.$ref.'">';
        $out .= '('.$langs->trans('OtherDossier').')';
        $out .= '</a>';

        return $out;
    }

    function checkRef(&$db) {
        if(! function_exists('switchEntity')) dol_include_once('/financement/lib/financement.lib.php');

        $TEntityShared = getOneEntityGroup($this->entity, 'fin_dossier', array(4, 17));
        $strEntityShared = implode(',', $TEntityShared);

        if($this->nature_financement == 'INTERNE') {

            $refClient = $this->financement->reference;
            $id_fin = (int) $this->financement->getId();

            if(! empty($refClient)) {
                $sql = 'SELECT count(df.rowid) as nb';
                $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
                $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (df.fk_fin_dossier=d.rowid)';
                $sql .= " WHERE df.type='CLIENT'";
                $sql .= " AND df.reference='".$refClient."'";
                $sql .= ' AND df.rowid!='.$id_fin;
                $sql .= ' AND d.entity IN ('.$strEntityShared.')';

                $db->Execute($sql);
                $obj = $db->Get_line();
                if($obj->nb > 0) return -1;
            }
        }

        $refLeaser = $this->financementLeaser->reference;
        $id_finLeaser = $this->financementLeaser->getId();

        if(! empty($refLeaser)) {
            $sql = 'SELECT count(df.rowid) as nb';
            $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
            $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (df.fk_fin_dossier=d.rowid)';
            $sql .= " WHERE df.type='LEASER'";
            $sql .= " AND df.reference='".$refLeaser."'";
            $sql .= ' AND df.rowid!='.$id_finLeaser;
            $sql .= ' AND d.entity IN ('.$strEntityShared.')';

            $db->Execute($sql);
            $obj = $db->Get_line();
            if($obj->nb > 0) return -2;
        }

        return true;
    }

    function save(&$db) {
        global $user;

        if(! $user->rights->financement->affaire->write) return false;
        $this->setNatureFinancementOnSimpleLink($db);

        $this->calculSolde();
        $this->calculRenta($db);
        if(! empty($this->financement)) $this->calculMontantRestantRelocation($this->financement);
        if(! empty($this->financementLeaser)) $this->calculMontantRestantRelocation($this->financementLeaser);

        $res = $this->checkRef($db);

        if($res === -1) {
            setEventMessage("Référence Client déjà utilisée ou en doublon", "errors");
            return false;
        }
        else if($res === -2) {
            setEventMessage("Référence Leaser déjà utilisée ou en doublon", "errors");
            return false;
        }

        parent::save($db);

        foreach($this->TLien as &$lien) {
            $lien->fk_fin_dossier = $this->getId();
            $lien->save($db);
        }

        // Calcul de la date et du numéro de prochaine échéance
        if($this->nature_financement == 'EXTERNE') {
            $this->financementLeaser->setEcheanceExterne();
            if(! empty($this->financement)) {
                $this->financement->to_delete = true;
                $this->financement->save($db);
            }
        }

        $this->financementLeaser->fk_fin_dossier = $this->getId();
        $this->financementLeaser->type = 'LEASER';
        $this->financementLeaser->save($db);

        if($this->nature_financement == 'INTERNE') {
            $this->financement->fk_fin_dossier = $this->getId();
            $this->financement->fk_soc = FIN_LEASER_DEFAULT;
            $this->financement->type = 'CLIENT';
            $this->financement->save($db);
        }
    }

    function calculSolde() {
        if($this->nature_financement == 'INTERNE') {
            $f = &$this->financement;
        }
        else {
            $f = &$this->financementLeaser;
        }

        $this->montant = $f->montant;
        $this->taux = $f->taux;
        $this->date_debut = $f->date_debut;
        $this->date_fin = $f->date_fin;
        $this->echeance = $f->echeance;
        $this->duree = $f->duree.' / '.$f->TPeriodicite[$f->periodicite];
        $this->incident_paiement = $f->incident_paiement;

        $this->somme_affaire = 0;

        foreach($this->TLien as &$lien) {

            $this->somme_affaire += $lien->affaire->montant;

            if($lien->affaire->nature_financement == 'INTERNE') {
                $this->nature_financement = 'INTERNE';
            }
        }

        $this->solde = $this->montant - $this->somme_affaire;// attention en cas d'affaire ajouté à la création du dossier ce chiffre sera faux, car non encore répercuté sur l'affaire

        // Calcul des sommes totales
        if(! empty($this->financement)) $this->financement->somme_echeance = $this->financement->duree * $this->financement->echeance;
        $this->financementLeaser->somme_echeance = $this->financementLeaser->duree * $this->financementLeaser->echeance;
    }

    function calculRenta(&$ATMdb) {
        $this->renta_previsionnelle = $this->getRentabilitePrevisionnelle();
        $this->renta_attendue = $this->getRentabiliteAttendue($ATMdb);
        $this->renta_reelle = $this->getRentabiliteReelle();

        $this->marge_previsionnelle = $this->getMargePrevisionnelle();
        $this->marge_attendue = $this->getMargeAttendue($ATMdb);
        $this->marge_reelle = $this->getMargeReelle();
    }

    function calculMontantRestantRelocation(TFin_financement &$financement) {
        global $db, $conf;

        $financement->encours_reloc = 0;

        $numLastEcheance = $financement->numero_prochaine_echeance - 1;

        if(! empty($financement->date_solde)) {
            $financement->reloc = 'NON';
            $financement->relocOK = 'OUI';

            return;
        }

        if($financement->relocOK == 'OUI' || $financement->duree <= 0 || $numLastEcheance < $financement->duree) {
            return;
        }

        $coeff = 1;

        $TFactures = &$this->TFacture;

        if($financement->type == 'LEASER') {
            $TFactures = &$this->TFactureFournisseur;

            $leaser = new Societe($db);
            $leaser->fetch($financement->fk_soc);
            if(empty($leaser->array_options) && method_exists($leaser, 'fetch_optionals')) {
                $leaser->fetch_optionals();
            }

            $coeff = (! empty($leaser->array_options['options_percent_relocation']) ? $leaser->array_options['options_percent_relocation'] : floatval($conf->global->FINANCEMENT_DEFAULT_EXTERNAL_RELOCATION_PERCENT)) / 100;
        }

        if(empty($TFactures[$numLastEcheance - 1])) {
            $financement->encours_reloc = price2num($coeff * $financement->echeance, 'MT');
        }
    }

    function load_facture(&$ATMdb, $all = false) {
        global $db;

        $this->somme_facture = 0;
        $this->somme_facture_reglee = 0;
        $this->TFacture = array();

        $sql = "SELECT fk_target";
        $sql .= " FROM ".MAIN_DB_PREFIX."element_element ee";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = ee.fk_target";
        $sql .= " WHERE sourcetype='dossier'";
        $sql .= " AND targettype='facture'";
        $sql .= " AND fk_source=".$this->getId();
        $sql .= ' AND f.entity IN('.getEntity('fin_dossier', true).')';
        $sql .= " ORDER BY f.facnumber ASC";

        $ATMdb->Execute($sql);

        dol_include_once("/compta/facture/class/facture.class.php");

        while($ATMdb->Get_line()) {
            $fact = new Facture($db);
            $fact->fetch($ATMdb->Get_field('fk_target'));
            if($fact->socid == $this->financementLeaser->fk_soc) continue; // Facture matériel associée au leaser, ne pas prendre en compte comme une facture client au sens CPRO

            $datePeriode = strtotime(implode('-', array_reverse(explode('/', $fact->ref_client))));
            $echeance = $this->_get_num_echeance_from_date($datePeriode);

            if(! $all) {
                $facidavoir = $fact->getListIdAvoirFromInvoice();

                foreach($facidavoir as $idAvoir) {
                    $avoir = new Facture($db);
                    $avoir->fetch($idAvoir);
                    $fact->total_ht += $avoir->total_ht;
                }

                if($fact->type == 0 && $fact->total_ht > 0) { // Récupération uniquement des factures standard et sans avoir qui l'annule complètement
                    $this->somme_facture += $fact->total_ht;
                    if($fact->paye == 1) $this->somme_facture_reglee += $fact->total_ht;

                    //TODO si plusieurs facture même échéance alors modification affichage pour afficher tous les liens
                    if(! empty($this->TFacture[$echeance])) {
                        if(is_array($this->TFacture[$echeance])) {
                            $this->TFacture[$echeance] = array_merge($this->TFacture[$echeance], array($fact));
                        }
                        else {
                            $this->TFacture[$echeance] = array($this->TFacture[$echeance], $fact);
                        }
                    }
                    else {
                        $this->TFacture[$echeance] = $fact;
                    }
                }
            }
            else {
                //TODO si plusieurs facture même échéance alors modification affichage pour afficher tous les liens
                if(! empty($this->TFacture[$echeance])) {
                    if(is_array($this->TFacture[$echeance])) {
                        $this->TFacture[$echeance] = array_merge($this->TFacture[$echeance], array($fact));
                    }
                    else {
                        $this->TFacture[$echeance] = array($this->TFacture[$echeance], $fact);
                    }
                }
                else {
                    $this->TFacture[$echeance] = $fact;
                }
            }
        }

        ksort($this->TFacture);
    }

    //Réorganisation spécifique pour l'affichage des factures intégrale
    function format_facture_integrale(&$ATMdb) {
        global $db;

        foreach($this->TFacture as $echeance => $Tfacture) {
            if(is_array($Tfacture)) {
                foreach($Tfacture as $k => $facture) {
                    //Si la facture est un avoir qui annule totalement la facture d'origine, on supprime l'avoir du tableau
                    if($facture->type == 2) {
                        $facture_origine = new Facture($db);
                        $facture_origine->fetch($facture->fk_facture_source);
                        if(abs($facture_origine->total_ht) == abs($facture->total_ht)) {
                            unset($this->TFacture[$echeance][$k]);
                        }
                    }
                    else {
                        $facidavoir = $facture->getListIdAvoirFromInvoice();

                        foreach($facidavoir as $idAvoir) {
                            $avoir = new Facture($db);
                            $avoir->fetch($idAvoir);

                            if(abs($avoir->total_ht) == abs($facture->total_ht)) {
                                unset($this->TFacture[$echeance][$k]);
                            }
                        }
                    }
                }
            }
        }
    }

    // Donne le numéro d'échéance correspondant à une date
    function _get_num_echeance_from_date($date) {
        if($this->nature_financement == 'EXTERNE') {
            $f = &$this->financementLeaser;
        }
        else {
            $f = &$this->financement;
        }

        if(strpos($date, '-')) $date = strtotime($date);
        if($date - ($f->date_debut + $this->financement->calage) < 0) {
            return -1;
        }

        $flag = true;
        $cpt = 0;
        $t = $f->date_debut + $f->calage;

        $iEcheance = 0;
        while($flag && $cpt < 100) {
            $t = strtotime('+'.$f->getiPeriode().'month', $t);
            if($t > $date) break;

            $iEcheance++;

            $cpt++;
        }

        return $iEcheance;
    }

    function load_factureFournisseur(&$ATMdb, $all = false) {
        global $db;

        $this->somme_facture_fournisseur = 0;
        $this->TFactureFournisseur = array();

        $sql = "SELECT fk_target";
        $sql .= " FROM ".MAIN_DB_PREFIX."element_element";
        $sql .= " WHERE sourcetype='dossier'";
        $sql .= " AND targettype='invoice_supplier'";
        $sql .= " AND fk_source=".$this->getId();

        $ATMdb->Execute($sql);

        dol_include_once("/fourn/class/fournisseur.facture.class.php");

        while($ATMdb->Get_line()) {
            $fact = new FactureFournisseur($db);
            $fact->fetch($ATMdb->Get_field('fk_target'));

            // Permet d'afficher la facture en face de la bonne échéance, le numéro de facture fournisseur finissant par /XX (XX est le numéro d'échéance)
            $TTmp = explode('/', $fact->ref_supplier);
            $echeance = array_pop($TTmp) - 1;

            if(! $all) {
                $facidavoir = $fact->getListIdAvoirFromInvoice();
                foreach($facidavoir as $idAvoir) {
                    $avoir = new FactureFournisseur($db);
                    $avoir->fetch($idAvoir);
                    $fact->total_ht += $avoir->total_ht;
                }

                if($fact->type == 0 && $fact->total_ht > 0) { // Récupération uniquement des factures standard et sans avoir qui l'annule complètement
                    $this->somme_facture_fournisseur += $fact->total_ht;
                    $this->TFactureFournisseur[$echeance] = $fact;
                }
            }
            else {
                $this->TFactureFournisseur[$echeance] = $fact;
            }
        }
    }

    function load_factureMateriel(&$ATMdb) {
        global $db;

        $sql = "SELECT fk_target";
        $sql .= " FROM ".MAIN_DB_PREFIX."element_element";
        $sql .= " WHERE sourcetype='dossier'";
        $sql .= " AND targettype='facture'";
        $sql .= " AND fk_source=".$this->getId();

        $ATMdb->Execute($sql);

        dol_include_once("/fourn/class/fournisseur.facture.class.php");

        while($ATMdb->Get_line()) {
            $fact = new FactureFournisseur($db);
            $fact->fetch($ATMdb->Get_field('fk_target'));
            if($fact->fk_soc == $this->financementLeaser->fk_soc) $this->facture_materiel = $fact;
        }
    }

    /**
     * TODO remove function
     * Attention, paramètre nature_financement prête à confusion :
     * Si INTERNE => utilisation de la penalité configurée sur la fiche tiers CPRO
     * Si EXTERNE => utilisation de la penalite configurée sur la fiche leaser
     */
    function getPenalite_old(&$ATMdb, $type, $nature_financement = 'INTERNE', $iPeriode = 0) {
        $g = new TFin_grille_leaser('PENALITE_'.$type);

        if($nature_financement == 'INTERNE' && $this->financement->id > 0) $f = &$this->financement;
        else $f = &$this->financementLeaser;

        $fk_soc = ($nature_financement == 'INTERNE') ? FIN_LEASER_DEFAULT : $f->fk_soc;

        $g->get_grille($ATMdb, $f->fk_soc, $this->contrat, '', array(), $this->entity);
        $coeff = (double) $g->get_coeff($ATMdb, $fk_soc, $this->contrat, $f->periodicite, $f->montant, $f->duree, $iPeriode, array(), $this->entity);

        return $coeff > 0 ? $coeff : 0;
    }

    function getPenalite(&$PDOdb, $type, $iPeriode = 0, $date_deb_periode = '', $coef_cpro = false) {
        $grille = new TFin_grille_leaser('PENALITE_'.$type);
        $grille->get_grille($PDOdb, $this->financementLeaser->fk_soc, $this->contrat, '', array(), $this->entity);
        $TCoeff = $grille->get_coeff($PDOdb, $this->financementLeaser->fk_soc, $this->contrat, $this->financementLeaser->periodicite, $this->financementLeaser->montant, $this->financementLeaser->duree, $iPeriode, array(), $this->entity);

        if($TCoeff == -1) $coeff = 0;
        else {
            $coeff = $TCoeff[0];

            if($coef_cpro && ! empty($date_deb_periode)) {
                $coeff = 0;
                if(in_array($this->entity, array(1, 2, 3))) $coeff = 3;
                if(in_array($this->entity, array(13, 14))) $coeff = 2;
                $date_application = $this->getDateApplicationPenInterne($PDOdb, $grille, $type, $this->financementLeaser->fk_soc, $this->contrat, $this->entity);
                if(strtotime($date_deb_periode) >= $date_application) $coeff = $TCoeff[1]; // Renvoi de la pénalité interne
            }
        }

        return $coeff;
    }

    function getMontantCommission() {
        $f = &$this->financement;

        return ($f->taux_commission / 100) * $f->montant;
    }

    // Récupère le coeff de renta attendue dans le tableau défini en admin
    function getRentabilite(&$ATMdb) {
        $g = new TFin_grille_leaser('RENTABILITE');
        $coeff = $g->get_coeff($ATMdb, $this->financement->fk_soc, $this->contrat, 'TRIMESTRE', $this->financement->montant, 5, 0, array(), $this->entity);

        return (is_array($coeff) && $coeff[0] > 0) ? $coeff[0] : 0;
    }

    function getRentabilitePrevisionnelle() {
        return $this->financement->somme_echeance - $this->financementLeaser->somme_echeance
            + $this->financement->reste - $this->financementLeaser->reste
            + $this->financement->frais_dossier - $this->financementLeaser->frais_dossier
            + $this->financement->loyer_intercalaire;
    }

    function getRentabiliteAttendue(&$ATMdb) {
        return $this->financement->montant * $this->getRentabilite($ATMdb) / 100;
    }

    function getRentabiliteReelle() {
        return $this->somme_facture_reglee - $this->somme_facture_fournisseur;
    }

    function getMargePrevisionnelle() {
        if(empty($this->financement->montant)) return 0;
        return $this->getRentabilitePrevisionnelle() / $this->financement->montant * 100;
    }

    function getMargeAttendue(&$ATMdb) {
        return $this->getRentabilite($ATMdb);
    }

    function getMargeReelle() {
        if(empty($this->financement->montant)) return 0;
        return $this->getRentabiliteReelle() / $this->financement->montant * 100;
    }

    function getRentabiliteReste(&$ATMdb) {
        $r = $this->getRentabiliteAttendue($ATMdb) - $this->getRentabiliteReelle();
        if($r < 0) $r = 0;
        return $r;
    }

    function getCRDandLRD($type, $iPeriode = 0) {
        if($type == 'LEASER') {
            $financement = &$this->financementLeaser;
        }
        else {
            $financement = &$this->financement;
        }

        $duree_restante = ($iPeriode == 0) ? $financement->duree_restante : $financement->duree - $iPeriode;

        $CRD = $financement->valeur_actuelle($duree_restante);
        $LRD = $financement->echeance * $duree_restante + $financement->reste;

        return array($CRD, $LRD);
    }

    function getDateApplicationPenInterne(&$PDOdb, &$grille, $type, $fk_soc, $type_contrat, $fk_entity) {
        $grille_date = new TFin_grille_leaser_date;
        $grille_date->loadByFkSocAndTypeContratAndEntity($PDOdb, $fk_soc, $type_contrat, $fk_entity);

        if($type == 'R') return $grille_date->date_start_pr;
        else return $grille_date->date_start_pnr; // $type == NR
    }

    /**
     * Type EXTERNE
     *  - CRD Leaser + Pénalité Leaser
     *
     * Type INTERNE
     *  - CRD Leaser + Pénalité Leaser
     *
     * Capé LRD Leaser
     */
    function getSolde_SNR_LEASER(&$PDOdb, $iPeriode, $duree_restante_leaser, $CRD_Leaser, $LRD_Leaser, $type_penalite = 'NR', $nature_financement = 'EXTERNE') {
        return $this->getSolde_SR_LEASER($PDOdb, $iPeriode, $duree_restante_leaser, $CRD_Leaser, $LRD_Leaser, $type_penalite, $nature_financement);
    }

    /**
     * Type EXTERNE
     *  - CRD Leaser + Pénalité Leaser
     *
     * Type INTERNE
     *  - CRD Leaser + pénalité Leaser
     *
     * Capé LRD Leaser
     */
    function getSolde_SR_LEASER(&$PDOdb, $iPeriode, $duree_restante_leaser, $CRD_Leaser, $LRD_Leaser, $type_penalite = 'R', $nature_financement = 'EXTERNE') {
        global $conf, $db;

        $temps_restant = ($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode();

        //FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH
        if($temps_restant <= $conf->global->FINANCEMENT_SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;
        if($this->financementLeaser->duree <= $iPeriode) return $this->financementLeaser->reste; // TODO check si ça doit rester

        $solde = $CRD_Leaser * (1 + $this->getPenalite($PDOdb, $type_penalite, $iPeriode) / 100); // Même avec un $this->nature_financement == 'INTERNE' on passe la valeur EXTERNE (l'ancien code renvoyé la même chose)
        $fk_leaser = $this->financementLeaser->fk_soc;
        $leaser = new Societe($db);
        $leaser->fetch($fk_leaser);
        if(empty($leaser->array_options)) $leaser->fetch_optionals();
        if(! empty($leaser->array_options['options_non_cape_lrd'])) $this->financementLeaser->cape_lrd = false;

        if($solde > $LRD_Leaser && $this->financementLeaser->cape_lrd) return $LRD_Leaser; // Capé LRD sauf si règle spécifique
        //Ticket 4622 : si solde calculé inférieur à la VR, alors solde = VR !!!! uniquement pour ABG
        else if($solde < $this->financementLeaser->reste) {
            return $this->financementLeaser->reste;
        }
        else return $solde;
    }

    /**
     * Type EXTERNE
     *  - CRD Leaser + Pénalité Leaser + Pénalité CPRO
     *
     * Type INTERNE
     *  - Adossé / Mandaté : CRD Client + % admin
     *  - Pure : LRD Client
     *  - Uniquement pour INTERNE => capé LRD Client
     */
    function getSolde_SR_CLIENT(&$PDOdb, $iPeriode, $duree_restante_leaser, $duree_restante_client, $LRD, $CRD, $CRD_Leaser, $LRD_Leaser, $nature_financement = 'EXTERNE') {
        global $conf, $db;

        $solde = 0;
        $capeLRD = true;
        if(in_array($this->entity, array(12, 15))) $capeLRD = false;

        if($nature_financement == 'EXTERNE') {
            $date_deb_periode = $this->getDateDebutPeriode($iPeriode - 1);
            $p = ($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode();
            $TSoldeRule = $this->getRuleSolde($p, $date_deb_periode);

            $fk_leaser = $this->financementLeaser->fk_soc;
            $leaser = new Societe($db);
            $leaser->fetch($fk_leaser);
            if(empty($leaser->array_options)) $leaser->fetch_optionals();

            if(! empty($leaser->array_options['options_non_cape_lrd'])) $capeLRD = false;

            if($TSoldeRule->base_solde == 'MF') {
                $solde = $this->financementLeaser->montant;
                $capeLRD = false;
            }
            else if($TSoldeRule->base_solde == 'CRD') {
                $solde = $CRD_Leaser * (1 + $TSoldeRule->percent / 100);
            }
            else if($TSoldeRule->base_solde == 'LRD') {
                $solde = $LRD_Leaser * (1 + $TSoldeRule->percent / 100);
            }
            else {
                if($p <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;

                if($this->financementLeaser->duree < $iPeriode) return $this->financementLeaser->reste; // TODO check si ça doit rester

                // Add Pen Leaser + CPro
                $solde = $CRD_Leaser * (1 + $this->getPenalite($PDOdb, 'R', $iPeriode, $date_deb_periode) / 100) * (1 + $this->getPenalite($PDOdb, 'R', $iPeriode, $date_deb_periode, true) / 100);

                if($solde > $LRD_Leaser && $capeLRD) return $LRD_Leaser; // Capé LRD dans tous les cas car solde vendeur
                //Ticket 4622 : si solde calculé inférieur à la VR, alors solde = VR !!!! uniquement pour ABG
                else if($solde < $this->financementLeaser->reste) {
                    return $this->financementLeaser->reste;
                }
                else return $solde;
            }

            // Mini VR
            if($solde < $this->financementLeaser->reste) {
                $solde = $this->financementLeaser->reste;
            }

            // Capé LRD
            if($solde > $LRD_Leaser && $capeLRD) $solde = $LRD_Leaser;
        }
        else // INTERNE
        {
            $p = ($this->financement->duree - $duree_restante_client) * $this->financement->getiPeriode();
            $date_deb_periode = $this->getDateDebutPeriode($iPeriode - 1, 'CLIENT');
            $TSoldeRule = $this->getRuleSolde($p, $date_deb_periode);

            $fk_soc = $this->financement->fk_soc;
            $soc = new Societe($db);
            $soc->fetch($fk_soc);
            if(empty($soc->array_options)) $soc->fetch_optionals();

            if(! empty($soc->array_options['options_non_cape_lrd'])) $capeLRD = false;

            if($TSoldeRule->base_solde == 'MF') {
                $solde = $this->financement->montant;
                $capeLRD = false;
            }
            else if($TSoldeRule->base_solde == 'CRD') {
                $solde = $CRD * (1 + $TSoldeRule->percent / 100);
            }
            else if($TSoldeRule->base_solde == 'LRD') {
                $solde = $LRD * (1 + $TSoldeRule->percent / 100);
            }
            else {
                if($p <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financement->montant;

                if(! empty($this->type_financement_affaire['ADOSSEE']) || ! empty($this->type_financement_affaire['MANDATEE'])) {
                    $solde = $CRD * (1 + $conf->global->FINANCEMENT_PERCENT_AUG_CRD / 100);
                }
                else if(! empty($this->type_financement_affaire['PURE'])) {
                    $solde = $LRD;
                }
                else // ['FINANCIERE']
                {
                    $solde = $LRD; // LRD client
                }

                if($solde > $LRD && $capeLRD) return $LRD; // Capé LRD dans tous les cas car solde vendeur
                //Ticket 4622 : si solde calculé inférieur à la VR, alors solde = VR !!!! uniquement pour ABG
                else if($solde < $this->financement->reste) {
                    $solde = $this->financement->reste;
                }
            }

            // Capé LRD
            if($solde > $LRD && $capeLRD) $solde = $LRD;

            // Mini VR
            if($solde < $this->financement->reste) {
                $solde = $this->financement->reste;
            }
        }

        return $solde;
    }

    /**
     * Type EXTERNE
     *  - LRD
     *
     * Type INTERNE
     *  - Adossé / Mandaté : CRD Client + % admin
     *  - Pure : LRD CSlient
     *  - Uniquement pour INTERNE => capé LRD Client
     */
    function getSolde_SNR_CLIENT(&$PDOdb, $iPeriode, $duree_restante_leaser, $duree_restante_client, $CRD, $LRD, $CRD_Leaser, $LRD_Leaser, $nature_financement = 'EXTERNE') {
        global $conf;

        $solde = 0;
        $capeLRD = true;
        if(in_array($this->entity, array(12, 15))) $capeLRD = false;

        if($nature_financement == 'EXTERNE') {
            $date_deb_periode = $this->getDateDebutPeriode($iPeriode - 1);
            $p = ($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode();
            $TSoldeRule = $this->getRuleSolde($p, $date_deb_periode);

            if($TSoldeRule->base_solde == 'MF') {
                $solde = $this->financementLeaser->montant;
                $capeLRD = false;
            }
            else if($TSoldeRule->base_solde == 'CRD') {
                $solde = $CRD_Leaser * (1 + $TSoldeRule->percent_nr / 100);
            }
            else if($TSoldeRule->base_solde == 'LRD') {
                $solde = $LRD_Leaser * (1 + $TSoldeRule->percent_nr / 100);
            }
            else {
                if($p <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;

                //Ticket 4622 : si solde calculé inférieur à la VR, alors solde = VR !!!! uniquement pour ABG
                if($LRD_Leaser < $this->financement->reste) {
                    return $this->financementLeaser->reste;
                }
                else return $LRD_Leaser;
            }

            // Mini VR
            if($solde < $this->financementLeaser->reste) {
                $solde = $this->financementLeaser->reste;
            }

            // Capé LRD
            if($solde > $LRD_Leaser && $capeLRD) $solde = $LRD_Leaser;
        }
        else // INTERNE
        {
            $date_deb_periode = $this->getDateDebutPeriode($iPeriode - 1, 'CLIENT');
            $p = ($this->financement->duree - $duree_restante_client) * $this->financement->getiPeriode();
            $TSoldeRule = $this->getRuleSolde($p, $date_deb_periode);

            // SPECIFIQUE LEASER HEXAPAGE => calculer le solde comme un externe avec la pénalité leaser
            if(in_array($this->financementLeaser->fk_soc, array(204904, 204905, 204906))) {
                $solde = $CRD * (1 + $this->getPenalite($PDOdb, 'NR', $iPeriode, '1998-07-12', true) / 100);
            }
            else if($TSoldeRule->base_solde == 'MF') {
                $solde = $this->financement->montant;
                $capeLRD = false;
            }
            else if($TSoldeRule->base_solde == 'CRD') {
                $solde = $CRD * (1 + $TSoldeRule->percent_nr / 100);
            }
            else if($TSoldeRule->base_solde == 'LRD') {
                $solde = $LRD * (1 + $TSoldeRule->percent_nr / 100);
            }
            else {
                if($p <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financement->montant;

                if(! empty($this->type_financement_affaire['ADOSSEE']) || ! empty($this->type_financement_affaire['MANDATEE'])) {
                    $solde = $CRD * (1 + (FINANCEMENT_PERCENT_AUG_CRD / 100));
                }
                else if(! empty($this->type_financement_affaire['PURE'])) {
                    $solde = $LRD;
                }
                else // ['FINANCIERE']
                {
                    $solde = $LRD;
                }

                if($solde > $LRD) $solde = $LRD; // Capé LRD sauf si ACECOM
                //Ticket 4622 : si solde calculé inférieur à la VR, alors solde = VR !!!! uniquement pour ABG
                else if($solde < $this->financement->reste) {
                    $solde = $this->financement->reste;
                }
            }

            // Mini VR
            if($solde < $this->financement->reste) {
                $solde = $this->financement->reste;
            }

            // Capé LRD
            if($solde > $LRD && $capeLRD) $solde = $LRD;
        }

        return $solde;
    }

    function getSolde_SR_NR_SAME($iPeriode, $duree_restante_client, $LRD, $LRD_leaser, $nature_financement = 'EXTERNE') {
        global $conf;

        //Calcul du Solde Renouvelant et Non Renouvelant CPRO
        $this->financement->capital_restant = $this->financement->montant;
        $this->financement->total_loyer = $this->financement->montant;

        for($i = 0 ; $i < $iPeriode ; $i++) {
            $capital_amortit = $this->financement->amortissement_echeance($i + 1, $this->financement->capital_restant);
            $part_interet = $this->financement->echeance - $capital_amortit;
            $this->financement->capital_restant -= $capital_amortit;
            $this->financement->total_loyer -= $this->financement->echeance;
        }

        $seuil_solde = $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH;
        $temps_restant = ($this->financement->duree - $duree_restante_client) * $this->financement->getiPeriode();

        if($temps_restant <= $seuil_solde) $solde = $this->financement->montant;
        else if($this->TLien[0]->affaire->type_financement == 'MANDATEE' || $this->TLien[0]->affaire->type_financement == 'ADOSSEE') $solde = $this->financement->capital_restant * (1 + (FINANCEMENT_PERCENT_AUG_CRD / 100));
        else if($this->TLien[0]->affaire->type_financement == 'PURE') $solde = $LRD;

        if($nature_financement == 'EXTERNE') return ($solde > $LRD_leaser && $solde != $this->financementLeaser->montant) ? $LRD_leaser : $solde;
        else return ($solde > $LRD && $solde != $this->financement->montant) ? $LRD : $solde; // INTERNE
    }

    /*****************************************************************************************/
    function getSolde(&$PDOdb, $type = 'SRBANK', $iPeriode = 0) {
        $duree_restante_leaser = ($iPeriode == 0) ? $this->financementLeaser->duree_restante : $this->financementLeaser->duree - $iPeriode;

        $CRD_Leaser = $this->financementLeaser->valeur_actuelle($duree_restante_leaser);
        $LRD_Leaser = $this->financementLeaser->echeance * $duree_restante_leaser + $this->financementLeaser->reste;

        $duree_restante_client = ($iPeriode == 0) ? $this->financement->duree_restante : $this->financement->duree - $iPeriode;

        $CRD = $this->financement->valeur_actuelle($duree_restante_client);
        if($duree_restante_client == 0) $CRD = 0;
        $LRD = $this->financement->echeance * $duree_restante_client + $this->financement->reste;

        // Montant presta ajouté au solde pour les entités TELECOM
        if($this->nature_financement == 'EXTERNE') {
            $mt_presta_restante = $this->financementLeaser->montant_prestation * $duree_restante_leaser;
        }
        else {
            $mt_presta_restante = $this->financement->montant_prestation * $duree_restante_client;
        }

        // Chargement des règle de solde (dictionnaire)
        $this->load_c_conf_solde();

        switch($type) {
            case 'SRBANK': //BANK = leaser sur le PDF
                $solde = $this->getSolde_SR_LEASER($PDOdb, $iPeriode, $duree_restante_leaser, $CRD_Leaser, $LRD_Leaser, 'R', $this->nature_financement);
                break;
            case 'SNRBANK':
                $solde = $this->getSolde_SNR_LEASER($PDOdb, $iPeriode, $duree_restante_leaser, $CRD_Leaser, $LRD_Leaser, 'NR', $this->nature_financement);
                break;
            case 'SRCPRO': // CPRO = client sur le PDF
                $solde = $this->getSolde_SR_CLIENT($PDOdb, $iPeriode, $duree_restante_leaser, $duree_restante_client, $LRD, $CRD, $CRD_Leaser, $LRD_Leaser, $this->nature_financement);
                // Spécifique Télécom, on ajoute au solde la maintenance restante
                if($this->entity == 3 || $this->entity == 10) {
                    $solde += $mt_presta_restante;
                }
                break;
            case 'SNRCPRO':
                $solde = $this->getSolde_SNR_CLIENT($PDOdb, $iPeriode, $duree_restante_leaser, $duree_restante_client, $CRD, $LRD, $CRD_Leaser, $LRD_Leaser, $this->nature_financement);
                // Spécifique Télécom, on ajoute au solde la maintenance restante
                if($this->entity == 3 || $this->entity == 10) {
                    $solde += $mt_presta_restante;
                }
                break;
            case 'SRNRSAME': // [PH] case dernièrement ajouté par Geoffrey qui remplacement selon moi SRCPRO et SNRCPRO mais qui n'est plus à utiliser
                $solde = $this->getSolde_SR_NR_SAME($iPeriode, $duree_restante_client, $LRD, $LRD_leaser, $this->nature_financement);
                break;
            case 'perso':
                $solde = $this->soldeperso;
                break;
        }

        return ($solde > 0) ? $solde : 0;
    }
    /*****************************************************************************************/

    // TODO remove
    function getSolde_old($ATMdb, $type = 'SRBANK', $iPeriode = 0) {
        global $conf;

        $duree_restante_leaser = ($iPeriode == 0) ? $this->financementLeaser->duree_restante : $this->financementLeaser->duree - $iPeriode;

        $CRD_Leaser = $this->financementLeaser->valeur_actuelle($duree_restante_leaser);
        $LRD_Leaser = $this->financementLeaser->echeance * $duree_restante_leaser + $this->financementLeaser->reste;

        // MKO 13.09.19 : base de calcul différente en fonction du leaser : voir fichier config
        global $TLeaserTypeSolde;
        $baseCalcul = $CRD_Leaser;
        if(! empty($TLeaserTypeSolde[$this->financementLeaser->fk_soc]) && $TLeaserTypeSolde[$this->financementLeaser->fk_soc] == 'LRD') {
            $baseCalcul = $LRD_Leaser;
        }

        $duree_restante_client = ($iPeriode == 0) ? $this->financement->duree_restante : $this->financement->duree - $iPeriode;

        $CRD = $this->financement->valeur_actuelle($duree_restante_client);
        $LRD = $this->financement->echeance * $duree_restante_client + $this->financement->reste;

        switch($type) {
            case 'SRBANK':/* réel renouvellant */
                if((($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode()) <= $conf->global->FINANCEMENT_SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;
                if($this->financementLeaser->duree <= $iPeriode) return $this->financementLeaser->reste;

                if($this->nature_financement == 'INTERNE') {
                    return $baseCalcul * (1 + $this->getPenalite($ATMdb, 'R', 'EXTERNE', $iPeriode) / 100);
                }
                else {
                    return $baseCalcul * (1 + $this->getPenalite($ATMdb, 'R', 'EXTERNE', $iPeriode) / 100);
                }

                break;
            case 'SNRBANK': /* réel non renouvellant */
                if((($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode()) <= $conf->global->FINANCEMENT_SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;
                if($this->financementLeaser->duree <= $iPeriode) return $this->financementLeaser->reste;

                if($this->nature_financement == 'INTERNE') {
                    return $baseCalcul * (1 + $this->getPenalite($ATMdb, 'NR', 'EXTERNE', $iPeriode) / 100);
                }
                else {
                    return $baseCalcul * (1 + $this->getPenalite($ATMdb, 'NR', 'EXTERNE', $iPeriode) / 100);
                }

                break;
            /* ******************************************************************************************************
			 * TODO ne sert actuellement plus mais par sécurité on conserve
			 * *****************************************************************************************************/
            case 'SNRCPRO': /* Vendeur non renouvellant */

                if($this->nature_financement == 'INTERNE') {
                    if((($this->financement->duree - $duree_restante_client) * $this->financement->getiPeriode()) <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financement->montant;
                    if($this->financement->duree < $iPeriode) return $this->financement->reste;
                }
                else {
                    if((($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode()) <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;
                    if($this->financementLeaser->duree < $iPeriode) return $this->financementLeaser->reste;
                }

                if($this->nature_financement == 'INTERNE') {
                    return $LRD;
                }
                else {
                    return $LRD_Leaser;
                }
                break;

            case 'SRCPRO': /* Vendeur renouvellant */

                if($this->nature_financement == 'INTERNE') {
                    if((($this->financement->duree - $duree_restante_client) * $this->financement->getiPeriode()) <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financement->montant;
                    if($this->financement->duree < $iPeriode) return $this->financement->reste;
                }
                else {
                    if((($this->financementLeaser->duree - $duree_restante_leaser) * $this->financementLeaser->getiPeriode()) <= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) return $this->financementLeaser->montant;
                    if($this->financementLeaser->duree < $iPeriode) return $this->financementLeaser->reste;
                }

                if($this->nature_financement == 'INTERNE') {

                    $rentabiliteReste = $this->getRentabiliteReste($ATMdb);

                    $solde = $CRD + ($rentabiliteReste > ($CRD * CRD_COEF_RENTA_ATTEINTE) ? $rentabiliteReste : $CRD * CRD_COEF_RENTA_ATTEINTE) + $this->getMontantCommission();

                    return ($solde > $LRD && $solde != $this->financement->montant) ? $LRD : $solde;
                }
                else {

                    $nb_periode_passe = $this->financementLeaser->duree_passe;
                    if($iPeriode > 0) $nb_periode_passe++;
                    $nb_month = (($nb_periode_passe - 1) * $this->financementLeaser->getiPeriode());
                    $dateProchaine = strtotime('+'.$nb_month.' month', $this->date_debut + $this->calage);

                    $solde = ($baseCalcul * (1 + $this->getPenalite($ATMdb, 'R', 'EXTERNE', $iPeriode) / 100)) + $this->financementLeaser->reste;
                    if($dateProchaine > strtotime('2016-07-01') || $conf->entity == 1) {
                        $solde *= (1 + $this->getPenalite($ATMdb, 'R', 'INTERNE', $iPeriode) / 100);
                    }

                    $solde = ($solde > $LRD_Leaser && $solde != $this->financementLeaser->montant) ? $LRD_Leaser : $solde;

                    return ($solde > 0) ? $solde : 0;
                }

                break;

            /**********************************************************************************************************************
             * TODO FIN ne sert actuellement plus
             * ********************************************************************************************************************/
            case 'perso': /* solde personnalisé */

                return $this->soldeperso;

                break;
            case 'SRNRSAME':
                //Calcul du Solde Renouvelant et Non Renouvelant CPRO
                $this->financement->capital_restant = $this->financement->montant;
                $this->financement->total_loyer = $this->financement->montant;
                for($i = 0 ; $i < $iPeriode ; $i++) {
                    $capital_amortit = $this->financement->amortissement_echeance($i + 1, $this->financement->capital_restant);
                    $part_interet = $this->financement->echeance - $capital_amortit;
                    $this->financement->capital_restant -= $capital_amortit;

                    $this->financement->total_loyer -= $this->financement->echeance;
                }

                $seuil_solde = $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH;

                if((($this->financement->duree - $duree_restante_client) * $this->financement->getiPeriode()) <= $seuil_solde) {
                    $solde = $this->financement->montant;
                }
                else if($this->TLien[0]->affaire->type_financement == 'MANDATEE' || $this->TLien[0]->affaire->type_financement == 'ADOSSEE') {
                    $solde = $this->financement->capital_restant * (1 + (FINANCEMENT_PERCENT_AUG_CRD / 100));
                }
                else if($this->TLien[0]->affaire->type_financement == 'PURE') {
                    $solde = $LRD;
                }

                if($this->nature_financement == 'INTERNE') {
                    return ($solde > $LRD && $solde != $this->financement->montant) ? $LRD : $solde;
                }
                else {
                    return ($solde > $LRD_leaser && $solde != $this->financementLeaser->montant) ? $LRD_leaser : $solde;
                }
                break;
        }
    }

    public function _add_month($how_many_month, $time) {
        $time_result = strtotime('+'.$how_many_month.' month', $time);

        if(date('d', $time) == date('t', $time) || date('d', $time) > date('d', $time_result)) {
            $time1 = strtotime(date('Y-m-01', $time));
            $time2 = strtotime('+'.$how_many_month.' month', $time1);
            $time = strtotime(date('Y-m-t', $time2));
        }
        else {
            $time = $time_result;
        }

        return $time;
    }

    function echeancier(&$ATMdb, $type_echeancier = 'CLIENT', $echeanceInit = 1, $return = false, $withSolde = true) {
        global $conf;

        if($type_echeancier == 'CLIENT') $f = &$this->financement;
        else $f = &$this->financementLeaser;

        /*
		 * Affiche l'échéancier
		 * ----
		 * Périodes
		 * Dates des Loyers
		 * Période
		 * Valeurs de Rachat - Pénal 8.75%
		 * Capital Résid.Risque Résid. HT
		 * Amortissmt Capital HT
		 * Part Intérêts
		 * Assurance
		 * Loyers HT
		 * Loyers TTC
		 */
        $total_capital_amorti = 0;
        $total_part_interet = 0;
        $total_assurance = 0;
        $total_loyer = 0;
        $total_facture = 0;
        $capital_restant_init = $f->montant;
        $capital_restant = $capital_restant_init;
        $f->capital_restant = $capital_restant;
        $f->total_loyer = $f->montant;
        $TLigne = array();

        $lastEcheance = max($f->duree, $f->numero_prochaine_echeance - 1);

        for($i = ($echeanceInit - 1) ; $i < $lastEcheance ; $i++) {
            $time = $this->_add_month($i * $f->getiPeriode(), $f->date_debut + $f->calage);

            $capital_amorti = $f->amortissement_echeance($i + 1, $capital_restant);
            $part_interet = $f->echeance - $capital_amorti;

            $capital_restant -= $capital_amorti;
            $f->capital_restant = $capital_restant;
            $total_loyer += $f->echeance;

            $f->total_loyer -= $f->echeance;

            $total_assurance += $f->assurance;
            $total_capital_amorti += $capital_amorti;
            $total_part_interet += $part_interet;

            // Construction donnée pour échéancier
            $data = array(
                'date' => date('d/m/Y', $time)
                /*,'valeur_rachat'=>$capital_restant*$this->getPenalite($ATMdb,'NR')*/
                , 'capital' => ($i < $f->duree ? $capital_restant : ' - ')
                , 'amortissement' => ($i < $f->duree ? $capital_amorti : ' - ')
                , 'interet' => ($i < $f->duree ? $part_interet : ' - ')
                , 'assurance' => ($i < $f->duree ? $f->assurance : ' - ')
                , 'loyerHT' => $f->echeance
                , 'loyer' => ($f->echeance + $f->assurance) * FIN_TVA_DEFAUT
            );

            // Ajout factures liées au dossier
            $iFacture = $i;

            $fact = false;
            if($type_echeancier == 'CLIENT' && ! empty($this->TFacture[$iFacture])) {
                $fact = $this->TFacture[$iFacture];
            }
            else if($type_echeancier == 'LEASER' && ! empty($this->TFactureFournisseur[$iFacture])) $fact = $this->TFactureFournisseur[$iFacture];

            if(is_object($fact)) { // Financement Client avec une seule facture
                $data['facture_total_ht'] = $fact->total_ht;
                $data['facture_multiple'] = '0';
                $data['facture_link'] = ($type_echeancier == 'CLIENT') ? DOL_URL_ROOT.'/compta/facture.php?facid=' : DOL_URL_ROOT.'/fourn/facture/card.php?facid=';
                $data['facture_link'] .= $fact->id;
                $data['facture_bg'] = ($fact->paye == 1) ? '#00FF00' : '#FF0000';
                // Côté client, affichage en bleu si facture créée manuellement
                $data['facture_bg'] = (! empty($fact->user_author) && $fact->user_author != 1 && $type_echeancier == 'CLIENT') ? '#00CCFF' : $data['facture_bg'];
            }
            else if(is_array($fact)) { // Financement Client avec plusieurs factures
                foreach($fact as $facture_client) {
                    $data['facture_total_ht'] += $facture_client->total_ht;
                    $data['facture_multiple'] = '1';
                    $bg_color = ($facture_client->paye == 1) ? '#00FF00' : '#FF0000';
                    $data['facture_link'] .= ($type_echeancier == 'CLIENT') ? '<a style="display:block;margin:0;background-color:'.$bg_color.'" href="'.DOL_URL_ROOT.'/compta/facture.php?facid=' : '<a href="'.DOL_URL_ROOT.'/fourn/facture/fiche.php?facid=';
                    $data['facture_link'] .= $facture_client->id.'">'.number_format($facture_client->total_ht, 2, ',', '').' €</a>';
                    $data['facture_bg'] = ($bg_color === '#FF0000') ? '#CC9933' : '#00FF00';
                }
            }
            else if($type_echeancier == 'CLIENT' && $this->nature_financement == 'INTERNE' && $time < time() && $f->date_solde <= 0 && $f->montant_solde == 0) {
                $link = dol_buildpath('/financement/dossier.php?action=new_facture_client&id_dossier='.$this->rowid.'&echeance='.($i + 1), 1);
                $data['facture_total_ht'] = '+';
                $data['facture_multiple'] = '0';
                $data['facture_link'] = $link;
                $data['facture_bg'] = '';
            }
            else if($type_echeancier == 'LEASER' && $this->nature_financement == 'INTERNE' && $time < time() && $f->date_solde <= 0 && $f->montant_solde == 0) {
                $link = dol_buildpath('/financement/dossier.php?action=new_facture_leaser&id_dossier='.$this->rowid.'&echeance='.($i + 1), 1);
                $data['facture_total_ht'] = '+';
                $data['facture_multiple'] = '0';
                $data['facture_link'] = $link;
                $data['facture_bg'] = '';
            }
            else {
                $data['facture_total_ht'] = '';
                $data['facture_multiple'] = '0';
                $data['facture_link'] = '';
                $data['facture_bg'] = '';
            }
            $total_facture += $fact->total_ht;

            if($withSolde) {
                // Ajout des soldes par période
                global $db;
                $form = new Form($db);
                $htmlSoldes = '<table>';

                $seuil_solde = $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH;

                if($type_echeancier == 'CLIENT') {
                    $SR = $this->getSolde($ATMdb, 'SRCPRO', $i + 1);
                    $SNR = $this->getSolde($ATMdb, 'SNRCPRO', $i + 1);

                    $htmlSoldes .= '<tr><td colspan="2" align="center">Apr&egrave;s l\'&eacute;ch&eacute;ance n&deg;'.($i + 1).'</td></tr>';
                    $htmlSoldes .= '<tr><td>Solde renouvellant : </td><td align="right"><strong>'.number_format($SR, 2, ',', ' ').' &euro;</strong></td></tr>';
                    $htmlSoldes .= '<tr><td>Solde non renouvellant : </td><td align="right"><strong>'.number_format($SNR, 2, ',', ' ').' &euro;</strong></td></tr>';
                }
                else {
                    //Ticket 3049
                    $SR = $this->getSolde($ATMdb, 'SRBANK', $i + 1);
                    $SNR = $this->getSolde($ATMdb, 'SNRBANK', $i + 1);

                    if($this->nature_financement == 'EXTERNE') {
                        $SRcpro = $this->getSolde($ATMdb, 'SRCPRO', $i + 1);
                        $SNRcpro = $this->getSolde($ATMdb, 'SNRCPRO', $i + 1);
                    }

                    $htmlSoldes .= '<tr><td colspan="2" align="center">Apr&egrave;s l\'&eacute;ch&eacute;ance n&deg;'.($i + 1).'</td></tr>';
                    if($this->nature_financement == 'EXTERNE') {
                        $htmlSoldes .= '<tr><td>Solde renouvellant CPRO : </td><td align="right"><strong>'.number_format($SRcpro, 2, ',', ' ').' &euro;</strong></td></tr>';
                        $htmlSoldes .= '<tr><td>Solde non renouvellant CPRO : </td><td align="right"><strong>'.number_format($SNRcpro, 2, ',', ' ').' &euro;</strong></td></tr>';
                    }
                    $htmlSoldes .= '<tr><td>Solde renouvellant LEASER : </td><td align="right"><strong>'.number_format($SR, 2, ',', ' ').' &euro;</strong></td></tr>';
                    $htmlSoldes .= '<tr><td>Solde non renouvellant LEASER : </td><td align="right"><strong>'.number_format($SNR, 2, ',', ' ').' &euro;</strong></td></tr>';
                }
                $htmlSoldes .= '</table>';
                $data['soldes'] = htmlentities($htmlSoldes);
            }

            $TLigne[] = $data;
        }
        $f->somme_echeance = $total_loyer;
        $total_loyer += $f->reste;

        $TBS = new TTemplateTBS;

        $autre = array(
            'reste' => $f->reste
            , 'resteTTC' => ($f->reste * FIN_TVA_DEFAUT)
            , 'capitalInit' => $capital_restant_init
            , 'total_capital_amortit' => $total_capital_amorti
            , 'total_part_interet' => $total_part_interet
            , 'total_loyer' => $total_loyer
            , 'total_assurance' => $total_assurance
            , 'total_facture' => $total_facture
            , 'loyer_intercalaire' => $f->loyer_intercalaire
            , 'nature_financement' => $this->nature_financement
            , 'date_debut' => date('d/m/Y', $f->date_debut)
        );

        if($f->loyer_intercalaire > 0) {
            $fact = false;
            if($type_echeancier == 'CLIENT' && ! empty($this->TFacture[-1])) $fact = $this->TFacture[-1];
            else if($type_echeancier == 'LEASER' && ! empty($this->TFactureFournisseur[-1])) $fact = $this->TFactureFournisseur[-1];

            if(is_object($fact)) {
                $autre['loyer_intercalaire_facture_total_ht'] = $fact->total_ht;
                $autre['loyer_intercalaire_facture_link'] = ($type_echeancier == 'CLIENT') ? DOL_URL_ROOT.'/compta/facture.php?facid=' : DOL_URL_ROOT.'/fourn/facture/card.php?facid=';
                $autre['loyer_intercalaire_facture_link'] .= $fact->id;
                $autre['loyer_intercalaire_facture_bg'] = ($fact->paye == 1) ? '#00FF00' : '#FF0000';
                $autre['loyer_intercalaire_facture_bg'] = (! empty($fact->user_author) && $fact->user_author != 1 && $type_echeancier == 'CLIENT') ? '#00CCFF' : $data['loyer_intercalaire_facture_bg'];
                $autre['total_facture'] += $fact->total_ht;
                $autre['total_loyer'] += $f->loyer_intercalaire;
            }
            else {
                $link = dol_buildpath('/financement/dossier.php?action=new_facture_client&id_dossier='.$this->rowid.'&echeance=-1', 1);
                $autre['loyer_intercalaire_facture_total_ht'] = '+';
                $autre['loyer_intercalaire_facture_multiple'] = '0';
                $autre['loyer_intercalaire_facture_link'] = $link;
                $autre['loyer_intercalaire_facture_bg'] = '';
            }
        }
        else {
            $autre['loyer_intercalaire_facture_total_ht'] = 0;
            $autre['loyer_intercalaire_facture_link'] = '';
            $autre['loyer_intercalaire_facture_bg'] = '';
        }

        if($return) {
            return array(
                'ligne' => $TLigne
                , 'autre' => $autre
            );
        }
        else {
            return $TBS->render('./tpl/echeancier.tpl.php'
                , array(
                    'ligne' => $TLigne
                )
                , array(
                    'autre' => $autre
                )
            );
        }
    }

    function generate_factures_leaser($paid = false, $delete_all = false) {
        if($delete_all) {
            foreach($this->TFactureFournisseur as $fact) {
                $fact->delete($fact->rowid);
                $fact->deleteObjectLinked();
            }

            $this->financementLeaser->initEcheance();
        }

        $res = '';
        $f = &$this->financementLeaser;

        $cpt = 0;
        while($f->date_prochaine_echeance < time() && ($f->date_prochaine_echeance < $f->date_solde || $f->date_solde <= 0) && $f->numero_prochaine_echeance <= $f->duree && $cpt < 50) { // On ne créé la facture que si l'échéance est passée et qu'il en reste
            $facture = $this->create_facture_leaser($paid);
            $f->setEcheance(1, true);
            $cpt++;
        }

        if($cpt == 50) print "Erreur cycle infini dans generate_factures_leaser()<br />";
    }

    private function create_facture_leaser_addline(&$echeance, &$f, &$d, &$object, &$res, &$user, $validate, $date, $paid = false) {
        global $db;

        // TVA
        $tva = (FIN_TVA_DEFAUT - 1) * 100;
        if($date < strtotime('2014-01-01')) $tva = 19.6;
        $object->fetch_thirdparty();
        if($object->thirdparty->country_id != 1) $tva = 0; // Si Leaser pas en France, pas de TVA

        if($f->frais_dossier > 0 && (($echeance == 1 && $f->loyer_intercalaire == 0) || ($echeance == 0 && $f->loyer_intercalaire > 0))) {
            /* Ajoute les frais de dossier uniquement sur la 1ère facture */
            $res .= "Ajout des frais de dossier<br />";
            $fk_product = FIN_PRODUCT_FRAIS_DOSSIER;
            // Pour export compta ABG
            if($object->entity == 5) $fk_product = FIN_PRODUCT_ABG;
            $result = $object->addline("", $f->frais_dossier, $tva, 0, 0, 1, $fk_product);
        }

        /* Ajout la ligne de l'échéance	*/
        $fk_product = 0;
        if(! empty($d->TLien[0]->affaire)) {
            if($d->TLien[0]->affaire->type_financement == 'ADOSSEE') $fk_product = FIN_PRODUCT_LOC_ADOSSEE;
            else if($d->TLien[0]->affaire->type_financement == 'MANDATEE') $fk_product = FIN_PRODUCT_LOC_MANDATEE;
        }

        // Pour export compta ABG
        if($object->entity == 5) $fk_product = FIN_PRODUCT_ABG;

        if($echeance == -1 && $f->loyer_intercalaire > 0) {
            $result = $object->addline("Echéance de loyer intercalaire banque", $f->loyer_intercalaire, $tva, 0, 0, 1, $fk_product);
        }
        else {
            $result = $object->addline("Echéance de loyer banque", $f->echeance, $tva, 0, 0, 1, $fk_product);
        }

        if($validate) {
            $result = $object->validate($user, '', 0);
        }

        if($paid) {
            $result = $object->set_paid($user); // La facture reste en impayée pour le moment, elle passera à payée lors de l'export comptable
        }

        $res .= "Création facture fournisseur ($id) : ".$object->ref."<br />";
    }

    private function create_facture_client_addline(&$echeance, &$f, &$d, &$object, &$res, &$user, $validate, $date, $paid = false) {
        $tva = (FIN_TVA_DEFAUT - 1) * 100;
        if($date < strtotime('2014-01-01')) $tva = 19.6;

        /* Ajout la ligne de l'échéance	*/
        $fk_product = 0;
        if(! empty($d->TLien[0]->affaire)) {
            if($d->TLien[0]->affaire->type_financement == 'ADOSSEE') $fk_product = 667;
            else if($d->TLien[0]->affaire->type_financement == 'MANDATEE') $fk_product = 667;
        }

        if($echeance == -1 && $f->loyer_intercalaire > 0) {
            $result = $object->addline("Echéance de loyer intercalaire", $f->loyer_intercalaire, 1, $tva, 0, 0, $fk_product);
        }
        else {
            $result = $object->addline("Echéance de loyer", $f->echeance, 1, $tva, 0, 0, $fk_product);
        }

        if($validate) {
            $result = $object->validate($user, '', 0);
        }

        if($paid) {
            $result = $object->set_paid($user); // La facture reste en impayée pour le moment, elle passera à payée lors de l'export comptable
        }

        $TFactures = &$this->TFacture;
        if($f->type == 'LEASER') $TFactures = &$this->TFactureFournisseur;

        if(is_array($TFactures[$echeance])) {
            $TFactures[$echeance][] = $object;
        }
        else if(! empty($TFactures[$echeance])) {
            $TFactures[$echeance] = array($TFactures[$echeance], $object);
        }
        else {
            $TFactures[$echeance] = $object;
        }

        $res .= "Création facture client ($id) : ".$object->ref."<br />";

        return $res;
    }

    function create_facture_leaser($paid = false, $validate = true, $echeance = 0, $date = 0) {
        global $user, $db, $conf;

        $d = &$this;
        $f = &$this->financementLeaser;

        $res = '';

        // Ajout pour gérer création facture manuelle
        if(empty($echeance)) $echeance = $f->duree_passe + 1;
        if(empty($date)) $date = $f->date_prochaine_echeance;

        $object = new FactureFournisseur($db);

        $reference = $f->reference.'/'.$echeance;

        $createFacture = true;
        $object->fetch(null, $reference);
        if($object->id > 0) {

            $object->fetchObjectLinked();
            $TIdAvoir = $object->getListIdAvoirFromInvoice();

            if($this->rowid == $object->linkedObjectsIds['dossier'][0] && empty($TIdAvoir)) {

                $createFacture = false;
                $object->origin = 'dossier';
                $object->origin_id = $d->getId();
                $object->deleteObjectLinked();
                $object->add_object_linked(); // Ajout de la liaison éventuelle vers ce dossier
                $res .= "Erreur facture fournisseur déjà existante : ".$object->ref."<br />";
            }
            else {
                $createFacture = true;
            }
        }

        if($createFacture && $this->financementLeaser->echeance > 0) {

            $object = new FactureFournisseur($db);

            $object->ref_supplier = $reference;
            $object->ref = $reference;
            $object->socid = $f->fk_soc;
            $object->libelle = "ECH DOS. ".$d->reference_contrat_interne." ".$echeance."/".$f->duree;
            $object->date = $date;
            $object->date_echeance = $date;
            $object->note_public = '';
            $object->origin = 'dossier';
            $object->origin_id = $d->getId();

            // Période de la facture
            $date_debut_periode = $this->getDateDebutPeriode($echeance - 1);
            $date_fin_periode = $this->getDateFinPeriode($echeance - 1);
            $object->array_options['options_date_debut_periode'] = $date_debut_periode;
            $object->array_options['options_date_fin_periode'] = $date_fin_periode;

            // Permet la création d'une facture leaser dans l'entité du dossier
            $curEntity = $conf->entity;
            $conf->entity = $d->entity;
            $id = $object->create($user);

            if($id > 0) {
                $this->create_facture_leaser_addline($echeance, $f, $d, $object, $res, $user, $validate, $date);
            }
        }

        $conf->entity = $curEntity;

        return $object;
    }

    function create_facture_client($paid = false, $validate = true, $echeance = 0, $date = 0) {
        global $user, $db, $conf;

        $d = &$this;
        $f = &$this->financement;

        $res = '';

        // Ajout pour gérer création facture manuelle
        if(empty($echeance)) $echeance = $this->_get_num_echeance_from_date($date);
        if($echeance == -1) $ech = 0;
        else $ech = $echeance;
        if(empty($date)) $date = $this->getDateDebutPeriode($ech - 1, 'CLIENT');

        $object = new Facture($db);

        $reference = $f->reference.'/'.$echeance;

        $createFacture = true;
        $object->fetch(null, $reference);
        if($object->id > 0) {

            $object->fetchObjectLinked();
            $TIdAvoir = $object->getListIdAvoirFromInvoice();

            if($this->rowid == $object->linkedObjectsIds['dossier'][0] && empty($TIdAvoir)) {

                $createFacture = false;
                $object->origin = 'dossier';
                $object->origin_id = $d->getId();
                $object->deleteObjectLinked();
                $object->add_object_linked(); // Ajout de la liaison éventuelle vers ce dossier
                $res .= "Erreur facture client déjà existante : ".$object->ref."<br />";
            }
            else {
                $createFacture = true;
            }
        }

        if($createFacture && $this->financement->echeance > 0) {

            $object = new Facture($db);

            $object->ref_client = date('d/m/Y', strtotime($date));
            $object->socid = $d->TLien[0]->affaire->fk_soc;
            $object->date = time();
            $object->note_public = '';
            $object->origin = 'dossier';
            $object->origin_id = $d->getId();
            $object->array_options['options_visa_renta_loyer_leaser'] = 1;
            $object->array_options['options_visa_renta_loyer_client'] = 1;

            // Permet la création d'une facture leaser dans l'entité du dossier
            $curEntity = $conf->entity;
            $conf->entity = $d->entity;
            $id = $object->create($user);

            $object->add_object_linked($object->origin, $object->origin_id);
            $object->ref = $reference;

            if($id > 0) {
                $res = $this->create_facture_client_addline($echeance, $f, $d, $object, $res, $user, $validate, time(), $paid);
            }
        }

        $conf->entity = $curEntity;

        return $object;
    }

    function getDateDebutPeriode($echeance, $type = 'LEASER') {
        if($type == 'LEASER') {
            $date = date('Y-m-d', $this->financementLeaser->date_debut + $this->financementLeaser->calage);
            $date = date('Y-m-d', $this->_add_month($echeance * $this->financementLeaser->getiPeriode(), strtotime($date)));
        }
        else {
            $date = date('Y-m-d', $this->financement->date_debut + $this->financement->calage);
            $date = date('Y-m-d', $this->_add_month($echeance * $this->financement->getiPeriode(), strtotime($date)));
        }

        return $date;
    }

    function getDateFinPeriode($echeance, $type = 'LEASER') {
        $date = $this->getDateDebutPeriode($echeance + 1, $type);
        $date = date('Y-m-d', strtotime('-1 day', strtotime($date)));

        return $date;
    }

    //Retourne le volume (noir + couleur) réalisé, le volume noir engagé et le colument couleur engagé sur les 4 dernière échéances du dossier
    function getSommesIntegrale(&$PDOdb, $copiesup = false) {
        global $conf;

        $sommeRealise = $sommeNoir = $sommeCouleur = $sommeCopieSupNoir = $sommeCopieSupCouleur = 0;
        $nbEcheance = count($this->TFacture) - 1; //-1 car échéance 1 = 0
        $nbEcheance = $this->financement->numero_prochaine_echeance - 1;

        foreach($this->TFacture as $echeance => $Tfacture) {
            if($echeance == -1) $nbEcheance -= 1; //supression loyer intercalaire

            //Somme uniquement sur les 4 dernières échéances
            if($echeance > ($nbEcheance - $conf->global->FINANCEMENT_NB_TRIM_COPIES_SUP)) {
                if(is_array($Tfacture)) {
                    foreach($Tfacture as $k => $facture) {
                        $integrale = new TIntegrale;
                        $integrale->loadBy($PDOdb, $facture->ref, 'facnumber');

                        //Somme Réalisé = somme réalisé noir + somme réalisé couleur
                        $sommeRealise += $integrale->vol_noir_realise;
                        $sommeRealise += $integrale->vol_coul_realise;

                        //Somme engagé Noir
                        $sommEngageNoir += $integrale->vol_noir_engage;

                        //Somme engagé Couleur
                        $sommeEngageCouleur += $integrale->vol_coul_engage;

                        //Copie suplémantaire
                        $sommeCopieSupNoir += $integrale->vol_noir_facture - $integrale->vol_noir_engage;
                        $sommeCopieSupCouleur += $integrale->vol_coul_facture - $integrale->vol_coul_engage;
                    }
                }
                else {
                    $integrale = new TIntegrale;
                    $integrale->loadBy($PDOdb, $Tfacture->ref, 'facnumber');

                    //Somme Réalisé = somme réalisé noir + somme réalisé couleur
                    $sommeRealise += $integrale->vol_noir_realise;
                    $sommeRealise += $integrale->vol_coul_realise;

                    //Somme engagé Noir
                    $sommEngageNoir += $integrale->vol_noir_engage;

                    //Somme engagé Couleur
                    $sommeEngageCouleur += $integrale->vol_coul_engage;

                    //Copie suplémantaire
                    $sommeCopieSupNoir += $integrale->vol_noir_facture - $integrale->vol_noir_engage;
                    $sommeCopieSupCouleur += $integrale->vol_coul_facture - $integrale->vol_coul_engage;
                }
            }
        }

        if($copiesup) {
            return array($sommeCopieSupNoir, $sommeCopieSupCouleur);
        }
        else {
            return array($sommeRealise, $sommEngageNoir, $sommeEngageCouleur);
        }
    }

    function getSoldePersoIntegrale(&$PDOdb) {
        $soldepersointegrale = 0;

        return $soldepersointegrale;
    }

    // Chargement des dictionnaires
    function load_statut_dossier() {
        global $conf, $db;

        // Statut dossier
        $sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_financement_statut_dossier WHERE entity IN (0, '.$conf->entity.') AND active = 1';
        $resql = $db->query($sql);
        $this->TStatutDossier[] = '';

        if($resql) {
            while($row = $db->fetch_object($resql)) {
                $this->TStatutDossier[$row->rowid] = $row->label;
            }
        }

        // Statut renta neg anomalie
        $sql = 'SELECT rowid, label FROM '.MAIN_DB_PREFIX.'c_financement_statut_renta_neg_ano WHERE entity IN (0, '.$conf->entity.') AND active = 1';
        $resql = $db->query($sql);
        $this->TStatutRentaNegAno[] = '';

        if($resql) {
            while($row = $db->fetch_object($resql)) {
                $this->TStatutRentaNegAno[$row->rowid] = $row->label;
            }
        }
    }

    // Chargement du dictionnaire contenant les règle de calcul de soldes
    function load_c_conf_solde() {
        global $db;

        $sql = "SELECT periode, base_solde, percent, date_application, percent_nr FROM ".MAIN_DB_PREFIX."c_financement_conf_solde
				WHERE entity = ".$this->entity." 
				AND active = 1 
				AND fk_type_contrat = '".$this->contrat."'
				AND fk_nature = '".$this->nature_financement."'
				ORDER BY periode, date_application ASC";
        $res = array();
        $resql = $db->query($sql);

        if($resql) {
            while($line = $db->fetch_object($resql)) {
                $res[] = $line;
            }
        }

        $this->TConfSolde = $res;
        return $res;
    }

    function getRuleSolde($periode, $date_periode) {
        $confsolde = array_reverse($this->TConfSolde, true);

        foreach($confsolde as $rule) {
            if($periode >= $rule->periode && (is_null($rule->date_application) || $date_periode >= $rule->date_application)) return $rule;
        }
    }

    function getNomUrl() {
        $link = dol_buildpath('/financement/dossier.php', 1).'?id='.$this->getId();
        $label = ! empty($this->reference) ? $this->reference : 'ID '.$this->getId();
        $picto = img_picto($label, 'object_financementico@financement');

        return '<a href="'.$link.'">'.$picto.' '.$label.'</a>';
    }

    /**
     * Liste des dossier clients en cours pour choix dans la simulation lors d'une demande d'adjonction
     */
    static function getListeDossierClient(&$PDOdb, $fk_soc, $siren, $open = true) {
        $sql = "SELECT d.rowid, dfcli.reference as refcli, dflea.reference as reflea, a.contrat";
        $sql .= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (da.fk_fin_dossier = d.rowid)";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (da.fk_fin_affaire = a.rowid)";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type = 'CLIENT')";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";
        $sql .= " WHERE 1";
        $sql .= " AND (dfcli.reference IS NULL OR dfcli.reference NOT LIKE '%ADJ%')";
        $sql .= " AND (
					(a.nature_financement = 'INTERNE' AND (dfcli.date_solde <= '1970-00-00 00:00:00' OR dfcli.date_solde IS NULL))";
        $sql .= " 	OR (a.nature_financement = 'EXTERNE' AND (dflea.date_solde <= '1970-00-00 00:00:00' OR dflea.date_solde IS NULL))
				)";
        $sql .= " AND (a.fk_soc = ".$fk_soc;

        $sql .= " OR a.fk_soc IN
					(
						SELECT s.rowid 
						FROM ".MAIN_DB_PREFIX."societe as s
							LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON (se.fk_object = s.rowid)
						WHERE
						(
							s.siren = '".$siren."'
							AND s.siren != ''
						) 
						OR
						(
							se.other_siren LIKE '%".$siren."%'
							AND se.other_siren != ''
						)
					)";
        $sql .= ")";

        $TRes = $PDOdb->ExecuteAsArray($sql);
        $TDoss = array();
        foreach($TRes as $obj) {
            $ref = (! empty($obj->refcli)) ? $obj->refcli : $obj->reflea;
            $TDoss[$obj->rowid] = array('label' => $ref, 'type_contrat' => $obj->contrat);
        }

        asort($TDoss);

        return $TDoss;
    }

    /**
     * Règles spécifique permettant de savoir si le solde du dossier doit être affiché ou non sur les simulations
     *
     * @return int < 0 s'il ne faut pas afficher le solde, 1 sinon
     */
    function get_display_solde() {
        global $conf;

        if(empty($this->display_solde)) return -1; // Champ manuel de la fiche dossier

        // Règle du montant max
        $min_amount = price2num($conf->global->FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE);
        if(empty($min_amount)) $min_amount = 50000;
        if($this->montant >= $min_amount) return -2;

        // Règle du solde perso (toujours utilisé ?)
        if($this->soldepersodispo == 2) return -3;

        if($this->nature_financement == 'EXTERNE') {
            // Règle de l'incident de paiement sur les externes
            if($this->financementLeaser->incident_paiement == 'OUI') return -4;
            // Règle du taux min
            if(($this->financementLeaser->taux) < $conf->global->FINANCEMENT_MIN_TAUX_TO_SHOW_SOLDE) return -5;
            // Règle du nombre de mois min
            $nb_month_passe = ($this->financementLeaser->numero_prochaine_echeance - 1) * $this->financementLeaser->getiPeriode();
        }
        else {
            // Règle de l'incident de paiement sur les externes
            if($this->financement->incident_paiement == 'OUI') return -4;
            // Règle du taux min
            if(($this->financement->taux) < $conf->global->FINANCEMENT_MIN_TAUX_TO_SHOW_SOLDE) return -5;
            // Règle du nombre de mois min
            $nb_month_passe = ($this->financement->numero_prochaine_echeance - 1) * $this->financement->getiPeriode();
        }

        // Règle du nombre de mois min
        if($nb_month_passe <= $conf->global->FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH) return -6;

        // Règle du nombre d'impayé client
        $cpt = 0;
        $TFactures = array_reverse($this->TFacture, true);
        foreach($TFactures as $echeance => $facture) {
            if(! is_array($facture)) $facture = array($facture);

            foreach($facture as $key => $fact) {
                if($fact->paye == 0) {
                    $cpt++;
                    if($cpt > $conf->global->FINANCEMENT_NB_INVOICE_UNPAID) {
                        return -7;
                    }
                }
            }
        }

        // Si aucune règle ci-dessus ne s'applique, on peut donc afficher le solde
        return 1;
    }
}

/*
 * Lien dossier affaire
 */

class TFin_dossier_affaire extends TObjetStd
{
    function __construct() { /* declaration */
        parent::set_table(MAIN_DB_PREFIX.'fin_dossier_affaire');
        parent::add_champs('fk_fin_affaire,fk_fin_dossier', 'type=entier;index');
        parent::start();
        parent::_init_vars();

        $this->dossier = new TFin_dossier;
        $this->affaire = new TFin_affaire;
    }

    function save(&$db) {
        parent::save($db);
    }
}

/*
 * Financement Dossier
 */

class TFin_financement extends TObjetStd
{
    const STATUS_TRANSFER_NO    = 0;
    const STATUS_TRANSFER_YES   = 1;
    const STATUS_TRANSFER_READY = 2;
    const STATUS_TRANSFER_SENT  = 3;

    function __construct() { /* declaration */
        parent::set_table(MAIN_DB_PREFIX.'fin_dossier_financement');
        parent::add_champs('duree,numero_prochaine_echeance,terme', 'type=entier;');
        parent::add_champs('montant_prestation,montant,echeance,loyer_intercalaire,reste,taux,capital_restant,assurance,montant_solde,penalite_reprise,taux_commission,frais_dossier,loyer_actualise,assurance_actualise,encours_reloc', 'type=float;');
        parent::add_champs('reference,periodicite,reglement,incident_paiement,type', 'type=chaine;');
        parent::add_champs('date_debut,date_fin,date_prochaine_echeance,date_solde', 'type=date;index;');
        parent::add_champs('fk_soc,fk_fin_dossier', 'type=entier;index;');
        parent::add_champs('okPourFacturation,transfert,reloc,relocOK,intercalaireOK', 'type=chaine;index;');
        parent::add_champs('loyer_reference', 'type=float;');
        parent::add_champs('date_application,date_envoi', 'type=date;index;');

        parent::start();
        parent::_init_vars();

        $this->TPeriodicite = array(
            'MOIS' => 'Mensuel'
            , 'TRIMESTRE' => 'Trimestriel'
            , 'SEMESTRE' => 'Semestriel'
            , 'ANNEE' => 'Annuel'
        );

        $this->TCalage = array(
            '' => '',
            '0M' => '0 mois',
            '1M' => '1 mois',
            '2M' => '2 mois',
            '3M' => '3 mois',
            '4M' => '4 mois',
            '5M' => '5 mois'
        );

        $this->TReglement = array();
        $this->load_reglement();

        $this->taux_commission = 1;
        $this->duree_passe = 0;
        $this->duree_restante = 0;
        $this->TIncidentPaiement = array(
            'OUI' => 'Oui'
            , 'NON' => 'Non'
        );

        $this->somme_affaire = 0;
        $this->periodicite = 'TRIMESTRE';
        $this->incident_paiement = 'NON';
        $this->reglement = 'PRE';

        $this->numero_prochaine_echeance = 1;
        $this->date_prochaine_echeance = 0;

        $this->somme_facture = 0;
        $this->somme_echeance = 0;

        $this->terme = 1;
        $this->TTerme = array(
            0 => 'Echu'
            , 1 => 'A Echoir'
        );

        $this->okPourFacturation = 'NON';

        $this->TOkPourFacturation = array(
            'NON' => 'Non'
            , 'OUI' => 'Oui'
            , 'AUTO' => 'Toujours (verrouillé)'
            , 'MANUEL' => 'Manuel'
        );

        $this->TTransfert = array(
            self::STATUS_TRANSFER_NO => 'Non',
            self::STATUS_TRANSFER_YES => 'Oui',
            self::STATUS_TRANSFER_READY => 'Prêt',
            self::STATUS_TRANSFER_SENT => 'Envoyé'
        );

        $this->date_solde = 0;

        $this->TReloc = array(
            'OUI' => 'Oui'
            , 'NON' => 'Non'
        );
        $this->reloc = 'NON';

        $this->TRelocOK = array(
            'OUI' => 'Oui'
            , 'NON' => 'Non'
        );
        $this->relocOK = 'OUI';

        $this->TIntercalaireOK = array(
            'OUI' => 'Oui'
            , 'NON' => 'Non'
        );
        $this->intercalaireOK = 'OUI';

        $this->date_application = null; // Obligé d'init à null vu que la fonction parent::_init_vars() met des valeurs dedans
        $this->date_envoi = null;
    }

    /*
	 * Définie la date de prochaine échéance et le numéro d'échéance en fonction de nb
	 * Augmente de nb periode la date de prochaine échéance et de nb le numéro de prochaine échéance
	 */
    function setEcheance($nb = 1, $script_auto = false) {
        //On empêche de passer à l'échéance suivante les financements interne Leaser si il n'y a pas de facture
        if($this->type == 'LEASER' && $script_auto) {
            $PDOdb = new TPDOdb;

            $dossier = new TFin_dossier;
            $dossier->load($PDOdb, $this->fk_fin_dossier, false);
            $dossier->load_factureFournisseur($PDOdb);

            if(! isset($dossier->TFactureFournisseur[$this->numero_prochaine_echeance - 1])) {
                return 'erreur';
            }

            $PDOdb->close();
        }

        $this->numero_prochaine_echeance += $nb;
        $this->duree_passe = $this->numero_prochaine_echeance - 1;
        $this->duree_restante = $this->duree - $this->duree_passe;

        $nb_month = ($this->duree_passe * $this->getiPeriode());
        if($nb_month == 0) {
            $this->date_prochaine_echeance = $this->date_debut + $this->calage;
        }
        else {
            $this->date_prochaine_echeance = strtotime('+'.$nb_month.' month', $this->date_debut + $this->calage);
        }

        if($this->date_prochaine_echeance < $this->date_debut) $this->date_prochaine_echeance = $this->date_debut;
    }

    function setProchaineEcheanceClient(&$PDOdb, &$dossier) {
        $dossier->load_facture($PDOdb);

        //On récupère le numéro de la dernière échéance facturée +1
        $echeance = array_pop(array_keys($dossier->TFacture));
        $echeance++;

        //On récupère la date de prochaine échéance
        $date_echeance = $dossier->getDateDebutPeriode($echeance, 'CLIENT');
        $date_echeance = date('d/m/Y', strtotime($date_echeance));

        $echeance++;

        $this->numero_prochaine_echeance = $echeance;
        $this->set_date('date_prochaine_echeance', $date_echeance);

        $this->save($PDOdb, false);
    }

    /*
	 * Pour les affaire de financement externe
	 * recalcule numéro d'échéance sur la base de la date puis appel de setEcheance()
	 */
    function setEcheanceExterne($date = null) {
        if($this->duree == 0 || $this->date_debut == $this->date_fin) return false;

        if(empty($date)) $t_jour = time();
        else $t_jour = strtotime($t_jour);  // FIXME Undefined variable $t_jour

        $iPeriode = $this->getiPeriode();

        $echeance_courante = 0; // 1ere échéance
        $t_current = $this->date_debut + $this->calage;
        $t_fin = $this->date_fin;
        if($t_jour < $t_fin) $t_fin = $t_jour;

        while($t_current < $t_fin) {
            $echeance_courante++;

            $t_current = strtotime('+'.$iPeriode.' month', $t_current);
        }

        $this->numero_prochaine_echeance = $echeance_courante;

        $this->setEcheance();

        return true;
    }

    function initEcheance() {
        $this->numero_prochaine_echeance = 0;
        if($this->loyer_intercalaire > 0) $this->numero_prochaine_echeance--;
        $this->setEcheance();
    }

    function load_reglement() {
        global $db;

        if(! isset($db)) return false;

        $this->TReglement = array();

        if(class_exists('Form')) {
            $form = new Form($db);
            $form->load_cache_types_paiements();

            foreach($form->cache_types_paiements as $row) {
                if($row['code'] != '') {
                    $this->TReglement[$row['code']] = $row['label'];
                }
            }
        }
    }

    function loadReference(&$PDOdb, $reference, $type = 'LEASER', $entity = null) {
        global $db;

        $sql = 'SELECT df.rowid';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement df';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier d ON (df.fk_fin_dossier=d.rowid AND df.type='".$db->escape($type)."')";
        $sql .= " WHERE df.reference LIKE '".$db->escape($reference)."'";
        if(! is_null($entity) && is_numeric($entity)) $sql .= ' AND d.entity = '.$entity;
        if(! empty($entity) && is_array($entity)) $sql .= ' AND d.entity IN ('.implode(',', $entity).')';

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        if($obj = $db->fetch_object($resql)) return $this->load($PDOdb, $obj->rowid);

        return false;
    }

    function loadOrCreateSirenMontant(&$db, $data) {
        global $conf;

        $sql = "SELECT a.rowid, a.nature_financement, a.montant, df.rowid as idDossierLeaser, df.reference as refDossierLeaser ";
        $sql .= "FROM ".MAIN_DB_PREFIX."fin_affaire a ";
        $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc = s.rowid) ";
        $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON (se.fk_object = s.rowid) ";
        $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (da.fk_fin_affaire = a.rowid) ";
        $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (da.fk_fin_dossier = d.rowid) ";
        $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid) ";
        if(strlen($data['siren']) == 14) {
            $sql .= "WHERE (s.siret = '".$data['siren']."' OR s.siren = '".substr($data['siren'], 0, 9)."' OR se.other_siren LIKE '%".substr($data['siren'], 0, 9)."%') ";
        }
        else $sql .= "WHERE (s.siren = '".$data['siren']."' OR se.other_siren LIKE '%".$data['siren']."%') ";
        $sql .= "AND df.type = 'LEASER' ";
        $sql .= "AND (df.reference = '' OR df.reference IS NULL) ";
        $sql .= "AND a.montant >= ".($data['montant'] - 0.01)." ";
        $sql .= "AND a.montant <= ".($data['montant'] + 0.01)." ";

        $db->Execute($sql); // Recherche d'un dossier leaser en cours sans référence et dont le montant de l'affaire correspond
        $TRes = $db->Get_All();
        if(count($TRes) == 0) { // Aucun dossier trouvé, on essaye de le créer
            // Création d'une affaire pour création dossier fin externe
            $sql = "SELECT s.rowid ";
            $sql .= "FROM ".MAIN_DB_PREFIX."societe s ";
            $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON (se.fk_object = s.rowid) ";
            if(strlen($data['siren']) == 14) {
                $sql .= "WHERE (s.siret = '".$data['siren']."' OR s.siren = '".substr($data['siren'], 0, 9)."' OR se.other_siren LIKE '%".substr($data['siren'], 0, 9)."%') ";
            }
            else $sql .= "WHERE (s.siren = '".$data['siren']."' OR se.other_siren LIKE '%".$data['siren']."%') ";
            $sql .= "AND a.solde >= ".($data['montant'] - 0.01)." ";
            $sql .= "AND a.solde <= ".($data['montant'] + 0.01)." ";

            $TIdClient = TRequeteCore::_get_id_by_sql($db, $sql);

            if(! empty($TIdClient[0])) {
                $d = new TFin_dossier;
                $d->entity = $data['entity'];
                $d->financementLeaser = $this;
                $d->save($db);
                $idClient = $TIdClient[0];
                $a = new TFin_affaire();
                $a->entity = $data['entity'];
                $a->reference = 'EXT-'.date('ymd').'-'.$data['reference'];
                $a->montant = $data['montant'];
                $a->fk_soc = $idClient;
                $a->nature_financement = 'EXTERNE';
                $a->type_financement = 'FINANCIERE';
                if($data['montant_prestation'] > 0
                    && ! empty($conf->global->FINANCEMENT_IMPORT_LEASER_CONTRAT_TYPE)
                    && ! empty($a->TContrat[$conf->global->FINANCEMENT_IMPORT_LEASER_CONTRAT_TYPE])) {
                    $a->contrat = $conf->global->FINANCEMENT_IMPORT_LEASER_CONTRAT_TYPE;
                }
                $a->addDossier($db, $d->getId());
                $a->save($db);

                return true;
            }
            else if(count($TRes) == 0) { // Création d'une affaire pour création dossier fin externe
                $sql = "SELECT s.rowid ";
                $sql .= "FROM ".MAIN_DB_PREFIX."societe s ";
                $sql .= "LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields se ON (se.fk_object = s.rowid) ";
                if(strlen($data['siren']) == 14) {
                    $sql .= "WHERE (s.siret = '".$data['siren']."' OR s.siren = '".substr($data['siren'], 0, 9)."' OR se.other_siren LIKE '%".substr($data['siren'], 0, 9)."%') ";
                }
                else $sql .= "WHERE (s.siren = '".$data['siren']."' OR se.other_siren LIKE '%".$data['siren']."%') ";

                $TIdClient = TRequeteCore::_get_id_by_sql($db, $sql);
                if(! empty($TIdClient[0])) {
                    $d = new TFin_dossier;
                    $d->entity = $data['entity'];
                    $d->financementLeaser = $this;
                    $d->save($db);

                    $idClient = $TIdClient[0];
                    $a = new TFin_affaire();
                    $a->entity = $data['entity'];
                    $a->reference = 'EXT-'.date('ymd').'-'.$idClient;
                    $a->montant = $data['montant'];
                    $a->fk_soc = $idClient;
                    $a->nature_financement = 'EXTERNE';
                    if($data['montant_prestation'] > 0
                        && ! empty($conf->global->FINANCEMENT_IMPORT_LEASER_CONTRAT_TYPE)
                        && ! empty($a->TContrat[$conf->global->FINANCEMENT_IMPORT_LEASER_CONTRAT_TYPE])) {
                        $a->contrat = $conf->global->FINANCEMENT_IMPORT_LEASER_CONTRAT_TYPE;
                    }
                    $a->addDossier($db, $d->getId());
                    $a->save($db);

                    return true;
                }
                else {
                    return false;
                }
            }
            else {
                return false;
            }
        }
        else if(count($TRes) == 1) { // Un seul dossier trouvé, load
            $idDossierFin = $TRes[0]->idDossierLeaser;
            $this->load($db, $idDossierFin);

            return true;
        }
        else { // Plusieurs dossiers trouvé correspondant, utilisation du premier trouvé
            $idDossierFin = $TRes[0]->idDossierLeaser;
            $this->load($db, $idDossierFin);

            return true;
        }

        return false;
    }

    function getiPeriode() {
        if($this->periodicite == 'TRIMESTRE') $iPeriode = 3;
        else if($this->periodicite == 'SEMESTRE') $iPeriode = 6;
        else if($this->periodicite == 'ANNEE') $iPeriode = 12;
        else $iPeriode = 1;

        return $iPeriode;
    }

    function calculDateFin() {
        $this->calculCalage();
        $this->date_fin = strtotime('+'.($this->getiPeriode() * ($this->duree)).' month -1 day', $this->date_debut + $this->calage);
        $this->date_prochaine_echeance = strtotime('+'.($this->getiPeriode() * ($this->duree_passe)).' month', $this->date_debut + $this->calage);
    }

    function calculTaux() {
        $this->taux = round($this->taux($this->duree, $this->echeance, -$this->montant, $this->reste, $this->terme) * (12 / $this->getiPeriode()) * 100, 4);
    }

    function load(&$ATMdb, $id, $annexe = false) {
        $res = parent::load($ATMdb, $id);
        $this->duree_passe = $this->numero_prochaine_echeance - 1;
        $this->duree_restante = $this->duree - $this->duree_passe;
        $this->calculCalage();

        if($annexe) {
            $this->load_facture($ATMdb);
            $this->load_factureFournisseur($ATMdb);
        }

        return $res;
    }

    function calculCalage() {
        if($this->loyer_intercalaire > 0) {
            $p = $this->getiPeriode();
            $nextPeriod = strtotime(date('Y-m-01', $this->date_debut));
            $nextPeriod = strtotime('+'.($p).' month', $nextPeriod);
            $firstDayOfNextPeriod = strtotime(strftime('%Y', $nextPeriod).'-'.(ceil(strftime('%m', $nextPeriod) / $p) * $p - ($p - 1)).'-1');
            $this->calage = $firstDayOfNextPeriod - $this->date_debut;
        }
        else {
            $this->calage = 0;
        }
    }

    function save(&$ATMdb, $donotcalculdatefin = true) {
        global $user;

        if(! $user->rights->financement->affaire->write) return false;

        if($donotcalculdatefin) {
            $this->calculDateFin();
        }

        $this->calculTaux();

        //Gestion des cas ou on créé un solde partiel donc on renomme l'ancien dossier en "-OLD"
        if(strpos(strtoupper($this->reference), "-OLD") > 0 && $this->type == 'LEASER') {
            $dossier = new TFin_dossier;
            $dossier->load($ATMdb, $this->fk_fin_dossier);
            $dossier->load_factureFournisseur($ATMdb);

            foreach($dossier->TFactureFournisseur as $echeance => $facturefourn) {
                $facturefourn->ref_supplier = $this->reference."/".($echeance + 1);
                $facturefourn->update($user);
            }
        }

        //Dans le cas d'un financement LEASER, si la date du sole est renseignée, alors on créé les avoirs correspondant au factures fournisseur
        //qui existe pour les échéances situées après cette date
        if($this->type == 'LEASER' && (! empty($this->date_solde) && $this->date_solde > 0)) {
            $dossier = new TFin_dossier;
            $dossier->load($ATMdb, $this->fk_fin_dossier);
            $dossier->load_factureFournisseur($ATMdb);

            foreach($dossier->TFactureFournisseur as $echeance => $facturefourn) {

                $date_debut_echeance = $dossier->getDateDebutPeriode($echeance);

                if(strtotime($date_debut_echeance) >= $this->date_solde) {
                    $this->createAvoirLeaserFromFacture($facturefourn->id);
                }
            }
        }

        if($this->type == 'CLIENT') {
            $dossier = new TFin_dossier;
            $dossier->load($ATMdb, $this->fk_fin_dossier, false);
            $dossier->financement->calage = $this->calage;
            if($dossier->financement->date_debut != $this->date_debut) $dossier->financement->date_debut = $this->date_debut;

            $echeance = $dossier->_get_num_echeance_from_date(time());
            $echeance = $echeance + 2;

            $this->numero_prochaine_echeance = $echeance;

            $this->set_date('date_prochaine_echeance', $dossier->getDateDebutPeriode($echeance - 1, 'CLIENT'));
        }

        parent::save($ATMdb);

        return true;
    }

    /**
     * Création d'un avoir fournisseur à partir de la facture d'origine
     * @param $idFactureFourn Id de la facture d'origine
     * @return int Id de l'avoir créé
     */
    function createAvoirLeaserFromFacture($idFactureFourn) {
        global $db, $user, $conf;

        dol_include_once('/fourn/class/fournisseur.facture.class.php');
        dol_include_once('/product/class/product.class.php');

        // Chargement de la facture d'origine
        $origine = new FactureFournisseur($db);
        $origine->fetch($idFactureFourn);

        // Changement d'entité pour pouvoir créer avec la bonne numérotation la facture avoir
        $curent = $conf->entity;
        switchEntity($origine->entity);

        // Création de l'avoir via clone de la facture
        $fact = new FactureFournisseur($db);
        $idClone = $fact->createFromClone($idFactureFourn);
        $fact->fetch($idClone);

        // Modification du clone pour transformation en avoir
        $fact->type = 2;
        $fact->entity = $origine->entity;
        $fact->fk_facture_source = $origine->id;
        $fact->facnumber = 'AV'.$origine->ref_supplier;
        $fact->ref_supplier = 'AV'.$origine->ref_supplier;
        $fact->update($user);

        // Passage des lignes en négatif
        foreach($fact->lines as $line) {
            $line->pu_ht *= -1;
            $fact->updateline($line->rowid, $line->libelle, $line->pu_ht, $line->tva_tx, 0, 0, $line->qty, $line->fk_product);
        }

        // Validation de la facture
        $fact->validate($user);

        // Retour à l'entité courante
        switchEntity($curent);

        // Ajout lien avoir / dossier
        $fact->add_object_linked('dossier', $this->fk_fin_dossier);

        return $fact->id;
    }

    /**
     * FONCTION FINANCIERES PROVENANT D'EXCEL PERMETTANT DE CALCULER LE LOYER, LE MONTANT OU LE TAUX
     * Source : http://www.tiloweb.com/php/php-formules-financieres-excel-en-php
     */

    function amortissement_echeance($periode, $capital_restant_du = 0) {
        //Cas spécifique Leaser = LOCAM
        if($this->type == "LEASER" && $this->is_locam && ! empty($capital_restant_du) && $this->terme == 1) {

            if($periode == 1) {
                $r = $this->echeance;
            }
            else {
                $r = $this->echeance - ($capital_restant_du * $this->taux / 100 / (12 / $this->getiPeriode()));
            }
        }
        else {
            $r = $this->PRINCPER(($this->taux / (12 / $this->getiPeriode())) / 100, $periode, $this->duree, $this->montant - $this->reste, $this->reste, $this->terme);
            $r = -$r;
        }

        return $r;
    }

    private function PRINCPER($taux, $p, $NPM, $VA, $valeur_residuelle, $type) {
        $valeur_residuelle = 0;
        $type = 0;
        @        $res = $taux / (1 + $taux * $type) * $VA * (pow(1 + $taux, -$NPM + $p - 1)) / (pow(1 + $taux, -$NPM) - 1) - $valeur_residuelle * (pow(1 + $taux, $p - 1)) / (pow(1 + $taux, $NPM) - 1);

        return $res;
    }

    function valeur_actuelle($duree = -1) {
        if($duree == -1) $duree = $this->duree_restante;

        //Cas spécifique Leaser = LOCAM
        if($this->type == "LEASER" && $this->is_locam) {
            $catpital_restant = $this->montant;

            for($i = 0 ; $i < $this->duree - $duree ; $i++) {
                $capital_amorti = $this->amortissement_echeance($i + 1, $catpital_restant);
                $catpital_restant -= $capital_amorti;
            }

            return $catpital_restant;
        }
        else {
            return $this->va($this->taux / (12 / $this->getiPeriode()) / 100, $duree, $this->echeance, $this->reste, $this->terme);
        }
    }

    /**
     * VA : Calcule la valeur actuelle d'un investissement
     * @param $taux Float : Le taux d'intérêt par période (à diviser par 4 si remboursement trimestriel, par 12 si mensuel, ...)
     * @param $npm Float : Le nombre total de périodes de paiement de l'annuité (= Duree)
     * @param $vpm Float : Echéance constante payée pour chaque période
     * @param $vc Float : Valeur future. La valeur capitalisée que vous souhaitez obtenir après le dernier paiement (= Valeur résiduelle)
     * @param $type Int : Terme de l'échéance (0 = terme échu, 1 = terme à échoir)
     * @return $va Float : Montant de l'investissement
     */
    private function va($taux, $npm, $vpm, $vc = 0, $type = 0) {
        if(! is_numeric($taux) || ! is_numeric($npm) || ! is_numeric($vpm) || ! is_numeric($vc) || ! is_numeric($type)) return false;
        if($type > 1 || $type < 0) return false;

        $tauxAct = pow(1 + $taux, -$npm);

        if((1 - $tauxAct) == 0) return 0;

        $va = -$vpm * (1 + $taux * $type) * (1 - $tauxAct) / $taux - $vc * $tauxAct;

        return -$va;
    }

    /**
     * VA : Calcule la valeur actuelle d'un investissement
     * @param $nper Float : Le nombre total de périodes de paiement de l'annuité (= Duree)
     * @param $pmt Float : Echéance constante payée pour chaque période
     * @param $pv Float : Valeur actuelle. La valeur, à la date d'aujourd'hui, d'une série de remboursement futurs (= Montant financé)
     * @param $fv Float : Valeur future. La valeur capitalisée que vous souhaitez obtenir après le dernier paiement (= Valeur résiduelle)
     * @param $type Int : Terme de l'échéance (0 = terme échu, 1 = terme à échoir)
     * @param $guess Float : ???
     * @return $rate Float : Taux d'intérêt
     */
    private function taux($nper, $pmt, $pv, $fv = 0.0, $type = 0, $guess = 0.1) {
        $rate = $guess;
        $ecartErreurOK = 0.0000001;

        if(abs($rate) < 20) {
            $y = $pv * (1 + $nper * $rate) + $pmt * (1 + $rate * $type) * $nper + $fv;
        }
        else {
            $f = exp($nper * log(1 + $rate));
            $y = $pv * $f + $pmt * (1 / $rate + $type) * ($f - 1) + $fv;
        }

        $y0 = $pv + $pmt * $nper + $fv;
        $y1 = $pv * $f + $pmt * (1 / $rate + $type) * ($f - 1) + $fv;

        $i = $x0 = 0.0;
        $x1 = $rate;
        while((abs($y0 - $y1) > $ecartErreurOK) && ($i < 50)) {
            $rate = ($y1 * $x0 - $y0 * $x1) / ($y1 - $y0);
            $x0 = $x1;
            $x1 = $rate;

            if(abs($rate) < $ecartErreurOK) {
                $y = $pv * (1 + $nper * $rate) + $pmt * (1 + $rate * $type) * $nper + $fv;
            }
            else {
                $f = exp($nper * log(1 + $rate));
                $y = $pv * $f + $pmt * (1 / $rate + $type) * ($f - 1) + $fv;
            }

            $y0 = $y1;
            $y1 = $y;
            ++$i;
        }

        return $rate;
    }

    public function printModifAccordCMCIC() {
        if($this->type == 'CLIENT' || $this->fk_soc != 21382 || empty($this->reference)) return '';
        global $db, $langs;

        $sql = 'SELECT s.rowid, s.montant, ss.surfact, ss.surfactplus, ss.statut, ss.statut_demande';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_simulation_suivi ss';
        $sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (ss.fk_simulation=s.rowid)';
        $sql .= ' WHERE ss.fk_leaser = '.$this->fk_soc;
        $sql .= " AND ss.numero_accord_leaser = '".$db->escape($this->reference)."'";
        $sql .= ' ORDER BY s.date_simul DESC';  // On prend la plus récente

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }
        $nbRows = $db->num_rows($resql);

        // Si on passe dedans c'est que le leaser c'est CMCIC MANDATEE et que la référence contrat leaser existe dans la base simulation
        while($obj = $db->fetch_object($resql)) {
            if($nbRows == 1 && $this->montant != ($obj->montant + $obj->surfact + $obj->surfactplus)) {
                $isCard = array_key_exists('id', $_GET);
                $ret = '';
                if($isCard) $ret .= '<a href="'.$_SERVER['PHP_SELF'].'?id='.$this->fk_fin_dossier.'&action=modifAccord&fk_simu='.$obj->rowid.'" title="'.$langs->trans('SendUpdateRequest').'" >';
                $ret .= get_picto('webservice');
                if($isCard) $ret .= '</a>';
                return $ret;
            }
            elseif($nbRows > 1) {
                if($obj->statut_demande == 1) continue; // On ne veut prendre que le suivi qui a son statut_demande à 2
//                dol_include_once('/financement/class/simulation.class.php');
//                $suivi = new TSimulationSuivi;

//                $title = $langs->trans('UpdateRequestStatus').' : '.$suivi->TStatut[$obj->statut];
                return get_picto($obj->statut/*, $title*/);
            }
        }

        return '';
    }

    public static function getVR($fk_leaser) {
        if(in_array($fk_leaser, array(19068, 19483))) return 0.15;    // Lixxbail
        elseif(in_array($fk_leaser, array(19553, 20113))) return 1; // BNP
        elseif($fk_leaser == 21382) return 0.15; // CMCIC
        elseif(in_array($fk_leaser, array(21921, 23164))) return 1; // Grenke
        elseif(in_array($fk_leaser, array(30749, 30748))) return 15; // Locam
        elseif($fk_leaser == 18495) return 1; // Loc Pure

        return 0;
    }

    public static function isFinancementAlreadyExists($refFinLeaser) {
        global $db;

        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement';
        $sql.= " WHERE type = 'LEASER'";
        $sql.= " AND reference = '".$db->escape($refFinLeaser)."'";

        $resql = $db->query($sql);
        if($resql) {
            if($db->num_rows($resql) > 0) return true;
            return false;
        }
        else {
            dol_print_error($db);
            exit;
        }
    }
}
