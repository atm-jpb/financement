<?php

class TImport extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_import');
		parent::add_champs('fk_user_author,entity','type=entier;');
		parent::add_champs('nb_lines,nb_errors,nb_create,nb_update','type=entier;');
		parent::add_champs('date','type=date;');
		parent::add_champs('type_import,filename','type=chaine;');
		parent::start();
		parent::_init_vars();
		
		$this->TType_import_interne = array(
			'client' => 'Fichier client'
			,'commercial' => 'Fichier commercial'
			,'affaire' => 'Fichier affaire'
			,'materiel' => 'Fichier matériel'
			,'facture_materiel' => 'Fichier facture matériel'
			,'facture_location' => 'Fichier facture location'
			,'facture_lettree' => 'Fichier facture lettrée'
			,'score' => 'Fichier score'
		);
		$this->TType_import = array('fichier_leaser' => 'Fichier leaser','dossier_init_adossee'=>'Import initial adosées','dossier_init_mandatee'=>'Import initial mandatées','dossier_init_all'=>'Import initial');
		$this->current_line = array();
	}

	/**
	 * Récupération des fichiers à importer
	 * Stockage dans le dossier import
	 */
	function getFiles($targetFolder)
	{
		
	}
	
	/*
	 * Récupération de la liste de fichier du répertoire source, correspondant à un préfixe
	 */
	function getListOfFiles($folder, $filePrefix)
	{
		$result = array();
		
		$dirHandle = opendir($folder);
		while ($fname = readdir($dirHandle)) {
			if(substr($fname, 0, strlen($filePrefix)) == $filePrefix) $result[] = $fname;
		}
		closedir($dirHandle);
		sort($result);
		
		return $result;
	}
	
	function init($fileName, $fileType) {
		$this->filename = $fileName;
		$this->type_import = $fileType;
		$this->nb_lines = 0;
		$this->nb_errors = 0;
		$this->nb_create = 0;
		$this->nb_update = 0;
		$this->date = time();
	}
	
	function getMapping($mappingFile) {
		$this->mapping = parse_ini_file($mappingFile, true);
	}
	
	/**
	 * Log une erreur concernant l'import
	 * @param $ATMdb : objet BDD ATM
	 * @param $errMsg : Message d'erreur
	 * @param $errData : Donnée utilisée qui a déclenché l'erreur
	 * @param $type : Type (ERROR, WARNING, ...)
	 * @param $is_sql : Erreur SQL (0 = non, 1 = oui ATMdb, 2 = oui Doli db)
	 */
	function addError(&$ATMdb, $errMsg, $errData, $type='ERROR', $is_sql=0, $doliError='') {
		global $user;
		$thisErr = new TImportError();
		$thisErr->fk_import = $this->getId();
		$thisErr->num_line = $this->nb_lines;
		$thisErr->content_line = serialize($this->current_line);
		$thisErr->error_msg = $errMsg;
		$thisErr->error_data = $errData;
		$thisErr->type_erreur = $type;
		if($is_sql == 1) {
			$infos = $ATMdb->db->errorInfo();
			$thisErr->sql_executed = $ATMdb->query;
			$thisErr->sql_errno = $infos[0];
			$thisErr->sql_error = $infos[2];
		} else if($is_sql == 2) {
			$thisErr->sql_executed = '';
			$thisErr->sql_errno = 0;
			$thisErr->sql_error = $doliError;
		}
		$thisErr->save($ATMdb);

		if($type == 'ERROR') $this->nb_errors++;
	}
	
	function importLine(&$ATMdb, $dataline, &$TInfosGlobale) {
		global $db;
		
		$this->current_line = $dataline;
		
		// Compteur du nombre de lignes
		$this->nb_lines++;
		// On save l'import tout les X enregistrements traités pour voir l'avancement de l'import
		if($this->nb_lines % 50 == 0) $this->save($ATMdb);

		if(!$this->checkData()) return false;
		$data = $this->contructDataTab();
		
		switch ($this->type_import) {
			case 'client':
				$this->importLineTiers($ATMdb, $data);
				break;
			case 'materiel':
				$this->importLineMateriel($ATMdb, $data);
				break;
			case 'facture_materiel':
				$this->importLineFactureMateriel($ATMdb, $data, $TInfosGlobale);
				break;
			case 'facture_location':
				$this->importLineFactureLocation($ATMdb, $data, $TInfosGlobale);
				$this->importLineFactureIntegrale($ATMdb, $data, $TInfosGlobale);
				break;
			case 'facture_lettree':
				$this->importLineFactureLettree($ATMdb, $data);
				break;
			case 'commercial':
				$this->importLineCommercial($ATMdb, $data, $TInfosGlobale);
				break;
			case 'affaire':
				$this->importLineAffaire($ATMdb, $data, $TInfosGlobale);
				break;
			case 'fichier_leaser':
				
				$this->importFichierLeaser($ATMdb, $data);
				
				break;
			case 'score':
				if($this->nb_lines == 1) return false; // Le fichier score contient une ligne d'en-tête
				$this->importLineScore($ATMdb, $data);
				break;
			case 'dossier_init_adossee':
			case 'dossier_init_mandatee':
			case 'dossier_init_all':
				$this->importDossierInit($ATMdb, $data);
				break;
			
			default:
				
				break;
		}
		
		$db->commit();
	}

	function importFichierLeaser(&$ATMdb, $data) {
		/*$ATMdb->debug=true;
		echo '<hr><pre>'.$this->nb_lines;
		print_r($data);
		echo '</pre>';*/
	
		/*if($data['echeance']==0) {
			return false;
		}*/
	
		$f=new TFin_financement;
		if($f->loadReference($ATMdb, $data['reference'], 'LEASER')) { // Recherche du financement leaser par référence
			// Le financement leaser a été trouvé avec la référence contrat leaser
		} else if (!empty($data['reference_dossier_interne']) && $f->loadReference($ATMdb, $data['reference_dossier_interne'], 'CLIENT')) { // Recherche du financement client par référence CPRO
			// Le financement client a été trouvé avec la référence CPRO
		} else if ($f->loadOrCreateSirenMontant($ATMdb, $data['siren'], $data['montant'])) { // Recherche du financement leaser par siren et montant
			// Le financement leaser a été trouvé ou créé par le siren et le montant de l'affaire
		} else {
			$this->addError($ATMdb, 'cantFindOrCreateFinancement', $data['reference']);
			return false;
		}
		
		if(!empty($f->fk_soc) && $f->fk_soc!=$data['idLeaser']) { // Si le dossier de financement récupéré n'est pas lié au bon leaser, erreur
			$this->addError($ATMdb, 'leaserNotAllgood', $data['idLeaser']);
			return false;
		}
		
		$dossier = new TFin_dossier();
		if($dossier->load($ATMdb, $f->fk_fin_dossier)) { // Chargement du dossier correspondant
			
			if($dossier->nature_financement == 'EXTERNE') { // Dossier externe => MAJ des informations
				// Echéance à 0 dans le fichier, on classe le dossier a soldé
				// 14.10.15 : suite échange avec Damien on fait sauter cette règle
				/*if($data['echeance'] == 0 && $dossier->financementLeaser->date_solde == 0) {
					$dossier->financementLeaser->date_solde = time();
					$data['echeance'] = $dossier->financementLeaser->echeance;
				}*/
				
				foreach ($data as $key => $value) {
					$dossier->financementLeaser->{$key} = $value;
				}
				$dossier->financementLeaser->fk_soc = $data['idLeaser'];
				
				$dossier->financementLeaser->duree /= $dossier->financementLeaser->getiPeriode();
				
			} else { // Dossier interne => Vérification des informations
				$echeance = $data['echeance'];
				$montant = $data['montant'];
				$date_debut = $data['date_debut'];
				$date_fin = $data['date_fin'];
				
				if(
						$echeance != $dossier->financementLeaser->echeance
						|| $montant > ($dossier->financementLeaser->montant + 0.01)
						|| $montant < ($dossier->financementLeaser->montant - 0.01)
						|| $date_debut != $dossier->financementLeaser->date_debut
						//|| $date_fin != $dossier->financementLeaser->date_fin
					) {
					$this->addError($ATMdb, 'cantMatchDataLine', $data['reference'], 'WARNING');
					return false;
				}
				else {
					$dossier->financementLeaser->okPourFacturation='OUI';
				}
			}
			
			$dossier->save($ATMdb);
			$this->nb_update++;
		
			return true;
		
		}
		
		return false;
	}

	function importLineTiers(&$ATMdb, $data) {
		global $user, $db;
		
		// Recherche si tiers existant dans la base via code client Artis
		$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		if($socid === false) return false;
		
		if(empty($socid)) {
			// Recherche si tiers existant dans la base via code prospect WonderBase
			$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key'], $data[$this->mapping['code_wb']]);
			if($socid === false) return false;
		}
		
		// Construction de l'objet final
		$societe = new Societe($db);
		if($socid > 0) {
			$societe->fetch($socid);
		}

		foreach ($data as $key => $value) {
			$societe->{$key} = $value;
		}
		
		$societe->idprof1 = substr($societe->idprof2,0,9);

		// Mise à jour ou création
		if($socid > 0) {
			$res = $societe->update($socid, $user);
			
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $societe->error);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $societe->create($user);
			
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $societe->error);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		return true;
	}

	function importLineFactureMateriel(&$ATMdb, $data, &$TInfosGlobale) {
		global $user, $db;
		
		if(empty($TInfosGlobale['fact'][$data[$this->mapping['search_key']]])) {
			// Recherche si facture existante dans la base
			$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
			if($facid === false) return false;
			
			$TInfosGlobale[$data[$this->mapping['search_key']]] = $facid;
		} else {
			$facid = &$TInfosGlobale['fact'][$data[$this->mapping['search_key']]];
		}
			
		if(empty($TInfosGlobale['societe'][$data[$this->mapping['search_key_client']]])) {
			// Recherche tiers associé à la facture existant dans la base
			$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true);
			if(!$socid) return false;
			
			$TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]] = $socid;
		} else {
			$socid = &$TInfosGlobale['societe'][$data[$this->mapping['search_key_client']]];
		}
			
		$data['socid'] = $socid;
		
		// Construction de l'objet final
		$facture_mat = new Facture($db);
		if($facid > 0) {
			$facture_mat->fetch($facid);
		}

		foreach ($data as $key => $value) {
			$facture_mat->{$key} = $value;
		}
			
		// Gestion des avoirs
		if(!empty($data['facture_annulee'])) {
			$avoirid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']], true);
			if($avoirid === false) return false;
			
			$facture_mat->type = 2;
			$facture_mat->fk_facture_source = $avoirid;
		}
		
		// Création des liens
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'])) {
			// Mise à jour ou création de la facture
			if($facid > 0) {
				$res = $facture_mat->update($facid, $user);
				// Erreur : la mise à jour n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_mat->error);
					return false;
				} else {
					$this->nb_update++;
				}			
			} else {
				$res = $facture_mat->create($user);
				// Erreur : la création n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_mat->error);
					return false;
				} else {
					$this->nb_create++;
				}
			}
			
			
			// Mise à jour de l'affaire
			$affaire->montant = $this->validateValue('total_ht',$data['total_ht']);	
			$affaire->save($ATMdb);
			
			// Création des liens entre affaire et matériel
			$TSerial = explode(' - ',$data['matricule']);
		
			foreach($TSerial as $serial) {
				$serial = trim($serial);
				
				$asset=new TAsset;
				if($asset->loadReference($ATMdb, $serial)) {
					$asset->fk_soc = $affaire->fk_soc;
					
					$asset->add_link($affaire->getId(),'affaire');
					$asset->add_link($facture_mat->id,'facture');
					
					$asset->save($ATMdb);	
				}
				else {
					$this->addError($ATMdb, 'ErrorMaterielNotFound', $serial);
				}
			}
			
			// Création du dossier de financement si non existant
			$financement=new TFin_financement;
			if(!empty($data['reference_dossier_interne']) && !$financement->loadReference($ATMdb, $data['reference_dossier_interne'],'CLIENT')) {
				$dossier = new TFin_dossier;
				if(!$dossier->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'])) {
					if($dossier->addAffaire($ATMdb, $affaire->getId())) {
						$dossier->montant = $data['total_ht'];
						$dossier->nature_financement = $affaire->nature_financement;
						$dossier->reference_contrat_interne = $data['reference_dossier_interne'];
						$dossier->financement->montant = $data['total_ht'];
						$dossier->financementLeaser->montant = $data['total_ht'];
						$dossier->financement->reference = $data['reference_dossier_interne'];
						if($dossier->nature_financement=='EXTERNE') {
							unset($dossier->financement);
						}
						$dossier->save($ATMdb);
					} else {
						$this->addError($ATMdb, 'ErrorCreatingDossierOnThisAffaire', $data['code_affaire'], 'ERROR');
					}
				}
			} else if(!empty($data['reference_dossier_interne'])) { // Lien avec l'affaire sinon
				$dossier = new TFin_dossier;
				$dossier->load($ATMdb, $financement->fk_fin_dossier);
				$dossier->addAffaire($ATMdb, $affaire->getId());
				$dossier->save($ATMdb);
			}

			// On repasse en brouillon pour supprimer les ligne
			$facture_mat->set_draft($user);
			
			// On supprime les lignes (pour ne pas créer de ligne en double)
			// Sur les facture matériel, 1 ligne = 1 facture mais une même facture peut apparaître plusieurs fois => plusieurs dossiers de financement
			foreach ($facture_mat->lines as $line) {
				$facture_mat->deleteline($line->rowid);
			}
		} else {
			$this->addError($ATMdb, 'ErrorAffaireNotFound', $data['code_affaire']);
			return false;
		}
		
		// Création du lien facture matériel / affaire financement
		$facture_mat->add_object_linked('affaire', $affaire->getId());
		
		// Actions spécifiques
		// On repasse en brouillon pour ajouter la ligne
		$facture_mat->set_draft($user);
		
		//On choisis le taux de tva en fonction de la date limite de règlement : 19.6% avant 2014, 20% après 2014
		if($data['date_lim_reglement'] < strtotime("2014-01-01"))
			$taux_tva = 19.6;
		else
			$taux_tva = 20;
		
		// On ajoute la ligne
		$facture_mat->addline($facture_mat->id, 'Matricule(s) '.$data['matricule'], $data['total_ht'], 1, $taux_tva);
		// Force la validation avec numéro de facture
		$facture_mat->validate($user, $data[$this->mapping['search_key']]); // Force la validation avec numéro de facture
		
		// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
		$facture_mat->date_lim_reglement = $data['date_lim_reglement'];
		$facture_mat->update($user, 0);
		
		return true;
	}

	function importLineFactureLocation(&$ATMdb, $data, &$TInfosGlobale) {
		global $user, $db;
		
		if(!in_array($data['ref_service'], array('SSC101','SSC102','SSC106','037004','037003','033741'))) {
			//On importe uniquement certaine ref produit
			//$this->addError($ATMdb, 'InfoRefServiceNotNeededNow', $data['ref_service'], 'WARNING');
			return false;
		}
		
		if(empty($TInfosGlobale[$data[$this->mapping['search_key']]])) {
			// Recherche si facture existante dans la base
			$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
			if($facid === false) return false;
			
			// Recherche tiers associé à la facture existant dans la base
			$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true);
			if(!$socid) return false;
			
			$data['socid'] = $socid;
			
			// Construction de l'objet final
			$facture_loc = new Facture($db);
			if($facid > 0) {
				$facture_loc->fetch($facid);
			}
	
			foreach ($data as $key => $value) {
				$facture_loc->{$key} = $value;
			}
	
			// Gestion des avoirs
			if(!empty($data['facture_annulee'])) {
				// Recherche de la facture annulee par l'avoir
				$avoirid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']]);
				if($avoirid === false) return false;
				
				$facture_loc->type = 2;
				$facture_loc->fk_facture_source = $avoirid;
			}
		
			// Création des liens
			$financement=new TFin_financement;
			if($financement->loadReference($ATMdb, $data['reference_dossier_interne'],'CLIENT')) {
				$nb = ($facture_loc->type == 2) ? -1 : 1;
				// On ne va changer l'échéance que si c'est la première fois que cette facture est intégrée dans Dolibarr
				if(empty($facid)) {
					$financement->setEcheance($nb);
				}
				$financement->save($ATMdb);
	
				// Création du lien entre dossier et facture
				$facture_loc->linked_objects['dossier'] = $financement->fk_fin_dossier;
			} else {
				$dossier = new TFin_dossier;
				if($dossier->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'])) { // Dossier trouvé, financement non => erreur de qualification (EXTERNE) 
					$dossier->nature_financement = 'INTERNE';
					$dossier->financement->reference = $data['reference_dossier_interne'];
					$nb = ($facture_loc->type == 2) ? -1 : 1;
					$dossier->financement->setEcheance($nb);
					$dossier->save($ATMdb);
					$this->addError($ATMdb, 'InfoWrongNatureAffaire', $data['reference_dossier_interne'], 'WARNING');
				} else {
					/* PAS OK */
					$this->addError($ATMdb, 'ErrorWhereIsFinancement', $data['reference_dossier_interne']);
				}
			}
		
			// Mise à jour ou création
			if($facid > 0) {
				$res = $facture_loc->update($facid, $user);
				// Erreur : la mise à jour n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_loc->error);
					return false;
				} else {
					$this->nb_update++;
					
					// Ajout objet lié
					if(!empty($facture_loc->linked_objects['dossier'])) {
						$facture_loc->add_object_linked('dossier', $facture_loc->linked_objects['dossier']);
					}
				}
			} else {
				$res = $facture_loc->create($user);
				// Erreur : la création n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_loc->error);
					return false;
				} else {
					$this->nb_create++;
				}
			}
			
			// On repasse en brouillon pour supprimer les lignes
			$facture_loc->set_draft($user);
			
			// On supprime les lignes (pour ne pas créer de ligne en double)
			foreach ($facture_loc->lines as $line) {
				$facture_loc->deleteline($line->rowid);
			}
			
			// Permet d'éviter de faire plusieurs fois les même actions sur une même facture
			// Le fichier facture contient les lignes de factures
			$TInfosGlobale[$data[$this->mapping['search_key']]] = $facture_loc->id;
		} else {
			$facid = &$TInfosGlobale[$data[$this->mapping['search_key']]];
			$facture_loc = new Facture($db);
			$facture_loc->fetch($facid);
		}
		
		// Actions spécifiques

		// On repasse en brouillon pour ajouter la ligne
		$facture_loc->set_draft($user);
		
		/*
		 * Création du service
		 */
		$fk_service = $this->createProduct(
			array(
				'ref_produit'=>$data['ref_service']
				,'libelle_produit'=>$data['libelle_ligne']
				,'prix_ttc'=> 0/*$data['pu']*FIN_TVA_DEFAUT*/
				,'marque'=> 'Service'
			)
		,1);
		// print "Création du service($fk_service)";
		
		//On choisis le taux de tva en fonction de la date limite de règlement : 19.6% avant 2014, 20% après 2014
		if($data['date_lim_reglement'] < strtotime("2014-01-01"))
			$taux_tva = 19.6;
		else
			$taux_tva = 20;
		
		// On ajoute la ligne
		$facture_loc->addline($facture_loc->id, $data['libelle_ligne'], $data['pu'], $data['quantite'], $taux_tva,0,0,$fk_service, 0, '', '', 0, 0, '', 'HT', 0, 0, -1, 0, '', 0, 0, null, 0, $data['libelle_ligne']);
		// Force la validation avec numéro de facture
		$facture_loc->validate($user, $data[$this->mapping['search_key']]);
		
		// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
		$facture_loc->date_lim_reglement = $data['date_lim_reglement'];
		$facture_loc->update($user, 0);
		
		// 2014.10.30 : Evolution pour stocker assurance, maintenance et loyer actualisé
		$facture_loc->fetchObjectLinked('','dossier');
		if(!empty($facture_loc->linkedObjectsIds['dossier'][0])) {
			$dossier = new TFin_dossier;
			$dossier->load($ATMdb, $facture_loc->linkedObjectsIds['dossier'][0]);
			if(!empty($dossier->TLien[0]->affaire) && $dossier->TLien[0]->affaire->contrat == 'FORFAITGLOBAL') {
				if($data['ref_service'] == '037004') {
					$dossier->financement->assurance = $data['total_ht'];
				}
				
				if($data['ref_service'] == 'XXXXXX') {
					$dossier->financement->montant_prestation = $data['total_ht'];
				}
			}
			$dossier->financement->loyer_actualise = $facture_loc->total_ht;
		}
		
		return true;
	}

	function importLineFactureIntegrale(&$ATMdb, $data, &$TInfosGlobale) {
		global $user, $db;
		
		$facture_loc = new Facture($db);
		$facture_loc->fetch('',$data[$this->mapping['search_key']]);
		$facture_loc->fetchObjectLinked('','dossier');
		if(!empty($facture_loc->linkedObjectsIds['dossier'][0])) {
			$dossier = new TFin_dossier;
			$dossier->load($ATMdb, $facture_loc->linkedObjectsIds['dossier'][0]);
			
			// 2014.12.05 : on ne charge les données intégrale que si affaire de type intégral
			if(!empty($dossier->TLien[0]->affaire) && $dossier->TLien[0]->affaire->contrat != 'INTEGRAL') {
				return false;
			}
		}
		
		if(empty($TInfosGlobale['integrale'][$data[$this->mapping['search_key']]])) {
			$TInfosGlobale['integrale'][$data[$this->mapping['search_key']]] = new TIntegrale();
			$TInfosGlobale['integrale'][$data[$this->mapping['search_key']]]->loadBy($ATMdb, $data[$this->mapping['search_key']], $this->mapping['search_key']);
		}
		
		$integrale = &$TInfosGlobale['integrale'][$data[$this->mapping['search_key']]];
		$integrale->facnumber = $data[$this->mapping['search_key']];
		
		// Gestion des frais divers
		// FASS
		$TFASS = array('SSC025', 'SSC054', 'SSC114', 'SSC115', 'SSC121', 'SSC124', 'SSC127');
		if(in_array($data['ref_service'], $TFASS)) {
			if(empty($integrale->fass_somme)) { // Gestion FASS sur plusieurs lignes
				$integrale->fass	= $data['total_ht'];
				$integrale->fass_somme = true;
			} else {
				$integrale->fass	+= $data['total_ht'];
			}
		}
		// FAS
		//$TFAS = array('SSC101', 'SSC102', 'SSC106');
		//if(in_array($data['ref_service'], $TFAS)) {
		if(strpos($data['label_integrale'], '(FAS)') !== false || substr($data['label_integrale'], -3) === 'FAS') {
			if(empty($integrale->fas_somme)) { // Gestion FAS sur plusieurs lignes
				$integrale->fas	= $data['total_ht'];
				$integrale->fas_somme = true;
			} else {
				$integrale->fas	+= $data['total_ht'];
			}
			
		}
		// Frais dossier
		if($data['ref_service'] == '037003') {
			$integrale->frais_dossier = $data['total_ht'];
		}
		// Frais bris de machine
		if($data['ref_service'] == '037004') {
			$integrale->frais_bris_machine	= $data['total_ht'];
		}
		// Frais de facturation
		if($data['libelle_ligne'] == 'FRAIS DE FACTURATION') {
			$integrale->frais_facturation	= $data['total_ht'];
		}
		
		// ENGAGEMENT NOIR
		if($data['ref_service'] == 'SSC015') {
			if(empty($integrale->materiel_noir)) {
				$integrale->materiel_noir = $data['matricule'];
				$integrale->vol_noir_engage = $data['quantite'];
				$integrale->vol_noir_realise = $data['quantite_integrale'];
				$integrale->vol_noir_facture = $data['quantite'];
			} else if($integrale->materiel_noir != $data['matricule']) {
				$integrale->materiel_noir = $data['matricule'];
				$integrale->vol_noir_engage+= $data['quantite'];
				$integrale->vol_noir_realise+= $data['quantite_integrale'];
				$integrale->vol_noir_facture+= $data['quantite'];
			}
			
			$integrale->cout_unit_noir = $data['cout_integrale'];
		}
		// COPIE SUP NOIR
		if($data['ref_service'] == 'SSC016') {
			$integrale->vol_noir_facture+= $data['quantite'];
		}
		// COPIE ECHUES NOIR
		if($data['ref_service'] == 'SSC017') {
			$integrale->vol_noir_realise+= $data['quantite_integrale'];
			$integrale->vol_noir_facture+= $data['quantite'];
			
			$integrale->cout_unit_noir = $data['pu'];
		}
		
		// ENGAGEMENT COULEUR
		if($data['ref_service'] == 'SSC010') {
			if(empty($integrale->materiel_coul)) {
				$integrale->materiel_coul = $data['matricule'];
				$integrale->vol_coul_engage = $data['quantite'];
				$integrale->vol_coul_realise = $data['quantite_integrale'];
				$integrale->vol_coul_facture = $data['quantite'];
			} else if($integrale->materiel_coul != $data['matricule']) {
				$integrale->materiel_coul = $data['matricule'];
				$integrale->vol_coul_engage+= $data['quantite'];
				$integrale->vol_coul_realise+= $data['quantite_integrale'];
				$integrale->vol_coul_facture+= $data['quantite'];
			}
			
			$integrale->cout_unit_coul = $data['cout_integrale'];
		}
		// COPIE SUP COULEUR
		if($data['ref_service'] == 'SSC011') {
			$integrale->vol_coul_facture+= $data['quantite'];
		}
		// COPIE ECHUES COULEUR
		if($data['ref_service'] == 'SSC012') {
			$integrale->vol_coul_realise+= $data['quantite_integrale'];
			$integrale->vol_coul_facture+= $data['quantite'];
			
			$integrale->cout_unit_coul = $data['pu'];
		}
		
		$integrale->save($ATMdb);
	}

	function sendAlertEmailIntegrale($ATMdb, $TInfosGlobale) {
		global $conf, $db, $langs;
		
		$TMailToSend = array();
		foreach ($TInfosGlobale['integrale'] as $facnumber => $integrale) {
			if(empty($TInfosGlobale[$facnumber])) continue;
			if($integrale->ecart < $conf->global->FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL) continue;
			
			// Récupération des informations à envoyer au commerial
			$sql= "SELECT s.nom, df.reference";
			$sql.= " FROM llx_facture f";
			$sql.= " LEFT JOIN llx_societe s ON s.rowid = f.fk_soc";
			$sql.= " LEFT JOIN llx_element_element ee ON ee.fk_target = f.rowid AND ee.targettype = 'facture'";
			$sql.= " LEFT JOIN llx_fin_dossier d ON d.rowid = ee.fk_source AND ee.sourcetype = 'dossier'";
			$sql.= " LEFT JOIN llx_fin_dossier_financement df ON df.fk_fin_dossier = d.rowid";
			$sql.= " WHERE f.facnumber = ".$facnumber;
			$sql.= " AND df.type = 'CLIENT'";
			
			$ATMdb->Execute($sql);
			$TRes = $ATMdb->Get_All();
			$obj = $TRes[0];
			
			//Compilation avant envois
			$data = array(
				'client' => $obj->nom
				,'contrat' => $obj->reference
				,'facture' => $facnumber
				,'montant_engage' => $integrale->total_ht_engage
				,'montant_facture' => $integrale->total_ht_facture
				,'ecart' => $integrale->ecart
				,'1-Copieur'=>''
				,'2-Traceur'=>''
				,'3-Solution'=>''
			);
			
			// Récupération du destinataire
			$sql= "SELECT u.rowid as id_user, u.firstname, u.name, u.email, u.login, ";
			$sql.=" CASE sc.type_activite_cpro WHEN 'Copieur' THEN '1-Copieur' WHEN 'Traceur' THEN '2-Traceur' WHEN 'Solution' THEN '3-Solution' END activite";
			$sql.= " FROM llx_facture f";
			$sql.= " LEFT JOIN llx_societe s ON s.rowid = f.fk_soc";
			$sql.= " LEFT JOIN llx_societe_commerciaux sc ON sc.fk_soc = s.rowid AND sc.type_activite_cpro IN ('Copieur','Traceur','Solution')";
			$sql.= " LEFT JOIN llx_user u ON u.rowid = sc.fk_user";
			$sql.= " WHERE f.facnumber = ".$facnumber;
			$sql.= " ORDER BY activite, u.login";
			
			$ATMdb->Execute($sql);
			$TRes = $ATMdb->Get_All();
			
			if(!empty($TRes[0])) {
				$email = $TRes[0]->email;
				$name = $TRes[0]->firstname.' '.$TRes[0]->name;
				$id_user = $TRes[0]->id_user;
			} else {
				$email = 'financement@cpro.fr';
				$name = 'Cellule financement';
				$id_user = 999999;
			}
			
			foreach($TRes as $user) {
				if(!empty($data[$user->activite])) {
					$data[$user->activite].= ', '.$user->login;
				} else {
					$data[$user->activite] = $user->login;
				}
			}
			
			$TMailToSend[$id_user]['usermail'] = $email;
			$TMailToSend[$id_user]['username'] = $name;
			$TMailToSend[$id_user]['content'][] = $data;
		}
//pre($TMailToSend,true);
		$contentMail = '';
		$csvfile = fopen(FIN_IMPORT_FOLDER.'alertesintegrale'.date('Ymd').'.csv', 'w');
		foreach($TMailToSend as $data) {
			$tabalert = '<table cellpadding="2">';
			$tabalert.='<tr>';
			$tabalert.='<th>Client</th>';
			$tabalert.='<th>Contrat Artis</th>';
			$tabalert.='<th>Facture</th>';
			$tabalert.='<th>Montant engagement</th>';
			$tabalert.='<th>Montant factur&eacute;</th>';
			$tabalert.='<th>&Eacute;cart</th>';
			$tabalert.='<th>Copieur</th>';
			$tabalert.='<th>Traceur</th>';
			$tabalert.='<th>Solution</th>';
			$tabalert.='</tr>';
			foreach ($data['content'] as $infos) {
				$tabalert.='<tr>';
				$tabalert.='<td>'.$infos['client'].'</td>';
				$tabalert.='<td>'.$infos['contrat'].'</td>';
				$tabalert.='<td>'.$infos['facture'].'</td>';
				$tabalert.='<td align="right">'.price($infos['montant_engage'],0,'',1,-1,2).' &euro;</td>';
				$tabalert.='<td align="right">'.price($infos['montant_facture'],0,'',1,-1,2).' &euro;</td>';
				$tabalert.='<td align="right">'.price($infos['ecart'],0,'',1,-1,2).' %</td>';
				$tabalert.='<td align="center">'.$infos['1-Copieur'].'</td>';
				$tabalert.='<td align="center">'.$infos['2-Traceur'].'</td>';
				$tabalert.='<td align="center">'.$infos['3-Solution'].'</td>';
				$tabalert.='</tr>';
				
				fputs($csvfile, implode(';', $infos)."\n");
			}
			$tabalert.= '</table>';
			
			$mailto = $data['usermail'];
			$mailto = 'financement@cpro.fr';
			$subjectMail = '[Lease Board] - Alertes facturation intégrale pour '.$data['username'];
			$contentMail.= $subjectMail.'<br><br>';
			$contentMail.= $langs->transnoentitiesnoconv('IntegraleEmailAlert', $data['username'], $conf->global->FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL, $tabalert).'<br><br>';
			
			//$r=new TReponseMail($conf->notification->email_from, $mailto, $subjectMail, $contentMail);
			//$r->emailtoBcc = 'maxime@atm-consulting.fr';
			//$r->send(true);
			
			echo "<hr>".$subjectMail."<br>".$contentMail;
		}

		fclose($csvfile);
		
		$mailto = 'financement@cpro.fr';
		$subjectMail = '[Lease Board] - Alertes facturation intégrale';
		
		$r=new TReponseMail($conf->notification->email_from, $mailto, $subjectMail, $contentMail);
		$r->emailtoBcc = 'maxime@atm-consulting.fr';
		//$r->send(true, 'UTF-8');
	}

	function importLineFactureLettree(&$ATMdb, $data) {
		global $user, $db;
		
		if (!preg_match('/^[A-Z]+$/', $data['code_lettrage'])) {
			// Code lettrage en minuscule = pré-lettrage = ne pas prendre en compte (ajout d'un addWarning ou addInfo ?)
			return false;
		}
		
		// Recherche si facture existante dans la base
		$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		if(!$facid) return false;
		
		// Construction de l'objet final
		$facture = new Facture($db);
		$facture->fetch($facid);
		$res = $facture->set_paid($user, '', $data['code_lettrage']);
		if($res < 0) {
			$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture->error);
			return false;
		} else {
			$this->nb_update++;
		}

		return true;
	}

	function importLineAffaire(&$ATMdb, $data, &$TInfosGlobale) {
		global $user;
		
		if(empty($TInfosGlobale['user'][$data[$this->mapping['search_key_user']]])) {
			$fk_user = $this->_recherche_user($ATMdb, $this->mapping['search_key_user'], $data[$this->mapping['search_key_user']]);
			if($fk_user === false) return false;
			if($fk_user === 0) $fk_user = $user->id;
			
			$TInfosGlobale['user'][$data[$this->mapping['search_key_user']]] = $fk_user;
		} else {
			$fk_user = $TInfosGlobale['user'][$data[$this->mapping['search_key_user']]];
		}
		
		if(empty($TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]])) {
			$fk_soc = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true);
			if($fk_soc === false) return false;
			
			$TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]] = $fk_soc;
		} else {
			$fk_soc = $TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]];
		}
		
		$a=new TFin_affaire;
		$a->loadReference($ATMdb, $data[$this->mapping['search_key']]);
		
		if($a->fk_soc > 0 && $a->fk_soc != $fk_soc) { // client ne correspond pas
			$this->addError($ATMdb, 'ErrorClientDifferent', $data[$this->mapping['search_key']]);
			return false;
		}

		$a->loadCommerciaux($ATMdb);
		
		foreach ($data as $key => $value) {
			$a->{$key} = $value;
		}
		
		$a->fk_soc = $fk_soc;
		$a->addCommercial($ATMdb, $fk_user);
		
		if($a->getId() > 0) {
			$this->nb_update++;
		} else {
			$this->nb_create++;
		}
		
		$a->save($ATMdb);
		
		return true;
	}

	function createProduct($data, $type=0) {
		global $user, $db;
		
		$produit =new Product($db);
		$res=$produit->fetch('', $data['ref_produit']);
		$fk_produit = $produit->id;
		
		if($fk_produit > 0) {
			return $fk_produit;
		} else {
		
			$produit->ref = $data['ref_produit'];
			$produit->libelle = $data['libelle_produit'];
			$produit->type=$type; //0 produit, 1 service
			
			$produit->price_base_type    = 'TTC';
	        $produit->price_ttc = isset($data['prix_ttc']) ? $data['prix_ttc'] : 0;
			$produit->price_min_ttc = 0;
	
	        $produit->tva_tx             = 20;
	        $produit->tva_npr            = 0;
	
	        // local taxes.
	        $produit->localtax1_tx 			= get_localtax($produit->tva_tx,1);
	        $produit->localtax2_tx 			= get_localtax($produit->tva_tx,2);
			
		    $produit->status             	= 1;
	        $produit->status_buy           	= 1;
	        $produit->description        	= $data['marque'];
	        $produit->note               	= "Produit créé par import automatique";
	        $produit->customcode            = '';
	        $produit->country_id            = 1;
	        $produit->duration_value     	= 0;
	        $produit->duration_unit      	= 0;
	        $produit->seuil_stock_alerte 	= 0;
	        $produit->weight             	= 0;
	        $produit->weight_units       	= 0;
	        $produit->length             	= 0;
	        $produit->length_units       	= 0;
	        $produit->surface            	= 0;
	        $produit->surface_units      	= 0;
	        $produit->volume             	= 0;
	        $produit->volume_units       	= 0;
	        $produit->finished           	= 1;
	        $produit->hidden =0;

			return $produit->create($user);
		}
	}

	function importLineMateriel(&$ATMdb, $data) {
		global $user,$conf;
	
		$fk_produit = $this->createProduct($data,0);
	
		$TSerial = explode(' - ',$data['serial_number']);
		
		foreach($TSerial as $serial) {
			$asset=new TAsset;
			$asset->loadReference($ATMdb,$serial);
			
			$asset->fk_product = $fk_produit;
			
			$asset->serial_number = $serial;
			
			$asset->set_date('date_achat',$data['date_achat']);
			if($data['type_copie']=='MCENB')$asset->copy_black = $this->validateValue('cout_copie', $data['cout_copie']); 
			else $asset->copy_color = $this->validateValue('cout_copie', $data['cout_copie']); 
			
			if($asset->getId() > 0) {
				$this->nb_update++;
			} else {
				$this->nb_create++;
			}
			
			$asset->entity = $conf->entity;
			
			$asset->save($ATMdb);
		}
			
		return true;
	}

	function importLineCommercial(&$ATMdb, $data, &$TInfosGlobales) { 
		global $user, $conf, $db;

		if(empty($TInfosGlobales['user'][$data[$this->mapping['search_key']]])) {
			$fk_user = $this->_recherche_user($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
			if($fk_user === false) return false;
			if($fk_user === 0) $fk_user = $user->id;
			
			$TInfosGlobale['user'][$data[$this->mapping['search_key']]] = $fk_user;
		} else {
			$fk_user = $TInfosGlobales['user'][$data[$this->mapping['search_key']]];
		}
		
		if(empty($TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]])) {
			$fk_soc = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true);
			if($fk_soc === false) return false;
			
			$TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]] = $fk_soc;
		} else {
			$fk_soc = $TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]];
		}
		
		$c=new TCommercialCpro;
		$c->loadUserClient($ATMdb, $fk_user, $fk_soc); // charge l'objet si existant
		
		$c->fk_soc = $fk_soc;
		$c->fk_user = $fk_user;
		
		$c->type_activite_cpro = $data['type_activite_cpro'];
		
		if($c->getId() > 0) {
			$this->nb_update++;
		} else {
			$this->nb_create++;
		}
		
		$c->save($ATMdb);
		
		return true;
	}

	function importLineScore(&$ATMdb, $data) {
		global $user, $db;
		
		// Recherche si tiers existant dans la base
		$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], true, false);
		if($socid === false) return false;
		
		if(!is_array($socid)) $socid = array($socid);
		
		// Construction de l'objet final
		$score = new TScore();

		foreach ($data as $key => $value) {
			$score->{$key} = $value;
		}
		
		$score->fk_import = $this->getId();
		$score->fk_user_author = $user->id;
		
		foreach ($socid as $fk_soc) {
			$score->start();
			$score->fk_soc = $fk_soc;
	
			$res = $score->save($ATMdb);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], 'ERROR', 1);
				return false;
			} else {
				$this->nb_create++;
			}
			
			// Mise à jour de la fiche tiers
			$societe = new Societe($db);
			$societe->fetch($fk_soc);
			$societe->fk_forme_juridique = $this->validateValue('forme_juridique', $data['forme_juridique']);
			$societe->idprof3 = $this->validateValue('naf', $data['naf']);
			$societe->update($societe->id, $user);
		}
		
		return true;
	}

	function importDossierInit(&$ATMdb, $data) {
		global $user, $db;
		
		if(empty($data['code_affaire']) || empty($data['reference_dossier_interne']) || empty($data['reference_dossier_leaser'])
			|| empty($data['montant']) //|| empty($data['leaser_montant'])
			|| empty($data['periodicite']) || empty($data['duree']) || empty($data['date_debut'])
			|| empty($data['echeance']) || ($data['terme'] == '') || ($data['reste'] == '')
			|| empty($data['leaser_periodicite']) || empty($data['leaser_duree']) || empty($data['leaser_date_debut'])
			|| empty($data['leaser_echeance']) || ($data['leaser_reste'] == '')) {
			
			/*echo '<pre>';
			print_r($data);
			echo '</pre>';*/
			$this->addError($ATMdb, 'ErrorDataNotComplete', $data['reference_dossier_interne']);
			return false;
		}
		
		$data['reference_dossier_interne'] = str_pad($data['reference_dossier_interne'], 8, '0', STR_PAD_LEFT);
		$data['code_affaire'] = str_pad($data['code_affaire'], 5, '0', STR_PAD_LEFT);
		$data['code_client'] = str_pad($data['code_client'], 6, '0', STR_PAD_LEFT);
		if(empty($data['leaser_montant'])) $data['leaser_montant'] = $data['montant'];
		if(empty($data['date_debut'])) $data['date_debut'] = 0;
		if(empty($data['leaser_date_debut'])) $data['leaser_date_debut'] = 0;
		
		// Chargement de l'affaire
		$affaire = new TFin_affaire;
		$found = false;
		if($affaire->loadReference($ATMdb, $data['code_affaire'], true)) {
			// Vérification client
			if(!empty($data['code_client']) && $affaire->societe->code_client != $data['code_client']) {
				$this->addError($ATMdb, 'ErrorClientDifferent', $data['code_affaire'].' - '.$data['code_client'], 'WARNING');
			}
			
			foreach ($affaire->TLien as $lien) {
				$doss = &$lien->dossier;
				if(!empty($doss->reference_contrat_interne) && $doss->reference_contrat_interne == $data['reference_dossier_interne']) { // On a trouvé le bon dossier
					$found = true;
					$this->_save_dossier_init($ATMdb, $doss, $affaire, $data);
				}
			}

			if($affaire->nature_financement == 'EXTERNE') {
				$affaire->nature_financement == 'INTERNE';
				$affaire->save($ATMdb);
				$this->addError($ATMdb, 'InfoWrongNatureAffaire', $data['code_affaire'], 'WARNING');
			}
			
			$this->nb_update++;
		} else {
			$this->addError($ATMdb, 'ErrorAffaireNotFound', $data['code_affaire'], 'WARNING');
		}
		
		if(!$found) {
			$doss = new TFin_dossier;
			if($doss->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'], true)) { // Dossier existe, âs rattaché à l'affaire attendue
				$this->addError($ATMdb, 'InfoWrongAffaireForDossier', $data['code_affaire'], 'WARNING');
				if(!empty($doss->TLien[0])) {
					$affaire = &$doss->TLien[0]->affaire;
					$doss->addAffaire($ATMdb, $affaire->getId());
					$this->_save_dossier_init($ATMdb, $doss, $affaire, $data);
					$found = true;
					$this->nb_update++;
				}
			}
		}

		if(!$found) {
			$this->addError($ATMdb, 'ErrorDossierClientNotFound', $data['reference_dossier_interne']);
			return false;
		}
		
		return true;
	}

	function _save_dossier_init(&$ATMdb, &$doss, &$affaire, $data) {
		$doss->nature_financement == 'INTERNE';
		$doss->load_facture($ATMdb);
		$doss->load_factureFournisseur($ATMdb);
		
		// Partie client
		$doss->financement->fk_soc = FIN_LEASER_DEFAULT;
		$doss->financement->periodicite = $data['periodicite'];
		$doss->financement->duree = $data['duree'];
		$doss->financement->montant = $data['montant'];
		$doss->financement->echeance = $data['echeance'];
		$doss->financement->reste = $data['reste'];
		$doss->financement->terme = $data['terme'];
		$doss->financement->date_debut = $data['date_debut'];
		$doss->financement->loyer_intercalaire = $data['loyer_intercalaire'];
		$doss->financement->frais_dossier = $data['frais_dossier'];
		$doss->financement->assurance = $data['assurance'];
		$doss->financement->date_solde = $data['date_solde'];
		
		if($doss->financement->date_prochaine_echeance < $doss->financement->date_debut) {
			$doss->financement->date_prochaine_echeance = $data['date_debut'];
		}
		
		// Partie leaser
		$doss->financementLeaser->fk_soc = $data['banque'];
		$doss->financementLeaser->reference = $data['reference_dossier_leaser'];
		$doss->financementLeaser->periodicite = $data['leaser_periodicite'];
		$doss->financementLeaser->duree = $data['leaser_duree'];
		$doss->financementLeaser->montant = $data['leaser_montant'];
		$doss->financementLeaser->echeance = $data['leaser_echeance'];
		$doss->financementLeaser->reste = $data['leaser_reste'];
		$doss->financementLeaser->date_debut = $data['leaser_date_debut'];
		$doss->financementLeaser->frais_dossier = $data['leaser_frais_dossier'];
		
		if($doss->financementLeaser->date_prochaine_echeance < $doss->financementLeaser->date_debut) {
			$doss->financementLeaser->date_prochaine_echeance = $data['leaser_date_debut'];
		}
		
		// Création des factures leaser
		if(!empty($doss->financementLeaser->reference) && $doss->financementLeaser->date_prochaine_echeance > 0) {
			while($doss->financementLeaser->date_prochaine_echeance < time() && $doss->financementLeaser->numero_prochaine_echeance <= $doss->financementLeaser->duree) {
				$this->_createFactureFournisseur($doss->financementLeaser, $doss, $affaire);
				$doss->financementLeaser->setEcheance();
			}
		}
		
		$doss->save($ATMdb);
	}

	function _createFactureFournisseur(&$f, &$d, &$affaire) {
		global $user, $db, $conf;
		
		$tva = (FIN_TVA_DEFAUT-1)*100;
		
		$object =new FactureFournisseur($db);
		
		$object->ref           = $f->reference.'/'.($f->duree_passe+1);
	    $object->socid         = $f->fk_soc;
	    $object->libelle       = "ECH DOS. ".$d->reference_contrat_interne." ".($f->duree_passe+1)."/".$f->duree;
	    $object->date          = $f->date_prochaine_echeance;
	    $object->date_echeance = $f->date_prochaine_echeance;
	    $object->note_public   = '';
		$object->origin = 'dossier';
		$object->origin_id = $f->fk_fin_dossier;
		$id = $object->create($user);
		
		if($id > 0) {
			if($f->duree_passe==0 && $f->frais_dossier > 0) {
				/* Ajoute les frais de dossier uniquement sur la 1ère facture */
				//print "Ajout des frais de dossier<br>";
				$result=$object->addline("", $f->frais_dossier, $tva, 0, 0, 1, FIN_PRODUCT_FRAIS_DOSSIER);
			}
			
			/* Ajout la ligne de l'échéance	*/
			$fk_product = 0;
			if($affaire->type_financement == 'ADOSSEE') $fk_product = FIN_PRODUCT_LOC_ADOSSEE;
			elseif($affaire->type_financement == 'MANDATEE') $fk_product = FIN_PRODUCT_LOC_MANDATEE;
			$result=$object->addline("Echéance de loyer banque", $f->echeance, $tva, 0, 0, 1, $fk_product);
		
			$result=$object->validate($user,'',0);
			
			// La facture reste en impayée si antérieure à avril 2013, date de début de l'utilisation de l'export comptable
			if($object->date_echeance < strtotime('first day of april 2013')) {
				$result=$object->set_paid($user); 
			}
			
			//print "Création facture fournisseur ($id) : ".$object->ref."<br/>";
		}
	}

	function checkData() {
		// Vérification cohérence des données
		
		return true;
	}
	
	function contructDataTab() {
		// Construction du tableau de données
		$data = array();
		array_walk($this->current_line, 'trim');
		
		foreach($this->mapping['mapping'] as $k=>$field) {
			$data[$field] = $this->current_line[$k-1];
			$data[$field] = $this->validateValue($field,$data[$field]);
		}
		
		if(isset($this->current_line[9999])) $data['idLeaser'] = $this->current_line[9999];
		
		if(isset($this->mapping['more'])) $data = array_merge($data, $this->mapping['more']); // Ajout des valeurs autres
		
		return $data;
	}
	
	function validateValue($key, $value) {
		// Nettoyage de la valeur
		$value = trim($value);
		
		// Si un tableau de transco existe, on l'utilise
		if(!empty($this->mapping['transco'][$key])) {
			if(isset($this->mapping[$key][$value])) {
				$value = $this->mapping[$key][$value];
			} else {
				$value = $this->mapping[$key]['default'];
			}
		}
		
		// Si un format spécial existe, on l'applique
		if(!empty($value) && !empty($this->mapping['format'][$key])) {
			switch($this->mapping['format'][$key]) {
				case 'date':
					list($day, $month, $year) = explode("/", $value);
					$value = dol_mktime(0, 0, 0, $month, $day, $year);
					break;
				case 'dateYYYYMMDD':
					$day = substr($value, 6, 2);
					$month = substr($value, 4, 2);
					$year = substr($value, 0, 4);
					$value = dol_mktime(0, 0, 0, $month, $day, $year);
					break;
				case 'date_english':
					$sep = (strpos($value,'-')===false) ? '/': '-';
					list($year, $month, $day) = explode($sep, $value);
					$value = mktime(0, 0, 0, $month, $day, $year);
					break;
				case 'float':
					$value = floatval(strtr($value, array(',' => '.', ' ' => '', ' '=>'')));
					break;
				default:
					break;
			}
		}
		return $value;
	}

	function _recherche_facture(&$ATMdb, $key, $val, $errorNotFound = false) {
		global $conf;
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'facture',array($key=>$val, 'entity' => $conf->entity));
		
		$rowid = 0;
		$num = count($TRes);
		if($num == 1) { // Enregistrement trouvé, mise à jour
			$rowid = $TRes[0];
		} else if($num > 1) { // Plusieurs trouvés, erreur
			$this->addError($ATMdb, 'ErrorMultipleFactureFound', $val);
			return false;
		} else if($errorNotFound) {
			$this->addError($ATMdb, 'ErrorFactureNotFound', $val);
			return false;
		}
		
		return $rowid;
	}

	function _recherche_client(&$ATMdb, $key, $val, $errorNotFound = false, $errorMultipleFound = true) {
		global $conf;
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array($key=>$val, 'entity' => $conf->entity));
		
		$rowid = 0;
		$num = count($TRes);
		if($num == 1) { // Enregistrement trouvé
			$rowid = $TRes[0];
		} else if($num > 1) { // Plusieurs trouvés
			if($errorMultipleFound) {
				$this->addError($ATMdb, 'ErrorMultipleClientFound', $val);
				return false;
			} else {
				$rowid = $TRes;
			}
		} else if($errorNotFound) { // Non trouvé, erreur seulement si précisé
			$this->addError($ATMdb, 'ErrorClientNotFound', $val);
			return false;
		}
		
		return $rowid;
	}
	
	function _recherche_user(&$ATMdb, $key, $val, $errorNotFound = false) {
		global $conf;
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'user',array($key=>$val, 'entity' => $conf->entity));
		
		$rowid = 0;
		$num = count($TRes);
		if($num == 1) { // Enregistrement trouvé
			$rowid = $TRes[0];
		} else if($num > 1) { // Plusieurs trouvés, erreur
			$this->addError($ATMdb, 'ErrorMultipleUserFound', $val);
			return false;
		} else if($errorNotFound) { // Non trouvé, erreur seulement si précisé
			$this->addError($ATMdb, 'ErrorUserNotFound', $val);
			return false;
		}
		
		return $rowid;
	}
}
?>
