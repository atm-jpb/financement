<?php
if(! class_exists('TObjetStd')) {
    define('INC_FROM_DOLIBARR', true);
    require_once dirname(__FILE__).'/../config.php';
}
if(! class_exists('DossierRachete')) dol_include_once('/financement/class/dossierRachete.class.php');

class TSimulation extends TObjetStd
{

    /** @var TSimulationSuivi[] $TSimulationSuivi */
    public $TSimulationSuivi;

    /**
     * @var DossierRachete[]
     */
    public $DossierRachete;

    function __construct($setChild = false) {
        global $langs;
        if(! function_exists('get_picto')) dol_include_once('/financement/lib/financement.lib.php');

        parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
        parent::add_champs('entity,fk_soc,fk_user_author,fk_user_suivi,fk_leaser,accord_confirme', 'type=entier;');
        parent::add_champs('attente,duree,opt_administration,opt_creditbail,opt_adjonction,opt_no_case_to_settle', 'type=entier;');
        parent::add_champs('montant,montant_rachete,montant_rachete_concurrence,montant_decompte_copies_sup,montant_rachat_final,montant_total_finance,echeance,vr,coeff,cout_financement,coeff_final,montant_presta_trim', 'type=float;');
        parent::add_champs('date_simul,date_validite,date_accord,date_demarrage', 'type=date;');
        parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme,fk_type_contrat,accord,type_financement,commentaire,type_materiel,marque_materiel,numero_accord,reference,opt_calage', 'type=chaine;');
        parent::add_champs('modifs,dossiers,dossiers_rachetes_m1,dossiers_rachetes_nr_m1,dossiers_rachetes,dossiers_rachetes_nr,dossiers_rachetes_p1,dossiers_rachetes_nr_p1,dossiers_rachetes_perso', 'type=tableau;');
        parent::add_champs('thirdparty_name,thirdparty_address,thirdparty_zip,thirdparty_town,thirdparty_code_client,thirdparty_idprof2_siret, thirdparty_idprof3_naf', 'type=chaine;');
        parent::add_champs('montant_accord', 'type=float;'); // Sert à stocker le montant pour lequel l'accord a été donné
        parent::add_champs('fk_categorie_bien,fk_nature_bien', array('type' => 'integer'));
        parent::add_champs('pct_vr,mt_vr', array('type' => 'float'));
        parent::add_champs('fk_fin_dossier,fk_fin_dossier_adjonction', array('type' => 'integer'));
        parent::add_champs('fk_simu_cristal,fk_projet_cristal', array('type' => 'integer'));
        parent::add_champs('note_public,note_private', array('type' => 'text'));
        parent::add_champs('fk_action_manuelle', array('type' => 'integer'));

        parent::start();
        parent::_init_vars();

        $this->init();

        $this->setChild('DossierRachete', 'fk_simulation');

        if($setChild) {
            $this->setChild('TSimulationSuivi', 'fk_simulation');
        }
        else {
            $this->TSimulationSuivi = array();
        }

        $this->TStatut = array(
            'WAIT' => $langs->trans('Etude'),
            'WAIT_LEASER' => $langs->trans('Etude_Leaser'),
            'WAIT_SELLER' => $langs->trans('Etude_Vendeur'),
            'WAIT_MODIF' => $langs->trans('Modif'),
            'WAIT_AP' => $langs->trans('AccordPrincipe'),
            'OK' => $langs->trans('Accord'),
            'KO' => $langs->trans('Refus'),
            'SS' => $langs->trans('SansSuite')
        );

        $this->TStatutIcons = array(
            'WAIT' => 'wait',
            'WAIT_LEASER' => 'wait_leaser',
            'WAIT_SELLER' => 'wait_seller',
            'WAIT_MODIF' => 'edit',
            'WAIT_AP' => 'wait_ap',
            'OK' => 'super_ok',
            'KO' => 'refus',
            'SS' => 'sans_suite'
        );

        $this->TStatutShort = array(
            'WAIT' => $langs->trans('Etude'),
            'WAIT_LEASER' => $langs->trans('Etude_Leaser_Short'),
            'WAIT_SELLER' => $langs->trans('Etude_Vendeur_Short'),
            'WAIT_MODIF' => $langs->trans('Modif'),
            'WAIT_AP' => $langs->trans('AccordPrincipe'),
            'OK' => $langs->trans('Accord'),
            'KO' => $langs->trans('Refus'),
            'SS' => $langs->trans('SansSuite')
        );

        $this->TTerme = array(
            0 => 'Echu'
            , 1 => 'A Echoir'
        );

        $this->TMarqueMateriel = self::getMarqueMateriel();
        $this->logo = '';

        // Obligé d'init à null vu que la fonction parent::_init_vars() met des valeurs dedans
        $this->date_accord = null;
        $this->date_demarrage = null;
    }

    public static function getMarqueMateriel() {
        global $conf, $db;

        $TRes = array();

        $sql = 'SELECT code, label FROM '.MAIN_DB_PREFIX.'c_financement_marque_materiel WHERE entity = '.$conf->entity.' AND active = 1 ORDER BY label';
        dol_syslog('TSimulation::getMarqueMateriel sql='.$sql, LOG_INFO);
        $resql = $db->query($sql);

        if($resql) {
            while($row = $db->fetch_object($resql)) {
                $TRes[$row->code] = $row->label;
            }
        }
        else {
            dol_syslog('TSimulation::getMarqueMateriel SQL FAIL - look up', LOG_ERR);
        }

        return $TRes;
    }

    function init() {
        global $user;

        $this->reference = $this->getRef();
        $this->opt_periodicite = 'TRIMESTRE';
        $this->opt_mode_reglement = 'PRE';
        $this->opt_terme = '1';
        $this->opt_calage = '';
        $this->date_demarrage = '';
        $this->vr = 0;
        $this->mt_vr = 0.15;
        $this->pct_vr = 0;
        $this->coeff = 0;
        $this->fk_user_author = $user->id;
        $this->user = $user;
        $this->dossiers = array();
        $this->dossiers_rachetes = array();
        $this->dossiers_rachetes_nr = array();
        $this->dossiers_rachetes_p1 = array();
        $this->dossiers_rachetes_nr_p1 = array();
        $this->dossiers_rachetes_perso = array();
        $this->modifs = array();
        $this->modifiable = 1; // 1 = modifiable, 2 = modifiable +- 10%, 0 = non modifiable

        // Catégorie et nature par défaut pour transfert EDI
        $this->fk_categorie_bien = 26;
        $this->fk_nature_bien = 665;
    }

    function getRef() {
        if($this->getId() > 0) return 'S'.str_pad($this->getId(), 6, '0', STR_PAD_LEFT);
        else return 'DRAFT';
    }

    function load(&$db, $id, $loadChild = true) {
        parent::load($db, $id);

        if($loadChild) {
            $this->load_annexe($db);
        }
    }

    function save(&$PDOdb) {
        global $db;

        parent::save($PDOdb);

        $this->reference = $this->getRef();

        $this->save_dossiers_rachetes($PDOdb, $db);

        if($this->accord == 'OK') {
            $this->date_validite = strtotime('+ 5 months', $this->date_accord);
        }

        parent::save($PDOdb);

        //Création du suivi simulation leaser s'il n'existe pas
        //Sinon chargement du suivi
        $this->load_suivi_simulation($PDOdb);
    }

    function save_dossiers_rachetes(&$PDOdb, &$doliDB) {
        global $conf;
        if(! class_exists('DossierRachete')) dol_include_once('/financement/class/dossierRachete.class.php');

        $TDoss = $this->dossiers;

        foreach($this->dossiers_rachetes as $k => $TDossiers) {
            // On enregistre les données que lors du 1er enregistrement de la simulation pour les figer
            if(empty($TDoss) || empty($TDoss[$k]['date_debut_periode_client_m1'])) { // Retro compatibilité pour les ancienne simulations
                $dossier = new TFin_dossier;
                $dossier->load($PDOdb, $k);

                $fin_leaser = &$dossier->financementLeaser;
                if($dossier->nature_financement == 'INTERNE') {
                    $fin = &$dossier->financement;
                }
                else {
                    $fin = &$dossier->financementLeaser;
                }

                // Récupération des soldes banques
                $echeance = $dossier->_get_num_echeance_from_date($dossier->financementLeaser->date_prochaine_echeance);
                $solde_banque_m1 = $dossier->getSolde($PDOdb, 'SRBANK', $echeance - 1);
                $solde_banque = $dossier->getSolde($PDOdb, 'SRBANK', $echeance);
                $solde_banque_p1 = $dossier->getSolde($PDOdb, 'SRBANK', $echeance + 1);

                // Soldes NR
                $solde_banque_nr_m1 = $dossier->getSolde($PDOdb, 'SNRBANK', $echeance - 1);
                $solde_banque_nr = $dossier->getSolde($PDOdb, 'SNRBANK', $echeance);
                $solde_banque_nr_p1 = $dossier->getSolde($PDOdb, 'SNRBANK', $echeance + 1);

                // ?
                $soldeperso = round($dossier->getSolde($PDOdb, 'perso'), 2);
                if(empty($dossier->display_solde)) $soldeperso = 0;
                if(! $dossier->getSolde($PDOdb, 'perso')) $soldeperso = ($soldepersointegrale * ($conf->global->FINANCEMENT_PERCENT_RETRIB_COPIES_SUP / 100));

                $leaser = new Societe($doliDB);
                $leaser->fetch($fin_leaser->fk_soc);

                if(empty($TDoss[$k])) { // On fige toutes les données si c'est la première fois qu'on enregistre
                    $TDoss[$k]['ref_simulation'] = $this->reference;
                    $TDoss[$k]['num_contrat'] = $fin->reference;
                    $TDoss[$k]['num_contrat_leaser'] = $fin_leaser->reference;
                    $TDoss[$k]['leaser'] = $leaser->nom;
                    $TDoss[$k]['object_leaser'] = $leaser;
                    $TDoss[$k]['retrait_copie_supp'] = $dossier->soldeperso;

                    $TDoss[$k]['date_debut_periode_leaser'] = $date_debut_periode_leaser;
                    $TDoss[$k]['date_fin_periode_leaser'] = $date_fin_periode_leaser;
                    $TDoss[$k]['decompte_copies_sup'] = $soldeperso;
                    $TDoss[$k]['solde_banque_a_periode_identique'] = $solde;
                    $TDoss[$k]['type_contrat'] = $dossier->TLien[0]->affaire->contrat;
                    $TDoss[$k]['duree'] = $fin->duree.' '.substr($fin->periodicite, 0, 1);
                    $TDoss[$k]['echeance'] = $fin->echeance;
                    $TDoss[$k]['loyer_actualise'] = $fin->loyer_actualise;
                    $TDoss[$k]['date_debut'] = $fin->date_debut;
                    $TDoss[$k]['date_fin'] = $fin->date_fin;
                    $TDoss[$k]['date_prochaine_echeance'] = $fin->date_prochaine_echeance;
                    $TDoss[$k]['numero_prochaine_echeance'] = $fin->numero_prochaine_echeance.'/'.$fin->duree;
                    $TDoss[$k]['terme'] = $fin->TTerme[$fin->terme];
                    $TDoss[$k]['reloc'] = $fin->reloc;
                    $TDoss[$k]['maintenance'] = $fin->montant_prestation;
                    $TDoss[$k]['assurance'] = $fin->assurance;
                    $TDoss[$k]['assurance_actualise'] = $fin->assurance_actualise;
                    $TDoss[$k]['montant'] = $fin->montant;
                }

                // On enregistre les dates et soldes
                $TDoss[$k]['date_debut_periode_client_m1'] = $this->dossiers_rachetes_m1[$dossier->rowid]['date_deb_echeance'];
                $TDoss[$k]['date_fin_periode_client_m1'] = $this->dossiers_rachetes_m1[$dossier->rowid]['date_fin_echeance'];
                $TDoss[$k]['solde_vendeur_m1'] = $this->dossiers_rachetes_m1[$dossier->rowid]['montant'];
                $TDoss[$k]['solde_banque_m1'] = $solde_banque_m1;
                $TDoss[$k]['solde_banque_nr_m1'] = $solde_banque_nr_m1;
                $TDoss[$k]['date_debut_periode_client'] = $this->dossiers_rachetes[$dossier->rowid]['date_deb_echeance'];
                $TDoss[$k]['date_fin_periode_client'] = $this->dossiers_rachetes[$dossier->rowid]['date_fin_echeance'];
                $TDoss[$k]['solde_vendeur'] = $this->dossiers_rachetes[$dossier->rowid]['montant'];
                $TDoss[$k]['solde_banque'] = $solde_banque;
                $TDoss[$k]['solde_banque_nr'] = $solde_banque_nr;
                $TDoss[$k]['date_debut_periode_client_p1'] = $this->dossiers_rachetes_p1[$dossier->rowid]['date_deb_echeance'];
                $TDoss[$k]['date_fin_periode_client_p1'] = $this->dossiers_rachetes_p1[$dossier->rowid]['date_fin_echeance'];
                $TDoss[$k]['solde_vendeur_p1'] = $this->dossiers_rachetes_p1[$dossier->rowid]['montant'];
                $TDoss[$k]['solde_banque_p1'] = $solde_banque_p1;
                $TDoss[$k]['solde_banque_nr_p1'] = $solde_banque_nr_p1;
            }

            // On va seulement enregistrer le choix de la période de solde
            $choice = 'no';
            if(! empty($this->dossiers_rachetes_m1[$k]['checked'])) {
                $choice = 'prev';
            }
            else if(! empty($this->dossiers_rachetes[$k]['checked'])) {
                $choice = 'curr';
            }
            else if(! empty($this->dossiers_rachetes_p1[$k]['checked'])) {
                $choice = 'next';
            }
            $TDoss[$k]['choice'] = $choice;
        }

        // Nouvelle méthode d'enregistrement
        if(empty($this->DossierRachete)) {
            foreach($TDoss as $fk_dossier => $TValues) {
                unset($TValues['leaser']);

                $dossierRachete = new DossierRachete;
                $dossierRachete->set_values($TValues);

                $dossierRachete->fk_dossier = $fk_dossier;
                $dossierRachete->fk_simulation = $this->rowid;

                // On détermine le type de solde
                $solde = self::getTypeSolde($this->rowid, $fk_dossier, $this->fk_leaser);
                $dossierRachete->type_solde = $solde;

                $dossierRachete->save($PDOdb);
                $this->DossierRachete[] = $dossierRachete;  // Une fois le dossierRachete créé, il faut le mettre dans ce tableau
            }
        }
        else {
            foreach($this->DossierRachete as $dossierRachete) {
                if($dossierRachete->choice !== $TDoss[$dossierRachete->fk_dossier]['choice']) {
                    $dossierRachete->choice = $TDoss[$dossierRachete->fk_dossier]['choice'];
                }

                // On détermine le type de solde
                $solde = self::getTypeSolde($this->rowid, $dossierRachete->fk_dossier, $this->fk_leaser);
                $dossierRachete->type_solde = $solde;

                $dossierRachete->save($PDOdb);
            }
        }

        $this->dossiers = $TDoss;
    }

    public function generatePDF(TPDOdb $PDOdb) {
        global $db;

        // Permet de générer correctement les PDFs même avec le symbole '&'
        $this->encodeTextFields();

        $this->gen_simulation_pdf($PDOdb, $db);

        // Uniquement pour les simuls d'ESUS et de ABS qui ont des dossiers à solder !
        if(in_array($this->entity, [18, 25, 28]) && empty($this->opt_no_case_to_settle)) {
            $this->gen_simulation_pdf_esus($PDOdb, $db);
        }
    }

    function setThirparty() {
        if(! empty($this->societe->id)) {
            $this->thirdparty_name = $this->societe->nom;
            $this->thirdparty_address = $this->societe->address;
            $this->thirdparty_zip = $this->societe->zip;
            $this->thirdparty_town = $this->societe->town;
            $this->thirdparty_code_client = $this->societe->code_client;
            $this->thirdparty_idprof2_siret = $this->societe->idprof2;
            $this->thirdparty_idprof3_naf = $this->societe->idprof3;
        }
    }

    function create_suivi_simulation(&$PDOdb) {
        global $db;

        $this->TSimulationSuivi = array();

        // Pour créer le suivi leaser simulation, on prend les leaser définis dans la conf et parmi ceux-la, on met en 1er le leaser prioritaire
        $TFinGrilleSuivi = new TFin_grille_suivi;
        $grille = $TFinGrilleSuivi->get_grille($PDOdb, 'DEFAUT_'.$this->fk_type_contrat, false, $this->entity);

        // Ajout des autres leasers de la liste (sauf le prio)
        foreach($grille as $TData) {
            // Le montant de LOC PURE change uniquement pour C'Pro Ouest & Copy Concept
            if(($this->montant < 1000 && ! in_array($this->entity, array(5, 7, 16)) || $this->montant < 500 && in_array($this->entity, array(5, 7, 16))) && $TData['fk_leaser'] != 18495) continue;     // Spécifique LOC PURE

            $simulationSuivi = new TSimulationSuivi;
            $simulationSuivi->leaser = new Fournisseur($db);
            $simulationSuivi->leaser->fetch($TData['fk_leaser']);
            $simulationSuivi->init($PDOdb, $simulationSuivi->leaser, $this->getId());
            $simulationSuivi->save($PDOdb);

            $this->TSimulationSuivi[$simulationSuivi->getId()] = $simulationSuivi;
        }

        // Une fois la grille constituée, on calcule l'aiguillage pour mettre dans le bon ordre
        $this->calculAiguillageSuivi($PDOdb);
    }

