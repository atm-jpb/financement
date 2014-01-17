<?php

class TSimulation extends TObjetStd {
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
		parent::add_champs('entity,fk_soc,fk_user_author,fk_leaser,accord_confirme','type=entier;');
		parent::add_champs('duree,opt_administration,opt_creditbail','type=entier;');
		parent::add_champs('montant,montant_rachete,montant_rachete_concurrence,montant_total_finance,echeance,vr,coeff,cout_financement,coeff_final,montant_presta_trim','type=float;');
		parent::add_champs('date_simul,date_validite,date_accord,date_demarrage','type=date;');
		parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme,fk_type_contrat,accord,type_financement,commentaire,type_materiel,numero_accord,reference,opt_calage','type=chaine;');
		parent::add_champs('dossiers_rachetes,dossiers_rachetes_nr,dossiers_rachetes_p1,dossiers_rachetes_nr_p1', 'type=tableau;');
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
		$this->reference = $this->getRef();
		$this->opt_periodicite = 'TRIMESTRE';
		$this->opt_mode_reglement = 'PRE';
		$this->opt_terme = '1';
		$this->opt_calage = '';
		$this->vr = 0;
		$this->coeff = 0;
		$this->fk_user_author = $user->id;
		$this->user = $user;
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
		parent::save($db);
		$this->gen_simulation_pdf($db, $doliDB);
		$this->reference = $this->getRef();
		parent::save($db);
	}
	
	function getStatut() {
		return $this->TStatut[$this->accord];
	}
	
	function getAuthorFullName() {
		global $langs;
		return $this->user->getFullName($langs);
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
				$TDossier = array_merge($TDossier, $simu->dossiers_rachetes, $simu->dossiers_rachetes_p1);
			}
		}
		return $TDossier;
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
			$mesg.= 'Vous trouverez ci-joint l\'accord de financement concernant votre simulation n° '.$this->reference.'.'."\n\n";
			$mesg.= 'Cordialement,'."\n\n";
			$mesg.= 'La cellule financement'."\n\n";
		} else {
			$accord = 'Demande de financement refusée';
			$mesg = 'Bonjour '.$this->user->getFullName($langs)."\n\n";
			$mesg.= 'Votre demande de financement via la simulation n° '.$this->reference.' n\'a pas été acceptée.'."\n\n";
			$mesg.= 'Cordialement,'."\n\n";
			$mesg.= 'La cellule financement'."\n\n";
		}
		$subject = 'Simulation '.$this->reference.' - '.$this->societe->getFullName($langs).' - '.number_format($this->montant_total_finance,2,',',' ').' € - '.$accord;
		
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
		
		/*
		if ($mailfile->error) {
			echo 'ERR : '.$mailfile->error;
		}
			$mailfile->sendfile();*/
	}
	
	function gen_simulation_pdf(&$ATMdb, &$doliDB) {
		global $conf;
		$a = new TFin_affaire;
		$f = new TFin_financement;
		
		// Infos de la simulation
		$simu = $this;
		$simu->type_contrat = $a->TContrat[$this->fk_type_contrat];
		$simu->periodicite = $f->TPeriodicite[$this->opt_periodicite];
		$simu->statut = html_entity_decode($this->getStatut());
		
		// Dossiers rachetés dans la simulation
		$TDossier = array();
		
		$TSimuDossier = array_merge($this->dossiers_rachetes, $this->dossiers_rachetes_p1,$this->dossiers_rachetes_nr,$this->dossiers_rachetes_nr_p1);
		foreach($TSimuDossier as $idDossier) {
			$d = new TFin_dossier();
			$d->load($ATMdb, $idDossier, false);
			
			if($d->nature_financement == 'INTERNE') {
				$f = &$d->financement;
			} else { 
				$f = &$d->financementLeaser;
			}
			
			if(in_array($idDossier, $this->dossiers_rachetes)) {
				$solde = $d->getSolde($ATMdb2, 'SRCPRO');
			} elseif(in_array($idDossier, $this->dossiers_rachetes_nr)) {
				$solde = $d->getSolde($ATMdb2, 'SNRCPRO');
			} elseif(in_array($idDossier, $this->dossiers_rachetes_p1)) {
				$solde = $d->getSolde($ATMdb2, 'SRCPRO',$fin->duree_passe + 1);
			} elseif(in_array($idDossier, $this->dossiers_rachetes_nr_p1)) {
				$solde = $d->getSolde($ATMdb2, 'SNRCPRO',$fin->duree_passe + 1);
			} else {
				$solde = 0;
			}
			
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
			
			$leaser = new Societe($doliDB);
			$leaser->fetch($d->financementLeaser->fk_soc);
			
			$TDossier[] = array(
				'reference' => $f->reference
				,'leaser' => $leaser->name
				,'type_contrat' => $d->type_contrat
				,'solde' => $solde
			);
		}

		$this->hasdossier = count($TDossier);
		
		// Création du répertoire
		$fileName = dol_sanitizeFileName($this->getRef()).'.odt';
		$filePath = $conf->financement->dir_output . '/' . dol_sanitizeFileName($this->getRef());
		dol_mkdir($filePath);
		
		// Génération en ODT
		$TBS = new TTemplateTBS;
		$file = $TBS->render('./tpl/doc/simulation.odt'
			,array(
				'dossier'=>$TDossier
			)
			,array(
				'simulation'=>$simu
				,'client'=>$this->societe
			)
			,array()
			,array('outFile' => $filePath.'/'.$fileName)
		);
		
		// Transformation en PDF
		$cmd = 'export HOME=/tmp'."\n";
		$cmd.= 'libreoffice --invisible --norestore --headless --convert-to pdf --outdir '.$filePath.' '.$filePath.'/'.$fileName;
		ob_start();
		system($cmd);
		$res = ob_get_clean();
	}
}
