<?php

class TSimulation extends TObjetStd {
	function __construct($setChild=false) {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
		parent::add_champs('entity,fk_soc,fk_user_author,fk_user_suivi,fk_leaser,accord_confirme','type=entier;');
		parent::add_champs('duree,opt_administration,opt_creditbail,opt_adjonction,opt_no_case_to_settle','type=entier;');
		parent::add_champs('montant,montant_rachete,montant_rachete_concurrence,montant_decompte_copies_sup,montant_rachat_final,montant_total_finance,echeance,vr,coeff,cout_financement,coeff_final,montant_presta_trim','type=float;');
		parent::add_champs('date_simul,date_validite,date_accord,date_demarrage','type=date;');
		parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme,fk_type_contrat,accord,type_financement,commentaire,type_materiel,marque_materiel,numero_accord,reference,opt_calage','type=chaine;');
		parent::add_champs('dossiers,dossiers_rachetes_m1,dossiers_rachetes_nr_m1,dossiers_rachetes,dossiers_rachetes_nr,dossiers_rachetes_p1,dossiers_rachetes_nr_p1,dossiers_rachetes_perso', 'type=tableau;');
		parent::add_champs('thirdparty_name,thirdparty_address,thirdparty_zip,thirdparty_town,thirdparty_code_client,thirdparty_idprof2_siret, thirdparty_idprof3_naf','type=chaine;');
		parent::add_champs('fk_categorie_bien,fk_nature_bien', array('type'=>'integer'));
		parent::add_champs('pct_vr,mt_vr', array('type'=>'float'));

		parent::start();
		parent::_init_vars();
		
		$this->init();
		
		if ($setChild) $this->setChild('TSimulationSuivi','fk_simulation');
		else $this->TSimulationSuivi = array();
		
		$this->TStatut=array(
			'OK'=>$langs->trans('Accord')
			,'WAIT'=>$langs->trans('Etude')
			/*,'WAIT_LEASER'=>$langs->trans('Etude_Leaser')
			,'WAIT_SELLER'=>$langs->trans('Etude_Vendeur')*/
			,'KO'=>$langs->trans('Refus')
			,'SS'=>$langs->trans('SansSuite')
		);
		
		$this->TStatutShort=array(
			'OK'=>$langs->trans('Accord')
			,'WAIT'=>$langs->trans('Etude')
			/*,'WAIT_LEASER'=>$langs->trans('Etude_Leaser_Short')
			,'WAIT_SELLER'=>$langs->trans('Etude_Vendeur_Short')*/
			,'KO'=>$langs->trans('Refus')
			,'SS'=>$langs->trans('SansSuite')
		);
		
		$this->TTerme = array(
			0=>'Echu'
			,1=>'A Echoir'
		);
		
		$this->TMarqueMateriel = array(
			'CANON' => 'CANON'
			,'DELL' => 'DELL'
			,'KONICA MINOLTA' => 'KONICA MINOLTA'
			,'KYOCERA' => 'KYOCERA'
			,'LEXMARK' => 'LEXMARK'
			,'HEWLETT-PACKARD' => 'HEWLETT-PACKARD'
			,'OCE' => 'OCE'
			,'OKI' => 'OKI'
			,'SAMSUNG' => 'SAMSUNG'
			,'TOSHIBA' => 'TOSHIBA'
		);

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
		$this->coeff = 0;
		$this->fk_user_author = $user->id;
		$this->user = $user;
		$this->dossiers = array();
		$this->dossiers_rachetes = array();
		$this->dossiers_rachetes_nr = array();
		$this->dossiers_rachetes_p1 = array();
		$this->dossiers_rachetes_nr_p1 = array();
		$this->dossiers_rachetes_perso = array();
	}
 
	function getRef() {
		if($this->getId() > 0) return 'S'.str_pad($this->getId(), 6, '0', STR_PAD_LEFT);
		else return 'DRAFT';
	}
	
	function load(&$db, &$doliDB, $id, $annexe=true) {
		parent::load($db, $id);
		
		if($annexe) {
			$this->load_annexe($db, $doliDB);
		}
	}
	
	function save(&$db, &$doliDB) {
		//parent::save($db);
		//pre($this,true);exit;
	//	var_dump($this->dossiers_rachetes, $_REQUEST);exit;
		parent::save($db);
		
		$this->reference = $this->getRef();
		
		if(empty($this->dossiers) || count($this->dossiers) != count($this->dossiers_rachetes)){
			
			foreach($this->dossiers_rachetes as $k=>$TDossiers){
				$dossier =  new TFin_dossier;
				$dossier->load($db, $k);
				
				// Renouvelant, renouvellant + 1, non renouvellant ou non renouvellant + 1
				$periode = 0;
				if(!empty($this->dossiers_rachetes_m1[$dossier->rowid]['checked'])) {
					$type = 'SRBANK';
					$periode = -1;
				} elseif(!empty($this->dossiers_rachetes_nr_m1[$dossier->rowid]['checked'])) {
					$type = 'SNRBANK';
					$periode = -1;
				} elseif(!empty($this->dossiers_rachetes[$dossier->rowid]['checked'])) {
					$type = 'SRBANK';
					$periode = 0;
				} elseif(!empty($this->dossiers_rachetes_nr[$dossier->rowid]['checked'])) {
					$type = 'SNRBANK';
					$periode = 0;
				} elseif(!empty($this->dossiers_rachetes_p1[$dossier->rowid]['checked'])) {
					$type = 'SRBANK';
					$periode = 1;
				} elseif(!empty($this->dossiers_rachetes_nr_p1[$dossier->rowid]['checked'])) {
					$type = 'SNRBANK';
					$periode = 1;
				}
				
				$echeance = $dossier->_get_num_echeance_from_date($dossier->financementLeaser->date_prochaine_echeance);
				//echo '*'.$type.' : '.$periode.' : '.($echeance + $periode).'*';
				$solde = $dossier->getSolde($db, $type, $echeance + $periode);
				
				if($dossier->nature_financement == 'INTERNE') {
					$fin = &$dossier->financement;
					$fin_leaser = &$dossier->financementLeaser;
					$echeance = $dossier->_get_num_echeance_from_date($dossier->financement->date_prochaine_echeance);
					$date_debut_periode_client = $dossier->getDateDebutPeriode(($echeance-1) + $periode,'CLIENT');
					$date_fin_periode_client = $dossier->getDateFinPeriode(($echeance-1) + $periode,'CLIENT');
					$echeance = $dossier->_get_num_echeance_from_date($dossier->financementLeaser->date_prochaine_echeance);
					$date_debut_periode_leaser = $dossier->getDateDebutPeriode(($echeance-1) + $periode);
					$date_fin_periode_leaser = $dossier->getDateFinPeriode(($echeance-1) + $periode);
				}
				else{
					$fin = &$dossier->financementLeaser;
					$fin_leaser = &$dossier->financementLeaser;
					$echeance = $dossier->_get_num_echeance_from_date($dossier->financementLeaser->date_prochaine_echeance);
					$date_debut_periode_client = $date_debut_periode_leaser = $dossier->getDateDebutPeriode(($echeance-1) + $periode);
					$date_fin_periode_client = $date_fin_periode_leaser = $dossier->getDateFinPeriode(($echeance-1) + $periode);
				}
				
				/*echo $dossier->rowid.' : '.$dossier->financementLeaser->date_prochaine_echeance.' : '.$date_debut_periode_client.' : '.$date_fin_periode_client;
				echo '<br>';*/
				//pre($this, true);
				$soldeperso = round($dossier->getSolde($db, 'perso'),2);
				if(empty($dossier->display_solde)) $soldeperso = 0;
				if(!$dossier->getSolde($db, 'perso')) $soldeperso = ($soldepersointegrale * (FINANCEMENT_PERCENT_RETRIB_COPIES_SUP/100));
				
				$this->dossiers[$k]['ref_simulation'] = $this->reference;
				$this->dossiers[$k]['num_contrat'] = $fin->reference;
				$this->dossiers[$k]['num_contrat_leaser'] = $fin_leaser->reference;
				$this->dossiers[$k]['date_debut_periode_client'] = $date_debut_periode_client;
				$this->dossiers[$k]['date_fin_periode_client'] = $date_fin_periode_client;
				$this->dossiers[$k]['date_debut_periode_leaser'] = $date_debut_periode_leaser;
				$this->dossiers[$k]['date_fin_periode_leaser'] = $date_fin_periode_leaser;
				$this->dossiers[$k]['decompte_copies_sup'] = $soldeperso;
				$this->dossiers[$k]['solde_banque_a_periode_identique'] = $solde;
				$this->dossiers[$k]['type_contrat'] = $dossier->TLien[0]->affaire->contrat;
				$this->dossiers[$k]['duree'] = $fin->duree.' '.substr($fin->periodicite,0,1);
				$this->dossiers[$k]['echeance'] = $fin->echeance;
				$this->dossiers[$k]['loyer_actualise'] = $fin->loyer_actualise;
				$this->dossiers[$k]['date_debut'] = $fin->date_debut;
				$this->dossiers[$k]['date_fin'] = $fin->date_fin;
				$this->dossiers[$k]['date_prochaine_echeance'] = $fin->date_prochaine_echeance;
				$this->dossiers[$k]['numero_prochaine_echeance'] = $fin->numero_prochaine_echeance.'/'.$fin->duree;
				$this->dossiers[$k]['terme'] = $fin->TTerme[$fin->terme];
				$this->dossiers[$k]['reloc'] = $fin->reloc;
				$this->dossiers[$k]['maintenance'] = $fin->montant_prestation;
				$this->dossiers[$k]['assurance'] = $fin->assurance;
				$this->dossiers[$k]['assurance_actualise'] = $fin->assurance_actualise;
				$this->dossiers[$k]['montant'] = $fin->montant;
			}
		}

		//pre($this, true);exit;
		
		$this->gen_simulation_pdf($db, $doliDB);
		
		parent::save($db);
		
		//Création du suivi simulation leaser s'il n'existe pas
		//Sinon chargement du suivi
		$this->load_suivi_simulation($db);
	}
	
	function setThirparty()
	{
		if (!empty($this->societe->id))
		{
			$this->thirdparty_name = $this->societe->nom;
			$this->thirdparty_address = $this->societe->address;
			$this->thirdparty_zip = $this->societe->zip;
			$this->thirdparty_town = $this->societe->town;
			$this->thirdparty_code_client = $this->societe->code_client;
			$this->thirdparty_idprof2_siret = $this->societe->idprof2;
			$this->thirdparty_idprof3_naf = $this->societe->idprof3;
		}
	}
	