    function getStatut() {
        return $this->TStatut[$this->accord];
    }

    function getAuthorFullName() {
        global $langs;

        $this->user->fetch($this->fk_user_author);
        return utf8_decode($this->user->getFullName($langs));
    }

    function load_annexe(&$PDOdb) {
        global $conf, $user, $db;
        dol_include_once('/categories/class/categorie.class.php');

        if(! empty($this->fk_soc)) {
            // Récupération des infos du client
            if(empty($this->societe)) {
                $this->societe = new Societe($db);
                $this->societe->fetch($this->fk_soc);
            }

            // Récupération du score du client
            if(empty($this->societe->score)) {
                $this->societe->score = new TScore();
                $this->societe->score->load_by_soc($PDOdb, $this->fk_soc);
            }

            // Récupération des autres simulations du client
            if(empty($this->societe->TSimulations)) {
                $this->societe->TSimulations = $this->load_by_soc($PDOdb, $db, $this->fk_soc);
            }

            // Récupération des dossiers en cours du client et de l'encours CPRO
            if(empty($this->societe->TDossiers)) {
                $sql = "SELECT s.rowid
						FROM ".MAIN_DB_PREFIX."societe as s
							LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_soc = s.rowid)
							LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (cf.fk_categorie = c.rowid)
						WHERE c.label = 'Encours CPRO'";

                $TEncours = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

                $sql = "SELECT d.rowid";
                $sql .= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
                $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
                $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
                $sql .= " WHERE a.entity = ".$conf->entity;
                $sql .= " AND a.fk_soc = ".$this->fk_soc;
                $TDossiers = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

                $this->societe->encours_cpro = 0;
                foreach($TDossiers as $idDossier) {
                    $doss = new TFin_dossier;
                    $doss->load($PDOdb, $idDossier);
                    $this->societe->TDossiers[] = $doss;

                    // 2013.12.02 Modification : ne prendre en compte que les leaser faisant partie de la catégorie "Encours CPRO"
                    // 2013.10.02 MKO : Modification demandée par Damien de ne comptabiliser que les dossier internes
                    if(! empty($doss->financement)
                        && (empty($doss->financement->date_solde) || $doss->financement->date_solde < 0)
                        && in_array($doss->financementLeaser->fk_soc, $TEncours)) {

                        $this->societe->encours_cpro += $doss->financement->valeur_actuelle();
                    }
                }
                $this->societe->encours_cpro = round($this->societe->encours_cpro, 2);
            }
        }

        if(! empty($this->fk_leaser) && $this->fk_leaser > 0) {
            $this->leaser = new Societe($db);
            $this->leaser->fetch($this->fk_leaser);

            // Si un leaser a été préconisé, la simulation n'est plus modifiable
            // Modifiable à +- 10 % sauf si leaser dans la catégorie "Cession"
            // Sauf pour les admins
            if(empty($user->rights->financement->admin->write)) {
                $this->modifiable = 2;
            }
        }

        if(! empty($this->fk_user_author)) {
            $this->user = new User($db);
            $this->user->fetch($this->fk_user_author);
        }

        if(! empty($this->fk_user_suivi)) {
            $this->user_suivi = new User($db);
            $this->user_suivi->fetch($this->fk_user_suivi);
        }

        //Récupération des suivis demande de financement leaser s'ils existent
        //Sinon on les créé
        $this->load_suivi_simulation($PDOdb);

