<?php

class TSimulation extends TObjetStd {
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
		parent::add_champs('entity,fk_soc,fk_user_author,fk_leaser,accord_confirme','type=entier;');
		parent::add_champs('duree,opt_administration,opt_creditbail','type=entier;');
		parent::add_champs('montant,montant_rachete,montant_rachete_concurrence,montant_total_finance,echeance,vr,coeff,cout_financement,coeff_final,montant_presta_trim','type=float;');
		parent::add_champs('date_simul,date_validite','type=date;');
		parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme,fk_type_contrat,accord,type_financement,commentaire','type=chaine;');
		parent::add_champs('dossiers_rachetes,dossiers_rachetes_p1', 'type=tableau;');
		parent::start();
		parent::_init_vars();
		
		$this->init();
		
		$this->TStatut=array(
			'OK'=>$langs->trans('Accord')
			,'WAIT'=>$langs->trans('Etude')
			,'KO'=>$langs->trans('Refus')
			,'SS'=>$langs->trans('SansSuite')
		);
	}
	
	function init() {
		global $user;
		$this->opt_periodicite = 'TRIMESTRE';
		$this->opt_mode_reglement = 'PRE';
		$this->opt_terme = '1';
		$this->vr = 1;
		$this->coeff = 0;
		$this->fk_user_author = $user->id;
		$this->user = $user;
	}
	
	function load(&$db, &$doliDB, $id, $annexe=true) {
		parent::load($db, $id);
		
		if($annexe) {
			$this->load_annexe($db, $doliDB);
		}
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
					if($doss->date_solde < 0) {
						if($doss->nature_financement == 'EXTERNE') {
							$this->societe->encours_cpro += $doss->financementLeaser->valeur_actuelle();
						} else {
							$this->societe->encours_cpro += $doss->financement->valeur_actuelle();
						}
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

		if(empty($this->fk_type_contrat)) { // Type de contrat obligatoire
			$this->error = 'ErrorNoTypeContratSelected';
			return false;
		}
		else if(empty($this->montant_total_finance) && empty($this->echeance)) { // Montant ou échéance obligatoire
			$this->error = 'ErrorMontantOrEcheanceRequired';
			return false;
		}
		else if($this->vr > $this->montant_total_finance) { // Erreur VR ne peut être supérieur au mopntant
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
		
		// Récupération de la grille pour les paramètres donnés
		$grille = new TFin_grille_leaser;
		$grille->get_grille($ATMdb, $idLeaser, $this->fk_type_contrat, $this->opt_periodicite, $options);
		
		if(empty($grille->TGrille)) { // Pas de grille chargée, pas de calcul
			$this->error = 'ErrorNoGrilleSelected';
			return false;
		}

		if(!empty($this->montant_total_finance) && !empty($this->echeance)) { // Si montant ET échéance renseignés, on calcule à partir du montant
			$this->echeance = 0;
		}
		
		$this->coeff=0;
		foreach($grille->TGrille[$this->duree] as $palier => $infos) {
			if((!empty($this->montant_total_finance) && $this->montant_total_finance <= $palier)
			|| (!empty($this->echeance) && $this->echeance <= $infos['echeance']))
			{
					$this->coeff = $infos['coeff']; // coef trimestriel
					break;
			}
		}
		if($this->coeff==0){
			$this->error = 'ErrorAmountOutOfGrille';
			return false;
		}
		if(!empty($this->coeff_final) && $this->coeff_final != $this->coeff) {
			// TODO : à revoir avec Damien
		}
		
		$coeffTrimestriel = $this->coeff / 4 /100; // en %

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
				
				$this->montant = round($this->montant, 2);
				$this->montant_total_finance = $this->montant;
		}
		
		return true;
	}
	
	// TODO : Revoir validation financement avec les règles finales
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
				$percent_rachat = (($this->montant_rachete + montant_rachete_concurrence) / $this->montant_total_finance) * 100;
				
				if($this->societe->score->score >= $conf->global->FINANCEMENT_SCORE_MINI // Score minimum
					&& $montant_dispo > $this->montant_total_finance // % "d'endettement"
					&& $percent_rachat <= $conf->global->FINANCEMENT_PERCENT_RACHAT_AUTORISE // % de rachat
					&& !in_array($this->societe->idprof3, explode(FIN_IMPORT_FIELD_DELIMITER, $conf->global->FINANCEMENT_NAF_BLACKLIST)) // NAF non black-listé
					&& !empty($this->societe->TDossiers)) // A déjà eu au moins un dossier chez CPRO
				{
					$this->accord = 'OK';
					$this->date_validite = strtotime('+ 2 months');
				} 
			}
		}
	}
	
	function load_by_soc(&$db, &$doliDB, $fk_soc) {
		$sql = "SELECT ".OBJETSTD_MASTERKEY;
		$sql.= " FROM ".$this->get_table();
		$sql.= " WHERE fk_soc = ".$fk_soc;
		
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
		$TDossier = array();
		if(!empty($this->societe->TSimulations)) {
			foreach ($this->societe->TSimulations as $simu) {
				if($except_current && $simu->{OBJETSTD_MASTERKEY} == $this->{OBJETSTD_MASTERKEY}) continue;
				$TDossier = array_merge($TDossier, $simu->dossiers_rachetes);
			}
		}
		return $TDossier;
	}
}

