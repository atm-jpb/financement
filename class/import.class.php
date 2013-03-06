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
			'client' => 'Fichier client','commercial' => 'Fichier commercial'
			,'affaire' => 'Fichier affaire','materiel' => 'Fichier matériel'
			,'facture_materiel' => 'Fichier facture matériel','facture_location' => 'Fichier facture location'
			,'facture_lettree' => 'Fichier facture lettrée'
		);
		$this->TType_import = array('fichier_leaser' => 'Fichier leaser', 'score' => 'Fichier score');
	}

	/**
	 * Récupération des fichiers à importer
	 * Stockage dans le dossier import
	 */
	function getFiles($targetFolder)
	{
		// TODO
		// Fonction inutile car fichier déposés directement par CPRO dans le répertoire qui est partagé en samba
	}
	
	function getListOfFiles($folder, $filePrefix)
	{
		$result = array();
		
		$dirHandle = opendir($folder);
		while ($fname = readdir($dirHandle)) {
			if(substr($fname, 0, strlen($filePrefix)) == $filePrefix) $result[] = $fname;
		}
		
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
	
	function addError(&$ATMdb, $errMsg, $errData, $dataLine, $sqlExecuted='', $type='ERROR', $is_sql=false) {
		global $user;
		$thisErr = new TImportError();
		$thisErr->fk_import = $this->getId();
		$thisErr->num_line = $this->nb_lines;
		$thisErr->content_line = serialize($dataLine);
		$thisErr->error_msg = $errMsg;
		$thisErr->error_data = $errData;
		$thisErr->sql_executed = $sqlExecuted;
		$thisErr->type_erreur = $type;
		if($is_sql) {
			$thisErr->sql_errno = $this->db->lasterrno;
			$thisErr->sql_error = lastqueryerror."\n".$this->db->lasterror."\n".$this->db->lastquery;
		}
		$thisErr->save($ATMdb);

		$this->nb_errors++;
	}
	
	function importLine($dataline, $type) {
		$ATMdb = new TPDOdb;
		switch ($type) {
			case 'client':
				$this->importLineTiers($ATMdb, $dataline);
				break;
			case 'materiel':
				$this->importLineMateriel($ATMdb, $dataline);
				break;
			case 'facture_materiel':
				$this->importLineFactureMateriel($ATMdb, $dataline);
				break;
			case 'facture_location':
				$this->importLineFactureLocation($ATMdb, $dataline);
				break;
			case 'facture_lettree':
				$this->importLineFactureLettree($ATMdb, $dataline);
				break;
			case 'commercial':
				$this->importLineCommercial($ATMdb, $dataline);
				break;
			case 'affaire':
				$this->importLineAffaire($ATMdb, $dataline);
				break;
			case 'fichier_leaser':
				/*print_r($dataline); 
				print '<br>';*/
				$this->importFichierLeaser($ATMdb, $dataline);
				break;
			case 'score':
				$this->importLineScore($ATMdb, $dataline);
				break;
			
			default:
				
				break;
		}
		$ATMdb->close();
	}
	function importFichierLeaser(&$ATMdb, $dataline) {
		//	$ATMdb->db->debug=true;
		$data= $this->contructDataTab($dataline);
	//	print_r($data);
		$this->nb_lines++;
	
		if($data['echeance']==0) {
			
			return false;
		}
	
		$f=new TFin_financement;
		if($f->loadReference($ATMdb, $data['reference_financement'], 'LEASER')) {
			/*
			 * Youpi, on a retrouvé le financement et donc le client
			 */
			 
		}
		elseif($f->createWithfindClientBySiren($ATMdb, $data['siren'], $data['reference_financement'])) {
			/*
			 * On trouve le financement recherch d'une affaire sans financement dans un client sur siren
			 */
		}
		else {	
			$this->addError($ATMdb, 'cantFindOrCreateFinancement', $data['reference_financement'], $dataline, '', 'ERROR');
			return false;		
		}
		
		if($f->fk_soc!=$data['idLeaser']) {
			$this->addError($ATMdb, 'leaserNotAllgood', $data['idLeaser'], $dataline, '', 'ERROR');
			return false;
		}
		
		$echeance = $data['echeance'];
		$montant = $data['montant'];
		$date_debut =$data['date_debut'];
		$date_fin = $data['date_fin'];
		
		if($echeance!=$f->echeance || $montant!=$f->montant || $date_debut!=$f->date_debut || $date_fin!=$f->date_fin) {
			$this->addError($ATMdb, 'cantMatchDataLine', $data['reference_financement'], $dataline, '', 'WARNING');	
		}
		else {
			$f->okPourFacturation=1;
		}
		
		$f->echeance = $echeance;
		$f->montant = $montant;
		$f->numero_prochaine_echeance = $data['nb_echeance'];
		
		if($f->duree<$f->numero_prochaine_echeance)$f->duree = $f->numero_prochaine_echeance;
		
		$f->periodicite = $data['periodicite'];
		$f->date_debut = $date_debut;
		$f->date_fin = $date_fin;
		
		$f->save($ATMdb);
		$this->nb_update++;
		
		$ATMdb->close();
		
		return true;
	}


	function importLineTiers(&$ATMdb, $dataline) {
		global $user;
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si tiers existant dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		//print $sql;
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleClientFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingClient', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Construction de l'objet final
		$societe = new Societe($this->db);
		if($rowid > 0) {
			$societe->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$societe->{$key} = $value;
		}
		
		$societe->idprof1 = substr($societe->idprof2,0,9);

		// Mise à jour ou création
		
		if($rowid > 0) {
			$res = $societe->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $societe->create($user);
			// Erreur : la création n'a pas marché
			
			if($res < 0) {
				//print 'NOK';
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				//print 'OK';
				$this->nb_create++;
			}
		}
		
		return true;
	}

	function importLineFactureMateriel(&$ATMdb, $dataline) {
		global $user;
		$sqlSearchFacture = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE %s = '%s'";
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleFactureFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingFacture', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			} else {
				$this->addError($ATMdb, 'ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingClient', $data[$this->mapping['search_key_client']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		$data['socid'] = $fk_soc;
		
		// Construction de l'objet final
		$facture_mat = new Facture($this->db);
		if($rowid > 0) {
			$facture_mat->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$facture_mat->{$key} = $value;
		}
		
		// Gestion des avoirs
		if(!empty($data['facture_annulee'])) {
			// Recherche de la facture annulee par l'avoir
			$fac_annulee_id = 0;
			$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']]);
			$resql = $this->db->query($sql);
			if($resql) {
				$num = $this->db->num_rows($resql);
				if($num == 1) { // Enregistrement trouvé, mise à jour
					$obj = $this->db->fetch_object($resql);
					$fac_annulee_id = $obj->rowid;
				} else if($num > 1) { // Plusieurs trouvés, erreur
					$this->addError($ATMdb, 'ErrorMultipleFactureFound', $data[$this->mapping['search_key_fac_annulee']], $dataline, $sql);
					return false;
				}
			} else {
				$this->addError($ATMdb, 'ErrorWhileSearchingFacture', $data[$this->mapping['search_key_fac_annulee']], $dataline, $sql, 'ERROR', true);
				return false;
			}
			$facture_mat->type = 2;
			$facture_mat->source = $fac_annulee_id;
		}
		
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'])) {
			// Mise à jour de l'affaire
			$affaire->montant = $this->validateValue('total_ht',$data['total_ht']);	
			$affaire->save($ATMdb);	
			
			// Création du lien entre affaire et facture
			$facture_mat->linked_objects['affaire'] = $affaire->getId();
			
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
					$this->addError($ATMdb, 'ErrorMaterielNotFound', $serial, $dataline);
				}
			}
			
			// Création du dossier de financement si non existant
			$financement=new TFin_financement;
			if(!empty($data['reference_dossier_interne']) && !$financement->loadReference($ATMdb, $data['reference_dossier_interne'],'CLIENT')) {
				$dossier = new TFin_dossier;
				if($dossier->addAffaire($ATMdb, $affaire->rowid)) {
					$dossier->montant = $data['total_ht'];
					$dossier->nature_financement = $affaire->nature_financement;
					$dossier->financement->montant = $data['total_ht'];
					$dossier->financement->reference = $data['reference_dossier_interne'];
					if($affaire->nature_financement=='EXTERNE') {
						unset($dossier->financement);
					}
					$dossier->save($ATMdb);
				} else {
					$this->addError($ATMdb, 'ErrorCreatingDossierOnThisAffaire', $data['code_affaire'], $dataline, '', 'ERROR', true);
				}
			}
		}
		
		// Mise à jour ou création
		if($rowid > 0) {
			$res = $facture_mat->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $facture_mat->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		// Actions spécifiques
		// On repasse en brouillon pour ajouter la ligne
		$facture_mat->set_draft($user);
		// On ajoute la ligne
		$facture_mat->addline($facture_mat->id, 'Matricule '.$data['matricule'], $data['total_ht'], 1, 19.6);
		// Force la validation avec numéro de facture
		$facture_mat->validate($user, $data[$this->mapping['search_key']]); // Force la validation avec numéro de facture
		
		return true;
	}

	function importLineFactureLocation(&$ATMdb, $dataline) {
		global $user;
		$sqlSearchFacture = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE %s = '%s'";
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleFactureFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingFacture', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			} else {
				$this->addError($ATMdb, 'ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingClient', $data[$this->mapping['search_key_client']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		$data['socid'] = $fk_soc;
		
		// Construction de l'objet final
		$facture_loc = new Facture($this->db);
		if($rowid > 0) {
			$facture_loc->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$facture_loc->{$key} = $value;
		}

		// Gestion des avoirs
		if(!empty($data['facture_annulee'])) {
			// Recherche de la facture annulee par l'avoir
			$fac_annulee_id = 0;
			$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']]);
			$resql = $this->db->query($sql);
			if($resql) {
				$num = $this->db->num_rows($resql);
				if($num == 1) { // Enregistrement trouvé, mise à jour
					$obj = $this->db->fetch_object($resql);
					$fac_annulee_id = $obj->rowid;
				} else if($num > 1) { // Plusieurs trouvés, erreur
					$this->addError($ATMdb, 'ErrorMultipleFactureFound', $data[$this->mapping['search_key_fac_annulee']], $dataline, $sql);
					return false;
				}
			} else {
				$this->addError($ATMdb, 'ErrorWhileSearchingFacture', $data[$this->mapping['search_key_fac_annulee']], $dataline, $sql, 'ERROR', true);
				return false;
			}
			$facture_loc->type = 2;
			$facture_loc->source = $fac_annulee_id;
		}
		
		
		// Mise à jour ou création
		if($rowid > 0) {
			$res = $facture_loc->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}
		} else {
			$res = $facture_loc->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_create++;
			}
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
		
		/*
		 * Création du lien  affaire/facture
		 */
		$financement=new TFin_financement;
		if($financement->loadReference($ATMdb, $data['reference_dossier_interne'],'CLIENT')) {
			/* OK */
			$dossier=new TFin_dossier_affaire;
			$dossier->load($ATMdb, $financement->fk_fin_dossier);

			/*
			 * $dossier->addLiaisonFacture()
			 * 
			 */
							
		}
		else {
			/* PAS OK */
			$this->addError($ATMdb, 'ErrorWhereIsFinancement', $data[$this->mapping['search_key_client']], $dataline, $sql);
			return false;
		}
		
		$this->db->commit();
		
		return true;
	}

	function importLineFactureLettree(&$ATMdb, $dataline) {
		global $user;
		$sqlSearchFacture = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		if (!preg_match('/^[A-Z]+$/', $data['code_lettrage'])) {
			// Code lettrage en minuscule = pré-lettrage = ne pas prendre en compte (ajout d'un addWarning ou addInfo ?)
			return false;
		}
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleFactureFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			} else {
				$this->addError($ATMdb, 'ErrorFactureNotFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingFacture', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Construction de l'objet final
		$facture_loc = new Facture($this->db);
		$facture_loc->fetch($rowid);
		$res = $facture_loc->set_paid($user, '', $data['code_lettrage']);
		if($res < 0) {
			$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
			return false;
		} else {
			$this->nb_update++;
		}

		return true;
	}

	function importLineAffaire(&$ATMdb, $dataline) {
		global $user, $db;
		/*
		 *	référence	date_affaire, code_client login_user
		 *  "002-53740";"24/09/2012";"012469";"dpn"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;
		
		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		$commercial = new User($db);
		if(!$commercial->fetch('',$data[$this->mapping['search_key_user']])) {
			// Pas d'erreur si l'utilisateur n'est pas trouvé, lien avec l'utilisateur admin
			$fk_user = $user->id;
			//$this->addError($ATMdb, 'ErrorUserNotFound', $data[$this->mapping['search_key_user']], $dataline);
			//return false;
		}
		else {
			$fk_user = $commercial->id;
		}
		
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array($this->mapping['search_key_client']=>$data[$this->mapping['search_key_client']]));
		if(count($TRes)==0) {
			$this->addError($ATMdb, 'ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		}
		else if(count($TRes) > 1) { // Plusieurs trouvés, erreur
			$this->addError($ATMdb, 'ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		} else {
			$fk_soc = $TRes[0];
		}
		
		$a=new TFin_affaire;
		$a->loadReference($ATMdb, $data[$this->mapping['search_key']]);
		
		if($a->fk_soc > 0 && $a->fk_soc != $fk_soc) { // client ne correspond pas
			$this->addError($ATMdb, 'ErrorClientDifferent', $data[$this->mapping['search_key']], $dataline);
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
		global $user;	
		
		$produit =new Product($this->db);
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

	function importLineMateriel(&$ATMdb, $dataline) {
	/*
	 * J'insére les produits sans les lier à l'affaire. C'est l'import facture matériel qui le fera
	 */
		global $user,$conf;
		/*
		 *	serial_number,libelle_produit, date_achat, marque, type_copie, cout_copie
		 *  "C2I256312";"ES 256 COPIEUR STUDIO ES 256";"06/12/2012";"TOSHIBA";"MCENB";"0,004"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;
		
		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
	
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

	function importLineCommercial(&$ATMdb, $dataline) { 
		global $user, $conf, $db;
		/*
		 *  code client; type_activité;login user
		 *  "000012";"Copieur";"ABX"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		$c=new TCommercialCpro;

		$commercial = new User($db);
		if(!$commercial->fetch('',$data[$this->mapping['search_key']])) {
			// Pas d'erreur si l'utilisateur n'est pas trouvé, pas de lien créé
			//$this->addError($ATMdb, 'ErrorUserNotFound', $data[$this->mapping['search_key']], $dataline);
			return false;
		}
		else {
			$fk_user = $commercial->id;
		}
		
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array('code_client'=>$data[$this->mapping['search_key_client']], 'entity' => $conf->entity));

		if(count($TRes)==0) {
			$this->addError($ATMdb, 'ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		}
		else if(count($TRes) > 1) { // Plusieurs trouvés, erreur
			$this->addError($ATMdb, 'ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		} else {
			$fk_soc = $TRes[0];
		}
		
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

	function importLineScore(&$ATMdb, $dataline) {
		global $user;
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si tiers existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError($ATMdb, 'ErrorMultipleClientFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			} else {
				$this->addError($ATMdb, 'ErrorClientNotFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError($ATMdb, 'ErrorWhileSearchingClient', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Construction de l'objet final
		$score = new TScore();

		foreach ($data as $key => $value) {
			$score->{$key} = $value;
		}
		
		$score->fk_soc = $fk_soc;
		$score->fk_import = $this->getId();
		$score->fk_user_author = $user->id;

		$res = $score->save($ATMdb);
		// Erreur : la création n'a pas marché
		if($res < 0) {
			$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
			return false;
		} else {
			$this->nb_create++;
		}
		
		// Mise à jour de la fiche tiers
		$societe = new Societe($this->db);
		$societe->fetch($fk_soc);
		$societe->capital = $this->validateValue('capital', $data['capital']);
		$societe->fk_forme_juridique = $this->validateValue('forme_juridique', $data['forme_juridique']);
		$societe->update($societe->id, $user);
		
		return true;
	}

	function checkData($dataline) {
		// Vérification cohérence des données
		
		/*if(count($this->mapping['mapping']) != count($dataline)) {
			$this->addError($ATMdb, 'ErrorNbColsNotMatchingMapping', $dataline);
			return false;
		}
		*/
		return true;
	}
	
	function contructDataTab($dataline) {
		// Construction du tableau de données
		$data = array();
		array_walk($dataline, 'trim');
		
		foreach($this->mapping['mapping'] as $k=>$field) {
			$data[$field] = $dataline[$k-1];
			$data[$field] = $this->validateValue($field,$data[$field]);
			
		}
		
		if(isset($dataline[9999])) $data['idLeaser'] = $dataline[9999];
		
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
		if(!empty($this->mapping['format'][$key])) {
			switch($this->mapping['format'][$key]) {
				case 'date':
					list($day, $month, $year) = explode("/", $value);
					$value = dol_mktime(0, 0, 0, $month, $day, $year);
					break;
				case 'date_english':
					$sep = (strpos($value,'-')===false) ? '/': '-';
					list($year, $month, $day) = explode($sep, $value);
					$value = mktime(0, 0, 0, $month, $day, $year);
					
					break;
				case 'float':
					$value = strtr($value, array(',' => '.', ' ' => ''));
					break;
				default:
					break;
			}
		}
		return $value;
	}
}
?>