        // Simulation non modifiable dans tous les cas si la date de validité est dépassée
        // Sauf pour les admins
        if(empty($user->rights->financement->admin->write)
            && $this->accord == 'OK' && ! empty($this->date_validite) && $this->date_validite < time()) {
            $this->modifiable = 0;
        }
    }

    //Charge dans un tableau les différents suivis de demande leaser concernant la simulation
    function load_suivi_simulation(&$PDOdb) {
        global $user;

        $TSuivi = array();
        if(! empty($this->TSimulationSuivi)) {
            foreach($this->TSimulationSuivi as $suivi) {
                $TSuivi[$suivi->getId()] = $suivi;
                if($suivi->date_historization <= 0) {
                    if($suivi->statut_demande > 0 && empty($user->rights->financement->admin->write)) {
                        $this->modifiable = 2;
                    }
                }
            }
            $this->TSimulationSuivi = $TSuivi;
        }
        else {
            $TRowid = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."fin_simulation_suivi", array('fk_simulation' => $this->getId()), 'rowid', 'rowid');

            if(count($TRowid) > 0) {
                foreach($TRowid as $rowid) {
                    $simulationSuivi = new TSimulationSuivi;
                    $simulationSuivi->load($PDOdb, $rowid);
                    // Attention les type date via abricot, c'est du timestamp
                    if($simulationSuivi->date_historization <= 0) {
                        $this->TSimulationSuivi[$simulationSuivi->getId()] = $simulationSuivi;
                        // Si une demande a déjà été lancée, la simulation n'est plus modifiable
                        // Sauf pour les admins
                        if($simulationSuivi->statut_demande > 0 && empty($user->rights->financement->admin->write)) {
                            $this->modifiable = 2;
                        }
                    }
                }

                if(empty($this->TSimulationSuivi)) $this->create_suivi_simulation($PDOdb);
            }

            if($this->rowid > 0 && empty($this->TSimulationSuivi)) {
                $this->create_suivi_simulation($PDOdb);
            }
        }

        if(! empty($this->TSimulationSuivi)) uasort($this->TSimulationSuivi, array($this, 'aiguillageSuiviRang'));
        if(! empty($this->TSimulationSuiviHistorized)) uasort($this->TSimulationSuiviHistorized, array($this, 'aiguillageSuiviRang'));
    }

    //Retourne l'identifiant leaser prioritaire en fonction de la grille d'administration
    function getIdLeaserPrioritaire(&$PDOdb) {
        global $db;

        $idLeaserPrioritaire = 0; //18305 ACECOM pour test

        $TFinGrilleSuivi = new TFin_grille_suivi;
        $grille = $TFinGrilleSuivi->get_grille($PDOdb, $this->fk_type_contrat, false, $this->entity);

        //Vérification si solde dossier sélectionné pour cette simulation : si oui on récupère le leaser associé
        $idLeaserDossierSolde = $this->getIdLeaserDossierSolde($PDOdb);

        //Récupération de la catégorie du client : entreprise, administration ou association
        // suivant sont code NAF
        // entreprise = les autres
        // association = 94
        // administration = 84
        $labelCategorie = $this->getLabelCategorieClient();

        //On récupère l'id du leaser prioritaire en fonction des règles de gestion
        foreach($grille as $TElement) {
            $TMontant = explode(';', $TElement['montant']);

            if($TMontant[0] < $this->montant_total_finance && $TMontant[1] >= $this->montant_total_finance && ! empty($TElement[$labelCategorie])) {
                //Si aucun solde sélectionné alors on on prends l'un des deux premier élément de la grille "Pas de solde / Refus du leaser en place"
                if($idLeaserDossierSolde) {
                    //Si dossier sélectionner à soldé, alors on prends la ligne concernée
                    $cat = new Categorie($db);
                    $TCats = $cat->containing($idLeaserDossierSolde, 1);

                    foreach($TCats as $categorie) {
                        if($categorie->id == $TElement['solde']) {
                            $idLeaserPrioritaire = $TElement[$labelCategorie];
                            return $idLeaserPrioritaire;
                        }
                    }
                }
                else {
                    $idLeaserPrioritaire = $TElement[$labelCategorie];
                    return $idLeaserPrioritaire;
                }
            }
        }

        return $idLeaserPrioritaire;
    }

    //Récupération de la catégorie du client : entreprise, administration ou association
    function getLabelCategorieClient() {
        switch(substr($this->societe->idprof3, 0, 2)) {
            case '84':
                $labelCategorie = 'administration';
                break;
            case '94':
                $labelCategorie = 'association';
                break;

            default:
                $labelCategorie = 'entreprise';
                break;
        }

        // On envoie toujours "entreprise" à BNP
        $labelCategorie = 'entreprise';

        return $labelCategorie;
    }

    //Vérification si solde dossier sélectionné pour cette simulation : si oui on récupère le leaser associé
    function getIdLeaserDossierSolde(&$PDOdb, $cat = false) {
        $idLeaserDossierSolde = $montantDossierSolde = 0;

        $TDossierUsed = array_merge(
            $this->dossiers_rachetes
            , $this->dossiers_rachetes_nr
            , $this->dossiers_rachetes_p1
            , $this->dossiers_rachetes_nr_p1
            , $this->dossiers_rachetes_m1
            , $this->dossiers_rachetes_nr_m1
        );

        if(count($TDossierUsed)) {
            foreach($TDossierUsed as $id_dossier => $data) {
                if(empty($data['checked'])) continue;
                if($data['montant'] > $montantDossierSolde) {
                    $dossier = new TFin_dossier;
                    $dossier->load($PDOdb, $data['checked']);
                    $idLeaserDossierSolde = $dossier->financementLeaser->fk_soc;
                    $montantDossierSolde = $data['montant'];
                }
            }
        }

        if($idLeaserDossierSolde > 0 && $cat) {
            return $this->getTCatLeaserFromLeaserId($idLeaserDossierSolde);
        }

        return $idLeaserDossierSolde;
    }

    // Vérifie si au moins un dossier a été sélectionné pour être soldé
    function has_solde_dossier_selected() {
        $TDossierUsed = array_merge(
            $this->dossiers_rachetes
            , $this->dossiers_rachetes_nr
            , $this->dossiers_rachetes_p1
            , $this->dossiers_rachetes_nr_p1
            , $this->dossiers_rachetes_m1
            , $this->dossiers_rachetes_nr_m1
        );

        if(count($TDossierUsed)) {
            foreach($TDossierUsed as $id_dossier => $data) {
                if(empty($data['checked'])) continue;
                return true;
            }
        }

        return false;
    }

    function get_suivi_simulation(&$PDOdb, &$form, $histo = false) {
        $TSuivi = array();
        foreach($this->TSimulationSuivi as $suivi) {
            if(($suivi->date_historization <= 0 && ! $histo) || ($suivi->date_historization > 0 && $histo)) {
                $TSuivi[$suivi->getId()] = $suivi;
            }
        }

        if($this->accord == "OK" || $histo) $form->type_aff = 'view';

        return $this->_get_lignes_suivi($TSuivi, $form);
    }

    private function _get_lignes_suivi(&$TSuivi, &$form) {
        global $db, $formDolibarr;

        require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
        if(empty($formDolibarr)) $formDolibarr = new Form($db);

        if(! class_exists('FormFile')) require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
        $formfile = new FormFile($db);

        $Tab = array();

        if(! empty($TSuivi)) {
            //Construction d'un tableau de ligne pour futur affichage TBS
            $TSuiviStdKeys = array_values($TSuivi);
            foreach($TSuiviStdKeys as $k => $simulationSuivi) {
                if($simulationSuivi->date_selection < 0) $simulationSuivi->date_selection = null;   // Prevent negative timestamps

                $link_user = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$simulationSuivi->fk_user_author.'">'.img_picto('', 'object_user.png', '', 0).' '.$simulationSuivi->user->login.'</a>';

                $ligne = array();

                $ligne['rowid'] = $simulationSuivi->getId();
                $ligne['class'] = ($k % 2) ? 'impair' : 'pair';
                $ligne['leaser'] = '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$simulationSuivi->fk_leaser.'">'.img_picto('', 'object_company.png', '', 0).' '.$simulationSuivi->leaser->nom.'</a>';
                $ligne['object'] = $simulationSuivi;
                $ligne['show_renta_percent'] = $formDolibarr->textwithpicto(price($simulationSuivi->renta_percent), implode('<br />', $simulationSuivi->calcul_detail), 1, 'help', '', 0, 3);
                $ligne['demande'] = ($simulationSuivi->statut_demande == 1) ? '<img src="'.dol_buildpath('/financement/img/check_valid.png', 1).'" />' : '';
                $ligne['date_demande'] = ($simulationSuivi->get_Date('date_demande')) ? $simulationSuivi->get_Date('date_demande', 'd/m/Y H:i:s') : '';
                $img = $simulationSuivi->statut;
                if(! empty($simulationSuivi->date_selection)) $img = 'super_ok';
                $ligne['resultat'] = ($simulationSuivi->statut) ? get_picto($img, $simulationSuivi->TStatut[$simulationSuivi->statut]) : '';
                $ligne['numero_accord_leaser'] = (($simulationSuivi->statut == 'WAIT' || $simulationSuivi->statut == 'OK') && $simulationSuivi->date_selection <= 0) ? $form->texte('', 'TSuivi['.$simulationSuivi->rowid.'][num_accord]', $simulationSuivi->numero_accord_leaser, 15, 0, 'style="text-align:right;"') : $simulationSuivi->numero_accord_leaser;

                $ligne['date_selection'] = ($simulationSuivi->get_Date('date_selection')) ? $simulationSuivi->get_Date('date_selection') : '';
                $ligne['utilisateur'] = ($simulationSuivi->fk_user_author && $simulationSuivi->date_cre != $simulationSuivi->date_maj) ? $link_user : '';

                $ligne['commentaire'] = nl2br($simulationSuivi->commentaire);
                $ligne['commentaire_interne'] = (($simulationSuivi->statut == 'WAIT' || $simulationSuivi->statut == 'OK') && $simulationSuivi->date_selection <= 0) ? $form->zonetexte('', 'TSuivi['.$simulationSuivi->rowid.'][commentaire_interne]', $simulationSuivi->commentaire_interne, 25, 0) : nl2br($simulationSuivi->commentaire_interne);
                $ligne['actions'] = $simulationSuivi->getAction($this);
                $ligne['action_save'] = $simulationSuivi->getAction($this, true);

                $subdir = $simulationSuivi->leaser->array_options['options_edi_leaser'];
                $ligne['doc'] = ! empty($subdir) ? $this->getDocumentsLink('financement', dol_sanitizeFileName($this->reference).'/'.$subdir, $this->getFilePath().'/'.$subdir) : '';

                $Tab[] = $ligne;
            }
        }

        return $Tab;
    }

    /**
     * Récupération et modification de la méthode getDocumentsLink() de la class FormFile
     * pour simplification du comportement et affichage des PDF
     *
     * @param type $modulepart
     * @param type $modulesubdir
     * @param type $filedir
     * @param type $entity
     * @return string
     */
    function getDocumentsLink($modulepart, $modulesubdir, $filedir, $entity = 1) {
        if(! function_exists('dol_dir_list')) include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

        $out = '';

        $file_list = dol_dir_list($filedir, 'files', 0, '[^\-]*'.'.pdf', '\.meta$|\.png$');

        if(! empty($file_list)) {
            // Loop on each file found
            foreach($file_list as $file) {
                // Define relative path for download link (depends on module)
                $relativepath = $file["name"];                                // Cas general
                if($modulesubdir) $relativepath = $modulesubdir."/".$file["name"];    // Cas propal, facture...

                $docurl = DOL_URL_ROOT.'/document.php?modulepart='.$modulepart.'&amp;file='.urlencode($relativepath);
                if(! empty($entity)) $docurl .= '&amp;entity='.$entity;
                // Show file name with link to download
                $out .= '<a data-ajax="false" href="'.$docurl.'"';
                $mime = dol_mimetype($relativepath, '', 0);
                if(preg_match('/text/', $mime)) $out .= ' target="_blank"';
                $out .= '>';
                $out .= img_pdf($file["name"], 2);
                $out .= '</a>'."\n";
            }
        }

        return $out;
    }

    /**
     * Calcul des élément du financement
     * Montant : Capital emprunté
     * Durée : Durée en trimestre
     * échéance : Echéance trimestrielle
     * VR : Valeur residuelle du financement
     * coeff : taux d'emprunt annuel
     *
     * @return $res :
     *            1    = calcul OK
     *            -1    = montant ou echeance vide (calcul impossible)
     *            -2    = montant hors grille
     *            -3    = echeance hors grille
     *            -4    = Pas de grille chargée
     */
    function calcul_financement(&$ATMdb, $idLeaser, $options, $typeCalcul = 'cpro') {
        global $conf;
        /*
		 * Formule de calcul échéance
		 *
		 * Echéance : Capital x tauxTrimestriel / (1 - (1 + tauxTrimestriel)^-nombreTrimestre )
		 *
		 */

        // Calcul du montant total financé
        $this->montant_total_finance = $this->montant + $this->montant_rachete + $this->montant_rachete_concurrence;
        // Changement du 13.09.02 : le montant renseigné comportera déjà le montant des rachats
        $this->montant_total_finance = $this->montant;

        $soldeSelected = $this->has_solde_dossier_selected();

        if(empty($this->fk_type_contrat)) { // Type de contrat obligatoire
            $this->error = 'ErrorNoTypeContratSelected';
            return false;
        }
        else if(empty($this->montant_total_finance) && empty($this->echeance)) { // Montant ou échéance obligatoire
            $this->error = 'ErrorMontantOrEcheanceRequired';
            return false;
        }
        else if($this->vr > $this->montant_total_finance && empty($this->echeance)) { // Erreur VR ne peut être supérieur au montant sauf si calcul via échéance
            $this->error = 'ErrorInvalidVR';
            return false;
        }
        else if(empty($this->duree)) { // Durée obligatoire
            $this->error = 'ErrorDureeRequired';
            return false;
        }
        else if(empty($this->opt_periodicite)) { // Périodicité obligatoire
            $this->error = 'ErrorPeriodiciteRequired';
            return false;
        }
        else if(empty($this->opt_no_case_to_settle)
            && empty($this->montant_rachete)
            && empty($soldeSelected)
            && empty($this->montant_rachete_concurrence)) { // Soit case "aucun dossier à solder", soit choix de dossier, soit saisie d'un montant rachat concurrence
            $this->error = 'ErrorCaseMandatory';
            return false;
        }
        else if(empty($this->type_materiel)) { // Périodicité obligatoire
            $this->error = 'ErrorMaterielRequired';
            return false;
        }
        else if($this->montant_presta_trim <= 0 && $this->fk_type_contrat == "FORFAITGLOBAL" && ! empty($conf->global->FINANCEMENT_MONTANT_PRESTATION_OBLIGATOIRE)) {
            $this->error = 'ErrorMontantTrimRequired';
            return false;
        }
        else if(! empty($this->opt_adjonction) && $this->fk_fin_dossier_adjonction <= 0) { // Dossier obligatoire si cochage adjonction
            $this->error = 'ErrorDossierAdjonctionRequired';
            return false;
        }

        // Récupération de la grille pour les paramètres donnés
        $grille = new TFin_grille_leaser;
        $grille->get_grille($ATMdb, $idLeaser, $this->fk_type_contrat, $this->opt_periodicite, $options, $this->entity);
        $this->last_grille_load = $grille;

        if(empty($grille->TGrille)) { // Pas de grille chargée, pas de calcul
            $this->error = 'ErrorNoGrilleSelected';
            return false;
        }

        if(! empty($this->montant_total_finance) && ! empty($this->echeance)) { // Si montant ET échéance renseignés, on calcule à partir du montant
            $this->echeance = 0;
        }

        $this->coeff = 0;
        // Calcul à partir du montant
        if(! empty($this->montant_total_finance)) {
            if(! empty($grille->TGrille[$this->duree])) {
                foreach($grille->TGrille[$this->duree] as $palier => $infos) {
                    if($this->montant_total_finance <= $palier) {
                        $this->coeff = $infos['coeff']; // coef trimestriel
                        break;
                    }
                }
            }
        }
        else if(! empty($this->echeance)) { // Calcul à partir de l'échéance
            foreach($grille->TGrille[$this->duree] as $palier => $infos) {
                if($infos['echeance'] >= $this->echeance) {
                    $this->coeff = $infos['coeff']; // coef trimestriel
                    break;
                }
            }
        }

        if($this->coeff == 0) {
            $this->error = 'ErrorAmountOutOfGrille'; // Be careful: clé de trad utilisé dans un test
            return false;
        }

        // Le coeff final renseigné par un admin prend le pas sur le coeff grille
        if(! empty($this->coeff_final) && $this->coeff_final != $this->coeff) {
            $this->coeff = $this->coeff_final;
        }

        $coeffTrimestriel = $this->coeff / 4 / 100; // en %

        if(! empty($this->montant_total_finance)) { // Calcul à partir du montant
            if($typeCalcul == 'cpro') { // Les coefficient sont trimestriel, à adapter en fonction de la périodicité de la simulation
                $this->echeance = ($this->montant_total_finance) * ($this->coeff / 100);
                if($this->opt_periodicite == 'ANNEE') $this->echeance *= 4;
                else if($this->opt_periodicite == 'SEMESTRE') $this->echeance *= 2;
                else if($this->opt_periodicite == 'MOIS') $this->echeance /= 3;
            }
            else {
                $this->echeance = $this->montant_total_finance * $coeffTrimestriel / (1 - pow(1 + $coeffTrimestriel, -$this->duree));
            }

            $this->echeance = round($this->echeance, 2);
        }
        else if(! empty($this->echeance)) { // Calcul à partir de l'échéance
            if($typeCalcul == 'cpro') {
                $this->montant = $this->echeance / ($this->coeff / 100);
                if($this->opt_periodicite == 'ANNEE') $this->montant /= 4;
                if($this->opt_periodicite == 'SEMESTRE') $this->montant /= 2;
                else if($this->opt_periodicite == 'MOIS') $this->montant *= 3;
            }
            else {
                $this->montant = $this->echeance * (1 - pow(1 + $coeffTrimestriel, -$this->duree)) / $coeffTrimestriel;
            }

            $this->montant = round($this->montant, 3);
            $this->montant_total_finance = $this->montant;
        }

        return true;
    }

    // TODO : Revoir validation financement avec les règles finales
    // Geoffrey to Maxime K => TOUJOURS D'ACTU ?
    function demande_accord() {
        global $conf;

        // Calcul du coût du financement
        $this->cout_financement = $this->echeance * $this->duree - $this->montant;

        // Résultat de l'accord
        $this->accord = '';

        // Accord interne de financement
        if(! (empty($this->fk_soc))) {
            $this->accord = 'WAIT';
            if($this->societe->score->rowid == 0 // Pas de score => WAIT
                || empty($this->societe->idprof3)) // Pas de NAF => WAIT
            {
                $this->accord = 'WAIT';
            }
            else { // Donnée suffisantes pour faire les vérifications pour l'accord
                // Calcul du montant disponible pour le client
                $montant_dispo = ($this->societe->score->encours_conseille - $this->societe->encours_cpro);
                $montant_dispo *= ($conf->global->FINANCEMENT_PERCENT_VALID_AMOUNT / 100);

                // Calcul du % de rachat
                $percent_rachat = (($this->montant_rachete + $this->montant_rachete_concurrence) / $this->montant_total_finance) * 100;

                if($this->societe->score->score >= $conf->global->FINANCEMENT_SCORE_MINI // Score minimum
                    && $this->montant_total_finance <= $conf->global->FINANCEMENT_MONTANT_MAX_ACCORD_AUTO // Montant ne dépasse pas le max
                    && $montant_dispo > $this->montant_total_finance // % "d'endettement"
                    && $percent_rachat <= $conf->global->FINANCEMENT_PERCENT_RACHAT_AUTORISE // % de rachat
                    && ! in_array($this->societe->idprof3, explode(FIN_IMPORT_FIELD_DELIMITER, $conf->global->FINANCEMENT_NAF_BLACKLIST)) // NAF non black-listé
                    && ! empty($this->societe->TDossiers)) // A déjà eu au moins un dossier chez CPRO
                {
                    $this->accord = 'OK';
                }
            }
        }
    }

    function load_by_soc(&$db, &$doliDB, $fk_soc) {
        dol_include_once('/financement/lib/financement.lib.php');

        $sql = "SELECT ".OBJETSTD_MASTERKEY;
        $sql .= " FROM ".$this->get_table();
        $sql .= " WHERE fk_soc = ".$fk_soc;
        $sql .= " AND entity IN(".getEntity('fin_simulation', true).')';

        $TIdSimu = TRequeteCore::_get_id_by_sql($db, $sql, OBJETSTD_MASTERKEY);
        $TResult = array();
        foreach($TIdSimu as $idSimu) {
            $simu = new TSimulation;
            $simu->load($db, $idSimu, false);
            $TResult[] = $simu;
        }

        return $TResult;
    }

    public static function isExistingObject($id = null, $fkSoc = null) {
        global $db;

        $sql = 'SELECT count(*) as nb';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation';
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

    function get_list_dossier_used($except_current = false) {
        $TDossier = array();

        if(! empty($this->societe->TSimulations)) {
            foreach($this->societe->TSimulations as $simu) {
                if($except_current && $simu->{OBJETSTD_MASTERKEY} == $this->{OBJETSTD_MASTERKEY}) continue;

                $datetimesimul = strtotime($simu->get_date('date_simul', 'Y-m-d'));
                $datetimenow = time();
                $nb_jour_diff = ($datetimenow - $datetimesimul) / 86400;

                foreach($simu->dossiers_rachetes as $k => $TDossiers_rachetes) {
                    if($this->dossier_used($simu, $TDossiers_rachetes, $nb_jour_diff) && ! in_array($k, array_keys($TDossier))) {
                        $TDossier[$k] = $TDossiers_rachetes['checked'];
                    }
                }
                foreach($simu->dossiers_rachetes_nr as $k => $TDossiers_rachetes) {
                    if($this->dossier_used($simu, $TDossiers_rachetes, $nb_jour_diff) && ! in_array($k, array_keys($TDossier))) {
                        $TDossier[$k] = $TDossiers_rachetes['checked'];
                    }
                }
                foreach($simu->dossiers_rachetes_p1 as $k => $TDossiers_rachetes) {
                    if($this->dossier_used($simu, $TDossiers_rachetes, $nb_jour_diff) && ! in_array($k, array_keys($TDossier))) {
                        $TDossier[$k] = $TDossiers_rachetes['checked'];
                    }
                }
                foreach($simu->dossiers_rachetes_nr_p1 as $k => $TDossiers_rachetes) {
                    if($this->dossier_used($simu, $TDossiers_rachetes, $nb_jour_diff) && ! in_array($k, array_keys($TDossier))) {
                        $TDossier[$k] = $TDossiers_rachetes['checked'];
                    }
                }
            }
        }

        return $TDossier;
    }

    function dossier_used(&$simu, &$TDossiers_rachetes, $nb_jour_diff) {
        global $conf;

        if(! is_array($TDossiers_rachetes)) $TDossiers_rachetes = array();
        if(array_key_exists('checked', $TDossiers_rachetes)
            && $nb_jour_diff <= $conf->global->FINANCEMENT_SIMU_NB_JOUR_DOSSIER_INDISPO) {
            return true;
        }

        return false;
    }

    function getFilePath() {
        $entityPath = '/';
        if($this->entity > 1 && ! file_exists(DOL_DATA_ROOT.'/financement/'.dol_sanitizeFileName($this->getRef()))) $entityPath .= $this->entity.'/';

        return DOL_DATA_ROOT.$entityPath.'financement/'.dol_sanitizeFileName($this->getRef());
    }

    function send_mail_vendeur($auto = false, $mailto = '') {
        global $langs, $conf, $db;

        dol_include_once('/core/class/html.formmail.class.php');
        dol_include_once('/core/lib/files.lib.php');
        dol_include_once('/core/class/CMailFile.class.php');
        if(! function_exists('switchEntity')) dol_include_once('/financement/lib/financement.lib.php');

        $PDFName = dol_sanitizeFileName($this->getRef()).'.pdf';
        $PDFPath = $this->getFilePath();

        $formmail = new FormMail($db);
        $formmail->clear_attached_files();
        $formmail->add_attached_files($PDFPath.'/'.$PDFName, $PDFName, dol_mimetype($PDFName));

        $attachedfiles = $formmail->get_attached_files();
        $filepath = $attachedfiles['paths'];
        $filename = $attachedfiles['names'];
        $mimetype = $attachedfiles['mimes'];

        if($this->accord == 'OK') {
            $accord = ($auto) ? 'Accord automatique' : 'Accord de la cellule financement';
            $mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
            $mesg .= 'Vous trouverez ci-joint l\'accord de financement concernant votre simulation n '.$this->reference.'.'."\n\n";
            if(! empty($this->commentaire)) $mesg .= 'Commentaire : '."\n".$this->commentaire."\n\n";
        }
        else if($this->accord == 'WAIT_AP') {
            $accord = 'Accord de principe';
            $mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
            $mesg .= 'Vous trouverez ci-joint un accord de principe concernant la simulation '.$this->reference.'.'."\n";
            $mesg .= "Dans un second temps nous vous enverrons l'accord de financement définitif\n\n";
        }
        else {  // Refus
            $retourLeaser = '';
//            foreach($this->TSimulationSuivi as $suivi) {
//                if(! empty($suivi->commentaire_interne)) {
//                    $retourLeaser .= ' - '.$suivi->commentaire_interne."\n";
//                }
//            }
            if(! empty($this->commentaire)) {
                $retourLeaser .= $this->commentaire;
            }

            $accord = 'Demande de financement refusée';
            $mesg = 'Bonjour '.$this->user->getFullName($langs).",\n\n";
            $mesg .= 'Nous avons bien réceptionné votre demande n° '.$this->reference.' du '.date('d/m/Y', $this->date_simul).' :'."\n\n";
            $mesg .= 'Notre réponse : Refus'."\n\n";
            $mesg .= 'Raison sociale : '.$this->societe->getFullName($langs)."\n";
            $mesg .= 'Matériel : '.$this->type_materiel."\n";
            $mesg .= 'Durée : '.$this->duree.' '.ucfirst(strtolower($this->opt_periodicite)).'s'."\n";
            $mesg .= 'Loyer trimestriel H.T. : '.price($this->echeance).' €'."\n\n";
            $mesg .= 'Nous avons étudié votre demande avec minutie. Cependant, nous regrettons de ne pouvoir y donner une suite favorable.'."\n";
            // On enlève cette phrase pour les entités Esus, ABS, Copem, Omniburo, Lorraine repro, CENA, Alkia
            if(! in_array($this->entity, array(18, 25, 6, 26, 20, 23, 29))) {
                $mesg .= 'Nous pouvons éventuellement réviser cette décision avec le dernier bilan de l\'entreprise.'."\n\n";
            }
            $mesg .= 'Motifs : '.$retourLeaser."\n\n";
            $mesg .= 'Veuillez agréer, nos sincères salutations.'."\n\n";
            $mesg .= 'Le Service Financement C\'PRO';
        }

        // Le mail de refus a une signature personnalisée
        if(in_array($this->accord, array('OK', 'WAIT_AP'))) {
            $mesg .= 'Cordialement,'."\n\n";
            $mesg .= 'La cellule financement'."\n\n";
        }

        $subject = 'Simulation '.$this->reference.' - '.$this->societe->getFullName($langs).' - '.number_format($this->montant_total_finance, 2, ',', ' ').' Euros - '.$accord;

        if(empty($mailto)) $mailto = $this->user->email;

        $old_entity = $conf->entity;
        switchEntity($this->entity);    // Switch to simulation entity

        $r = new TReponseMail($conf->global->MAIN_MAIL_EMAIL_FROM, $mailto, $subject, $mesg);

        if(! empty($conf->global->FINANCEMENT_DEFAULT_MAIL_RECIPIENT) && isValidEmail($conf->global->FINANCEMENT_DEFAULT_MAIL_RECIPIENT)) {
            $r->emailtoBcc = $conf->global->FINANCEMENT_DEFAULT_MAIL_RECIPIENT;
        }

        switchEntity($old_entity);

        foreach($filename as $k => $file) {
            $r->add_piece_jointe($filename[$k], $filepath[$k]);
        }

        $r->send(false);

        setEventMessage('Accord envoyé à : '.$mailto, 'mesgs');
    }

    /**
     * Fonction spécifique à ESUS et ABS pour leur envoyer des PDF tout aussi spécifiques, la classe...
     */
    function send_mail_vendeur_esus($auto = false) {
        global $langs, $conf, $db;

        dol_include_once('/core/class/html.formmail.class.php');
        dol_include_once('/core/lib/files.lib.php');
        dol_include_once('/core/class/CMailFile.class.php');
        if(! function_exists('switchEntity')) dol_include_once('/financement/lib/financement.lib.php');

        $PDFName = dol_sanitizeFileName($this->getRef());
        if($this->entity == 18) $PDFName .= '-esus.pdf';
        elseif($this->entity == 25) $PDFName .= '-abs.pdf';
        else if($this->entity == 28) $PDFName .= '-smep.pdf';

        $PDFPath = $this->getFilePath();

        $formmail = new FormMail($db);
        $formmail->clear_attached_files();
        $formmail->add_attached_files($PDFPath.'/'.$PDFName, $PDFName, dol_mimetype($PDFName));

        $attachedfiles = $formmail->get_attached_files();
        $filepath = $attachedfiles['paths'];
        $filename = $attachedfiles['names'];
        $mimetype = $attachedfiles['mimes'];

        if($this->accord == 'OK') {
            $accord = ($auto) ? 'Accord automatique' : 'Accord de la cellule financement';
            $mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
            $mesg .= 'Vous trouverez ci-joint l\'accord de financement concernant votre simulation n '.$this->reference.'.'."\n\n";
            if(! empty($this->commentaire)) $mesg .= 'Commentaire : '."\n".$this->commentaire."\n\n";
        }
        else {
            $retourLeaser = '';
            foreach($this->TSimulationSuivi as $suivi) {
                if(! empty($suivi->commentaire)) {
                    $retourLeaser .= ' - '.$suivi->commentaire."\n";
                }
            }

            $accord = 'Demande de financement refusée';

            // Message générique
            $mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
            $mesg .= 'Votre demande de financement via la simulation n '.$this->reference.' n\'a pas été acceptée.'."\n\n";
            if(! empty($this->commentaire)) $mesg .= 'Commentaire : '."\n".$this->commentaire."\n\n";
        }

        $mesg .= 'Cordialement,'."\n\n";
        $mesg .= 'La cellule financement'."\n\n";

        $subject = 'Simulation '.$this->reference.' - '.$this->societe->getFullName($langs).' - '.number_format($this->montant_total_finance, 2, ',', ' ').' Euros - '.$accord;

        if($this->entity == 18) $mailto = 'rachat.esus@zeenmail.com';   // ESUS
        elseif($this->entity == 25) $mailto = 'rachatabs.esus@zeenmail.com';    // ABS
        else if($this->entity == 28) $mailto = 'rachatsmep.esus@zeenmail.com';    // SMEP

        $old_entity = $conf->entity;
        switchEntity($this->entity);    // Switch to simulation entity

        if(empty($conf->global->FINANCEMENT_MODE_PROD)) return;   // Juste au cas où on se trouve sur la base TEST

        $r = new TReponseMail($conf->global->MAIN_MAIL_EMAIL_FROM, $mailto, $subject, $mesg);

        switchEntity($old_entity);

        foreach($filename as $k => $file) {
            $r->add_piece_jointe($filename[$k], $filepath[$k]);
        }

        $r->send(false);

        setEventMessage('Accord envoyé à : '.$mailto, 'mesgs');
    }

    function _getDossierSelected() {
        $TDossier = array();

        foreach($this->dossiers_rachetes_m1 as $idDossier => $TData) {
            if(! empty($this->dossiers_rachetes_m1[$idDossier]['checked'])) {
                $TDossier[$idDossier] = $idDossier;
            }
        }
        foreach($this->dossiers_rachetes as $idDossier => $TData) {
            if(! empty($this->dossiers_rachetes[$idDossier]['checked'])) {
                $TDossier[$idDossier] = $idDossier;
            }
        }
        foreach($this->dossiers_rachetes_p1 as $idDossier => $TData) {
            if(! empty($this->dossiers_rachetes_p1[$idDossier]['checked'])) {
                $TDossier[$idDossier] = $idDossier;
            }
        }
        foreach($this->dossiers_rachetes_nr_m1 as $idDossier => $TData) {
            if(! empty($this->dossiers_rachetes_nr_m1[$idDossier]['checked'])) {
                $TDossier[$idDossier] = $idDossier;
            }
        }
        foreach($this->dossiers_rachetes_nr as $idDossier => $TData) {
            if(! empty($this->dossiers_rachetes_nr[$idDossier]['checked'])) {
                $TDossier[$idDossier] = $idDossier;
            }
        }
        foreach($this->dossiers_rachetes_nr_p1 as $idDossier => $TData) {
            if(! empty($this->dossiers_rachetes_nr_p1[$idDossier]['checked'])) {
                $TDossier[$idDossier] = $idDossier;
            }
        }

        return $TDossier;
    }

    function gen_simulation_pdf(&$ATMdb, &$doliDB) {
        global $mysoc, $conf, $langs;

        $old_entity = $conf->entity;
        switchEntity($this->entity);    // $conf and $mysoc may be changed

        $a = new TFin_affaire;
        $f = new TFin_financement;

        // Infos de la simulation
        $simu = $this;
        $simu->type_contrat = $a->TContrat[$this->fk_type_contrat];
        $simu->periodicite = $f->TPeriodicite[$this->opt_periodicite];
        $simu->mode_rglt = $f->TReglement[$this->opt_mode_reglement];
        $simu->statut = html_entity_decode($this->getStatut());
        $back_opt_calage = $simu->opt_calage;
        $simu->opt_calage = $f->TCalage[$simu->opt_calage];
        $back_opt_adjonction = $simu->opt_adjonction;
        $simu->opt_adjonction = ($simu->opt_adjonction) ? "Oui" : "Non";

        // Dossiers rachetés dans la simulation
        $TDossier = array();
        $TDossierperso = array();

        $ATMdb2 = new TPDOdb; // #478 par contre je ne vois pas pourquoi il faut une connexion distincte :/

        $TSimuDossier = $this->_getDossierSelected();
        foreach($TSimuDossier as $idDossier => $Tdata) {
            $d = new TFin_dossier();
            $d->load($ATMdb, $idDossier);

            if($d->nature_financement == 'INTERNE') {
                $f = &$d->financement;
                $type = 'CLIENT';
            }
            else {
                $f = &$d->financementLeaser;
                $type = 'LEASER';
            }

            if($d->nature_financement == 'INTERNE') {
                $f->reference .= ' / '.$d->financementLeaser->reference;
            }

            $dossierRachete = '';
            foreach($this->DossierRachete as $dr) {
                if($dr->fk_dossier == $idDossier) {
                    $dossierRachete = $dr;
                    break;
                }
            }
            $periode_solde = ! empty($dossierRachete->choice) ? $dossierRachete->choice : '';
            $periode_solde = strtr($periode_solde, array('prev' => '_m1', 'curr' => '', 'next' => '_p1'));
            $datemax_deb = $dossierRachete->{'date_debut_periode_client'.$periode_solde};
            $datemax_fin = $dossierRachete->{'date_fin_periode_client'.$periode_solde};
            $solde_r = $dossierRachete->{'solde_vendeur'.$periode_solde};

            $leaser = new Societe($doliDB);
            $leaser->fetch($dossierRachete->fk_leaser);
            $TDossier[] = array(
                'reference' => $f->reference
                , 'leaser' => $leaser->name
                , 'type_contrat' => $d->type_contrat
                , 'solde_r' => $solde_r
                , 'datemax_debut' => $datemax_deb
                , 'datemax_fin' => $datemax_fin
            );
        }

        $this->hasdossier = count($TDossier) + count($TDossierperso);

        // Création du répertoire
        $fileName = dol_sanitizeFileName($this->getRef()).'.odt';
        $filePath = $this->getFilePath();
        dol_mkdir($filePath);

        if($this->fk_leaser) {
            $leaser = new Societe($doliDB);
            $leaser->fetch($this->fk_leaser);
            $this->leaser = $leaser;
        }

        $simu2 = $simu;
        // Le type de contrat est en utf8 (libellé vient de la table), contrairement au mode de prélèvement qui vient d'un fichier de langue.
        $simu2->type_contrat = utf8_decode($simu2->type_contrat);
        // Génération en ODT

        if(! empty($this->thirdparty_address)) $this->societe->address = $this->thirdparty_address;
        if(! empty($this->thirdparty_zip)) $this->societe->zip = $this->thirdparty_zip;
        if(! empty($this->thirdparty_town)) $this->societe->town = $this->thirdparty_town;

        if(! empty($this->thirdparty_code_client)) $this->societe->code_client = $this->thirdparty_code_client;
        if(! empty($this->thirdparty_idprof2_siret)) $this->societe->idprof2 = $this->thirdparty_idprof2_siret;
        if(! empty($this->thirdparty_idprof3_naf)) $this->societe->idprof3 = $this->thirdparty_idprof3_naf;

        if($simu2->opt_periodicite == 'MOIS') $simu2->coeff_by_periodicite = $simu2->coeff / 3;
        else if($simu2->opt_periodicite == 'SEMESTRE') $simu2->coeff_by_periodicite = $simu2->coeff * 2;
        else if($simu2->opt_periodicite == 'ANNEE') $simu2->coeff_by_periodicite = $simu2->coeff * 4;
        else $simu2->coeff_by_periodicite = $simu2->coeff; // TRIMESTRE

        if(in_array($this->entity, array(18, 25, 28))) $simu2->dateLabel = $langs->trans('DateDemarrageCustom');
        else $simu2->dateLabel = $langs->trans('DateDemarrage');

        // Récupération du logo de l'entité correspondant à la simulation
        $logo = DOL_DATA_ROOT.'/'.(($this->entity > 1) ? $this->entity.'/' : '').'mycompany/logos/'.$mysoc->logo;
        $simu2->logo = $logo;

        $TBS = new TTemplateTBS;
        $file = $TBS->render('./tpl/doc/simulation.odt'
            , array(
                'dossier' => $TDossier
            )
            , array(
                'simulation' => $simu2
                , 'client' => $this->societe
                , 'leaser' => array('nom' => (($this->leaser->nom != '') ? $this->leaser->nom : ''))
                , 'autre' => array('terme' => ($this->TTerme[$simu2->opt_terme]) ? $this->TTerme[$simu2->opt_terme] : ''
                                   , 'type' => ($this->hasdossier) ? 1 : 0)
            )
            , array()
            , array(
                'outFile' => $filePath.'/'.$fileName
                , 'charset' => 'utf-8'
            )
        );

        $simu->opt_adjonction = $back_opt_adjonction;
        $simu->opt_calage = $back_opt_calage;

        // Transformation en PDF
        $cmd = 'export HOME=/tmp'."\n";
        $cmd .= 'libreoffice --invisible --norestore --headless --convert-to pdf --outdir '.$filePath.' '.$filePath.'/'.$fileName;
        ob_start();
        system($cmd);
        $res = ob_get_clean();

        switchEntity($old_entity);    // $conf and $mysoc may be changed
    }

    /**
     * Fonction spécifique à ESUS et ABS qui demandent des infos en plus dans un autre PDF...
     */
    function gen_simulation_pdf_esus(&$ATMdb, &$doliDB) {
        global $mysoc, $TLeaserCat, $db, $conf, $langs;

        $old_entity = $conf->entity;
        switchEntity($this->entity);    // $conf and $mysoc may be changed

        if(empty($TLeaserCat)) {
            $sql = 'SELECT cf.fk_soc as fk_soc, cf.fk_categorie as fk_cat';
            $sql .= ' FROM llx_categorie_fournisseur cf';
            $sql .= ' LEFT JOIN llx_categorie c ON (c.rowid = cf.fk_categorie)';
            $sql .= ' LEFT JOIN llx_categorie c2 ON (c2.rowid = c.fk_parent)';
            $sql .= " WHERE c2.label = 'Leaser'";

            $resql = $db->query($sql);
            if($resql) {
                while($obj = $db->fetch_object($resql)) $TLeaserCat[$obj->fk_soc] = $obj->fk_cat;
            }
        }

        $a = new TFin_affaire;
        $f = new TFin_financement;

        // Infos de la simulation
        $simu = $this;
        $simu->type_contrat = $a->TContrat[$this->fk_type_contrat];
        $simu->periodicite = $f->TPeriodicite[$this->opt_periodicite];
        $simu->mode_rglt = $f->TReglement[$this->opt_mode_reglement];
        $simu->statut = html_entity_decode($this->getStatut());
        $back_opt_calage = $simu->opt_calage;
        $simu->opt_calage = $f->TCalage[$simu->opt_calage];
        $back_opt_adjonction = $simu->opt_adjonction;
        $simu->opt_adjonction = ($simu->opt_adjonction) ? "Oui" : "Non";

        // Dossiers rachetés dans la simulation
        $TDossier = array();
        $TDossierperso = array();

        $TSimuDossier = $this->_getDossierSelected();
        foreach($TSimuDossier as $idDossier) {
            $d = new TFin_dossier();
            $d->load($ATMdb, $idDossier);

            if($d->nature_financement == 'INTERNE') {
                $f = &$d->financement;
                $type = 'CLIENT';
            }
            else {
                $f = &$d->financementLeaser;
                $type = 'LEASER';
            }

            if($d->nature_financement == 'INTERNE') {
                $f->reference .= ' / '.$d->financementLeaser->reference;
            }

            $dossierRachete = '';
            foreach($this->DossierRachete as $dr) {
                if($dr->fk_dossier == $idDossier) {
                    $dossierRachete = $dr;
                    break;
                }
            }
            $periode_solde = ! empty($dossierRachete->choice) ? $dossierRachete->choice : '';
            $periode_solde = strtr($periode_solde, array('prev' => '_m1', 'curr' => '', 'next' => '_p1'));
            $datemax_deb = $dossierRachete->{'date_debut_periode_client'.$periode_solde};
            $datemax_fin = $dossierRachete->{'date_fin_periode_client'.$periode_solde};
            $solde_r = $dossierRachete->{'solde_vendeur'.$periode_solde};

            $leaser = new Societe($doliDB);
            $leaser->fetch($dossierRachete->fk_leaser);

            $refus = false;
            foreach($simu->TSimulationSuivi as $suivi) {
                if($TLeaserCat[$d->financementLeaser->fk_soc] == $TLeaserCat[$suivi->fk_leaser] && $suivi->statut == 'KO') {
                    $refus = true;
                    break;
                }
            }

            $echeance = $d->_get_num_echeance_from_date($datemax_deb);
            if($refus || $TLeaserCat[$simu->fk_leaser] == $TLeaserCat[$d->financementLeaser->fk_soc]) {
                $type_solde = 'R';
                $solde_banque = $d->getSolde($ATMdb, 'SRBANK', $echeance+1);
            }
            else {
                $type_solde = 'NR';
                $solde_banque = $d->getSolde($ATMdb, 'SNRBANK', $echeance+1);
            }

            $TDossier[] = array(
                'reference' => $f->reference
                , 'leaser' => $leaser->name
                , 'type_contrat' => $d->type_contrat
                , 'solde_r' => $solde_r
                , 'solde_banque' => $solde_banque
                , 'type_solde' => $type_solde
                , 'datemax_debut' => $datemax_deb
                , 'datemax_fin' => $datemax_fin
            );
        }

        $this->hasdossier = count($TDossier) + count($TDossierperso);

        // Création du répertoire
        $fileName = dol_sanitizeFileName($this->getRef());
        if($this->entity == 18) $fileName .= '-esus.odt';
        elseif($this->entity == 25) $fileName .= '-abs.odt';
        else if($this->entity == 28) $fileName .= '-smep.odt';

        $filePath = $this->getFilePath();
        dol_mkdir($filePath);

        if($this->fk_leaser) {
            $leaser = new Societe($doliDB);
            $leaser->fetch($this->fk_leaser);
            $this->leaser = $leaser;
        }

        $simu2 = $simu;
        // Le type de contrat est en utf8 (libellé vient de la table), contrairement au mode de prélèvement qui vient d'un fichier de langue.
        $simu2->type_contrat = utf8_decode($simu2->type_contrat);
        // Génération en ODT

        if(! empty($this->thirdparty_address)) $this->societe->address = $this->thirdparty_address;
        if(! empty($this->thirdparty_zip)) $this->societe->zip = $this->thirdparty_zip;
        if(! empty($this->thirdparty_town)) $this->societe->town = $this->thirdparty_town;

        if(! empty($this->thirdparty_code_client)) $this->societe->code_client = $this->thirdparty_code_client;
        if(! empty($this->thirdparty_idprof2_siret)) $this->societe->idprof2 = $this->thirdparty_idprof2_siret;
        if(! empty($this->thirdparty_idprof3_naf)) $this->societe->idprof3 = $this->thirdparty_idprof3_naf;

        if($simu2->opt_periodicite == 'MOIS') $simu2->coeff_by_periodicite = $simu2->coeff / 3;
        else if($simu2->opt_periodicite == 'SEMESTRE') $simu2->coeff_by_periodicite = $simu2->coeff * 2;
        else if($simu2->opt_periodicite == 'ANNEE') $simu2->coeff_by_periodicite = $simu2->coeff * 4;
        else $simu2->coeff_by_periodicite = $simu2->coeff; // TRIMESTRE

        // Récupération du logo de l'entité correspondant à la simulation
        $logo = DOL_DATA_ROOT.'/'.(($this->entity > 1) ? $this->entity.'/' : '').'mycompany/logos/'.$mysoc->logo;
        $simu2->logo = $logo;

        $TBS = new TTemplateTBS;
        $file = $TBS->render('./tpl/doc/simulation_esus.odt'
            , array(
                'dossier' => $TDossier
            )
            , array(
                'simulation' => $simu2
                , 'client' => $this->societe
                , 'leaser' => array('nom' => (($this->leaser->nom != '') ? $this->leaser->nom : ''))
                , 'autre' => array('terme' => ($this->TTerme[$simu2->opt_terme]) ? $this->TTerme[$simu2->opt_terme] : ''
                                   , 'type' => ($this->hasdossier) ? 1 : 0)
            )
            , array()
            , array(
                'outFile' => $filePath.'/'.$fileName
                , 'charset' => 'utf-8'
            )
        );

        $simu->opt_adjonction = $back_opt_adjonction;
        $simu->opt_calage = $back_opt_calage;

        // Transformation en PDF
        $cmd = 'export HOME=/tmp'."\n";
        $cmd .= 'libreoffice --invisible --norestore --headless --convert-to pdf --outdir '.$filePath.' '.$filePath.'/'.$fileName;
        ob_start();
        system($cmd);
        $res = ob_get_clean();

        switchEntity($old_entity);    // $conf and $mysoc may be changed
    }

    function _calcul(&$ATMdb, $mode = 'calcul', $options = array(), $forceoptions = false) {
        global $mesg, $error, $langs;

        if(empty($options)) {
            foreach($_POST as $k => $v) {
                if(substr($k, 0, 4) == 'opt_') {
                    $options[$k] = $v;
                }
            }
            // Si les paramètre ne sont pas passé par formulaire, on garde les options de l'objet
            foreach($this as $k => $v) {
                if(substr($k, 0, 4) == 'opt_' && (empty($options[$k]) || $forceoptions)) {
                    $options[$k] = $v;
                }
            }
        }

        // 2017.03.14 MKO : si type grand compte, on n'applique pas la pénalité sur le mode de règlement
        if($this->fk_type_contrat == 'GRANDCOMPTE') unset($options['opt_mode_reglement']);

        $calcul = $this->calcul_financement($ATMdb, FIN_LEASER_DEFAULT, $options); // Calcul du financement

        // 2017.12.13
        // Calcul VR
        $this->vr = round($this->montant_total_finance * $this->pct_vr / 100, 2);
        if(empty($this->vr)) $this->vr = 1;

        if(! $calcul) { // Si calcul non correct
            $this->montant_total_finance = 0;
            $mesg = $langs->trans($this->error);
            $error = true;
        }
        else if($this->accord_confirme == 0) { // Sinon, vérification accord à partir du calcul
            if($this->accord == 'OK') {
                $this->date_accord = time();
            }

            if(($this->accord == 'WAIT') && ($_REQUEST['accord'] == 'WAIT_LEASER' || $_REQUEST['accord'] == 'WAIT_SELLER')) $this->accord = $_REQUEST['accord'];

            if($mode == 'save' && ($this->accord == 'OK' || $this->accord == 'KO')) { // Si le vendeur enregistre sa simulation est OK automatique, envoi mail
                $this->send_mail_vendeur(true);

                if($this->accord = 'OK' && in_array($this->entity, array(18, 25, 28)) && empty($this->opt_no_case_to_settle)) {
                    $this->send_mail_vendeur_esus(true);
                }
            }
        }
    }

    function delete_accord_history(&$ATMdb) {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."fin_simulation_accord_log WHERE fk_simulation = ".$this->getId();
        $ATMdb->Execute($sql);
    }

    function historise_accord(&$ATMdb, $date = '') {
        global $user;

        if(empty($date)) $date = date("Y-m-d H:i:s", dol_now());
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."fin_simulation_accord_log (`entity`, `fk_simulation`, `fk_user_author`, `datechange`, `accord`)";
        $sql .= " VALUES ('".$this->entity."', '".$this->getId()."', '".$user->id."', '".$date."', '".$this->accord."');";
        $ATMdb->Execute($sql);
    }

    function get_attente(&$ATMdb, $nosave = 0) {
        global $conf, $db;

        if($this->getId() == '') return 0;

        $sql = "SELECT datechange, accord FROM ".MAIN_DB_PREFIX."fin_simulation_accord_log ";
        $sql .= " WHERE fk_simulation = ".$this->getId();
        $sql .= " AND entity = ".$this->entity;
        $sql .= " ORDER BY datechange ASC";
        $ATMdb->Execute($sql);

        $TDates = array();
        $i = 0;
        while($ATMdb->Get_line()) {
            $TDates[$i] = array('start' => $ATMdb->Get_field('datechange'), 'accord' => $ATMdb->Get_field('accord'), 'end' => date("Y-m-d H:i:s", dol_now()));
            if(! empty($i)) $TDates[$i - 1]['end'] = $ATMdb->Get_field('datechange');
            $i++;
        }

        $closed = array('OK', 'KO', 'SS');
        if(count($TDates) == 0) {
            if(! in_array($this->accord, $closed)) {
                $this->historise_accord($ATMdb, date("Y-m-d H:i:s", $this->date_simul));
                return $this->get_attente($ATMdb);
            }
            else {
                $oldAccord = $this->accord;
                $this->accord = "WAIT";
                $this->historise_accord($ATMdb, date("Y-m-d H:i:s", $this->date_simul));
                $this->accord = $oldAccord;
                $this->historise_accord($ATMdb, date("Y-m-d H:i:s", $this->date_accord));
                return $this->get_attente($ATMdb);
            }
        }
        else {
            $compteur = 0;
            foreach($TDates as $interval) {
                if($interval['accord'] == "WAIT" || $interval['accord'] == "WAIT_LEASER") {
                    $start = strtotime($interval['start']);
                    $end = strtotime($interval['end']);

                    $cpt = 0;
                    while($start < $end && $cpt < 40) {
                        $start = $this->_jourouvre($ATMdb, $start);
                        if($start > dol_now()) break 2;
                        if($start > $end) $start = $end;
                        if($start !== $end) $start = $this->_calcul_interval($compteur, $start, $end);
                        $cpt++;
                    }
                }
            }

            if($compteur < 0) $compteur = 0;

            $this->attente = $compteur;
            if(! $nosave) $this->save($ATMdb);

            $style = '';
            $min = (int) ($compteur / 60);
            if(! empty($conf->global->FINANCEMENT_FIRST_WAIT_ALARM) && $min >= (int) $conf->global->FINANCEMENT_FIRST_WAIT_ALARM) $style = 'color:orange';
            if(! empty($conf->global->FINANCEMENT_SECOND_WAIT_ALARM) && $min >= (int) $conf->global->FINANCEMENT_SECOND_WAIT_ALARM) $style = 'color:red';
            if(! empty($style)) $this->attente_style = $style;

            $min = ($compteur / 60) % 60;
            $heures = abs(round((($compteur / 60) - $min) / 60));

            $ret = '';
            $ret .= (! empty($heures) ? $heures." h " : "");
            $ret .= (! empty($min) ? $min." min" : "");

            return $ret;
        }
    }

    /**
     * Retourne le prochain jour ouvré ou le timestamp entré si celui-ci est dans un jour ouvré et dans un interval d'ouverture
     * @param timestamp $start
     * @return timestamp
     */
    function _jourouvre($ATMdb, $start) {
        global $conf;

        dol_include_once('/jouroff/class/jouroff.class.php');
        $Jo = new TRH_JoursFeries();
        $cp = 0;
        $searchjourouvre = true;
        // on cherche un jour ouvré jusqu'à ce qu'on en trouve un ou qu'on excède le nombre de 10 tentatives
        while($searchjourouvre && $cp < 10) {
            $matindebut = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_DEBUT_MATIN.":00", $start));
            $matinfin = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_FIN_MATIN.":00", $start));
            $apremdebut = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_DEBUT_APREM.":00", $start));
            $apremfin = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_FIN_APREM.":00", $start));

            $ferie = $Jo->estFerie($ATMdb, date("Y-m-d 00:00:00", $start)); // retourne true si c'est un jour férié
            $nextday = mktime(date("H", $matindebut), date("i", $matindebut), 0, date("m", $start), date("d", $start) + 1, date("Y", $start));
            $joursemaine = date("N", $start);

            if($ferie || $start >= $apremfin || $joursemaine == '6' || $joursemaine == '7') {
                $start = $nextday;
            }
            else if($start < $matindebut) {
                $start = $matindebut;
                $searchjourouvre = false;
            }
            else {
                $searchjourouvre = false;
            }
            $cp++;
        }

        return $start;
    }

    function _calcul_interval(&$compteur, $start, $end) {
        global $conf;

        $matindebut = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_DEBUT_MATIN.":00", $start));
        $matinfin = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_FIN_MATIN.":00", $start));
        $apremdebut = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_DEBUT_APREM.":00", $start));
        $apremfin = strtotime(date("Y-m-d ".$conf->global->FINANCEMENT_HEURE_FIN_APREM.":00", $start));

        if($start < $matindebut) $start = $matindebut;
        if($start < $matinfin) {
            if($end < $matinfin) {
                $compteur += $end - $start;
                $start = $end;
            }
            else {
                $compteur += $matinfin - $start;
                if($end < $apremdebut) $start = $end;
                if($end > $apremdebut && $end < $apremfin) {
                    $compteur += $end - $apremdebut;
                    $start = $end;
                }
                else if($end > $apremfin) {
                    $compteur += $apremfin - $apremdebut;
                    $start = strtotime(date("Y-m-d H:i:01", $apremfin));
                }
            }
        }
        else if($start < $apremfin) {
            if($end > $apremfin) {
                if($start > $apremdebut) $compteur += $apremfin - $start;
                else $compteur += $apremfin - $apremdebut;
                $start = strtotime(date("Y-m-d H:i:01", $apremfin));
            }
            else {
                if($start > $apremdebut) $compteur += $end - $start;
                else $compteur += $end - $apremdebut;
                $start = $end;
            }
        }

        return $start;
    }

    /**
     * Called from uasort
     *
     * @param type $a
     * @param type $b
     * @return int
     */
    public function aiguillageSuivi($a, $b) {
        if($a->renta_percent < $b->renta_percent) return 1;
        else if($a->renta_percent > $b->renta_percent) return -1;
        else return 0;
    }

    /**
     * Called from uasort
     *
     * @param type $a
     * @param type $b
     * @return int
     */
    public function aiguillageSuiviRang($a, $b) {
        if($a->rang < $b->rang) return -1;
        else if($a->rang > $b->rang) return 1;
        else return 0;
    }

    public function calculAiguillageSuivi(&$PDOdb, $force_calcul = false) {
        global $conf;

        $oldconf = $conf;
        switchEntity($this->entity);

        if(empty($conf->global->FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI)) return 0;

        // Adjonction : leaser du dossier concerné est à mettre en 1er dans le suivi
        $this->fk_leaser_adjonction = 0;
        if(! empty($this->fk_fin_dossier_adjonction)) {
            $doss = new TFin_dossier();
            $doss->load($PDOdb, $this->fk_fin_dossier_adjonction, false, false);
            $doss->load_financement($PDOdb);
            $this->fk_leaser_adjonction = $doss->financementLeaser->fk_soc;
        }

        $TMethod = explode(',', $conf->global->FINANCEMENT_METHOD_TO_CALCUL_RENTA_SUIVI);

        $min_turn_over = null;
        foreach($this->TSimulationSuivi as $fk_suivi => &$suivi) {
            // On ne s'occupe pas des suivis historisés
            if($suivi->date_historization > 0) continue;

            if($force_calcul) {
                $suivi->surfact = 0;
                $suivi->surfactplus = 0;
                $suivi->commission = 0;
                $suivi->intercalaire = 0;
                $suivi->diff_solde = 0;
                $suivi->prime_volume = 0;
                $suivi->turn_over = 0;
            }

            $this->calculMontantFinanceLeaser($PDOdb, $suivi);
            foreach($TMethod as $method_name) {
                $this->{$method_name}($PDOdb, $suivi);
            }

            if($suivi->turn_over > 0 && ($suivi->turn_over < $min_turn_over || is_null($min_turn_over))) $min_turn_over = $suivi->turn_over;
        }

        foreach($this->TSimulationSuivi as $fk_suivi => &$suivi) {
            // On ne s'occupe pas des suivis historisés
            if($suivi->date_historization > 0) continue;

            $suivi->renta_amount = $suivi->surfact + $suivi->surfactplus + $suivi->commission + $suivi->intercalaire + $suivi->diff_solde + $suivi->prime_volume;
            if($suivi->turn_over > 0) {
                $diffTurnOver = round($min_turn_over - $suivi->turn_over, 2);
                $suivi->renta_amount += $diffTurnOver;
                $suivi->calcul_detail['turn_over'] = 'Turn-over = ('.$min_turn_over.' - '.$suivi->turn_over.') = <strong>'.price($diffTurnOver).'</strong>';
            }
            $suivi->renta_percent = round(($suivi->renta_amount / $this->montant) * 100, 2);
            if(! empty($suivi->leaser->array_options['options_bonus_renta'])) {
                $suivi->renta_percent += $suivi->leaser->array_options['options_bonus_renta'];
                $suivi->calcul_detail['renta'] = 'Bonus renta = <strong>'.price($suivi->leaser->array_options['options_bonus_renta']).'</strong>';
            }
        }

        uasort($this->TSimulationSuivi, array($this, 'aiguillageSuivi'));

        $catLeaserDossierSolde = $this->getIdLeaserDossierSolde($PDOdb, true);

        // Update du rang pour priorisation
        $TSuivi = array_values($this->TSimulationSuivi);
        foreach($TSuivi as $k => &$suivi) {
            if($suivi->date_historization > 0) continue;    // On ne s'occupe pas des suivis historisés

            $suivi->rang = $k;

            // Priorité au leaser concerné par l'adjonction
            if($k > 0 && $suivi->fk_leaser == $this->fk_leaser_adjonction) {
                $suivi->rang = -1;
                for($i = 0 ; $i <= $k ; $i++) $TSuivi[$i]->rang += 1;   // Pour éviter les -1 et les trous dans les rangs
            }

            // Priorité au leaser concerné par le solde si diff_solde >= 150€ (paramétrable via la conf FINANCEMENT_PRIO_LEASER_MIN_DIFF_SOLDE)
            if($k > 0 && $suivi->leaser->array_options['options_prio_solde'] == 1 && ! empty($catLeaserDossierSolde) && abs($suivi->diff_solde) >= $conf->global->FINANCEMENT_PRIO_LEASER_MIN_DIFF_SOLDE) {
                $catLeaserSuivi = $this->getTCatLeaserFromLeaserId($suivi->fk_leaser);

                $intersect = array_intersect(array_keys($catLeaserDossierSolde), array_keys($catLeaserSuivi));
                if(! empty($intersect)) {
                    $suivi->rang = -1;
                    for($i = 0 ; $i <= $k ; $i++) $TSuivi[$i]->rang += 1;   // Pour éviter les -1 et les trous dans les rangs
                }
            }
        }

        foreach($this->TSimulationSuivi as &$suivi) $suivi->save($PDOdb);   // Pour des soucis de lisibilité

        // On remet la conf d'origine
        switchEntity($oldconf->entity);
    }

    function calculMontantFinanceLeaser(&$PDOdb, &$suivi) {
        $suivi->montantfinanceleaser = 0;

        $leaser = $suivi->loadLeaser();
        $coef_line = $suivi->getCoefLineLeaser($PDOdb, $this->montant, $this->fk_type_contrat, $this->duree, $this->opt_periodicite);

        if($coef_line == -1) $suivi->calcul_detail['montantfinanceleaser'] = 'Aucun coefficient trouvé pour le leaser "'.$leaser->nom.'" ('.$leaser->id.') avec une durée de '.$this->duree.' trimestres';
        else if($coef_line == -2) $suivi->calcul_detail['montantfinanceleaser'] = 'Montant financement ('.$this->montant.') hors tranches pour le leaser "'.$leaser->nom.'" ('.$leaser->id.')';
        else {
            if(! empty($coef_line['coeff'])) {
                $suivi->montantfinanceleaser = round($this->echeance / ($coef_line['coeff'] / 100), 2);
            }
            $suivi->calcul_detail['montantfinanceleaser'] = 'Montant financé leaser = '.$this->echeance.' / '.($coef_line['coeff'] / 100);
            $suivi->calcul_detail['montantfinanceleaser'] .= ' = <strong>'.price($suivi->montantfinanceleaser).'</strong><hr>';
        }

        return $suivi->montantfinanceleaser;
    }

    /**
     * a. on part de l’échéance client, on retrouve le coeff leaser, on calcule le montant finançable leaser
     * b. Surfact = Montant finançable leaser - montant financé client
     *
     * @param TSimulationSuivi $suivi
     */
    private function calcSurfact(&$PDOdb, &$suivi) {
        // Si déjà calculé alors je renvoi la valeur immédiatemment
        if(! empty($suivi->surfact)) return $suivi->surfact;

        $suivi->surfact = 0;

        if(empty($suivi->montantfinanceleaser)) $suivi->calcul_detail['surfact'] = 'Surfact = non calculable car pas de montant financé leaser';
        else {
            $suivi->surfact = $suivi->montantfinanceleaser - $this->montant;
            $suivi->calcul_detail['surfact'] = 'Surfact = '.$suivi->montantfinanceleaser.' - '.$this->montant;
            $suivi->calcul_detail['surfact'] .= ' = <strong>'.price($suivi->surfact).'</strong>';
        }

        return $suivi->surfact;
    }

    /**
     * a. % de surfact + à définir par Leaser (1% BNP pour commencer)
     * b. Surfact+ = Montant finançable leaser * % surfact+
     *
     * @param TSimulationSuivi $suivi
     */
    private function calcSurfactPlus(&$PDOdb, &$suivi) {
        // Si déjà calculé alors je renvoi la valeur immédiatemment
        if(! empty($suivi->surfactplus)) return $suivi->surfactplus;

        $suivi->surfactplus = 0;

        if(empty($suivi->montantfinanceleaser)) $suivi->calcul_detail['surfactplus'] = 'Surfact+ = non calculable car pas de montant financé leaser';
        else {
            if(! function_exists('price2num')) require DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

            $percent_surfactplus = round(price2num($suivi->leaser->array_options['options_percent_surfactplus']), 2); // 1%
            $suivi->surfactplus = round($suivi->montantfinanceleaser * ($percent_surfactplus / 100), 2);
            $suivi->calcul_detail['surfactplus'] = 'Surfact+ = '.$suivi->montantfinanceleaser.' * ('.$percent_surfactplus.' / 100)';
            $suivi->calcul_detail['surfactplus'] .= ' = <strong>'.price($suivi->surfactplus).'</strong>';
        }

        return $suivi->surfactplus;
    }

    /**
     * a. % de comm à définir par Leaser
     * b. Comm = Montant finançable leaser * % comm
     *
     * @param TSimulationSuivi $suivi
     */
    private function calcComm(&$PDOdb, &$suivi) {
        // Si déjà calculé alors je renvoi la valeur immédiatemment
        if(! empty($suivi->commission)) return $suivi->commission;

        $suivi->commission = 0;

        $leaser = $suivi->loadLeaser();

        $coef_line = $suivi->getCoefLineLeaser($PDOdb, $this->montant, $this->fk_type_contrat, $this->duree, $this->opt_periodicite);

        if(empty($suivi->montantfinanceleaser)) $suivi->calcul_detail['commission'] = 'Commission = non calculable car pas de montant financé leaser';
        else {
            if(! function_exists('price2num')) require DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

            $percent_commission = round(price2num($leaser->array_options['options_percent_commission']), 2);
            $suivi->commission = round(($suivi->montantfinanceleaser + $suivi->surfactplus) * ($percent_commission / 100), 2);
            $suivi->calcul_detail['commission'] = 'Commission = ('.$suivi->montantfinanceleaser.' + '.$suivi->surfactplus.') * ('.$percent_commission.' / 100)';
            $suivi->calcul_detail['commission'] .= ' = <strong>'.price($suivi->commission).'</strong>';
        }

        return $suivi->commission;
    }

    /**
     * a. % d’intercalaire à définir par Leaser
     * b. % moyen intercalaire C’Pro à définir pour C’Pro (par entité)
     * c. Intercalaire = Loyer * % moyen intercalaire * % intercalaire Leaser sauf si calage sur simulation
     *
     * @param TSimulationSuivi $suivi
     */
    private function calcIntercalaire(&$PDOdb, &$suivi) {
        global $conf;

        // Si déjà calculé alors je renvoi la valeur immédiatemment
        if(! empty($suivi->intercalaire)) return $suivi->intercalaire;

        $suivi->intercalaire = 0;
        $entity = $this->getDaoEntity($conf->entity);

        $suivi->calcul_detail['intercalaire'] = 'Intercalaire';

        if(empty($this->opt_calage)) {
            // Intercalaire C'Pro
            $percent_cpro = round(price2num($entity->array_options['options_percent_moyenne_intercalaire']), 2);
            $suivi->intercalaire = $this->echeance * ($percent_cpro / 100);
            $suivi->calcul_detail['intercalaire'] .= ' = '.$this->echeance.' * ('.$percent_cpro.' / 100)';
            // Intercalaire Leaser
            $percent_leaser = round(price2num($suivi->leaser->array_options['options_percent_intercalaire']), 2);
            $suivi->intercalaire *= ($percent_leaser / 100);
            $suivi->calcul_detail['intercalaire'] .= ' * ('.$percent_leaser.' / 100)';

            $suivi->intercalaire = round($suivi->intercalaire, 2);
        }

        $suivi->calcul_detail['intercalaire'] .= ' = <strong>'.price($suivi->intercalaire).'</strong>';

        return $suivi->intercalaire;
    }

    /**
     * a. Pour chaque dossier racheté, calcul de la différence de solde R et NR par Leaser, applicable aux autres
     * b. Différence solde = Somme différence dossiers rachetés des autres leasers
     *
     * @param TPDOdb           $PDOdb
     * @param TSimulationSuivi $suivi
     * @return float
     */
    private function calcDiffSolde(&$PDOdb, &$suivi) {
        // Si déjà calculé alors je renvoi la valeur immédiatemment
        if(! empty($suivi->diff_solde)) return $suivi->diff_solde;

        $suivi->diff_solde = 0;
        $suivi->calcul_detail['diff_solde'] = 'Diff solde = ';
        $detail_delta = array();

        $leaser = $suivi->loadLeaser();
        $TCatLeaser = self::getTCatLeaserFromLeaserId($leaser->id);

        $TDeltaByDossier = $this->getTDeltaByDossier($PDOdb);
        foreach($TDeltaByDossier as $fk_dossier => $delta) {
            $TCatLeaser_tmp = self::getTCatLeaserFromLeaserId($this->dossiers[$fk_dossier]['object_leaser']->id);
            $intersect = array_intersect(array_keys($TCatLeaser), array_keys($TCatLeaser_tmp));

            // Si pas d'intersect (pas de catégorie leaser commune), alors j'ajoute le delta
            if(empty($intersect)) {
                $suivi->diff_solde += $delta;
                $detail_delta[] = $delta;
            }
        }

        if(! empty($detail_delta)) {
            if(count($detail_delta) > 1) $suivi->calcul_detail['diff_solde'] .= '('.implode(' + ', $detail_delta).') = <strong>'.price($suivi->diff_solde).'</strong>';
            else $suivi->calcul_detail['diff_solde'] .= '<strong>'.price($suivi->diff_solde).'</strong>';
        }
        else $suivi->calcul_detail['diff_solde'] .= '<strong>'.price(0).'</strong>';

        return $suivi->diff_solde;
    }

    /**
     * a. % de pv à définir par Leaser
     * b. PV = (Surfact + Surfact+) * % pv
     *
     * @param TSimulationSuivi $suivi
     */
    private function calcPrimeVolume(&$PDOdb, &$suivi) {
        // Si déjà calculé alors je renvoi la valeur immédiatemment
        if(! empty($suivi->prime_volume)) return $suivi->prime_volume;

        $percent_pv = round(price2num($suivi->leaser->array_options['options_percent_prime_volume']), 2);
        $suivi->prime_volume = round(($suivi->montantfinanceleaser + $suivi->surfactplus) * ($percent_pv / 100), 2);
        $suivi->calcul_detail['prime_volume'] = 'PV = ('.$suivi->montantfinanceleaser.' + '.$suivi->surfactplus.') * ('.$percent_pv.' / 100)';
        $suivi->calcul_detail['prime_volume'] .= ' = <strong>'.price($suivi->prime_volume).'</strong>';

        return $suivi->prime_volume;
    }

    /**
     * a. % de durée de vie moyenne à définir par entité
     * b. Calcul de la durée théorique du dossier, arrondi supérieur
     * c. Simulation d’un dossier avec les paramètres de la simulation
     * d. Turn over = Solde du dossier simulé à durée théorique du dossier sauf si case administration cochée
     *
     * @param TSimulationSuivi $suivi
     */
    private function calcTurnOver(&$PDOdb, &$suivi) {
        global $conf;

        // Si déjà calculé alors je renvois la valeur immédiatemment
        if(! empty($suivi->turn_over) || ! empty($this->opt_administration)) return $suivi->turn_over;

        $suivi->turn_over = 0;
        $entity = $this->getDaoEntity($conf->entity);

        $duree_theorique = ceil($this->duree * ($entity->array_options['options_percent_duree_vie'] / 100));

        $Tab = (array) $this;
        // Simulation de l'écheancier
        $dossier_simule = new TFin_dossier();
        $dossier_simule->set_values($Tab);
        $dossier_simule->contrat = $this->fk_type_contrat;
        $dossier_simule->nature_financement = 'INTERNE';
        $dossier_simule->financementLeaser->set_values($Tab);
        // Il y a des différence entre les variables d'une simulation et celles d'un financement... le set_values ne suffit pas
        $dossier_simule->financementLeaser->periodicite = $this->opt_periodicite;
        $dossier_simule->financementLeaser->montant = $this->montant;
        $dossier_simule->financementLeaser->echeance = $this->echeance;
        $dossier_simule->financementLeaser->terme = $this->opt_terme;
        $dossier_simule->financementLeaser->duree = $this->duree;
        $dossier_simule->financementLeaser->reste = $this->vr;
        $dossier_simule->financementLeaser->reglement = $this->opt_mode_reglement;
        $dossier_simule->financementLeaser->fk_soc = $suivi->leaser->id;
        $dossier_simule->financementLeaser->montant = $suivi->montantfinanceleaser + $suivi->surfactplus;
        $dossier_simule->date_debut = date('d/m/Y');
        $dossier_simule->financementLeaser->calculTaux();
        $dossier_simule->calculSolde();
        $dossier_simule->calculRenta($PDOdb);

        $suivi->turn_over = round($dossier_simule->getSolde($PDOdb, 'SRBANK', $duree_theorique), 2);
        if(! empty($suivi->turn_over)) {
            $suivi->calcul_detail['solde_turn_over'] = 'Solde à échéance '.$duree_theorique.' = '.$suivi->turn_over;
        }
        else {
            $suivi->calcul_detail['solde_turn_over'] = 'Solde à échéance '.$duree_theorique.' impossible';
        }

        return $suivi->turn_over;
    }

    private function getDaoEntity($fk_entity) {
        global $db, $TDaoEntity;

        if(! empty($TDaoEntity[$fk_entity])) $entity = $TDaoEntity[$fk_entity];
        else {
            dol_include_once('/multicompany/class/dao_multicompany.class.php');
            $entity = new DaoMulticompany($db);
            $entity->fetch($fk_entity);
            $TDaoEntity[$fk_entity] = $entity;
        }

        return $entity;
    }

    private function getTDeltaByDossier(&$PDOdb, $force = false) {
        global $TDeltaByDossier;

        if(empty($TDeltaByDossier) || $force) {
            $TDeltaByDossier = array();

            $TTabToCheck = array('dossiers_rachetes_m1' => 'dossiers_rachetes_nr_m1', 'dossiers_rachetes' => 'dossiers_rachetes_nr', 'dossiers_rachetes_p1' => 'dossiers_rachetes_nr_p1');
            foreach($TTabToCheck as $attr_R => $attr_NR) {
                // On check la période -1, puis la période courrante et enfin la période +1
                foreach($this->{$attr_R} as $fk_dossier => $Tab) {
                    // Si quelque chose a été check dans un tableau R ou NR, alors je calcul le delta et je passe au dossier suivant (break)
                    if(! empty($Tab['checked']) || ! empty($this->{$attr_NR}[$fk_dossier]['checked'])) {
                        $d = new TFin_dossier();
                        $d->load($PDOdb, $fk_dossier);

                        $periode = $d->financementLeaser->numero_prochaine_echeance - 1;
                        if(strpos($attr_NR, 'm1') !== false) $periode--;
                        if(strpos($attr_NR, 'p1') !== false) $periode++;

                        $soldeR = $d->getSolde($PDOdb, 'SRBANK', $periode);
                        $soldeNR = $d->getSolde($PDOdb, 'SNRBANK', $periode);

                        $TDeltaByDossier[$fk_dossier] = round($soldeR - $soldeNR, 2);
                    }
                }
            }
        }

        return $TDeltaByDossier;
    }

    static function getTCatLeaserFromLeaserId($fk_leaser, $force = false) {
        global $db, $TCategoryByLeaser;

        if(empty($fk_leaser)) return array();

        if(empty($TCategoryByLeaser[$fk_leaser]) || $force) {
            $TCategoryByLeaser[$fk_leaser] = array();

            $c = new Categorie($db);
            $c->fetch(null, 'Leaser');

            $Tab = $c->containing($fk_leaser, 1);
            foreach($Tab as &$cat) {
                if($cat->fk_parent == $c->id) $TCategoryByLeaser[$fk_leaser][$cat->id] = $cat;
            }
        }

        return $TCategoryByLeaser[$fk_leaser];
    }

    function clone_simu() {
        global $langs;

        $this->start();
        $this->TSimulationSuivi = array();
        $this->DossierRachete = array();
        $this->TSimulationSuiviHistorized = array();
        $this->accord = 'DRAFT';
        $this->date_simul = time();

        // On vide les préconisations
        $this->fk_leaser = 0;
        $this->type_financement = '';
        $this->coeff_final = 0;
        $this->numero_accord = '';

        // On vide le stockage des anciennes valeurs
        $this->modifs = array();

        // Pas d'appel auto aux EDI sur un clone
        $this->no_auto_edi = true;
        $this->fk_action_manuelle = 0;

        // On vide les sélection de solde de dossier pour forcer à revalider les dossiers à solder
        if($this->has_solde_dossier_selected()) setEventMessages($langs->trans('SimuCheckSoldesAfterClone'), '', 'warnings');
        $this->dossiers = array();
        $this->dossiers_rachetes_m1 = array();
        $this->dossiers_rachetes_nr_m1 = array();
        $this->dossiers_rachetes = array();
        $this->dossiers_rachetes_p1 = array();
        $this->dossiers_rachetes_nr = array();
        $this->dossiers_rachetes_nr_p1 = array();
        $this->dossiers_rachetes_perso = array();
        $this->montant_rachete = 0;
    }

    function hasOtherSimulationRefused(&$PDOdb) {
        $sql = "SELECT rowid ";
        $sql .= "FROM ".MAIN_DB_PREFIX."fin_simulation s ";
        $sql .= "WHERE s.fk_soc = ".$this->fk_soc." ";
        $sql .= "AND s.rowid != ".$this->getId()." ";
        $sql .= "AND s.accord = 'KO' ";
        $sql .= "AND s.date_simul > '".date('Y-m-d', strtotime('-6 month'))."' ";

        $TRes = $PDOdb->ExecuteAsArray($sql);

        if(count($TRes) > 0) return true;

        return false;
    }

    function hasOtherSimulation(&$PDOdb, $nbDays = 30) {
        $sql = "SELECT rowid ";
        $sql .= "FROM ".MAIN_DB_PREFIX."fin_simulation s ";
        $sql .= "WHERE s.fk_soc = ".$this->fk_soc." ";
        $sql .= "AND s.rowid != ".$this->getId()." ";
        $sql .= "AND s.date_simul > '".date('Y-m-d', strtotime('-'.$nbDays.' days'))."' ";

        $TRes = $PDOdb->ExecuteAsArray($sql);

        if(count($TRes) > 0) return true;

        return false;
    }

    function set_values_from_cristal($post) {
        $TValuesToModify = array(
            'montant',
            'duree',
            'echeance',
            'opt_periodicite',
            'fk_type_contrat',
            'type_materiel',
            'fk_simu_cristal',
            'fk_projet_cristal'
        );

        foreach($TValuesToModify as $code) {
            if(! empty($post[$code])) $this->$code = $post[$code];
        }
    }

    static function getEntityFromCristalCode($entity_code_cristal) {
        $TRes = array(
            'CPRO-EST' => array(1, 2, 3, 10),
            'CPRO-OUEST' => array(5, 7, 16, 9, 11),
            'CPRO-SUD' => array(12, 13, 14, 15),
            'COPEM' => array(6),
            'EBM' => array(8)
        );

        if(array_key_exists($entity_code_cristal, $TRes)) return $TRes[$entity_code_cristal];
        return array();
    }

    static function getTypeContratFromCristal($code) {
        $TRes = array(
            'loc fi' => 'LOCSIMPLE',
            'total pro' => 'FORFAITGLOBAL',
            'integral' => 'INTEGRAL'
        );

        return $TRes[$code];
    }

    static function getAllByCode(TPDOdb &$PDOdb, TSimulation $simu, $fk_soc, $get_count = false) {
        global $db;

        $TSimu = $simu->load_by_soc($PDOdb, $db, $fk_soc);

        if($get_count) return count($TSimu);

        return $TSimu;
    }

    function update_note($note, $suffix) {
        global $db;

        $db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_simulation';
        $sql .= ' SET note'.$suffix."='".$db->escape($note)."'";
        $sql .= ' WHERE rowid='.$this->getId();

        $resql = $db->query($sql);
        if($resql) $db->commit();
        else {
            $db->rollback();
            dol_print_error($db);

            return -1;
        }
    }

    /**
     * Retourne tous les suivi leaser sans avoir à les load
     * @param   int     $fk_simu
     * @return  array
     */
    public static function getSimulationSuivi($fk_simu) {
        global $db;

        $TRes = array();

        $sql = 'SELECT rowid, fk_leaser, statut';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation_suivi';
        $sql.= ' WHERE fk_simulation = '.$fk_simu;
        $sql.= ' ORDER BY rang ASC';

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        while($obj = $db->fetch_object($resql)) {
            $TRes[] = $obj;
        }

        return $TRes;
    }

    public static function getLeaserCategory() {
        global $db;

        $TRes = array();
        $sql = 'SELECT cf.fk_soc, cf.fk_categorie as fk_cat';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'categorie_fournisseur cf';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie c ON (c.rowid = cf.fk_categorie)';
        $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie c2 ON (c2.rowid = c.fk_parent)';
        $sql .= " WHERE c2.label = 'Leaser'";

        $resql = $db->query($sql);
        if($resql) {
            while($obj = $db->fetch_object($resql)) $TRes[$obj->fk_soc] = $obj->fk_cat;
        }

        return $TRes;
    }

    /**
     * @param   int     $fk_simulation      Simulation id to load TSimulationSuivi
     * @param   int     $fk_dossier         Dossier id
     * @param   int     $fk_leaser_simu     Simulation Thirdparty id
     * @return  string  Renvoie le type de solde, R ou NR
     */
    public static function getTypeSolde($fk_simulation, $fk_dossier, $fk_leaser_simu) {
        if(empty($fk_simulation) || empty($fk_dossier) || empty($fk_leaser_simu)) return '';

        $PDOdb = new TPDOdb;
        $doss = new TFin_dossier;
        $doss->load($PDOdb, $fk_dossier, false);

        // Il faut récupérer les catégories de leaser pour savoir si on prendre le 'R' ou le 'NR'
        $TLeaserCat = TSimulation::getLeaserCategory();
        $TSimulationSuivi = TSimulation::getSimulationSuivi($fk_simulation);

        // On détermine le type de solde
        $refus = false;
        foreach($TSimulationSuivi as $suivi) {
            if($TLeaserCat[$doss->financementLeaser->fk_soc] == $TLeaserCat[$suivi->fk_leaser] && $suivi->statut == 'KO') {
                $refus = true;
                break;
            }
        }

        if($refus || $TLeaserCat[$fk_leaser_simu] == $TLeaserCat[$doss->financementLeaser->fk_soc]) {
            $solde = 'R';
        }
        else {
            $solde = 'NR';
        }

        return $solde;
    }

    /**
     * @param int   $source
     * @param int   $target
     * @param array $TEntity
     * @return bool
     */
    public static function replaceThirdparty($source, $target, $TEntity = array()) {
        if(empty($source) || empty($target)) return false;

        global $db, $conf;
        if(empty($TEntity)) $TEntity[] = $conf->entity;

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'fin_simulation';
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

    public static function load_board() {
        global $db, $conf, $langs;

        $nbWait = $nbDelayed = 0;

        $sql = "SELECT rowid, date_format(date_cre, '%Y-%m-%d') as date_cre";
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation';
        $sql.= " WHERE accord LIKE 'WAIT%'";
        $sql.= ' AND entity IN ('.getEntity('fin_simulation').')';

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            return -1;
        }

        while($obj = $db->fetch_object($resql)) {
            $nbWait++;

            $dateCre = strtotime($obj->date_cre);
            if(time() >= ($dateCre + $conf->global->FINANCEMENT_DELAY_DRAFT_SIMULATION * 86400)) $nbDelayed++;
        }
        $db->free($resql);

        $r = new WorkboardResponse;
        $r->warning_delay = $conf->global->FINANCEMENT_DELAY_DRAFT_SIMULATION;
        $r->label = $langs->trans('Delays_FINANCEMENT_DELAY_DRAFT_SIMULATION');
        $r->url = dol_buildpath('/financement/simulation/list.php', 1).'?search_statut=WAIT';
        $r->img = img_picto('', 'object_simul@financement');

        $r->nbtodo = $nbWait;
        $r->nbtodolate = $nbDelayed;

        return $r;
    }

    public function encodeTextFields(): void {
        $TFieldToEncode = [
            'type_materiel',
            'commentaire'
        ];

        foreach($TFieldToEncode as $field) $this->$field = htmlspecialchars($this->$field);
    }

    public static function getStaticRef($id) {
        return 'S'.str_pad($id, 6, '0', STR_PAD_LEFT);
    }
}

