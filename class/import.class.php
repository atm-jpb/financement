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
		$this->TType_import = array('fichier_leaser' => 'Fichier leaser');
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
	
	function addError(&$ATMdb, $errMsg, $errData, $sqlExecuted='', $type='ERROR', $is_sql=false) {
		global $user;
		$thisErr = new TImportError();
		$thisErr->fk_import = $this->getId();
		$thisErr->num_line = $this->nb_lines;
		$thisErr->content_line = serialize($this->current_line);
		$thisErr->error_msg = $errMsg;
		$thisErr->error_data = $errData;
		$thisErr->sql_executed = $sqlExecuted;
		$thisErr->type_erreur = $type;
		if($is_sql) {
			$infos = $ATMdb->db->errorInfo();
			$thisErr->sql_errno = $infos[0];
			$thisErr->sql_error = $infos[2];
		}
		$thisErr->save($ATMdb);

		$this->nb_errors++;
	}
	
	function importLine(&$ATMdb, $dataline, $type, &$TInfosGlobale) {
		global $db;
		
		$this->current_line = $dataline;
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData()) return false;
		$data = $this->contructDataTab();
		
		switch ($type) {
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
				/*print_r($this->current_line); 
				print '<br>';*/
				$this->importFichierLeaser($ATMdb, $data);
				break;
			case 'score':
				if($this->nb_lines == 1) return false; // Le fichier score contient une ligne d'en-tête
				$this->importLineScore($ATMdb, $data);
				break;
			
			default:
				
				break;
		}
		
		$db->commit();
	}

	function importFichierLeaser(&$ATMdb, $data) {
		$ATMdb->debug=true;
		$data= $this->contructDataTab($this->current_line);
		echo '<hr><pre>'.$this->nb_lines;
		print_r($data);
		echo '</pre>';
		$this->nb_lines++;
	
		if($data['echeance']==0) {
			return false;
		}
	
		$f=new TFin_financement;
		if($f->loadReference($ATMdb, $data['reference'], 'LEASER')) { // Recherche du financement leaser par référence
			// Le financement leaser a été trouvé avec la référence contrat leaser
		} else if ($f->loadOrCreateSirenMontant($ATMdb, $data['siren'], $data['montant'])) { // Recherche du financement leaser par siren et montant
			// Le financement leaser a été trouvé ou créé par le siren et le montant de l'affaire
		} else {
			$this->addError($ATMdb, 'cantFindOrCreateFinancement', $data['reference'], '', 'ERROR');
			return false;
		}
		
		if(!empty($f->fk_soc) && $f->fk_soc!=$data['idLeaser']) { // Si le dossier de financement récupéré n'est pas lié au bon leaser, erreur
			$this->addError($ATMdb, 'leaserNotAllgood', $data['idLeaser'], '', 'ERROR');
			return false;
		}
		
		$dossier = new TFin_dossier();
		$dossier->load($ATMdb, $f->fk_fin_dossier); // Chargement du dossier correspondant
		if($dossier->nature_financement == 'EXTERNE') { // Dossier externe => MAJ des informations
			foreach ($data as $key => $value) {
				$f->{$key} = $value;
			}
			$f->fk_soc = $data['idLeaser'];
		} else { // Dossier interne => Vérification des informations
			$echeance = $data['echeance'];
			$montant = $data['montant'];
			$date_debut =$data['date_debut'];
			$date_fin = $data['date_fin'];
			
			if($echeance!=$f->echeance || $montant!=$f->montant || $date_debut!=$f->date_debut || $date_fin!=$f->date_fin) {
				$this->addError($ATMdb, 'cantMatchDataLine', $data['reference'], '', 'WARNING');
				return false;
			}
			else {
				$f->okPourFacturation=1;
			}
		}
		
		$f->save($ATMdb);
		$this->nb_update++;
		
		return true;
	}

	function importLineTiers(&$ATMdb, $data) {
		global $user, $db;
		
		// Recherche si tiers existant dans la base
		$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		if($socid === false) return false;
		
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
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $societe->create($user);
			
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
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
			$avoirid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']]);
			if($avoirid === false) return false;
			
			$facture_mat->type = 2;
			$facture_mat->fk_facture_source = $avoirid;
		}
			
		// Création des liens
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'])) {
			// Mise à jour de l'affaire
			$affaire->montant = $this->validateValue('total_ht',$data['total_ht']);	
			$affaire->save($ATMdb);
			
			// Création des liens entre affaire et matériel
			$TSerial = explode(' - ',$data['matricule']);
		
			foreach($TSerial as $serial) {
				
				$asset=new TAsset;
				if($asset->loadReference($ATMdb, $serial)) {
					$asset->fk_soc = $affaire->fk_soc;
					
					$asset->add_link($affaire->getId(),'affaire');	
					
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
				if($dossier->addAffaire($ATMdb, $affaire->rowid)) {
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
					
					// Création du lien entre dossier et facture
					$facture_mat->linked_objects['dossier'] = $dossier->getId();
				} else {
					$this->addError($ATMdb, 'ErrorCreatingDossierOnThisAffaire', $data['code_affaire'], '', 'ERROR', true);
				}
			}
		} else {
			$this->addError($ATMdb, 'ErrorAffaireNotFound', $data['code_affaire']);
			return false;
		}
			
		// Mise à jour ou création
		if($facid > 0) {
			$res = $facture_mat->update($facid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $facture_mat->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		// Actions spécifiques
		// On repasse en brouillon pour ajouter la ligne
		$facture_mat->set_draft($user);
		
		// On supprime les lignes (pour ne pas créer de ligne en double)
		// Sur les facture matériel, 1 ligne = 1 facture mais une même facture peut apparaître plusieurs fois => plusieurs dossiers de financement
		foreach ($facture_mat->lines as $line) {
			$facture_mat->deleteline($line->id);
		}
		
		// On ajoute la ligne
		$facture_mat->addline($facture_mat->id, 'Matricule(s) '.$data['matricule'], $data['total_ht'], 1, 19.6);
		// Force la validation avec numéro de facture
		$facture_mat->validate($user, $data[$this->mapping['search_key']]); // Force la validation avec numéro de facture
		
		// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
		$facture_mat->date_lim_reglement = $data['date_lim_reglement'];
		$facture_mat->update($user, 0);
		
		return true;
	}

	function importLineFactureLocation(&$ATMdb, $data, &$TInfosGlobale) {
		global $user, $db;
		
		if(empty($TInfosGlobale[$data[$this->mapping['search_key']]])) {
			// Recherche si facture existante dans la base
			$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
			if($facid === false) return false;
			
			// Recherche tiers associé à la facture existant dans la base
			$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true);
			if(!$socid) return false;
			
			$data['socid'] = $fk_soc;
			
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
				/* OK */
				$dossier=new TFin_dossier;
				$dossier->load($ATMdb, $financement->fk_fin_dossier);
				
				$nb = ($facture_loc->type == 2) ? -1 : 1;
				$financement->setEcheance($nb);
				$financement->save($ATMdb);
	
				// Création du lien entre dossier et facture
				$facture_loc->linked_objects['dossier'] = $dossier->getId();
			} else {
				$dossier = new TFin_dossier;
				if($dossier->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'])) { // Dossier trouvé, financement non => erreur de qualification (EXTERNE) 
					$dossier->nature_financement = 'INTERNE';
					$dossier->financement->reference = $data['reference_dossier_interne'];
					$nb = ($facture_loc->type == 2) ? -1 : 1;
					$dossier->financement->setEcheance($nb);
					$dossier->save($ATMdb);
					$this->addError($ATMdb, 'InfoWrongNatureAffaire', $data['reference_dossier_interne'], $sql, 'WARNING');
				} else {
					/* PAS OK */
					$this->addError($ATMdb, 'ErrorWhereIsFinancement', $data['reference_dossier_interne'], $sql);
				}
			}
		
			// Mise à jour ou création
			if($facid > 0) {
				$res = $facture_loc->update($facid, $user);
				// Erreur : la mise à jour n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
					return false;
				} else {
					$this->nb_update++;
				}
			} else {
				$res = $facture_loc->create($user);
				// Erreur : la création n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
					return false;
				} else {
					$this->nb_create++;
				}
			}
			
			// On supprime les lignes (pour ne pas créer de ligne en double)
			foreach ($facture_loc->lines as $line) {
				$facture_loc->deleteline($line->id);
			}
			
			// Permet d'éviter de faire plusieurs fois les même actions sur une même facture
			// Le fichier facture contient les lignes de factures
			$TInfosGlobale[$data[$this->mapping['search_key']]] = $facture_loc;
		} else {
			$facture_loc = &$TInfosGlobale[$data[$this->mapping['search_key']]];
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
		
		// On ajoute la ligne
		$facture_loc->addline($facture_loc->id, $data['libelle_ligne'], $data['pu'], $data['quantite'], 19.6,0,0,$fk_service);
		// Force la validation avec numéro de facture
		$facture_loc->validate($user, $data[$this->mapping['search_key']]);
		
		// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
		$facture_loc->date_lim_reglement = $data['date_lim_reglement'];
		$facture_loc->update($user, 0);
		
		return true;
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
			$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], '', 'ERROR', true);
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
		
		$produit->ref = $data['ref_produit'];
		$produit->libelle = $data['libelle_produit'];
		$produit->type=$type; //0 produit, 1 service
		
		$produit->price_base_type    = 'TTC';
        $produit->price_ttc = isset($data['prix_ttc']) ? $data['prix_ttc'] : 0;
		$produit->price_min_ttc = 0;

        $produit->tva_tx             = 19.6;
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
		
		
		if(!$res) {
			$fk_produit = $produit->create($user);
			//print "Création du produit (".$produit->error.")";	
		}	
		else {
			//print "Mise à jour produit ($fk_produit)";
			$produit->update($fk_produit, $user);
		}
	
		return $fk_produit;
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
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $this->current_line, '', 'ERROR', true);
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

	function importDossierInit(&$ATMdb) {
		global $user, $db;
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($this->current_line)) return false;
		$data = $this->contructDataTab($this->current_line);
		
		// Chargement de l'affaire
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'], true)) {
			// Vérification client
			if($affaire->societe->code_client != $data['code_client']) {
				$this->addError($ATMdb, 'ErrorClientDifferent', $data['code_affaire'].' - '.$data['code_client'], $this->current_line, $sql, 'ERROR', true);
				return false;
			}
			
			$found = false;
			foreach ($affaire->TLien as $lien) {
				$doss = &$lien->dossier;
				if(!empty($doss->financement->reference) && $doss->financement->reference == $data['reference_dossier_interne']) { // On a trouvé le bon dossier
					$found = true;
					$doss->nature_financement == 'INTERNE';
					
					// Partie client
					$doss->financement->periodicite = $data['periodicite'];
					$doss->financement->duree = $data['duree'];
					$doss->financement->montant = $data['montant'];
					$doss->financement->echeance = $data['echeance'];
					$doss->financement->reste = $data['reste'];
					$doss->financement->terme = $data['terme'];
					$doss->financement->date_debut = $data['date_debut'];
					
					// Partie leaser
					$doss->financementLeaser->reference = $data['reference_dossier_leaser'];
					$doss->financementLeaser->periodicite = $data['leaser_periodicite'];
					$doss->financementLeaser->duree = $data['leaser_duree'];
					$doss->financementLeaser->montant = $data['leaser_montant'];
					$doss->financementLeaser->echeance = $data['leaser_echeance'];
					$doss->financementLeaser->reste = $data['leaser_reste'];
					$doss->financementLeaser->date_debut = $data['leaser_date_debut'];
					$doss->financementLeaser->frais_dossier = $data['leaser_frais_dossier'];
					
					$doss->save($ATMdb);
					
					// Création des factures leaser
					while($doss->financementLeaser->date_prochaine_echeance < time() && $doss->financementLeaser->numero_prochaine_echeance <= $doss->financementLeaser->duree) {
						_createFactureFournisseur($doss->financementLeaser, $doss);
						$doss->financementLeaser->setEcheance();
					}
				}
			}

			if(!$found) {
				$this->addError($ATMdb, 'ErrorDossierClientNotFound', $data['reference_dossier_interne'], $this->current_line, $sql, 'ERROR', true);
				return false;
			}

			if($affaire->nature_financement == 'EXTERNE') {
				$affaire->nature_financement == 'INTERNE';
				$affaire->save($ATMdb);
				$this->addError($ATMdb, 'InfoWrongNatureAffaire', $data['code_affaire'], $this->current_line, $sql, 'WARNING');
			}
		} else {
			$this->addError($ATMdb, 'ErrorAffaireNotFound', $data['code_affaire'], $this->current_line, $sql, 'ERROR', true);
			return false;
		}
		
		return true;
	}

	function _createFactureFournisseur(&$f, &$d) {
		global $user, $db, $conf;
		
		$tva = (FIN_TVA_DEFAUT-1)*100;
		
		$object =new FactureFournisseur($db);
		
		$object->ref           = $f->reference.'/'.($f->duree_passe+1); 
	    $object->socid         = $f->fk_soc;
	    $object->libelle       = "Facture échéance loyer banque (".($f->duree_passe+1).")";
	    $object->date          = $f->date_prochaine_echeance;
	    $object->date_echeance = $f->date_prochaine_echeance;
	    $object->note_public   = '';
		$object->origin = 'dossier';
		$object->origin_id = $f->fk_fin_dossier;
		$id = $object->create($user);
		
		if($f->duree_passe==0) {
			/* Ajoute les frais de dossier uniquement sur la 1ère facture */
			//print "Ajout des frais de dossier<br>";
			$result=$object->addline("", $f->frais_dossier, $tva, 0, 0, 1, FIN_PRODUCT_FRAIS_DOSSIER);
		}
		
		/* Ajout la ligne de l'échéance	*/
		$fk_product = 0;
		if(!empty($d->TLien[0]->affaire)) {
			if($d->TLien[0]->affaire->type_financement == 'ADOSSEE') $fk_product = FIN_PRODUCT_LOC_ADOSSEE;
			elseif($d->TLien[0]->affaire->type_financement == 'MANDATEE') $fk_product = FIN_PRODUCT_LOC_MANDATEE;
		}
		$result=$object->addline("Echéance de loyer banque", $f->echeance, $tva, 0, 0, 1, $fk_product);
	
		$result=$object->validate($user,'',0);
		
		$result=$object->set_paid($user);
		
		//print "Création facture fournisseur ($id) : ".$object->ref."<br/>";
	}

	function checkData() {
		// Vérification cohérence des données
		
		/*if(count($this->mapping['mapping']) != count($this->current_line)) {
			$this->addError($ATMdb, 'ErrorNbColsNotMatchingMapping', $this->current_line);
			return false;
		}
		*/
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
			if(!empty($this->mapping[$key][$value])) {
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
					$value = strtr($value, array(',' => '.', ' ' => '', ' '=>''));
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