	function create_suivi_simulation(&$PDOdb){
		global $db, $conf;
		dol_include_once('/categories/class/categorie.class.php');
		//echo 'create<br>';
		//Pour chacun des leasers, on créé un suivi demande de financement
		//Les leasers concernés sont ceux présent dans la catégorie "Type de financement" => id = 2
		$categorieParent = new Categorie($db);
		$categorieParent->fetch('','Type de financement');
		$TCategoriesFille = $categorieParent->get_filles();
		
		// Chargement du tableau des leaser par type de contrat
		$TFinGrilleSuivi = new TFin_grille_suivi;
		$grille = $TFinGrilleSuivi->get_grille($PDOdb, 'DEFAUT_'.$this->fk_type_contrat,false);
		$TGrille = array();
		foreach($grille as $TData) $TGrille[$TData['fk_leaser']] = $TData['fk_leaser'];
		
		$TLeasersDejaAjoutes = array();
		
		foreach ($TCategoriesFille as $categorieFille) {
			//$TLeaser = $categorieFille->get_type("societe","Fournisseur","fournisseur");
			$TLeaser = $categorieFille->getObjectsInCateg("supplier");
			
			//Pour chaque leaser, ajout d'une ligne de suivi
			foreach($TLeaser as $leaser){
				
				// On n'ajoute que les leaser qui sont en conf
				if(!in_array($leaser->id, $TGrille) || isset($TLeasersDejaAjoutes[$leaser->id])) continue;
				$simulationSuivi = new TSimulationSuivi;
				$simulationSuivi->init($PDOdb,$leaser,$this->getId());
				$simulationSuivi->save($PDOdb);
				
				// Ajout du leaser dans le tableau
				$TLeasersDejaAjoutes[$leaser->id] = $leaser->id;
				
				$this->TSimulationSuivi[$simulationSuivi->getId()] = $simulationSuivi;
				$this->reordreSimulationSuivi($PDOdb);
			}
		}
		/*foreach($this->TSimulationSuivi as $k=>$v) {
			echo $v->fk_leaser.'<br>';
		}exit;*/
	}
	
	function getStatut() {
		return $this->TStatut[$this->accord];
	}
	
	function getAuthorFullName() {
		global $langs;
		$this->user->fetch($this->fk_user_author);
		return utf8_decode($this->user->getFullName($langs));
	}
	
	function load_annexe(&$db, &$doliDB) {
		global $conf;
		if(!empty($this->fk_soc)) {
			// Récupếration des infos du client
			if(empty($this->societe)) {
				$this->societe = new Societe($doliDB);
				$this->societe->fetch($this->fk_soc);
			}
			
			// Récupération du score du client
			if(empty($this->societe->score)) {
				$this->societe->score = new TScore();
				$this->societe->score->load_by_soc($db, $this->fk_soc);
			}
			
			// Récupération des autres simulations du client
			if(empty($this->societe->TSimulations)) {
				$this->societe->TSimulations = $this->load_by_soc($db, $doliDB, $this->fk_soc);
			}
			
			// Récupération des dossiers en cours du client et de l'encours CPRO
			if(empty($this->societe->TDossiers)) {
				$sql = "SELECT s.rowid
						FROM ".MAIN_DB_PREFIX."societe as s
							LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = s.rowid)
							LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (cf.fk_categorie = c.rowid)
						WHERE c.label = 'Encours CPRO'";
				
				$TEncours = TRequeteCore::_get_id_by_sql($db, $sql);
			
				$sql = "SELECT d.rowid";
				$sql.= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
				$sql.= " WHERE a.entity = ".$conf->entity;
				$sql.= " AND a.fk_soc = ".$this->fk_soc;
				$TDossiers = TRequeteCore::_get_id_by_sql($db, $sql);

				$this->societe->encours_cpro = 0;
				foreach ($TDossiers as $idDossier) {
					$doss = new TFin_dossier;
					$doss->load($db, $idDossier);
					$this->societe->TDossiers[] = $doss;
					/*if($doss->nature_financement == 'EXTERNE' && (empty($doss->financement->date_solde) || $doss->financementLeaser->date_solde < 0)) {
						$this->societe->encours_cpro += $doss->financementLeaser->valeur_actuelle();
					} else if(empty($doss->financement->date_solde) || $doss->financement->date_solde < 0) {
						$this->societe->encours_cpro += $doss->financement->valeur_actuelle();
					}*/
				
					// 2013.12.02 Modification : ne prendre en compte que les leaser faisant partie de la catégorie "Encours CPRO"
					// 2013.10.02 MKO : Modification demandée par Damien de ne comptabiliser que les dossier internes
					if(!empty($doss->financement) 
						&& (empty($doss->financement->date_solde) || $doss->financement->date_solde < 0) 
						&& in_array($doss->financementLeaser->fk_soc,$TEncours)  ) {
						//echo $doss->financement->reference." : ".$doss->financement->valeur_actuelle()."<br>";
                        $this->societe->encours_cpro += $doss->financement->valeur_actuelle();
                    }
				}
				$this->societe->encours_cpro = round($this->societe->encours_cpro, 2);
			}
		}
		
		if(!empty($this->fk_leaser)) {
			$this->leaser = new Societe($doliDB);
			$this->leaser->fetch($this->fk_leaser);
		}
		
		if(!empty($this->fk_user_author)) {
			$this->user = new User($doliDB);
			$this->user->fetch($this->fk_user_author);
		}
		
		if(!empty($this->fk_user_suivi)) {
			$this->user_suivi = new User($doliDB);
			$this->user_suivi->fetch($this->fk_user_suivi);
		}
		