class TSimulationSuivi extends TObjetStd
{
    public static $TLeaserEDI = [
        'BNP',
        'LIXXBAIL',
        'CMCIC',
        'GRENKE',
        'FRANFINANCE'
    ];

    function __construct() {
        global $langs;

        parent::set_table(MAIN_DB_PREFIX.'fin_simulation_suivi');
        parent::add_champs('entity,fk_simulation,fk_leaser,fk_user_author,statut_demande', 'type=entier;');
        parent::add_champs('coeff_leaser', 'type=float;');
        parent::add_champs('date_demande,date_accord,date_selection,date_historization', 'type=date;');
        parent::add_champs('numero_accord_leaser,statut', 'type=chaine;');
        parent::add_champs('commentaire,commentaire_interne', 'type=text;');
        parent::add_champs('rang', array('type' => 'integer'));

        parent::add_champs('surfact,surfactplus,commission,intercalaire,diff_solde,prime_volume,turn_over,renta_amount,renta_percent', array('type' => 'float'));
        parent::add_champs('calcul_detail', array('type' => 'array'));

        // CM-CIC
        parent::add_champs('b2b_nodef,b2b_noweb', 'type=chaine;');
        // GRENKE
        parent::add_champs('leaseRequestID', 'type=chaine;');

        parent::start();
        parent::_init_vars();

        $this->TStatut = array(
            'OK' => $langs->trans('Accord')
            , 'WAIT' => $langs->trans('Etude')
            , 'KO' => $langs->trans('Refus')
            , 'SS' => $langs->trans('SansSuite')
            , 'MEL' => $langs->trans('Mise En Loyé')
            , 'ERR' => $langs->trans('Error')
        );

        $this->simulation = new TSimulation;

        // Obligé d'init à null vu que la fonction parent::_init_vars() met des valeurs dedans
        $this->date_demande = null;
        $this->date_accord = null;
        $this->date_selection = null;
        $this->date_historization = null;
    }

    /**
     * Permet de récupérer le tableau d'info du coefficient leaser qui correspond au montant et à la durée
     * retourne -1 si aucun coefficient de paramétré sur la durée
     * retourne -2 si la durée est trouvée mais qu'aucune tranche de paramétrée
     * autrement renvoi le talbeau d'info
     *
     * @param TPDOdb $PDOdb
     * @param float  $amount
     * @param string $fk_type_contrat
     * @param int    $duree
     * @return array|int if not found
     */
    public function getCoefLineLeaser($PDOdb, $amount, $fk_type_contrat, $duree, $periodicite) {
        if(! empty($this->TCoefLine[$amount])) return $this->TCoefLine[$amount];

        $grille = new TFin_grille_leaser;
        $grille->get_grille($PDOdb, $this->fk_leaser, $fk_type_contrat, 'TRIMESTRE', array(), 17);

        $fin_temp = new TFin_financement;
        $fin_temp->periodicite = $periodicite;
        $p1 = $fin_temp->getiPeriode();
        $duree *= $p1 / 3;

        if(! empty($grille->TGrille[$duree])) {
            foreach(array_keys($grille->TGrille[$duree]) as $amount_as_key) {
                if($amount_as_key > $amount) {
                    $this->TCoefLine[$amount] = $grille->TGrille[$duree][$amount_as_key];
                    $this->TCoefLine[$amount]['coeff'] *= $p1 / 3;
                    return $this->TCoefLine[$amount];
                }
            }

            return -2;
        }

        return -1;
    }