		//Récupération des suivis demande de financement leaser s'ils existent
		//Sinon on les créé
		$this->load_suivi_simulation($db);
	}
	
	//Charge dans un tableau les différents suivis de demande leaser concernant la simulation
	function load_suivi_simulation(&$PDOdb){
		//echo 'load<br>';
		$TRowid = TRequeteCore::get_id_from_what_you_want($PDOdb,MAIN_DB_PREFIX."fin_simulation_suivi",array('fk_simulation' => $this->getId()));
		//pre($TRowid,true);exit;
		//Si les suivis existent déjà
		if(count($TRowid) > 0){
			
			foreach($TRowid as $rowid){
				$simulationSuivi = new TSimulationSuivi;
				$simulationSuivi->load($PDOdb, $rowid);

				$this->TSimulationSuivi[$simulationSuivi->getId()] = $simulationSuivi;
			}
			//Réorganisation de l'ordre de la liste en fonction de la grille d'administration
			$TLeaser = $this->reordreSimulationSuivi($PDOdb);
		}
		elseif($this->rowid > 0){
			$this->create_suivi_simulation($PDOdb);
		}
	}
	
	//Réorganisation de l'ordre de la liste en fonction de la grille d'administration
	function reordreSimulationSuivi(&$PDOdb){
		
		global $conf;
		
		$TSimulationSuiviTemp = array();
		
		//Récupération du leaser prioritaire pour affichage en premier dans le tableau
		$idLeaserPrio = $this->getIdLeaserPrioritaire($PDOdb);
		//echo $idLeaserPrio;
		if($idLeaserPrio){
			//Récupération Id suivi simulation correspondant au leaser
			$idSimulationSuiviLeaserPrio = TRequeteCore::get_id_from_what_you_want($PDOdb,MAIN_DB_PREFIX."fin_simulation_suivi",array('fk_simulation' => $this->getId(),'fk_leaser'=>$idLeaserPrio));
			//Chargement du suivi simulation associé au leaser
			$simulationSuiviLeaserPrio = new TSimulationSuivi;
			$simulationSuiviLeaserPrio->load($PDOdb,$idSimulationSuiviLeaserPrio[0]);
			//Ajout du suivi simulation associé dans le tableau à la première place
			$id = $simulationSuiviLeaserPrio->getId();
			if(!empty($id)) $TSimulationSuiviTemp[$id] = $simulationSuiviLeaserPrio;
		}
		
		//Récupération de l'ordre par défaut pour les autres Leaser
		$sql = "SELECT rowid, fk_leaser_solde, montantbase 
				FROM ".MAIN_DB_PREFIX."fin_grille_suivi 
				WHERE fk_type_contrat = 'DEFAUT_".$this->fk_type_contrat."' AND entity = ".(empty($this->entity) ? $conf->entity : $this->entity);
		if($idLeaserPrio) $sql .= " AND fk_leaser_solde != ".$idLeaserPrio;	
		$sql .= " ORDER BY montantbase ASC";
	
		$PDOdb->Execute($sql);

		while($PDOdb->Get_line()){
			foreach($this->TSimulationSuivi as $simulationSuivi){
				if((int)$simulationSuivi->fk_leaser === (int)$PDOdb->Get_field('fk_leaser_solde')){
					$TSimulationSuiviTemp[$simulationSuivi->rowid] = $simulationSuivi;
				}
			}
		}
		$this->TSimulationSuivi = $TSimulationSuiviTemp;
	}
	
	
	//Retourne l'identifiant leaser prioritaire en fonction de la grille d'administration
	function getIdLeaserPrioritaire(&$PDOdb){
		global $db;
		
		$idLeaserPrioritaire = 0; //18305 ACECOM pour test
		
		$TFinGrilleSuivi = new TFin_grille_suivi;
		$grille = $TFinGrilleSuivi->get_grille($PDOdb, $this->fk_type_contrat,false);
		
		//Vérification si solde dossier sélectionné pour cette simulation : si oui on récupère le leaser associé
		$idLeaserDossierSolde = $this->getIdLeaserDossierSolde($PDOdb);
		//echo $idLeaserDossierSolde;
		//Récupération de la catégorie du client : entreprise, administration ou association
		// suivant sont code NAF
		// entreprise = les autres
		// association = 94
		// administration = 84
		$labelCategorie = $this->getLabelCategorieClient();
		
		//pre($grille,true);
		
		//On récupère l'id du leaser prioritaire en fonction des règles de gestion
		foreach($grille as $TElement){
			$TMontant = explode(';',$TElement['montant']);
			//echo $TElement['solde'].'<br>';
			//echo $TElement[$labelCategorie];exit;
			if($TMontant[0] < $this->montant_total_finance && $TMontant[1] >= $this->montant_total_finance && !empty($TElement[$labelCategorie])){
				//Si aucun solde sélectionné alors on on prends l'un des deux premier élément de la grille "Pas de solde / Refus du leaser en place"
				if($idLeaserDossierSolde){
					//Si dossier sélectionner à soldé, alors on prends la ligne concernée
					$cat = new Categorie($db);
					$TCats = $cat->containing($idLeaserDossierSolde, 1);
					//pre($TCats,true);exit;
					foreach ($TCats as $categorie) {
						if($categorie->id == $TElement['solde']){
							$idLeaserPrioritaire = $TElement[$labelCategorie];
							return $idLeaserPrioritaire;
						}
					}
				}
				else{
					$idLeaserPrioritaire = $TElement[$labelCategorie];
					return $idLeaserPrioritaire;
				}
				
			}
		}

		return $idLeaserPrioritaire;
	}
	
	//Récupération de la catégorie du client : entreprise, administration ou association
	function getLabelCategorieClient(){
		global $db;
		
		//Récupération de la catégorie du client : entreprise, administration ou association
		/*$categorie = new Categorie($db);
		$TCategories = $categorie->get_all_categories(2);
		$labelCategorie = '';
		if(count($TCategories)){
			foreach($TCategories as $categorie){
				if($categorie->label == 'Entreprise' || $categorie->label == 'Administration' || $categorie->label == 'Association'){
					$labelCategorie = strtolower($categorie->label);
				}
			}
		}*/
		
		switch (substr($this->societe->idprof3,0,2)) {
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
		
		return $labelCategorie;
	}
	
	//Vérification si solde dossier sélectionné pour cette simulation : si oui on récupère le leaser associé
	function getIdLeaserDossierSolde(&$PDOdb){
		
		$idLeaserDossierSolde = $montantDossierSole = 0;
		$TDossierUsed = $this->get_list_dossier_used();
		//pre($TDossierUsed,true);
		if(count($TDossierUsed)){
			foreach($TDossierUsed as $k => $id_dossier){
				$dossier = new TFin_dossier;
				$dossier->load($PDOdb, $id_dossier);
				//pre($dossier,true);
				//Si plusieurs dossiers soldé dans la simulation alors on prends le Leaser de celui ayant le plus gros montant
				if($dossier->montant > $montantDossierSole){
					$idLeaserDossierSolde = $dossier->financementLeaser->fk_soc;
					$montantDossierSole = $dossier->montant;
				}
			}
		}
		
		return $idLeaserDossierSolde;
	}
	
	function get_suivi_simulation(&$PDOdb,&$form){
		global $db;

		$this->load_suivi_simulation($PDOdb);
		//echo 'get<br>';
		$TLignes = array();
		if ($this->accord == "OK" ) $form->type_aff = 'view';
		//pre($this->TSimulationSuivi,true);
		//Construction d'un tableau de ligne pour futur affichage TBS
		foreach($this->TSimulationSuivi as $simulationSuivi){
			//echo $simulationSuivi->rowid.'<br>';
			$link_user = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$simulationSuivi->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$simulationSuivi->user->login.'</a>';
			
			$ligne = array();
			//echo $simulationSuivi->get_Date('date_demande').'<br>';
			$ligne['rowid'] = $simulationSuivi->getId();
			$ligne['class'] = (count($TLignes) % 2) ? 'impair' : 'pair';
			$ligne['leaser'] = '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$simulationSuivi->fk_leaser.'">'.img_picto('','object_company.png', '', 0).' '.$simulationSuivi->leaser->nom.'</a>';
			$ligne['demande'] = ($simulationSuivi->statut_demande == 1) ? '<img src="'.dol_buildpath('/financement/img/check_valid.png',1).'" />' : '' ;
			$ligne['date_demande'] = ($simulationSuivi->get_Date('date_demande')) ? $simulationSuivi->get_Date('date_demande') : '' ;
			$img = $simulationSuivi->statut;
			if(!empty($simulationSuivi->date_selection)) $img = 'super_ok';
			$ligne['resultat'] = ($simulationSuivi->statut) ? '<img title="'.$simulationSuivi->TStatut[$simulationSuivi->statut].'" src="'.dol_buildpath('/financement/img/'.$img.'.png',1).'" />' : '';
			$ligne['numero_accord_leaser'] = (($simulationSuivi->statut == 'WAIT' || $simulationSuivi->statut == 'OK') && $simulationSuivi->date_selection <= 0) ? $form->texte('', 'TSuivi['.$simulationSuivi->rowid.'][num_accord]', $simulationSuivi->numero_accord_leaser, 25,0,'style="text-align:right;"') : $simulationSuivi->numero_accord_leaser;
			
			$ligne['date_selection'] = ($simulationSuivi->get_Date('date_selection')) ? $simulationSuivi->get_Date('date_selection') : '' ;
			$ligne['utilisateur'] = ($simulationSuivi->fk_user_author && $simulationSuivi->date_cre != $simulationSuivi->date_maj) ? $link_user : '' ;
			
			$ligne['coeff_leaser'] = (($simulationSuivi->statut == 'WAIT' || $simulationSuivi->statut == 'OK') && $simulationSuivi->date_selection <= 0) ? $form->texte('', 'TSuivi['.$simulationSuivi->rowid.'][coeff_accord]', $simulationSuivi->coeff_leaser, 6,0,'style="text-align:right;"') : (($simulationSuivi->coeff_leaser>0) ? $simulationSuivi->coeff_leaser : '');
			$ligne['commentaire'] = (($simulationSuivi->statut == 'WAIT' || $simulationSuivi->statut == 'OK') && $simulationSuivi->date_selection <= 0) ? $form->zonetexte('', 'TSuivi['.$simulationSuivi->rowid.'][commentaire]', $simulationSuivi->commentaire, 25,0) : nl2br($simulationSuivi->commentaire);
			$ligne['actions'] = $simulationSuivi->getAction($this);
			$ligne['action_save'] = $simulationSuivi->getAction($this, true);
			
			$TLignes[] = $ligne;
		}

		return $TLignes;
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
	 * 			1	= calcul OK
	 * 			-1	= montant ou echeance vide (calcul impossible)
	 * 			-2	= montant hors grille
	 * 			-3	= echeance hors grille
	 * 			-4	= Pas de grille chargée
	 */
	function calcul_financement(&$ATMdb, $idLeaser, $options, $typeCalcul='cpro') {
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
				&& empty($this->dossiers_rachetes) && empty($this->dossiers_rachetes_nr)
				&& empty($this->dossiers_rachetes_p1) && empty($this->dossiers_rachetes_nr_p1)
				&& empty($this->dossiers_rachetes_perso)) { // Dossier obligatoire
			$this->error = 'ErrorCaseMandatory';
			return false;
		}
		else if(empty($this->type_materiel)) { // Périodicité obligatoire
			$this->error = 'ErrorMaterielRequired';
			return false;
		}
		else if($this->montant_presta_trim <= 0 && $this->fk_type_contrat == "FORFAITGLOBAL") {
			$this->error = 'ErrorMontantTrimRequired';
			return false;
		}
		
		// Récupération de la grille pour les paramètres donnés
		$grille = new TFin_grille_leaser;
		$grille->get_grille($ATMdb, $idLeaser, $this->fk_type_contrat, $this->opt_periodicite, $options, $this->entity);
		
		if(empty($grille->TGrille)) { // Pas de grille chargée, pas de calcul
			$this->error = 'ErrorNoGrilleSelected';
			return false;
		}

		if(!empty($this->montant_total_finance) && !empty($this->echeance)) { // Si montant ET échéance renseignés, on calcule à partir du montant
			$this->echeance = 0;
		}
		
		$this->coeff=0;
		// Calcul à partir du montant
		if(!empty($this->montant_total_finance)) {
			foreach($grille->TGrille[$this->duree] as $palier => $infos) {
				if($this->montant_total_finance <= $palier)
				{
					$this->coeff = $infos['coeff']; // coef trimestriel
					break;
				}
			}
		} else if(!empty($this->echeance)) { // Calcul à partir de l'échéance
			$montant = 0;
			$palierMin = 0;
			foreach($grille->TGrille[$this->duree] as $palier => $infos) {
				$montantMax = $this->echeance / ($infos['coeff'] / 100);
				if($montantMax > $montant && $montantMax <= $palier && $montantMax >= $palierMin) {
					$montant = $montantMax;
					$this->coeff = $infos['coeff']; // coef trimestriel
				}
				$palierMin = $palier;
			}
		}
		
		if($this->coeff==0){
			$this->error = 'ErrorAmountOutOfGrille';
			return false;
		}
		
		// Le coeff final renseigné par un admin prend le pas sur le coeff grille
		if(!empty($this->coeff_final) && $this->coeff_final != $this->coeff) {
			$this->coeff = $this->coeff_final;
		}
		
		$coeffTrimestriel = $this->coeff / 4 / 100; // en %

		if(!empty($this->montant_total_finance)) { // Calcul à partir du montant
			if($typeCalcul=='cpro') { // Les coefficient sont trimestriel, à adapter en fonction de la périodicité de la simulation
				$this->echeance = ($this->montant_total_finance - $this->vr) * ($this->coeff / 100);
				if($this->opt_periodicite == 'ANNEE') $this->echeance *= 4;
				else if($this->opt_periodicite == 'MOIS') $this->echeance /= 3;
			} else {
				$this->echeance = $this->montant_total_finance * $coeffTrimestriel / (1- pow(1+$coeffTrimestriel, -$this->duree) );
			}
			
			//print "$this->echeance = $this->montant_total_finance, &$this->duree, &$this->echeance, $this->vr, &$this->coeff::$coeffTrimestriel";
			
			$this->echeance = round($this->echeance, 2);
		} 
		else if(!empty($this->echeance)) { // Calcul à partir de l'échéance
			if($typeCalcul=='cpro') {
				$this->montant = $this->echeance / ($this->coeff / 100) + $this->vr;
				if($this->opt_periodicite == 'ANNEE') $this->montant /= 4;
				else if($this->opt_periodicite == 'MOIS') $this->montant *= 3;
			} else {
				$this->montant =  $this->echeance * (1- pow(1+$coeffTrimestriel, -$this->duree) ) / $coeffTrimestriel ;
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
		if(!(empty($this->fk_soc))) {
			$this->accord = 'WAIT';
			if($this->societe->score->rowid == 0 // Pas de score => WAIT
				|| empty($this->societe->idprof3)) // Pas de NAF => WAIT
			{
				$this->accord = 'WAIT';
			} else { // Donnée suffisantes pour faire les vérifications pour l'accord
				// Calcul du montant disponible pour le client
				$montant_dispo = ($this->societe->score->encours_conseille - $this->societe->encours_cpro);
				$montant_dispo *= ($conf->global->FINANCEMENT_PERCENT_VALID_AMOUNT / 100);
				
				// Calcul du % de rachat
				$percent_rachat = (($this->montant_rachete + $this->montant_rachete_concurrence) / $this->montant_total_finance) * 100;
				
				if($this->societe->score->score >= $conf->global->FINANCEMENT_SCORE_MINI // Score minimum
					&& $this->montant_total_finance <= $conf->global->FINANCEMENT_MONTANT_MAX_ACCORD_AUTO // Montant ne dépasse pas le max
					&& $montant_dispo > $this->montant_total_finance // % "d'endettement"
					&& $percent_rachat <= $conf->global->FINANCEMENT_PERCENT_RACHAT_AUTORISE // % de rachat
					&& !in_array($this->societe->idprof3, explode(FIN_IMPORT_FIELD_DELIMITER, $conf->global->FINANCEMENT_NAF_BLACKLIST)) // NAF non black-listé
					&& !empty($this->societe->TDossiers)) // A déjà eu au moins un dossier chez CPRO
				{
					$this->accord = 'OK';
				} 
			}
		}
	}
	
	function load_by_soc(&$db, &$doliDB, $fk_soc) {
		global $conf;
		
		$sql = "SELECT ".OBJETSTD_MASTERKEY;
		$sql.= " FROM ".$this->get_table();
		$sql.= " WHERE fk_soc = ".$fk_soc;
		$sql.= " AND entity = ".$conf->entity;
		
		$TIdSimu = TRequeteCore::_get_id_by_sql($db, $sql, OBJETSTD_MASTERKEY);
		$TResult = array();
		foreach($TIdSimu as $idSimu) {
			$simu = new TSimulation;
			$simu->load($db, $doliDB, $idSimu, false);
			$TResult[] = $simu;
		}
		
		return $TResult;
	}
	
	function get_list_dossier_used($except_current=false) {
		
		global $conf;
		$TDossier = array();
		if(!empty($this->societe->TSimulations)) {
			foreach ($this->societe->TSimulations as $simu) {
				if($except_current && $simu->{OBJETSTD_MASTERKEY} == $this->{OBJETSTD_MASTERKEY}) continue;
				//pre($simu->dossiers_rachetes,true);
				
				$datetimesimul = strtotime($simu->get_date('date_simul','Y-m-d'));
				$datetimenow = time();
				$nb_jour_diff = ($datetimenow - $datetimesimul)/86400;
				//pre($simu,true);
				foreach($simu->dossiers_rachetes as $k => $TDossiers_rachetes){
					if($this->dossier_used($simu,$TDossiers_rachetes,$nb_jour_diff) && !in_array($k, array_keys($TDossier))){
						$TDossier[$k] = $TDossiers_rachetes['checked'];
					}
				}
				foreach($simu->dossiers_rachetes_nr as $k => $TDossiers_rachetes){
					if($this->dossier_used($simu,$TDossiers_rachetes,$nb_jour_diff) && !in_array($k, array_keys($TDossier))){
						$TDossier[$k] = $TDossiers_rachetes['checked'];
					}
				}
				foreach($simu->dossiers_rachetes_p1 as $k => $TDossiers_rachetes){
					if($this->dossier_used($simu,$TDossiers_rachetes,$nb_jour_diff) && !in_array($k, array_keys($TDossier))){
						$TDossier[$k] = $TDossiers_rachetes['checked'];
					}
				}
				foreach($simu->dossiers_rachetes_nr_p1 as $k => $TDossiers_rachetes){
					if($this->dossier_used($simu,$TDossiers_rachetes,$nb_jour_diff) && !in_array($k, array_keys($TDossier))){
						$TDossier[$k] = $TDossiers_rachetes['checked'];
					}
				}
				//$TDossier = array_merge($TDossier, $simu->dossiers_rachetes, $simu->dossiers_rachetes_p1);
			}
		}
		return $TDossier;
	}

	function dossier_used(&$simu,&$TDossiers_rachetes,$nb_jour_diff){
		global $conf;
		if(!is_array($TDossiers_rachetes)) $TDossiers_rachetes = array();
		if(array_key_exists('checked', $TDossiers_rachetes) 
			//&& ($fin->accord == 'KO' || $fin->accord == 'SS' )
			&& $nb_jour_diff <= $conf->global->FINANCEMENT_SIMU_NB_JOUR_DOSSIER_INDISPO){
				return true;
		}
		return false;
	}
	
	function send_mail_vendeur($auto=false, $mailto='') {
		global $langs, $conf;
		
		dol_include_once('/core/class/html.formmail.class.php');
		dol_include_once('/core/lib/files.lib.php');
		dol_include_once('/core/class/CMailFile.class.php');
		
		$PDFName = dol_sanitizeFileName($this->getRef()).'.pdf';
		$PDFPath = $conf->financement->dir_output . '/' . dol_sanitizeFileName($this->getRef());
		
		$formmail = new FormMail($db);
		$formmail->clear_attached_files();
		$formmail->add_attached_files($PDFPath.'/'.$PDFName,$PDFName,dol_mimetype($PDFName));
		
		$attachedfiles=$formmail->get_attached_files();
		$filepath = $attachedfiles['paths'];
		$filename = $attachedfiles['names'];
		$mimetype = $attachedfiles['mimes'];
		
		if($this->accord == 'OK') {
			$accord = ($auto) ? 'Accord automatique' : 'Accord de la cellule financement';
			$mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
			$mesg.= 'Vous trouverez ci-joint l\'accord de financement concernant votre simulation n '.$this->reference.'.'."\n\n";
			if(!empty($this->commentaire)) $mesg.= 'Commentaire : '."\n".$this->commentaire."\n\n";
			$mesg.= 'Cordialement,'."\n\n";
			$mesg.= 'La cellule financement'."\n\n";
		} else {
			$accord = 'Demande de financement refusée';
			$mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
			$mesg.= 'Votre demande de financement via la simulation n '.$this->reference.' n\'a pas été acceptée.'."\n\n";
			if(!empty($this->commentaire)) $mesg.= 'Commentaire : '."\n".$this->commentaire."\n\n";
			$mesg.= 'Cordialement,'."\n\n";
			$mesg.= 'La cellule financement'."\n\n";
		}
		$subject = 'Simulation '.$this->reference.' - '.$this->societe->getFullName($langs).' - '.number_format($this->montant_total_finance,2,',',' ').' Euros - '.$accord;
		
		if(empty($mailto))$mailto = $this->user->email;
		
		/*$mailfile = new CMailFile(
			$subject,
			$mailto,
			$conf->notification->email_from,
			$mesg,
			$filepath,
			$mimetype,
			$filename,
			'',
			'',
			0,
			0
		);*/
		$r=new TReponseMail($conf->notification->email_from, $mailto, $subject, $mesg);

        foreach($filename as $k=>$file) {
                $r->add_piece_jointe($filename[$k], $filepath[$k]);

        }

        $r->send(false);
		
		setEventMessage('Accord envoyé à : '.$mailto,'mesgs');
		
		/*
		if ($mailfile->error) {
			echo 'ERR : '.$mailfile->error;
		}
			$mailfile->sendfile();*/
	}
	
	function _getDossierSelected(){
		
		$TDossier = array();
		//pre($this,true);exit;
		foreach($this->dossiers_rachetes_m1 as $idDossier => $TData){
			if($this->dossiers_rachetes_m1[$idDossier]['checked'])
				$TDossier[$idDossier] = $idDossier;
		}
		foreach($this->dossiers_rachetes as $idDossier => $TData){
			if($this->dossiers_rachetes[$idDossier]['checked'])
				$TDossier[$idDossier] = $idDossier;
		}
		foreach($this->dossiers_rachetes_p1 as $idDossier => $TData){
			if($this->dossiers_rachetes_p1[$idDossier]['checked'])
				$TDossier[$idDossier] = $idDossier;
		}
		foreach($this->dossiers_rachetes_nr_m1 as $idDossier => $TData){
			if($this->dossiers_rachetes_nr_m1[$idDossier]['checked'])
				$TDossier[$idDossier] = $idDossier;
		}
		foreach($this->dossiers_rachetes_nr as $idDossier => $TData){
			if($this->dossiers_rachetes_nr[$idDossier]['checked'])
				$TDossier[$idDossier] = $idDossier;
		}
		foreach($this->dossiers_rachetes_nr_p1 as $idDossier => $TData){
			if($this->dossiers_rachetes_nr_p1[$idDossier]['checked'])
				$TDossier[$idDossier] = $idDossier;
		}
		
		return $TDossier;
	}
	
	function gen_simulation_pdf(&$ATMdb, &$doliDB) {
		global $conf;
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
		$simu->opt_adjonction = ($simu->opt_adjonction) ? "Oui" : "Non" ;
		/*echo '<pre>';
		print_r($simu);
		echo '</pre>';exit;*/
		
		// Dossiers rachetés dans la simulation
		$TDossier = array();
		$TDossierperso = array();
		
		$ATMdb2 = new TPDOdb; // #478 par contre je ne vois pas pourquoi il faut une connexion distincte :/
		
		//$TSimuDossier = array_merge($this->dossiers_rachetes, $this->dossiers_rachetes_p1,$this->dossiers_rachetes_nr,$this->dossiers_rachetes_nr_p1,$this->dossiers_rachetes_perso);
		$TSimuDossier = $this->_getDossierSelected();
		//pre($TSimuDossier,true);exit;
		foreach($TSimuDossier as $idDossier => $Tdata) {
			$d = new TFin_dossier();
			$d->load($ATMdb, $idDossier);
			
			if($d->nature_financement == 'INTERNE') {
				$f = &$d->financement;
				$type = 'CLIENT';
			} else { 
				$f = &$d->financementLeaser;
				$type = 'LEASER';
			}
			
			//$date_prochaine_echeance = $this[]
			
			$echeance = $d->_get_num_echeance_from_date($this->dossiers[$idDossier]['date_prochaine_echeance']);
			$date_debut_periode_m1 = $d->getDateDebutPeriode($echeance-2,$type);
			$date_fin_periode_m1 = $d->getDateFinPeriode($echeance-2,$type);
			$date_debut_periode = $d->getDateDebutPeriode($echeance-1,$type);
			$date_fin_periode = $d->getDateFinPeriode($echeance-1,$type);
			$date_debut_periode_p1 = $d->getDateDebutPeriode($echeance,$type);
			$date_fin_periode_p1 = $d->getDateFinPeriode($echeance,$type);
			
			/*echo $d->reference.'<br>';
			echo $echeance.'<br>';
			echo $date_debut_periode.'<br>';
			echo $date_fin_periode.'<br>';
			echo $date_debut_periode_p1.'<br>';
			echo $date_fin_periode_p1.'<br>';*/
			
			$datemax_deb = $date_debut_periode;
			$datemax_fin = $date_fin_periode;
			
			//pre($this,true);exit;
			if($d->nature_financement == 'INTERNE') {
				if($this->dossiers_rachetes_m1[$idDossier]['checked']){
					$solde_r = $solde_nr = $this->dossiers_rachetes_m1[$idDossier]['montant'];
					$solde = 'R';
					$datemax_deb = $date_debut_periode_m1;
					$datemax_fin = $date_fin_periode_m1;
				}
				elseif($this->dossiers_rachetes[$idDossier]['checked']){
					$solde_r = $solde_nr = $this->dossiers_rachetes[$idDossier]['montant'];
					$solde = 'R';
				}
				elseif($this->dossiers_rachetes_p1[$idDossier]['checked']){
					$solde_r = $solde_nr = $this->dossiers_rachetes_p1[$idDossier]['montant'];
					$solde = 'R';
					$datemax_deb = $date_debut_periode_p1;
					$datemax_fin = $date_fin_periode_p1;
				}
				
				if($this->dossiers_rachetes_nr_m1[$idDossier]['checked']){
					$solde_r = $solde_nr = $this->dossiers_rachetes_nr_m1[$idDossier]['montant'];
					$solde = 'NR';
					$datemax_deb = $date_debut_periode_m1;
					$datemax_fin = $date_fin_periode_m1;
				}
				elseif($this->dossiers_rachetes_nr[$idDossier]['checked']){
					$solde_r = $solde_nr = $this->dossiers_rachetes_nr[$idDossier]['montant'];
					$solde = 'NR';
				}
				elseif($this->dossiers_rachetes_nr_p1[$idDossier]['checked']){
					$solde_r = $solde_nr = $this->dossiers_rachetes_nr_p1[$idDossier]['montant'];
					$solde = 'NR';
					$datemax_deb = $date_debut_periode_p1;
					$datemax_fin = $date_fin_periode_p1;
				}
			}
			else{
				if($this->dossiers_rachetes_m1[$idDossier]['checked']){
					$solde_r = $this->dossiers_rachetes_m1[$idDossier]['montant'];
					$solde_nr = $this->dossiers_rachetes_nr_m1[$idDossier]['montant'];
					$solde = 'R';
					$datemax_deb = $date_debut_periode_m1;
					$datemax_fin = $date_fin_periode_m1;
				}
				elseif($this->dossiers_rachetes[$idDossier]['checked']){
					$solde_r = $this->dossiers_rachetes[$idDossier]['montant'];
					$solde_nr = $this->dossiers_rachetes_nr[$idDossier]['montant'];
					$solde = 'R';
				}
				elseif($this->dossiers_rachetes_p1[$idDossier]['checked']){
					$solde_r = $this->dossiers_rachetes_p1[$idDossier]['montant'];
					$solde_nr = $this->dossiers_rachetes_nr_p1[$idDossier]['montant'];
					$solde = 'R';
					$datemax_deb = $date_debut_periode_p1;
					$datemax_fin = $date_fin_periode_p1;
				}
				
				if($this->dossiers_rachetes_nr_m1[$idDossier]['checked']){
					$solde_r = $this->dossiers_rachetes_m1[$idDossier]['montant'];
					$solde_nr = $this->dossiers_rachetes_nr_m1[$idDossier]['montant'];
					$solde = 'NR';
					$datemax_deb = $date_debut_periode_m1;
					$datemax_fin = $date_fin_periode_m1;
				}
				elseif($this->dossiers_rachetes_nr[$idDossier]['checked']){
					$solde_r = $this->dossiers_rachetes[$idDossier]['montant'];
					$solde_nr = $this->dossiers_rachetes_nr[$idDossier]['montant'];
					$solde = 'NR';
				}
				elseif($this->dossiers_rachetes_nr_p1[$idDossier]['checked']){
					$solde_r = $this->dossiers_rachetes_p1[$idDossier]['montant'];
					$solde_nr = $this->dossiers_rachetes_nr_p1[$idDossier]['montant'];
					$solde = 'NR';
					$datemax_deb = $date_debut_periode_p1;
					$datemax_fin = $date_fin_periode_p1;
				}
			}

			//echo $datemax_deb." ".$datemax_fin;exit;
			
			/*if(in_array($idDossier, $this->dossiers_rachetes) || in_array($idDossier, $this->dossiers_rachetes_nr)) {
				$solde_r = $d->getSolde($ATMdb2, 'SRNRSAME'); //SRCPRO
				$solde_nr = $d->getSolde($ATMdb2, 'SRNRSAME'); //SNRCPRO
				$soldeperso = '' ;
			}
			elseif(in_array($idDossier, $this->dossiers_rachetes_p1) || in_array($idDossier, $this->dossiers_rachetes_nr_p1)) {
				$solde_r = $d->getSolde($ATMdb2, 'SRNRSAME',$f->duree_passe + 1); //SRCPRO
				$solde_nr = $d->getSolde($ATMdb2, 'SRNRSAME',$f->duree_passe + 1); //SNRCPRO
				$soldeperso = '' ;
			}
			elseif(in_array($idDossier, $this->dossiers_rachetes_perso)) {
				$solde_r = '';
				$solde_nr = '';
				$soldeperso = $d->getSolde($ATMdb2, 'perso');
			}
			else{
				$solde_r = '';
				$solde_nr = '';
				$soldeperso = '' ;
			}*/
			
			//echo $solde_r." ".$solde_nr;
			
			/*if(in_array($idDossier, $this->dossiers_rachetes)) {
				$solde = 'R';
				$datemax = $f->date_prochaine_echeance;
			} elseif(in_array($idDossier, $this->dossiers_rachetes_nr)) {
				$solde = 'NR';
				$datemax = $f->date_prochaine_echeance;
			} elseif(in_array($idDossier, $this->dossiers_rachetes_p1)) {
				$solde = 'R';
				$datemax = strtotime('+ '.$f->getiPeriode().' months', $f->date_prochaine_echeance);
			} elseif(in_array($idDossier, $this->dossiers_rachetes_nr_p1)) {
				$solde = 'NR';
				$datemax = strtotime('+ '.$f->getiPeriode().' months', $f->date_prochaine_echeance);
			} elseif(in_array($idDossier, $this->dossiers_rachetes_perso)) {
				$solde = 'personnalisé';
				$datemax = $d->dateperso;
			} else {
				$solde = '';
			}*/
			
			/*if($d->nature_financement == 'INTERNE') {
				$f = &$d->financement;
				if($d->type_contrat == $this->fk_type_contrat) {
					if(in_array($idDossier, $this->dossiers_rachetes)) {
						$solde = $d->getSolde($ATMdb2, 'SRCPRO');
					} else {
						$solde = $d->getSolde($ATMdb2, 'SNRCPRO');
					}
				} else {
					if(in_array($idDossier, $this->dossiers_rachetes)) {
						$solde = $d->getSolde($ATMdb2, 'SRCPRO', $fin->duree_passe + 1);
					} else {
						$solde = $d->getSolde($ATMdb2, 'SNRCPRO', $fin->duree_passe + 1);
					}
				}
			} else {
				$f = &$d->financementLeaser;
				if($d->type_contrat == $this->fk_type_contrat) {
					if(in_array($idDossier, $this->dossiers_rachetes)) {
						$solde = $d->getSolde($ATMdb2, 'SRBANK');
					} else {
						$solde = $d->getSolde($ATMdb2, 'SNRBANK');
					}
				} else {
					if(in_array($idDossier, $this->dossiers_rachetes)) {
						$solde = $d->getSolde($ATMdb2, 'SRBANK', $fin->duree_passe + 1);
					} else {
						$solde = $d->getSolde($ATMdb2, 'SNRBANK', $fin->duree_passe + 1);
					}
				}
			}*/
			if($d->nature_financement == 'INTERNE') {
				$f->reference .= ' / '.$d->financementLeaser->reference;
			}
			
			$leaser = new Societe($doliDB);
			$leaser->fetch($d->financementLeaser->fk_soc);
			
			if($solde == 'R' || $solde == 'NR'){
				$TDossier[] = array(
					'reference' => $f->reference
					,'leaser' => $leaser->name
					,'type_contrat' => $d->type_contrat
					,'solde' => $solde
					,'solde_r' => $solde_r
					,'solde_nr' => $solde_nr
					,'datemax_debut' => $datemax_deb
					,'datemax_fin' => $datemax_fin
				);
			}
			/*else{
				$TDossierperso[] = array(
					'referenceperso' => $f->reference
					,'leaser' => $leaser->name
					,'type_contrat' => $d->type_contrat
					,'solde' => $solde
					,'soldeperso' => $soldeperso
					,'datemax' => $datemax
				);
			}*/
		}
		
		$this->hasdossier = count($TDossier) + count($TDossierperso);
		
		//pre($TDossier,true); exit;
		// Création du répertoire
		$fileName = dol_sanitizeFileName($this->getRef()).'.odt';
		$filePath = $conf->financement->dir_output . '/' . dol_sanitizeFileName($this->getRef());
		dol_mkdir($filePath);
		
		if($this->fk_leaser){
			$leaser = new Societe($doliDB);
			$leaser->fetch($this->fk_leaser);
			$this->leaser = $leaser;
		}
		
		$simu2 = $simu;
		//exit(htmlentities($simu->type_contrat));
		$simu2->type_contrat = html_entity_decode($simu2->type_contrat,ENT_QUOTES,'ISO-8859-1');
		//$simu2->commentaire = utf8_decode($simu2->commentaire);
		//$simu2->numero_accord = utf8_decode($simu2->numero_accord);
		/*echo '<pre>';
		print_r($TDossier);
		echo '</pre>';exit;*/
		// Génération en ODT
		
		if (!empty($this->thirdparty_address)) $this->societe->address = $this->thirdparty_address;
		if (!empty($this->thirdparty_zip)) $this->societe->zip = $this->thirdparty_zip;
		if (!empty($this->thirdparty_town)) $this->societe->town = $this->thirdparty_town;
		
		if (!empty($this->thirdparty_code_client)) $this->societe->code_client = $this->thirdparty_code_client;
		if (!empty($this->thirdparty_idprof2_siret)) $this->societe->idprof2 = $this->thirdparty_idprof2_siret;
		if (!empty($this->thirdparty_idprof3_naf)) $this->societe->idprof3 = $this->thirdparty_idprof3_naf;
		
		if ($simu2->opt_periodicite == 'MOIS') $simu2->coeff_by_periodicite = $simu2->coeff / 3;
		elseif ($simu2->opt_periodicite == 'SEMESTRE') $simu2->coeff_by_periodicite = $simu2->coeff * 2;
		elseif ($simu2->opt_periodicite == 'ANNEE') $simu2->coeff_by_periodicite = $simu2->coeff * 4;
		else $simu2->coeff_by_periodicite = $simu2->coeff; // TRIMESTRE
		
		$TBS = new TTemplateTBS;
		$file = $TBS->render('./tpl/doc/simulation.odt'
			,array(
				'dossier'=>$TDossier
				//,'dossierperso'=>$TDossierperso
			)
			,array(
				'simulation'=>$simu2
				,'client'=>$this->societe
				,'leaser'=>array('nom'=>(($this->leaser->nom != '') ? $this->leaser->nom : ''))
				,'autre'=>array('terme'=>($this->TTerme[$simu2->opt_terme]) ? $this->TTerme[$simu2->opt_terme] : ''
								,'type'=>($solde == 'R' || $solde == 'NR') ? 1 : 0)
			)
			,array()
			,array(
				'outFile' => $filePath.'/'.$fileName
				,'charset'=>OPENTBS_ALREADY_UTF8
			)
		);
		
		// Nécessaire sinon n'affiche pas les caractères accentués
		//$simu2->commentaire = utf8_encode($simu2->commentaire);
		
		$simu->opt_adjonction = $back_opt_adjonction;
		$simu->opt_calage = $back_opt_calage;
		
		// Transformation en PDF
		$cmd = 'export HOME=/tmp'."\n";
		$cmd.= 'libreoffice --invisible --norestore --headless --convert-to pdf --outdir '.$filePath.' '.$filePath.'/'.$fileName;
		ob_start();
		system($cmd);
		$res = ob_get_clean();
	}

	function _calcul(&$ATMdb, $mode='calcul', $options=array()) {
		global $mesg, $error, $langs, $db;
		
		if(empty($options)) {
			foreach($_POST as $k => $v) {
				if(substr($k, 0, 4) == 'opt_') {
					$options[$k] = $v;
				}
			}
		}
		
		$calcul = $this->calcul_financement($ATMdb, FIN_LEASER_DEFAULT, $options); // Calcul du financement
			
		if(!$calcul) { // Si calcul non correct
			$this->montant_total_finance = 0;
			$mesg = $langs->trans($this->error);
			$error = true;
		} else if($this->accord_confirme == 0) { // Sinon, vérification accord à partir du calcul
			$this->demande_accord();
			if($this->accord == 'OK') {
				$this->date_accord = time();
				$this->date_validite = strtotime('+ 3 months');
			}
			if($mode == 'save' && ($this->accord == 'OK' || $this->accord == 'KO')) { // Si le vendeur enregistre sa simulation est OK automatique, envoi mail
				$this->send_mail_vendeur(true);
			}
		}
		
	}

}


class TSimulationSuivi extends TObjetStd {
	function __construct() {
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'fin_simulation_suivi');
		parent::add_champs('entity,fk_simulation,fk_leaser,fk_user_author,statut_demande','type=entier;');
		parent::add_champs('coeff_leaser','type=float;');
		parent::add_champs('date_demande,date_accord,date_selection','type=date;');
		parent::add_champs('numero_accord_leaser,statut','type=chaine;');
		parent::add_champs('commentaire','type=text;');
		parent::start();
		parent::_init_vars();
		
		//Reset des dates car par défaut = time() à l'instanciation de la classe
		$this->date_demande = $this->date_accord = $this->date_selection = '';

		$this->TStatut=array(
			'OK'=>$langs->trans('Accord')
			,'WAIT'=>$langs->trans('Etude')
			,'KO'=>$langs->trans('Refus')
			,'SS'=>$langs->trans('SansSuite')
			,'MEL'=>$langs->trans('Mise En Loyé')
		);
		
		$this->TLeaserAuto=array(
			'3382' => 'BNP PARIBAS LEASE GROUP'
			,'19553' => 'BNP PARIBAS LEASE GROUP (ADOSSE)'
			,'20113' => 'BNP PARIBAS LEASE GROUP (MANDATE)'
			,'7411' => 'GE CAPITAL EQUIPEMENT FINANCE'
			,'21382' => 'GE CAPITAL EQUIPEMENT FINANCE (MANDATEE)'
		);
		
		$this->simulation = new TSimulation;
	}
	
	//Chargement du suivi simulation
	function load(&$PDOdb,$id){
		global $db;
		
		$res = parent::load($PDOdb, $id);
		$this->leaser = new Societe($db);
		$this->leaser->fetch($this->fk_leaser);
		
		if(!empty($this->fk_simulation)){
			$simulation = new TSimulation;
			$simulation->load($PDOdb, $db, $this->fk_simulation, false);
			$this->simulation = $simulation;
		}
		
		$this->user = new User($db);
		$this->user->fetch($this->fk_user_author);
		
		return $res;
	}
	
	//Initialisation de l'objet avec les infos de base
	function init(&$PDOdb,&$leaser,$fk_simulation){
		global $db, $conf, $user;
		
		$this->entity = $conf->entity;
		$this->fk_simulation = $fk_simulation;
		$this->fk_leaser = $leaser->id;
		$this->fk_user_author = $user->id;
	}
	
	//Retourne les actions possible pour ce suivi suivant les règles de gestion
	function getAction(&$simulation, $just_save=false){
		global $conf,$user;
		
		$actions = '';
		$ancre = '#suivi_leaser';
		
		if($simulation->accord != "OK"){
			//Demander
			if($this->statut_demande != 1){// && $this->date_demande < 0){
				if(!$just_save)
					$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander'.$ancre.'" title="Demande transmise au leaser"><img src="'.dol_buildpath('/financement/img/demander.png',1).'" /></a>&nbsp;';
			}
			else{
				//Sélectionner
				if($this->statut === 'OK'){
					if($just_save) {
						//Enregistrer
						$actions .= '<input type="image" src="'.dol_buildpath('/financement/img/save.png',1).'" value="submit" title="Enregistrer">&nbsp;';
					} else {
						//Reset
						$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander'.$ancre.'" title="Annuler"><img src="'.dol_buildpath('/financement/img/WAIT.png',1).'" /></a>&nbsp;';
						$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=selectionner'.$ancre.'" title="Sélectionner ce leaser"><img src="'.dol_buildpath('/financement/img/super_ok.png',1).'" /></a>&nbsp;';
					}
				}
				else{
					if($this->statut !== 'KO'){
						/*if($this->TLeaserAuto[$this->fk_leaser]){	
							//Envoyer
							$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander" title="Envoyer la demande"><img src="'.dol_buildpath('/financement/img/envoyer.png',1).'" /></a>&nbsp;';
						}*/
						if($just_save) {
							//Enregistrer
							$actions .= '<input type="image" src="'.dol_buildpath('/financement/img/save.png',1).'" value="submit" title="Enregistrer">&nbsp;';

						}
						else {
							//Accepter
							$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=accepter'.$ancre.'" title="Demande acceptée"><img src="'.dol_buildpath('/financement/img/OK.png',1).'" /></a>&nbsp;';
							//Refuser
							$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=refuser'.$ancre.'" title="Demande refusée"><img src="'.dol_buildpath('/financement/img/KO.png',1).'" /></a>&nbsp;';
						}
					
					} elseif($simulation->accord != "KO") {
						if(!$just_save) {
							//Reset
							$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=demander'.$ancre.'" title="Annuler"><img src="'.dol_buildpath('/financement/img/WAIT.png',1).'" /></a>&nbsp;';
						}
					}
				}
			}
		} elseif($simulation->accord == "OK" && !empty($this->date_selection)) {
			if(!$just_save) {
				//Reset
				$actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=accepter'.$ancre.'" title="Annuler"><img src="'.dol_buildpath('/financement/img/OK.png',1).'" /></a>&nbsp;';
			}
		}
		
		if (!$just_save && !empty($conf->global->FINANCEMENT_SHOW_RECETTE_BUTTON) && !empty($user->admin) && $user->entity == 0) $actions .= '<a href="?id='.$simulation->getId().'&id_suivi='.$this->getId().'&action=trywebservice'.$ancre.'" title="Annuler">'.img_picto('Webservice', 'call').'</a>&nbsp;';
		
		return $actions;
	}
	
	//Exécute une action et met en oeuvre les règles de gestion en conséquence
	function doAction(&$PDOdb,&$simulation,$action){
		
		//if($simulation->accord != "OK"){
		
			switch ($action) {
				case 'demander':
					//if(empty($this->statut)){ //Possibilité d'effectuer le demande une seule fois uniquement
						$this->doActionDemander($PDOdb,$simulation);
					//}
					break;
				case 'envoyer':
					$this->doActionEnvoyer($PDOdb,$simulation);
					break;
				case 'accepter':
					$this->doActionAccepter($PDOdb,$simulation);
					break;
				case 'refuser':
					$this->doActionRefuser($PDOdb,$simulation);
					break;
				case 'selectionner':
					$this->doActionSelectionner($PDOdb,$simulation);
					break;
				default:
					
					break;
			}
		//}
	}
	
	//Effectuer l'action de faire la demande de financement au leaser
	function doActionDemander(&$PDOdb,&$simulation){
		global $db;
		
	    // Leaser ACECOM = demande BNP mandaté et BNP cession + Lixxbail mandaté et Lixxbail cession
		if($this->fk_leaser == 18305){
			// 20113 = BNP Mandatée // 3382 = BNP Cession (Location simple) // 19483 = Lixxbail Mandatée // 6065 = Lixxbail Cession (Location simple)
			$sql = "SELECT rowid 
					FROM ".MAIN_DB_PREFIX."fin_simulation_suivi 
					WHERE (fk_leaser = 3382 
						OR fk_leaser = 6065)
						AND fk_simulation = ".$this->fk_simulation;
			$TIds = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

			foreach($TIds as $idSimulationSuivi){
				$simulation_suivi = new TSimulationSuivi;
				$simulation_suivi->load($PDOdb, $idSimulationSuivi);
				$simulation_suivi->doAction($PDOdb, $simulation, 'demander');
				$simulation_suivi->save($PDOdb);
			}
		}
		
		//Si leaser auto alors on envoye la demande par XML
		/*if(in_array($this->fk_leaser, array_keys($this->TLeaserAuto))){
			$this->_sendDemandeAuto($PDOdb);
		}*/
		
		$this->statut_demande = 1;
		$this->date_demande = time();
		$this->statut = 'WAIT';
		$this->date_selection = 0;
		$this->save($PDOdb);
	}
	
	//Effectuer l'action d'envoyer au leaser la demande de financement
	function doActionEnvoyer(&$PDOdb,&$simulation){
		global $db;
		
		$simulation->accord = 'WAIT';
		$simulation->save($PDOdb, $db);
		
		$this->statut = 'WAIT';
		$this->save($PDOdb);
	}
	
	//Effectue l'action de passer au statut accepter la demande de financement leaser
	function doActionAccepter(&$PDOdb,&$simulation){
		global $db;
		
		$simulation->accord = 'WAIT';
		$simulation->coeff_final = '';
		$simulation->fk_leaser = 0;
		$simulation->numero_accord = '';
		$simulation->save($PDOdb, $db);
		
		$this->statut = 'OK';
		$this->date_selection = 0;
		$this->save($PDOdb);
	}
	
	//Effectue l'action de passer au statut refusé la demande de financement leaser
	function doActionRefuser(&$PDOdb,&$simulation){
		global $db;

		/*$simulation->accord = 'KO';
		$simulation->save($PDOdb, $db);*/
		
		$this->statut = 'KO';
		$this->save($PDOdb);
	}
	
	//Effectue l'action de choisir définitivement un leaser pour financer la simulation
	function doActionSelectionner(&$PDOdb,&$simulation){
		global $db;
		
		$TTypeFinancement = array(3=>'ADOSSEE', 4=>'MANDATEE', 18=>'PURE', 19=>'FINANCIERE'); // En cléf : id categorie, en valeur, type financement associé
		$TCateg_tiers = array();
		
		if(!empty($this->fk_leaser)) {
			// Récupération des catégories du leaser. fk_categorie : 5 pour "Cession", 3 pour "Adossee", 18 pour Loc Pure, 4 pour Mandatee, 19 pour Financière
			$sql = 'SELECT fk_categorie FROM '.MAIN_DB_PREFIX.'categorie_fournisseur WHERE fk_categorie IN (3, 4, 5, 18, 19) and fk_societe = '.$this->fk_leaser;
			$resql = $db->query($sql);
			while($res = $db->fetch_object($resql)) {
				$TCateg_tiers[] = (int)$res->fk_categorie;
			}
		}
		
		if($simulation->type_financement != "ADOSSEE" && $simulation->type_financement != "MANDATEE" && in_array(5, $TCateg_tiers)){
			$simulation->coeff_final = $this->coeff_leaser;
			$simulation->montant = 0;
			$options = array(
							'opt_periodicite'=>$simulation->opt_periodicite
							,'opt_mode_reglement'=>$simulation->opt_mode_reglement
							,'opt_terme'=>$simulation->opt_terme
							,'opt_calage'=>$simulation->opt_calage
							,'opt_no_case_to_settle'=>$simulation->opt_no_case_to_settle
						);
			
			$simulation->_calcul($PDOdb, 'calcul', $options);
		}
		// Une fois le test précédent effectué, on ne garde dans le tableau que les id des groupes qui nous intéressent (3 4 ou 18).
		if(!empty($TCateg_tiers)) {
			$TTemp = $TCateg_tiers;
			$TCateg_tiers = array();
			foreach ($TTemp as $id_categ) {
				if($id_categ == 3 || $id_categ == 4 || $id_categ == 18 || $id_categ == 19) $TCateg_tiers[] = $id_categ;
			}
		} 
		
		$simulation->accord = 'OK';
		$simulation->numero_accord = $this->numero_accord_leaser;
		$simulation->fk_leaser = $this->fk_leaser;
		if(!empty($TTypeFinancement[$TCateg_tiers[0]])) $simulation->type_financement = $TTypeFinancement[$TCateg_tiers[0]];
		$simulation->save($PDOdb, $db);

		$simulation->send_mail_vendeur();

		$this->date_selection = time();

		$this->save($PDOdb);
	}
	
	function save(&$PDOdb){
		global $db;
		
		$res = parent::save($PDOdb);
		
		if(!empty($this->fk_simulation)){
			$simulation = new TSimulation;
			$simulation->load($PDOdb, $db, $this->fk_simulation,false);
			$this->simulation = $simulation;
			
			//Si Leaser possiblité demande auto alors on effectue directement l'action
			/*if(in_array($this->fk_leaser, array_keys($this->TLeaserAuto))){
				$this->doAction($PDOdb, $simulation, "demander");
			}*/
		}
	}
	
	function _sendDemandeAuto(&$PDOdb){
		global $db;
		
		$this->simulation->societe = new Societe($db);
		$this->simulation->societe->fetch($this->simulation->fk_soc);
		
		switch ($this->fk_leaser) {
			//BNP PARIBAS LEASE GROUP
			case '3382':
			case '19553':
			case '20113':
				$this->_createDemandeBNP($PDOdb);
				break;
			//GE CAPITAL EQUIPEMENT FINANCE
			case '7411':
			case '21382':
				$this->_createDemandeGE($PDOdb);
				break;
			default:
				
				break;
		}
	}
	
	function _createDemandeGE(&$PDOdb){
		
		if(GE_TEST){
			$soapWSDL = dol_buildpath('/financement/files/dealws.wsdl',2);
		}
		else{
			$soapWSDL = GE_WSDL_URL;
		}
		
		$soap = new SoapClient($soapWSDL,array('trace'=>TRUE));
		
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
		$soap_var_header = new SoapVar( $header_part, XSD_ANYXML, null, null, null );
		$soap_header = new SoapHeader($soapWSDL, 'wsse', $soap_var_header );
		$soap->__setSoapHeaders($soap_header);
		
		$TtransmettreDemandeFinancementRequest['CreateDemFinRequest'] = $this->_getGEDataTabForDemande($PDOdb);

		//pre($TtransmettreDemandeFinancementRequest,true);
		
		try{
			$reponseDemandeFinancement = $soap->__call('CreateDemFin',$TtransmettreDemandeFinancementRequest);
		}
		catch(SoapFault $reponseDemandeFinancement) {
			pre($reponseDemandeFinancement,true);
			//exit;
		}
		pre($reponseDemandeFinancement,true);exit;
		/*pre($soap->__getLastRequest());
		pre($soap->__getLastResponse());exit;*/
		
		$this->traiteGEReponseDemandeFinancement($PDOdb,$reponseDemandeFinancement);

	}

	function traiteErrorsDemandeGE(&$reponseDemandeFinancement){
		
		$this->errorLabel = "ERREUR SCORING GE : <br>";
		switch ($reponseDemandeFinancement->ReponseDemFin->ResponseDemFinShort->Rep_Statut_B2B->B2B_CDRET) {
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
	
	function traiteGEReponseDemandeFinancement(&$PDOdb,&$reponseDemandeFinancement){
		
		if($reponseDemandeFinancement->ReponseDemFin->ResponseDemFinShort->Rep_Statut_B2B->B2B_CDRET == '0'){
			$this->numero_accord_leaser = $reponseDemandeFinancement->numeroDemandeProvisoire;
			$this->save($PDOdb);
		}
		else{
			$this->traiteErrorsDemandeGE($reponseDemandeFinancement);
		}
	}

	function _getGEDataTabForDemande(&$PDOdb){
		
		$TData = array();

		$TAPP_Infos_B2B = array(
			'B2B_CLIENT' => 'CPRO0001' //communication id by GE
			,'B2B_TIMESTAMP' => date('c')
		);

		$TData['APP_Infos_B2B'] = $TAPP_Infos_B2B;

		$TAPP_CREA_Demande = array(
			'B2B_ECTR_FLG' => 0
			,'B2B_NATURE_DEMANDE' => 'S' //TODO a vérifier => 'S pour standard, 'P' ou 'A'
			,'B2B_TYPE_DEMANDE' => 'E' //TODO spcéfié inactif sur le doc, a voir ce qu'il faut en faire en définitif
			,'B2B_REF_EXT' => $this->simulation->reference
		);

		$TData['APP_CREA_Demande'] = $TAPP_CREA_Demande;
		
		
		
		$TInfos_Apporteur = array(
			//voir lequel on met => TODO
			//C'PRO VALENCE-C PRO - CESSION DE CONTRATS : 121326001 / 0251
			//C'PRO VALENCE-LOCATION MANDATEE – CPRO : 121326001 / 0240
			//CPRO TELECOM-C PRO - CESSION DE CONTRATS : 672730000 /0251
			//C PRO INFORMATIQUE-C PRO - CESSION DE CONTRATS : 470943000 / 0251
			'B2B_APPORTEUR_ID' => '121326001'
			,'B2B_PROT_ID' => '0251'
			//voir lequel on met => TODO
			//DFERRAZZI / Test321test / A40967000 
			//VCORBEAUX / Test321test / 959725000
			,'B2B_VENDEUR_ID' => 'A40967000'
		);

		$TData['Infos_Apporteur'] = $TInfos_Apporteur;

		$TInfos_Client = array(
			'B2B_SIREN' => ($this->simulation->societe->idprof1) ? $this->simulation->societe->idprof1 : $this->simulation->societe->array_options['options_other_siren']
		);

		$TData['Infos_Client'] = $TInfos_Client;
		
		if($this->simulation->opt_mode_reglement == 'PRE') $mode_reglement = 'AP';
		else $mode_reglement = $this->simulation->opt_mode_reglement;
		
		if($this->simulation->opt_terme == 0) $terme = 2;
		else $terme = (int)$this->simulation->opt_terme;
		
		//TODO Transmis par GE => Voir lequel on met
		//Crédit Bail (Hors protocole Location Mandatée): 975
		//LOA(Hors protocole Location Mandatée) : 979
		//Location Financière(Hors protocole Location Mandatée) : 983
		//Location Evolutive(Hors protocole Location Mandatée) : 991
		//Location Mandatée (pour Protocole Location Mandatée uniquement) : 4926
		$minervaAFPid = '983';
		if($this->simulation->type_financement == 'MANDATEE') $minervaAFPid = '4926';

		if($this->simulation->opt_periodicite=='TRIMESTRE')$freq=3;
		else if($this->simulation->opt_periodicite=='SEMESTRE')$freq=6;
		else if($this->simulation->opt_periodicite=='ANNEE')$freq=12;
		else $freq = 1;
		
		$TInfos_Financieres = array(
			'B2B_DUREE' => number_format($this->simulation->duree * $freq,2,'.','')
			,'B2B_FREQ' => number_format($freq,2,'.','')
			,'B2B_MODPAIE' => $mode_reglement
			,'B2B_MT_DEMANDE' => number_format($this->simulation->montant,2,'.','')
			,'B2B_NB_ECH' => number_format($this->simulation->duree,2,'.','')
			,'B2B_MINERVAFPID' => $minervaAFPid
			,'B2B_TERME' => $terme
		);
		
		$TData['Infos_Financieres'] = $TInfos_Financieres;
		
		$TMarqueMatGE=array(
			'CANON' => 'CAN'
			,'DELL' => 'DEL'
			,'KONICA MINOLTA' => 'KM'
			,'KYOCERA' => 'KYO'
			,'LEXMARK' => 'LEX'
			,'HEWLETT-PACKARD' => 'HP'
			,'OCE' => 'OCE'
			,'OKI' => 'OKI'
			,'SAMSUNG' => 'SAM'
			,'TOSHIBA' => 'TOS'
		);
		
		$TTypeMatGE=array(
			'PHOTOCOPIEUR' => 'PHOTOCO'
			,'PLIEUSE' => 'PLIEUSE'
			,'SERVEUR' => 'PHOTOCO'
			,'TRACEUR DE PLAN' => 'TRACPLA'
			,'ACCESSOIRES' => 'ACCESS'
			,'CONFIGURATION INFORMATIQUE' => 'CONFINF'
			,'IMPRIMANTES' => 'IMPRIM'
			,'IMPRIMANTE + DE 20P/MN SF LASER' => 'IMPRIMA'
		);
		
		//TODO => comment on définit quelle valeur prendre?
		//$marqueMat = $TMarqueMatGE[$this->simulation->type_materiel];
		$marqueMat = ($TMarqueMatGE[$this->simulation->marque_materiel]) ? $TMarqueMatGE[$this->simulation->marque_materiel] : 'CAN';
		//$typeMat = $TTypeMatGE[$this->simulation->type_materiel];
		$typeMat = ($TTypeMatGE[$this->simulation->type_materiel]) ? $TTypeMatGE[$this->simulation->type_materiel] : 'PHOTOCO';
		
		$TInfos_Materiel = array(
			'B2B_MARQMAT' => $marqueMat
			,'B2B_MT_UNIT' => number_format($this->simulation->montant,2,'.','') //TODO je n'ai pas cette info dans LeaserBoard :/
			,'B2B_QTE' => number_format(1,2,'.','') //TODO vérifier au prêt de Damien
			,'B2B_TYPMAT' => $typeMat
			,'B2B_ETAT' => 'N' //TODO vérifier au prêt de Damien => N = neuf, O = occasion
		);
		
		$TData['Infos_Materiel'] = $TInfos_Materiel;

		$TAPP_Reponse_B2B = array(
			'B2B_CLIENT_ASYNC' => '' //TODO adresse d'appel auto pour MAJ statut simulation => attend un WSDL :/
			,'B2B_INF_EXT' => $this->simulation->reference
			,'B2B_MODE' => 'A'
		);
		
		$TData['APP_Reponse_B2B'] = $TAPP_Reponse_B2B;
		
		return $TData;
	}
	
	function _createDemandeBNP(&$PDOdb){
		
		if(BNP_TEST){
			$soapWSDL = dol_buildpath('/financement/files/demandeFinancement.wsdl',2);
		}
		else{
			$soapWSDL = BNP_WSDL_URL;
		}

		try{
			$soap = new SoapClient($soapWSDL,array(
									'local_cert'=>"/usr/share/ca-certificates/extra/CPRO-BPLS-recette.crt"
									,'trace'=>1
									,'stream_context' => stream_context_create(array(
										    'ssl' => array(
										        'verify_peer' => false,
										        'allow_self_signed' => true
										    )
										))						
			));

		}
		catch(SoapFault $e) {
			var_dump($e);
			exit;
		}
		//pre($soap->__getFunctions(),true);exit;
//		echo "1<br>";
		$TtransmettreDemandeFinancementRequest['transmettreDemandeFinancementRequest'] = $this->_getBNPDataTabForDemande($PDOdb);
		//pre(preg_match('/[\S\t ]*/', 'ZI 8 RUE JEAN CHARCOT BP 279'),true);
		//pre($TtransmettreDemandeFinancementRequest,true);exit;
		try{
//		echo "2<br>";

			$reponseDemandeFinancement = $soap->__call('transmettreDemandeFinancement',$TtransmettreDemandeFinancementRequest);
			
			//pre($reponseDemandeFinancement,true);exit;
		}
		catch(SoapFault $reponseDemandeFinancement) {
			//echo '<pre>';
			//var_dump($reponseDemandeFinancement->detail);exit;
			$this->errorLabel = $this->traiteErrorsDemandeBNP($reponseDemandeFinancement->detail);
			return 0;
		}

		$this->traiteBNPReponseDemandeFinancement($PDOdb,$reponseDemandeFinancement);
	}

	function _consulterDemandeBNP($num_accord_leaser){
		
		if(BNP_TEST){
			$soapWSDL = dol_buildpath('/financement/files/demandeFinancement.wsdl',2);
		}
		else{
			$soapWSDL = BNP_WSDL_URL;
		}

		try{
			$soap = new SoapClient($soapWSDL,array(
									'local_cert'=>"/usr/share/ca-certificates/extra/CPRO-BPLS-recette.crt"
									,'trace'=>1
									,'stream_context' => stream_context_create(array(
										    'ssl' => array(
										        'verify_peer' => false,
										        'allow_self_signed' => true
										    )
										))						
			));

		}
		catch(SoapFault $e) {
			var_dump($e);
			exit;
		}
		
		$TconsulterSuivisDemandesRequest['consulterSuivisDemandesRequest'] = $this->_getBNPDataTabForConsultation($num_accord_leaser);
		
		//pre($TconsulterSuivisDemandesRequest,true);exit;
		try{
			$TreponseSuivisDemandes = $soap->__call('consulterSuivisDemandes',$TconsulterSuivisDemandesRequest);
			return $TreponseSuivisDemandes;
		}
		catch(SoapFault $TreponseSuivisDemandes) {
			//echo '<pre>';
			//var_dump($reponseDemandeFinancement->detail);exit;
			$this->errorLabel = $this->traiteErrorsDemandeBNP($TreponseSuivisDemandes->detail);
			return 0;
		}

		$this->traiteBNPReponseSuivisDemande($TreponseSuivisDemandes);
	}
	
	function traiteBNPReponseDemandeFinancement(&$PDOdb,&$reponseDemandeFinancement){
//		pre($reponseDemandeFinancemnent,true);exit;
		$this->numero_accord_leaser = $reponseDemandeFinancement->numeroDemandeProvisoire;
		$this->save($PDOdb);
	}
	
	function traiteErrorsDemandeBNP($TObjError){
		
		//Erreur sur les données transmisent
		$errorLabel = '';
		if($TObjError->retourErreur->erreur){
			
			if(count($TObjError->retourErreur->erreur)){
				$errorLabel = 'ERREUR SCORING BNP : <br>';
				if(is_array($TObjError->retourErreur->erreur)){
					foreach($TObjError->retourErreur->erreur as $ObjError){
						$errorLabel .= $ObjError->message.'<br>';
					}
				}
				else{
					$errorLabel .= $TObjError->retourErreur->erreur->message;
				}
			}
		}
		else{ //Erreur sur le formalisme envoyé
			if(count($TObjError->ValidationError)){
				$errorLabel = 'ERREUR FORMAT SCORING BNP : <br>';
				if(is_array($TObjError->ValidationError)){
					foreach($TObjError->ValidationError as $error){
						$errorLabel .= $error.'<br>';
					}
				}
				else{
					$errorLabel .= $TObjError->ValidationError;
				}
			}
		}
		
		return $errorLabel;
	}
	
	function traiteBNPReponseSuivisDemande(&$PDOdb,&$TreponseSuivisDemandes){
		
		//Statut spécifique retourné par BNP
		$TCodeStatut = array(
			'E1' => 'OK'
			,'E2' => 'KO'
			,'E3' => 'WAIT'
			,'E4' => 'SS'
			,'E5' => 'MEL'
		);
		
		foreach($TreponseSuivisDemandes->consulterSuivisDemandesResponse as $rapportSuivi){
			if($rapportSuivi->suiviDemande->numeroDemandeProvisoire == $this->numero_accord_leaser){
				$this->statut = $TCodeStatut[$apportSuivi->suiviDemande->etat->codeStatutDemande];
				$this->save($PDOdb);
			}
		}
	}

	function _getBNPDataTabForDemande(&$PDOdb){
		
		$TData = array();
		
		//Tableau Prescripteur
		$TPrescripteur = array(
			'prescripteurId' => BNP_PRESCRIPTEUR_ID //en attente de la communication par BNP
		);

		$TData['prescripteur'] = $TPrescripteur;
		$TData['numeroDemandePartenaire'] = $this->simulation->reference;
		//$TData['numeroDemandeProvisoire'] = '';
		$TData['codeFamilleMateriel'] = 'H'; //H = Bureautique OU T = Informatique, BUREAUTIQUE par défaut car score uniquement pour du bureautique
		
		//Tableau Client
		$TClient = $this->_getBNPDataTabClient($PDOdb);
		$TData['client'] = $TClient;
		
		//Tableau Matériel (Equipement)
		$TMateriel = $this->_getBNPDataTabMateriel();
		$TData['materiel'] = $TMateriel;
		
		//Tableau Financement
		$TFinancement = $this->_getBNPDataTabFinancement($TData);
		$TData['financement'] = $TFinancement;
		
		/*$TPrestation = array(
			'prestation' => array(
				'codeTypePrestation' => ''
				,'montantPrestation' => ''
			)
		);
		$TData['Prestations'] = $TPrestation;*/

		//$TData['commentairesPartenaire'] = '';
		
		return $TData;
	}

	function _getBNPDataTabClient(&$PDOdb){
		global $db;

		$typeClient = $this->simulation->getLabelCategorieClient();
		if($typeClient == "administration") $codeTypeClient = 3;
		elseif($typeClient == "entreprise") $codeTypeClient = 4;
		else $codeTypeClient = 0; //Général
		
		$this->simulation->societe->fetch_optionals($this->simulation->societe->id);
		
		$arraySearch = array(
			'  ',
			'.',
			"'",
		);
		$arrayToReplace = array(
			' ',
			'',
			'',
		);
		
		$TClient = array(
			'idNationnalEntreprise' => $this->simulation->societe->idprof2//($this->simulation->societe->idprof1) ? $this->simulation->societe->idprof1 : $this->simulation->societe->array_options['options_other_siren']
			,'codeTypeClient' => $codeTypeClient
			,'codeFormeJuridique' => '5499' //TODO
			,'raisonSociale' => str_replace($arraySearch, $arrayToReplace, $this->simulation->societe->name)
			//,'specificiteClientPays' => array(
				//'specificiteClientFrance' => array(
					//'dirigeant' => array(
						//'codeCivilite' => ''
						//,'nom' => ''
						//,'prenom' => ''
						//,'dateNaissance' => ''
					//)
				//)
			//)
			,'adresse' => array(
				'adresse' => 'A'//substr(str_replace($arraySearch,$arrayToReplace,preg_replace("/\n|\ -\ |[\,\ ]{1}/", ' ', $this->simulation->societe->address)),0,31)
				//,'adresseComplement' => ''
				,'codePostal' => str_replace($arraySearch, $arrayToReplace, $this->simulation->societe->zip)
				,'ville' => str_replace($arraySearch, $arrayToReplace, $this->simulation->societe->town)
			)
		);
		
		return $TClient;
	}

	function _getBNPDataTabMateriel(){
		
		$TCodeMarque = array(
			'CANON' => '335'
			,'DELL' => '344'
			,'KONICA MINOLTA' => '571'
			,'KYOCERA' => '347'
			,'LEXMARK' => '341'
			,'HEWLETT-PACKARD' => '321'
			,'OCE' => '336'
			,'OKI' => '930'
			,'SAMSUNG' => 'F80'
			,'TOSHIBA' => '331'
		);
		
		$TMateriel = array(
			'codeMateriel' => '300121' //Photocopieur
			,'codeEtatMateriel' => 'N'
			,'prixDeVente' => $this->simulation->montant
			//,'prixTarif' => ''
			//,'anneeFabrication' => ''
			,'codeMarque' => ($TCodeMarque[$this->simulation->marque_materiel]) ? $TCodeMarque[$this->simulation->marque_materiel] : '335' //909 = Divers informatique TODO
			//,'type' => ''
			//,'modele' => ''
			//,'dateDeMiseEnCirculation' => ''
			//,'nombreHeuresUtilisation' => ''
			//,'kilometrage' => ''
		);
		
		return $TMateriel;
	}

	function _getBNPDataTabFinancement(&$TData){
		global $db;
		$codeCommercial = '02'; //02 par défaut; 23 = Top Full; 2Q = Secteur Public
		$codeFinancier = $codeTypeCalcul = '';
		
		$cat = new Categorie($db);
		$TCats = $cat->containing($this->fk_leaser, 1);
		foreach($TCats as $categorie){
			if(strtoupper($categorie->label) == 'CESSION'){
				$codeFinancier = '021';
				$codeTypeCalcul = 'L';
				if($this->simulation->getLabelCategorieClient() == 'administration'){
					$codeCommercial = '2Q';
				}
				elseif($this->simulation->fk_type_contrat == 'FORFAITGLOBAL'){
					$codeCommercial = '23';
				}
			}
			elseif(strtoupper($categorie->label) == 'MANDATEE'){
				$codeFinancier = '024';
				$codeTypeCalcul = 'L';
				$codeCommercial = '02';
			}
		}
		
		$fin_temp = new TFin_financement;
		$fin_temp->periodicite = $this->simulation->opt_periodicite;
		
		$TFinancement = array(
			'codeTypeCalcul' => $codeTypeCalcul
			,'typeFinancement' => array(
				'codeProduitFinancier' => $codeFinancier //021 = Location Financière ; 024 = Location mantadée
				,'codeProduitCommercial' => $codeCommercial 
			)
			,'codeBareme' => $this->_getBNPBareme($TData,$codeCommercial)
			,'montantFinance' => $this->simulation->montant
			//,'codeTerme' => ''
			//,'valeurResiduelle' => array(
				//'montant'=> ''
				//,'pourcentage'=>''
				//,'periodicite'=>''
			//)
			//,'presenceFranchiseDeLoyer' => ''
			,'paliersDeLoyer' => array(
				'palierDeLoyer' => array(
					'nombreDeLoyers' => $this->simulation->duree
					,'periodicite' => $fin_temp->getiPeriode()
					//,'montantLoyers' => ''
					//,'poidsDuPalier' => ''
				)
			)
		);
		
		return $TFinancement;
	}
	
	//CF drive -> Barème pour webservice CPRO.xlsx
	function _getBNPBareme(&$TData,$codeCommercial){
		global $db;
		$codeBareme = '';
		
		$cat = new Categorie($db);
		$TCats = $cat->containing($this->fk_leaser, 1);
		
		foreach($TCats as $categorie){
			if($TData['codeFamilleMateriel'] == 'H'){ // => BUREAUTIQUE
				if(strtoupper($categorie->label) == 'MANDATEE'){
					$codeBareme = '00004046';
				}
				elseif(strtoupper($categorie->label) == 'CESSION'){
					switch ($codeCommercial) {
						case '02': // = ''
								if($this->simulation->opt_periodicite == 'TRIMESTRE'){
									$codeBareme = '00000868';
								}
								elseif($this->simulation->opt_periodicite == 'MOIS'){
									$codeBareme = '00004028';
								}
							break;
						case '23': // = Top Full
								if($this->simulation->opt_periodicite == 'TRIMESTRE'){
									$codeBareme = '00004049';
								}
								elseif($this->simulation->opt_periodicite == 'MOIS'){
									$codeBareme = '00004050';
								}
							break;
						case '2Q': // = Secteur Public
								$codeBareme = '00004051';
							break;
						default:
							
							break;
					}
				}
			}
			elseif($TData['codeFamilleMateriel'] == 'T'){ // => INFORMATIQUE
				if(strtoupper($categorie->label) == 'CESSION'){ //Uniquement FINANCIERE pour INFORMATIQUE
					if($this->simulation->opt_periodicite == 'TRIMESTRE'){
						$codeBareme = '00004043';
					}
					elseif($this->simulation->opt_periodicite == 'MOIS'){
						$codeBareme = '00004048';
					}
				}
			}
		}
		
		return $codeBareme;
	}

	function _getBNPDataTabForConsultation($num_accord_leaser){
		
		$TData = array();
		
		//Tableau Prescripteur
		$TPrescripteur = array(
			'prescripteurId' => BNP_PRESCRIPTEUR_ID
		);

		$TData['prescripteur'] = $TPrescripteur;
		
		//Tableau Numéro demande
		$TNumerosDemande = array(
			'numeroIdentifiantDemande' => array(
				'numeroDemandeProvisoire' => $num_accord_leaser
			)
		);
		
		$TData['numerosDemande'] = $TNumerosDemande;
//		pre($TData,true);exit;
		
		//Tableau Rapport Suivi
		/*$TRapportSuivi = $this->_getBNPDataTabRapportSuivi();

		$TData['rapportSuivi'] = $TRapportSuivi;*/

		return $TData;
	}

	function _getBNPDataTabRapportSuivi(){
		
		$TRapportSuivi = array(
			'suiviDemande'=>$this->__getBNPDataTabSuiviDemande()
			,'demandeNonTrouve' => array(
				'numeroDemandeDefinitif' => ''
				,'numeroDemandeDefinitif' => ''
			)
		);

		return $TRapportSuivi;
	}
	
	function __getBNPDataTabSuiviDemande(){
			
		$TSuiviDemande = array(
			'numeroDemandeProvisoire' => ''
			,'numeroDemandeDefinitif' => ''
			,'etat' => array(
				'codeStatutDemande' => ''
				,'libelleStatutDemande' => ''
				//,'situationAu' => ''
			)
			,'client' => array(
				'raisonSociale' => ''
			)
			//,'financement' => array(
				//'montantFinance' => ''
				//,'paliersDeLoyer' => array(
					//'palierDeLoyer'=> array(
						//'montantLoyers' => ''
					//)
				//)
			//)
			//,'demandeInformationComplementaires' => ''
		);
		
		return $TSuiviDemande;
	}
}