    //Chargement du suivi simulation
    function load(&$PDOdb, $id, $loadChild = true) {
        global $db;

        $res = parent::load($PDOdb, $id, $loadChild);
        $this->leaser = new Societe($db);
        $this->leaser->fetch($this->fk_leaser);

        if(! empty($this->fk_simulation)) {
            $simulation = new TSimulation;
            $simulation->load($PDOdb, $this->fk_simulation, false);
            $this->simulation = $simulation;
        }

        $this->user = new User($db);
        $this->user->fetch($this->fk_user_author);

        if(empty($this->calcul_detail)) $this->calcul_detail = array();

        return $res;
    }

    function loadLeaser() {
        global $db;

        if(empty($this->leaser->id)) {
            $this->leaser = new Societe($db);
            $this->leaser->fetch($this->fk_leaser);
        }

        return $this->leaser;
    }

    //Initialisation de l'objet avec les infos de base
    function init(&$PDOdb, &$leaser, $fk_simulation) {
        global $conf, $user;

        $this->entity = $conf->entity;
        $this->fk_simulation = $fk_simulation;
        $this->fk_leaser = $leaser->id;
        $this->fk_user_author = $user->id;
    }

    //Retourne les actions possible pour ce suivi suivant les règles de gestion
    function getAction(TSimulation &$simulation, $just_save = false) {
        global $conf, $langs;
        $PDOdb = new TPDOdb;

        $actions = '';
        $ancre = '#suivi_leaser';

        $iHaveToConfirm = false;
        if(empty($simulation->TSimulationSuivi)) $simulation->load_suivi_simulation($PDOdb);
        foreach($simulation->TSimulationSuivi as $suivi) {
            if($suivi->statut == 'WAIT') {
                $firstSuivi = $suivi;
                break;
            }

            if($suivi->rowid == $this->rowid) break;    // Aucun leaser "En étude" au dessus de moi
        }

        // S'il y a un leaser "En étude" au dessus de moi
        if(isset($firstSuivi) && $firstSuivi->rowid != $this->rowid) $iHaveToConfirm = true;

        // TODO ajouter le bouton permettant de refaire un appel webservice, rien d'autre à faire pour un update (en fait si, il faut aussi utiliser le code refactoré de l'appel webservice)
        // le fait que les attributs "b2b_nodef" & "b2b_noweb" soit renseigné sur l'objet permettra de faire appel à la bonne méthode

        if($simulation->accord != "OK") {
            //Demander
            if($this->statut_demande != 1) {
                if(! $just_save && ! empty($simulation->societe->idprof2)) {
                    $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander'.$ancre.'" title="'.$langs->trans('ActionSuiviScoringLeaser').'">'.get_picto('phone').'</a>&nbsp;';
                }
            }
            else {
                //Sélectionner
                if($this->statut === 'OK') {
                    if($just_save) {
                        //Enregistrer
                        $actions .= get_picto('save').'&nbsp;';
                    }
                    else {
                        //Reset
                        $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander'.$ancre.'" title="'.$langs->trans('Cancel').'">'.get_picto('wait').'</a>&nbsp;';

                        $url = '?id='.$simulation->getId().'&id_suivi='.$this->getId();
                        if($iHaveToConfirm) $url .= '&action=confirm_selectionner';
                        else $url .= '&action=selectionner'.$ancre;
                        $actions .= '<a href="'.$url.'" title="'.$langs->trans('SelectThisLeaser').'">'.get_picto('super_ok').'</a>&nbsp;';
                    }
                }
                else if($this->statut === 'ERR') {
					if($just_save) {
						//Enregistrer
						$actions .= get_picto('save').'&nbsp;';
					}
					else {
						// Try again after error
						$actions .= '<a href="?id=' . $simulation->getId() . '&id_suivi=' . $this->getId() . '&action=demander' . $ancre . '" title="'.$langs->trans('ActionSuiviScoringLeaser').'">' . get_picto('phone') . '</a>&nbsp;';
					}
				}
                else {
                    if($this->statut !== 'KO') {
                        if($just_save) {
                            //Enregistrer
                            $actions .= get_picto('save').'&nbsp;';
                        }
                        else {
                            //Accepter
                            $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=accepter'.$ancre.'" title="'.$langs->trans('ActionSuiviScoringOK').'">'.get_picto('ok').'</a>&nbsp;';
                            //Refuser
                            $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=refuser'.$ancre.'" title="'.$langs->trans('ActionSuiviScoringKO').'">'.get_picto('refus').'</a>&nbsp;';
                        }
                    }
                    else if($simulation->accord != "KO") {
                        if(! $just_save) {
                            //Reset
                            $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander'.$ancre.'" title="'.$langs->trans('Cancel').'">'.get_picto('wait').'</a>&nbsp;';
                        }
                    }
                }
            }
        }
        else if($simulation->accord == "OK" && ! empty($this->date_selection)) {
            if(! $just_save) {
                //Reset
                $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=accepter'.$ancre.'" title="'.$langs->trans('Cancel').'">'.get_picto('ok').'</a>&nbsp;';
            }
        }

        if(! $just_save && ! empty($conf->global->FINANCEMENT_SHOW_RECETTE_BUTTON) && ! empty($this->leaser->array_options['options_edi_leaser'])) {
            $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=trywebservice'.$ancre.'" title="'.$langs->trans('Cancel').'">'.get_picto('webservice').'</a>&nbsp;';
        }

        return $actions;
    }

    //Exécute une action et met en oeuvre les règles de gestion en conséquence
    function doAction(&$PDOdb, TSimulation &$simulation, $action, $debug = false) {
        global $db;

        switch($action) {
            case 'demander':
                $this->doActionDemander($PDOdb, $simulation, $debug);
                break;
            case 'accepter':
                $this->doActionAccepter($PDOdb, $simulation);
                break;
            case 'refuser':
                $this->doActionRefuser($PDOdb, $simulation);
                break;
            case 'selectionner':
                $this->doActionSelectionner($PDOdb, $simulation);
                break;
            case 'erreur':
                $this->doActionErreur($PDOdb, $simulation);
                break;
            default:

                break;
        }

        if($simulation->fk_action_manuelle > 0) {
            $simulation->fk_action_manuelle = 0;
            $simulation->save($PDOdb);
        }
    }

    //Effectuer l'action de faire la demande de financement au leaser
    function doActionDemander(&$PDOdb, &$simulation, $debug = false) {
        global $db, $conf;

        // Leaser ACECOM = demande BNP mandaté et BNP cession + Lixxbail mandaté et Lixxbail cession
        if($this->fk_leaser == 18305) {
            // 20113 = BNP Mandatée // 3382 = BNP Cession (Location simple) // 19483 = Lixxbail Mandatée // 6065 = Lixxbail Cession (Location simple)
            $sql = "SELECT rowid
					FROM ".MAIN_DB_PREFIX."fin_simulation_suivi
					WHERE (fk_leaser = 3382
						OR fk_leaser = 6065)
						AND fk_simulation = ".$this->fk_simulation;
            $TIds = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

            foreach($TIds as $idSimulationSuivi) {
                $simulation_suivi = new TSimulationSuivi;
                $simulation_suivi->load($PDOdb, $idSimulationSuivi);
                $simulation_suivi->doAction($PDOdb, $simulation, 'demander');
                $simulation_suivi->save($PDOdb);
            }
        }

        //Si leaser auto alors on envoye la demande par EDI
        if(! empty($this->leaser->array_options['options_edi_leaser'])
            && empty($conf->global->FINANCEMENT_SHOW_RECETTE_BUTTON)
            && (empty($this->statut))) { // On n'envoie le scoring par EDI que la 1ère fois
            $this->_sendDemandeAuto($PDOdb, $debug);
        }
        else {
            $this->statut_demande = 1;
            $this->date_demande = time();
            $this->statut = 'WAIT';
            $this->date_selection = 0;
            $this->save($PDOdb);
        }
    }

    //Effectue l'action de passer au statut accepter la demande de financement leaser
    function doActionAccepter(&$PDOdb, TSimulation &$simulation) {
        global $db;

        $this->statut = 'OK';
        $this->save($PDOdb);

        // Cas "Annuler"
        if(! empty($this->date_selection)) {
            $this->date_selection = 0;

            $simulation->accord = 'WAIT';
            $simulation->date_accord = null;
            $simulation->numero_accord = null;
            $simulation->fk_leaser = null;
            $simulation->type_financement = null;
            $simulation->save($PDOdb, $db);
        }
    }

    //Effectue l'action de passer au statut refusé la demande de financement leaser
    function doActionRefuser(&$PDOdb, &$simulation) {   // FIXME: Remove this useless parameter !!
        $this->statut = 'KO';
        $this->save($PDOdb);
    }

    //Effectue l'action de passer au statut erreur la demande de financement leaser
    function doActionErreur(&$PDOdb) {
        $this->statut = 'ERR';
        $this->save($PDOdb);
    }

    //Effectue l'action de choisir définitivement un leaser pour financer la simulation
    function doActionSelectionner(&$PDOdb, TSimulation &$simulation) {
        global $db, $user;

        $TTypeFinancement = array(3 => 'ADOSSEE', 4 => 'MANDATEE', 18 => 'PURE', 19 => 'FINANCIERE'); // En cléf : id categorie, en valeur, type financement associé
        $TCateg_tiers = array();

        if(! empty($this->fk_leaser)) {
            // Récupération des catégories du leaser. fk_categorie : 5 pour "Cession", 3 pour "Adossee", 18 pour Loc Pure, 4 pour Mandatee, 19 pour Financière
            $sql = 'SELECT fk_categorie FROM '.MAIN_DB_PREFIX.'categorie_fournisseur WHERE fk_categorie IN (3, 4, 5, 18, 19) and fk_soc = '.$this->fk_leaser;
            $resql = $db->query($sql);
            while($res = $db->fetch_object($resql)) {
                $TCateg_tiers[] = (int) $res->fk_categorie;
            }
        }

        if($simulation->type_financement != "ADOSSEE" && $simulation->type_financement != "MANDATEE" && in_array(5, $TCateg_tiers)) {
            // 2017.06.02 MKO : le coeff transmis par le leaser n'est plus utilisé comme coeff final, on prend le coeff simulation si final non renseigné

            if(empty($simulation->coeff_final)) $simulation->coeff_final = $simulation->coeff;
            $simulation->montant = 0;
            $options = array(
                'opt_periodicite' => $simulation->opt_periodicite
                , 'opt_mode_reglement' => $simulation->opt_mode_reglement
                , 'opt_terme' => $simulation->opt_terme
                , 'opt_calage' => $simulation->opt_calage
                , 'opt_no_case_to_settle' => $simulation->opt_no_case_to_settle
            );

            $simulation->_calcul($PDOdb, 'calcul', $options);
        }
        // Une fois le test précédent effectué, on ne garde dans le tableau que les id des groupes qui nous intéressent (3 4 ou 18).
        if(! empty($TCateg_tiers)) {
            $TTemp = $TCateg_tiers;
            $TCateg_tiers = array();
            foreach($TTemp as $id_categ) {
                if($id_categ == 3 || $id_categ == 4 || $id_categ == 18 || $id_categ == 19) $TCateg_tiers[] = $id_categ;
            }
        }

        $simulation->accord = 'OK';
        $simulation->date_accord = time();
        $simulation->numero_accord = $this->numero_accord_leaser;
        $simulation->fk_leaser = $this->fk_leaser;
        $simulation->montant_accord = $simulation->montant_total_finance;
        $simulation->fk_user_suivi = empty($user->id) ? 1035 : $user->id;   // $user->id ou 'admin_financement'
        if(! empty($TTypeFinancement[$TCateg_tiers[0]])) $simulation->type_financement = $TTypeFinancement[$TCateg_tiers[0]];

        if($simulation->fk_action_manuelle > 0) $simulation->fk_action_manuelle = 0;    // Si OK pour un leaser, plus aucune action manuelle n'est nécessaire

        $simulation->save($PDOdb);
        $simulation->generatePDF($PDOdb);

        $simulation->send_mail_vendeur();

        if(in_array($simulation->entity, array(18, 25, 28)) && empty($simulation->opt_no_case_to_settle)) {
            $simulation->send_mail_vendeur_esus();
        }

        $this->date_selection = time();

        $simulation->historise_accord($PDOdb);

        $this->save($PDOdb);
    }

    function save(&$PDOdb) {
        $res = parent::save($PDOdb);

        if(! empty($this->fk_simulation)) {
            $simulation = new TSimulation;
            $simulation->load($PDOdb, $this->fk_simulation, false);
            $this->simulation = $simulation;
        }
    }

    function _sendDemandeAuto(&$PDOdb, $debug = false) {
        global $db;

        $this->simulation->societe = new Societe($db);
        $this->simulation->societe->fetch($this->simulation->fk_soc);

        // Pas d'envoi de demande auto si le client n'a pas de SIRET
        if(empty($this->simulation->societe->idprof2)) return false;

        if(empty($this->leaser)) {
            $this->leaser = new Fournisseur($db);
            $this->leaser->fetch($this->fk_leaser);
        }

        $this->statut = 'WAIT';

        $res = null;
        switch($this->leaser->array_options['options_edi_leaser']) {
            //GE CAPITAL EQUIPEMENT FINANCE
            case 'GE':
                //$this->_createDemandeGE($PDOdb);
                break;
            // [PH] TODO ajouter ici les leaser devant faire un appel SOAP
            //LIXXBAIL, CMCIC
            case 'LIXXBAIL':
            case 'CMCIC':
            case 'GRENKE':
            case 'BNP':
            case 'FRANFINANCE':
                $res = $this->_createDemandeServiceFinancement($debug);
                break;
            default:
                // techniquement il est impossible d'arriver dans ce cas
                return 1;
                break;
        }

        $this->statut_demande = 1;
        $this->date_demande = time();
        $this->date_selection = 0;
        $this->save($PDOdb);
    }

    function _createDemandeServiceFinancement($debug = false) {
        global $conf;

        dol_include_once('/financement/class/service_financement.class.php');
        $PDOdb = new TPDOdb;

        // Chargement d'un objet TSimulation dans une nouvelle variable pour éviter les problème d'adressage
        $simulation = new TSimulation();
        $simulation->load($PDOdb, $this->fk_simulation);
        $old_entity = $conf->entity;
        switchEntity($simulation->entity);

        $simulation->encodeTextFields();

        $TLeaserMandate = array(
            19483,  // Lixxbail
            20113,  // BNP
            23164,  // Grenke
            30748,  // Locam
            216625, // Franfi
            21382   // CM CIC
        );

        // Si on est sur de la location mandatée, il faut forcer ces paramètres pour l'envoi en EDI
        if(in_array($this->fk_leaser, $TLeaserMandate)) {
            // Il ne suffit pas de changer le champ opt_periodicite pour que ça marche...
            if($simulation->opt_periodicite !== 'TRIMESTRE') {
                // FIXME: Non mais franchement... Virez moi ce truc là !!
                $f = new TFin_financement;
                $f->periodicite = $simulation->opt_periodicite; /// Vivement la refonte... FIXME: Kevin, c'est quand que tu vas mettre du refactoring dans ma vie ?

                $duree = $simulation->duree * $f->getiPeriode();    // On ramène la durée au mois...
                $simulation->duree = $duree / 3;    // ...Pour au final avoir du trimestre
            }
            $simulation->opt_periodicite = 'TRIMESTRE';
            $simulation->terme = 1; // à échoir
            $simulation->opt_mode_reglement = 'PRE';
        }

        $service = new ServiceFinancement($simulation, $this, $debug);

        // La méthode se charge de tester si la conf du module autorise l'appel au webservice (renverra true sinon active)
        $res = $service->call();

        switchEntity($old_entity);

        if(! $res) {
            $this->statut = 'ERR';
            return -1;
        }

        return 1;
    }

    function _createDemandeGE(&$PDOdb) {
        if(GE_TEST) {
            $soapWSDL = dol_buildpath('/financement/files/dealws.wsdl', 2);
        }
        else {
            $soapWSDL = GE_WSDL_URL;
        }

        $soap = new SoapClient($soapWSDL, array('trace' => true));

        $username = 'DFERRAZZI';
        $password = 'Test321test';
        $header_part = '
			<wsse:Security SOAP-ENV:mustUnderstand="0" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
				<wsse:UsernameToken wsu:Id="UsernameToken-21" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
					<wsse:Username>DFERRAZZI</wsse:Username>
					<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">Test321test</wsse:Password>
				</wsse:UsernameToken>
			</wsse:Security>
		';
        $soap_var_header = new SoapVar($header_part, XSD_ANYXML, null, null, null);
        $soap_header = new SoapHeader($soapWSDL, 'wsse', $soap_var_header);
        $soap->__setSoapHeaders($soap_header);

        $TtransmettreDemandeFinancementRequest['CreateDemFinRequest'] = $this->_getGEDataTabForDemande($PDOdb);

        try {
            $reponseDemandeFinancement = $soap->__call('CreateDemFin', $TtransmettreDemandeFinancementRequest);
        }
        catch(SoapFault $reponseDemandeFinancement) {
            pre($reponseDemandeFinancement, true);
        }
        pre($reponseDemandeFinancement, true);
        exit;

        $this->traiteGEReponseDemandeFinancement($PDOdb, $reponseDemandeFinancement);
    }

    function traiteErrorsDemandeGE(&$reponseDemandeFinancement) {
        $this->errorLabel = "ERREUR SCORING GE : <br>";

        switch($reponseDemandeFinancement->ReponseDemFin->ResponseDemFinShort->Rep_Statut_B2B->B2B_CDRET) {
            case '1':
                $this->errorLabel .= 'Format fichier XML demande incorrect';
                break;
            case '2':
                $this->errorLabel .= 'Demande déjà existante';
                break;
            case '3':
                $this->errorLabel .= 'Format fichier XSD demande incorrect';
                break;
            case '4':
                $this->errorLabel .= 'Service non disponible';
                break;
            case '5':
                $this->errorLabel .= 'Le contrôle de connées a identifié une incohérance';
                break;
            default:
                $this->errorLabel .= 'Anomalie inconnue';
                break;
        }
    }

    function traiteGEReponseDemandeFinancement(&$PDOdb, &$reponseDemandeFinancement) {
        if($reponseDemandeFinancement->ReponseDemFin->ResponseDemFinShort->Rep_Statut_B2B->B2B_CDRET == '0') {
            $this->numero_accord_leaser = $reponseDemandeFinancement->numeroDemandeProvisoire;
            $this->save($PDOdb);
        }
        else {
            $this->traiteErrorsDemandeGE($reponseDemandeFinancement);
        }
    }

    function _getGEDataTabForDemande(&$PDOdb) {
        $TData = array();

        $TAPP_Infos_B2B = array(
            'B2B_CLIENT' => 'CPRO0001' //communication id by GE
            , 'B2B_TIMESTAMP' => date('c')
        );

        $TData['APP_Infos_B2B'] = $TAPP_Infos_B2B;

        $TAPP_CREA_Demande = array(
            'B2B_ECTR_FLG' => 0
            , 'B2B_NATURE_DEMANDE' => 'S' //TODO a vérifier => 'S pour standard, 'P' ou 'A'
            , 'B2B_TYPE_DEMANDE' => 'E' //TODO spcéfié inactif sur le doc, a voir ce qu'il faut en faire en définitif
            , 'B2B_REF_EXT' => $this->simulation->reference
        );

        $TData['APP_CREA_Demande'] = $TAPP_CREA_Demande;

        $TInfos_Apporteur = array(
            //voir lequel on met => TODO
            //C'PRO VALENCE-C PRO - CESSION DE CONTRATS : 121326001 / 0251
            //C'PRO VALENCE-LOCATION MANDATEE – CPRO : 121326001 / 0240
            //CPRO TELECOM-C PRO - CESSION DE CONTRATS : 672730000 /0251
            //C PRO INFORMATIQUE-C PRO - CESSION DE CONTRATS : 470943000 / 0251
            'B2B_APPORTEUR_ID' => '121326001'
            , 'B2B_PROT_ID' => '0251'
            //voir lequel on met => TODO
            //DFERRAZZI / Test321test / A40967000
            //VCORBEAUX / Test321test / 959725000
            , 'B2B_VENDEUR_ID' => 'A40967000'
        );

        $TData['Infos_Apporteur'] = $TInfos_Apporteur;

        $TInfos_Client = array(
            'B2B_SIREN' => ($this->simulation->societe->idprof1) ? $this->simulation->societe->idprof1 : $this->simulation->societe->array_options['options_other_siren']
        );

        $TData['Infos_Client'] = $TInfos_Client;

        if($this->simulation->opt_mode_reglement == 'PRE') $mode_reglement = 'AP';
        else $mode_reglement = $this->simulation->opt_mode_reglement;

        if($this->simulation->opt_terme == 0) $terme = 2;
        else $terme = (int) $this->simulation->opt_terme;

        //TODO Transmis par GE => Voir lequel on met
        //Crédit Bail (Hors protocole Location Mandatée): 975
        //LOA(Hors protocole Location Mandatée) : 979
        //Location Financière(Hors protocole Location Mandatée) : 983
        //Location Evolutive(Hors protocole Location Mandatée) : 991
        //Location Mandatée (pour Protocole Location Mandatée uniquement) : 4926
        $minervaAFPid = '983';
        if($this->simulation->type_financement == 'MANDATEE') $minervaAFPid = '4926';

        if($this->simulation->opt_periodicite == 'TRIMESTRE') $freq = 3;
        else if($this->simulation->opt_periodicite == 'SEMESTRE') $freq = 6;
        else if($this->simulation->opt_periodicite == 'ANNEE') $freq = 12;
        else $freq = 1;

        $TInfos_Financieres = array(
            'B2B_DUREE' => number_format($this->simulation->duree * $freq, 2, '.', '')
            , 'B2B_FREQ' => number_format($freq, 2, '.', '')
            , 'B2B_MODPAIE' => $mode_reglement
            , 'B2B_MT_DEMANDE' => number_format($this->simulation->montant, 2, '.', '')
            , 'B2B_NB_ECH' => number_format($this->simulation->duree, 2, '.', '')
            , 'B2B_MINERVAFPID' => $minervaAFPid
            , 'B2B_TERME' => $terme
        );

        $TData['Infos_Financieres'] = $TInfos_Financieres;

        $TMarqueMatGE = array(
            'CANON' => 'CAN'
            , 'DELL' => 'DEL'
            , 'KONICA MINOLTA' => 'KM'
            , 'KYOCERA' => 'KYO'
            , 'LEXMARK' => 'LEX'
            , 'HEWLETT-PACKARD' => 'HP'
            , 'OCE' => 'OCE'
            , 'OKI' => 'OKI'
            , 'SAMSUNG' => 'SAM'
            , 'TOSHIBA' => 'TOS'
        );

        $TTypeMatGE = array(
            'PHOTOCOPIEUR' => 'PHOTOCO'
            , 'PLIEUSE' => 'PLIEUSE'
            , 'SERVEUR' => 'PHOTOCO'
            , 'TRACEUR DE PLAN' => 'TRACPLA'
            , 'ACCESSOIRES' => 'ACCESS'
            , 'CONFIGURATION INFORMATIQUE' => 'CONFINF'
            , 'IMPRIMANTES' => 'IMPRIM'
            , 'IMPRIMANTE + DE 20P/MN SF LASER' => 'IMPRIMA'
        );

        //TODO => comment on définit quelle valeur prendre?
        $marqueMat = ($TMarqueMatGE[$this->simulation->marque_materiel]) ? $TMarqueMatGE[$this->simulation->marque_materiel] : 'CAN';
        $typeMat = ($TTypeMatGE[$this->simulation->type_materiel]) ? $TTypeMatGE[$this->simulation->type_materiel] : 'PHOTOCO';

        $TInfos_Materiel = array(
            'B2B_MARQMAT' => $marqueMat
            , 'B2B_MT_UNIT' => number_format($this->simulation->montant, 2, '.', '') //TODO je n'ai pas cette info dans LeaserBoard :/
            , 'B2B_QTE' => number_format(1, 2, '.', '') //TODO vérifier au prêt de Damien
            , 'B2B_TYPMAT' => $typeMat
            , 'B2B_ETAT' => 'N' //TODO vérifier au prêt de Damien => N = neuf, O = occasion
        );

        $TData['Infos_Materiel'] = $TInfos_Materiel;

        $TAPP_Reponse_B2B = array(
            'B2B_CLIENT_ASYNC' => '' //TODO adresse d'appel auto pour MAJ statut simulation => attend un WSDL :/
            , 'B2B_INF_EXT' => $this->simulation->reference
            , 'B2B_MODE' => 'A'
        );

        $TData['APP_Reponse_B2B'] = $TAPP_Reponse_B2B;

        return $TData;
    }

    function _getBNPDataTabRapportSuivi() {
        $TRapportSuivi = array(
            'suiviDemande' => $this->__getBNPDataTabSuiviDemande(),
            'demandeNonTrouve' => array(
                'numeroDemandeDefinitif' => ''
            )
        );

        return $TRapportSuivi;
    }

    function __getBNPDataTabSuiviDemande() {
        $TSuiviDemande = array(
            'numeroDemandeProvisoire' => '',
            'numeroDemandeDefinitif' => '',
            'etat' => array(
                'codeStatutDemande' => '',
                'libelleStatutDemande' => ''
            ),
            'client' => array(
                'raisonSociale' => ''
            )
        );

        return $TSuiviDemande;
    }

    function accordAuto(TPDOdb $PDOdb, TSimulation $simu) {
        global $conf, $db;
        if(! function_exists('switchEntity')) dol_include_once('/financement/lib/financement.lib.php');
        $old_entity = $conf->entity;
        switchEntity($simu->entity);

        $isAccordAutoAllowed = $this->checkAccordAutoConstraint($simu);

        if($isAccordAutoAllowed) {
            $message = 'Un accord auto, un ! (Switched to entity '.$conf->entity.' ; fk_simu='.$simu->rowid.', fk_suivi='.$this->rowid.')';
            dol_syslog($message, LOG_INFO, 0, '_accord_auto');
            $this->doActionSelectionner($PDOdb, $simu);
        }
        else {
            $simu->fk_action_manuelle = 2;  // Can't give accord auto
            $simu->save($PDOdb);
        }
        switchEntity($old_entity);
    }

    private function checkAccordAutoConstraint(TSimulation $simu) {
        global $conf;
        $PDOdb = new TPDOdb;

        // Separate into variable only to log them
        $isActive = ! empty($conf->global->FINANCEMENT_ACTIVATE_ACCORD_AUTO) ? 1 : 0;   // false is converted to "" and not to "0"
        $isLessThanMaxAmount = $simu->montant_total_finance < $conf->global->FINANCEMENT_SIMUL_MAX_AMOUNT ? 1 : 0;
        $isEmptyComment = empty($simu->commentaire) ? 1 : 0;
        $isAdjonctionNotChecked = empty($simu->opt_adjonction) ? 1 : 0;
        $isNoCaseToSettleChecked = ! empty($simu->opt_no_case_to_settle) ? 1 : 0;
        $isNotEmptyNumAccordLeaser = ! empty($this->numero_accord_leaser) ? 1 : 0;
        $isLocPure = ($this->fk_leaser == 18495) ? 1 : 0;
        $isFirst = ($this->rang == 0) ? 1 : 0;

        $isDiffBelowMaxDiffPercentage = 1;  // Si la conf 'FINANCEMENT_MAX_DIFF_RENTA' n'est pas active, ça ne bloque pas les accords auto
        if(! empty($conf->global->FINANCEMENT_MAX_DIFF_RENTA)) {
            $isDiffBelowMaxDiffPercentage = 0;

            if(empty($simu->TSimulationSuivi)) $simu->load_suivi_simulation($PDOdb);
            // On doit prendre non pas le 1er leaser, mais le 1er leaser qui est en étude !
            foreach($simu->TSimulationSuivi as $suivi) {
                if($suivi->statut == 'WAIT') {
                    $firstSuivi = $suivi;
                    break;
                }

                if($suivi->rowid == $this->rowid) break;    // Aucun leaser "En étude" au dessus de moi
            }

            // On peut donner un accord si on est le 1er leaser en étude ou si la différence de renta entre le 1er leaser en étude et moi-même est inférieure à la conf
            if(! isset($firstSuivi) || ($firstSuivi->renta_percent - $this->renta_percent) < $conf->global->FINANCEMENT_MAX_DIFF_RENTA) {
                $isDiffBelowMaxDiffPercentage = 1;
            }
        }

        $logMessage = 'CONSTRAINTS FOR FK_SIMU='.$simu->rowid."\n";
        $logMessage .= 'AccordAuto active = '.$isActive."\n";
        $logMessage .= 'LessThanMaxAmount = '.$isLessThanMaxAmount."\n";
        $logMessage .= 'NoComment = '.$isEmptyComment."\n";
        $logMessage .= 'NoAdjonction = '.$isAdjonctionNotChecked."\n";
        $logMessage .= 'NoCaseToSettle = '.$isNoCaseToSettleChecked."\n";
        $logMessage .= 'NotEmptyNumAccordLeaser = '.$isNotEmptyNumAccordLeaser."\n";
        $logMessage .= 'IsLocPure = '.$isLocPure."\n";
        $logMessage .= 'isFirst = '.$isFirst."\n";
        if(! empty($conf->global->FINANCEMENT_MAX_DIFF_RENTA)) {
            $logMessage .= 'isDiffBelowMaxDiffPercentage = '.$isDiffBelowMaxDiffPercentage."\n";
        }
        dol_syslog($logMessage, LOG_INFO, 0, '_accord_auto_constraint');

        return $isActive                                        // Active
            && $isLessThanMaxAmount                             // Montant max
            && $isEmptyComment                                  // Pas de commentaire
            && ($isAdjonctionNotChecked || $isFirst)            // Adjonction pas coché
            && ($isNoCaseToSettleChecked || $isFirst)           // Aucun solde selectionné
            && $isNotEmptyNumAccordLeaser                       // Numéro accord leaser renseigné
            && $isDiffBelowMaxDiffPercentage                    // Différence de renta
            || ($isActive && $isLocPure && $isEmptyComment);    // LOC PURE
    }

    public function isEDI(): bool {
        global $db;

        $leaser = new Societe($db);
        $leaser->fetch($this->fk_leaser);
        if(empty($leaser->array_options)) $leaser->fetch_optionals();

        return (! empty($leaser->array_options['options_edi_leaser']) && in_array($leaser->array_options['options_edi_leaser'], self::$TLeaserEDI));
    }
}
