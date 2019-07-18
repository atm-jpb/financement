<?php

class TImport extends TObjetStd {
	
	public $TEntityByPrefix = array();
	
	function __construct() {
		global $conf;
		
		parent::set_table(MAIN_DB_PREFIX.'fin_import');
		parent::add_champs('fk_user_author,entity','type=entier;');
		parent::add_champs('nb_lines,nb_errors,nb_create,nb_update','type=entier;');
		parent::add_champs('date','type=date;');
		parent::add_champs('type_import,filename,artis','type=chaine;');
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
			,'ecritures_non_lettrees' => 'Fichier facture non lettrées'
			,'facture_nonlettree' => 'Fichier facture avec incident de paiement'
			,'solde_client' => 'Fichier des soldes client'
			,'score' => 'Fichier score'
		);
		$this->TType_import = array(
			'fichier_leaser' => 'Fichier leaser',
			'dossier_init_adossee'=>'Import initial adosées',
			'dossier_init_mandatee'=>'Import initial mandatées',
			'dossier_init_all'=>'Import initial',
			'dossier_init_loc_pure'=>'Import initial Loc Pures',
			'materiel_parc'=>'Import matricules parc'
		);
		$this->current_line = array();
		
		if (!empty($conf->global->FINANCEMENT_IMPORT_PREFIX_FOR_ENTITY))
		{
			$tab = explode(';', $conf->global->FINANCEMENT_IMPORT_PREFIX_FOR_ENTITY);
			foreach ($tab as $string)
			{
				if (empty($string)) continue;
				
				$tab2 = explode(':', $string);
				// [0] = prefix ; [1] = fk_entity
				if (empty($tab2[0]) || empty($tab2[1]) || !is_numeric($tab2[1])) continue;
				
				$this->TEntityByPrefix[$tab2[0]] = (int) $tab2[1];
			}
		}
		
	
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
	
	function importFile(&$ATMdb, $filePath) {
		global $TInfosGlobale;
		
		// Traitement du fichier contenant toutes les factures non payées, on classe d'abord payées toutes les factures Dolibarr
		if($this->type_import == 'ecritures_non_lettrees') {
			$nbfact = filesize($filePath) / 42;
			if($nbfact < 4000) { // Sécurité, si le fichier contient moins de 4000 lignes on ne le traite pas, pour éviter de mettre toutes les factures en payées si le fichier est vide
				$this->addError($ATMdb, 'ErrorFileTooSmall', $nbfact, 'ERROR');
				return false;
			}

			$this->classifyPaidAllInvoices($ATMdb);
		}
		
		// Traitement du fichier contenant tous les soldes client, on vide d'abord la table
		if($this->type_import == 'solde_client') {
			$sql = 'TRUNCATE TABLE '.MAIN_DB_PREFIX.'societe_solde';
			$ATMdb->Execute($sql);
		}

		$fileHandler = fopen($filePath, 'r');
		
		$TInfosGlobale = array();
		while($dataline = fgetcsv($fileHandler, 4096, FIN_IMPORT_FIELD_DELIMITER, FIN_IMPORT_FIELD_ENCLOSURE)) {
			$this->importLine($ATMdb, $dataline, $TInfosGlobale);
		}
		fclose($fileHandler);
	}
	
	function importLine(&$ATMdb, $dataline, &$TInfosGlobale) {
		global $db;
		
		$this->current_line = $dataline;
		
		// Compteur du nombre de lignes
		$this->nb_lines++;
		// On save l'import tout les X enregistrements traités pour voir l'avancement de l'import
		if($this->nb_lines % 50 == 0) $this->save($ATMdb);
		if($this->nb_lines % 500 == 0) sleep(1);
		if(!$this->checkData()) return false;
		$data = $this->contructDataTab();

		switch ($this->type_import) {
			case 'client':
				$this->importLineTiers($ATMdb, $data);
				break;
			case 'materiel':
				$this->importLineMateriel($ATMdb, $data);
				break;
			case 'materiel_parc':
				$this->importLineMaterielParc($ATMdb, $data);
				break;
			case 'facture_materiel':
				$this->getLeaserList($ATMdb, $TInfosGlobale);
				$this->importLineFactureMateriel($ATMdb, $data, $TInfosGlobale);
				break;
			case 'facture_location':
				$this->createFacture($ATMdb, $data,$TInfosGlobale);
				$this->importLineFactureLocation($ATMdb, $data, $TInfosGlobale);
				$this->importLineFactureIntegrale($ATMdb, $data, $TInfosGlobale);
				break;
			case 'facture_lettree':
				//$this->importLineLettrage($ATMdb, $data, 'lettree');
				break;
			case 'ecritures_non_lettrees':
				$this->importLineLettrage($ATMdb, $data, 'non_lettree');
				break;
			case 'facture_nonlettree':
				$this->importLineLettrage($ATMdb, $data, 'delettree');
				break;
			case 'solde_client':
				$this->importSoldeClient($ATMdb, $data);
				break;
			case 'commercial':
				$this->importLineCommercial($ATMdb, $data, $TInfosGlobale);
				break;
			case 'affaire':
				$this->importLineAffaire($ATMdb, $data, $TInfosGlobale);
				break;
			case 'fichier_leaser':
				
				$this->importFichierLeaser($ATMdb, $data, $TInfosGlobale);
				
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
			
			case 'dossier_init_loc_pure':
				$this->importDossierInitLocPure($ATMdb, $data, $TInfosGlobale);
				break;
			
			default:
				
				break;
		}
		
		$db->commit();
	}

	function createFacture(&$ATMdb,&$data,&$TInfosGlobale){
		global $db,$user;
		
		// Recherche si facture existante dans la base
		$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], false, $data['entity']);
		// Recherche du client
		$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true, true, $data['entity']);
		// Recherche du contrat
		$contid = $this->_recherche_dossier($ATMdb, $data['reference_dossier_interne'],true, $data['entity']);
		
		// Si existe pas alors on la créé
		if(!$facid && $socid && $contid){

			$data['socid'] = $socid;
			switchEntity($data['entity']);

			$facture_loc = new Facture($db);
			
			foreach ($data as $key => $value) {
				$facture_loc->{$key} = $value;
			}
	
			// Gestion des avoirs
			if(!empty($data['facture_annulee'])) {
				// Recherche de la facture annulee par l'avoir
				$avoirid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']], true, true, $data['entity']);
				if($avoirid === false) return false;
				
				$facture_loc->type = 2;
				$facture_loc->fk_facture_source = $avoirid;
			}
			
			$res = $facture_loc->create($user);
			
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_loc->error);
				return false;
			} else {
				$this->nb_create++;
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($facture_loc), $facture_loc->id,'create',$data);
			}
			
			// Force la validation avec numéro de facture
			$facture_loc->validate($user, $data[$this->mapping['search_key']]);
			
			// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
			$facture_loc->date_lim_reglement = $data['date_lim_reglement'];
			$res = $facture_loc->update($user, 0);

			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_loc->error);
				return false;
			} else {
				$facture_loc->add_object_linked('dossier', $contid);
				$this->updateObjectEntity($facture_loc, $data['entity']);
				$TInfosGlobale['newfacture'][$data['entity'].'-'.$data[$this->mapping['search_key']]] = $facture_loc->id;
			}
		}
	}

	function importFichierLeaser(&$ATMdb, $data, &$TInfosGlobale) {
		/*$ATMdb->debug=true;
		echo '<hr><pre>'.$this->nb_lines;
		print_r($data);
		echo '</pre>';*/
	
		/*if($data['echeance']==0) {
			return false;
		}*/
		if(empty($data['reference'])) {
			return false;
		}
		$entities = $this->get_entity_groups($data['entity']);
		$f=new TFin_financement;
		if($f->loadReference($ATMdb, $data['reference'], 'LEASER', $entities)) { // Recherche du financement leaser par référence
			// Le financement leaser a été trouvé avec la référence contrat leaser
		} else if($f->loadReference($ATMdb, $data['reference'], 'CLIENT', $entities)) { // Recherche du financement leaser par référence contrat client
			// Le financement leaser a été trouvé avec la référence contrat leaser
		} else if (!empty($data['reference_dossier_interne']) && $f->loadReference($ATMdb, $data['reference_dossier_interne'], 'CLIENT', $entities)) { // Recherche du financement client par référence CPRO
			// Le financement client a été trouvé avec la référence CPRO
		} else if ($f->loadOrCreateSirenMontant($ATMdb, $data)) { // Recherche du financement leaser par siren et montant
			// Le financement leaser a été trouvé ou créé par le siren et le montant de l'affaire
		} else {
			$this->addError($ATMdb, 'cantFindOrCreateFinancement', $data['reference']);
			return false;
		}
		
		$dossier = new TFin_dossier();
		if($dossier->load($ATMdb, $f->fk_fin_dossier,true)) { // Chargement du dossier correspondant
			
			if($dossier->nature_financement == 'EXTERNE') { // Dossier externe => MAJ des informations
				// Echéance à 0 dans le fichier, on classe le dossier a soldé
				// 14.10.15 : suite échange avec Damien on fait sauter cette règle
				/*if($data['echeance'] == 0 && $dossier->financementLeaser->date_solde == 0) {
					$dossier->financementLeaser->date_solde = time();
					$data['echeance'] = $dossier->financementLeaser->echeance;
				}*/
				
				foreach ($data as $key => $value) {
					if($value === '') continue;
					$dossier->financementLeaser->{$key} = $value;
				}
				$dossier->financementLeaser->fk_soc = $data['idLeaser'];
				
				$dossier->financementLeaser->duree /= $dossier->financementLeaser->getiPeriode();
				//pre($dossier->financementLeaser,true);echo '<hr>';flush();
			} else { // Dossier interne => Vérification des informations
				// On ne vérifie plus les données pour mettre Oui dans Bon pour facturation (fait manuellement)
				// Mais on permet de mettre à jour l'incident de paiement
				$dossier->financement->incident_paiement=$data['incident_paiement'];
			}
			
			/*if($dossier->entity != $data['entity']) {
				$dossier->entity = $data['entity'];
				$dossier->load_affaire($ATMdb);
				foreach ($dossier->TLien as $i => $TData) {
					$a = &$TData->affaire;
					$a->entity = $data['entity'];
					if($a->rowid > 0) $a->save($ATMdb);
				}
				
			}*/

			// Cas particulier (colonne 17) permettant d'indiquer si le solde du dossier doit être affiché ou non
			if(isset($data['display_solde'])) $dossier->display_solde = $data['display_solde'];
			// On met à jour l'entité du dossier (changement possible lors de fusion d'entités)
			$dossier->entity = $data['entity'];
			
			$dossier->save($ATMdb);
			$this->nb_update++;
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($dossier), $dossier->getId(),'update',$data);

			$TInfosGlobale[] = $dossier->financementLeaser->getId();
			
			//Ajout traitement
			//Si colonne biens renseigné alors on créé les equipements si inexistant
			//Puis ajout de la liaison equipement -> affaire sans passer par la facture matériel
			if(!empty($data['biens'])){
				$TRefBiens = explode(';', $data['biens']);
				
				foreach($TRefBiens as $refBien){
					$serial = trim($refBien);
				
					$asset=new TAsset;
					if(!$asset->loadReference($ATMdb, $serial)) { // Non trouvé dans la base, on créé
						$asset->entity = $dossier->entity;
						$asset->serial_number = $serial;
						$asset->fk_soc = $dossier->TLien[0]->affaire->fk_soc;
						$asset->save($ATMdb);
					}
					
					//Ajout du lien à l'affaire
					if($dossier->TLien[0]->affaire){
						$asset->add_link($dossier->TLien[0]->affaire->getId(),'affaire');
					}

					$asset->save($ATMdb);
				}
			}

			return true;

		}
		
		return false;
	}

	function solde_dossiers_non_presents(&$ATMdb, $idLeaser, &$TInfosGlobale) {
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."fin_dossier_financement f ";
		$sql.= "WHERE f.type = 'LEASER' ";
		$sql.= "AND f.fk_soc = ".$idLeaser." ";
		$sql.= "AND f.date_solde < '1970-00-00 00:00:00' ";
		echo $sql;
		
		$TRes = TRequeteCore::_get_id_by_sql($ATMdb, $sql);
		pre($TRes, true);
		pre($TInfosGlobale, true);
		$f = new TFin_financement();
		foreach ($TRes as $idFinancement) {
			if(!in_array($idFinancement, $TInfosGlobale)) {
				$f->load($ATMdb, $idFinancement);
				$f->date_solde = strtotime('1998-07-12');
				$f->save($ATMdb);
			}
		}
	}

	function importLineTiers(&$ATMdb, $data) {
		global $user, $db;

		// Recherche si tiers existant dans la base via code Artis
		$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], false, true, $data['entity']);
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
			$res = $societe->update($socid, $user, 1, 1);
			
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $societe->error);
				return false;
			} else {
				
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($societe), $societe->id,'update',$data);
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
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($societe), $societe->id,'create',$data);
			}
		}
		
		return true;
	}

	function importLineFactureMateriel(&$ATMdb, $data, &$TInfosGlobale) {
		global $user, $db;

		// Recherche si facture existante dans la base
		$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], false, $data['entity']);
		if($facid === false) return false;


		// Recherche tiers associé à la facture existant dans la base
		$key = !empty($this->artis) ? 'code_artis_'.$this->artis : 'code_artis';
		$socid = $this->_recherche_fournisseur($ATMdb, $key, '%'.$data[$this->mapping['search_key_client']].'%', false, true, 17); // Recherche du leaser sur base commune
		if(!$socid) return false;
		
		// 2016.11.18 MKO : si la facture matériel ne concerne pas un leaser, on ne l'importe pas, ni le contrat
		if(!in_array($socid, $TInfosGlobale['TIdLeaser'])) {
			$this->addError($ATMdb, 'ErrorMaterielNonFinance', $data[$this->mapping['search_key']], 'WARNING');
			return false;
		}
		
		$data['socid'] = $socid;
		
		// Construction de l'objet final
		$facture_mat = new Facture($db);
		if($facid > 0) {
			switchEntity(17); // Permet de fetcher toutes les factures car TEAM admin voit tout
			$facture_mat->fetch($facid);
		}

		foreach ($data as $key => $value) {
			$facture_mat->{$key} = $value;
		}
			
		// Gestion des avoirs
		if(!empty($data['facture_annulee'])) {
			$avoirid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key_fac_annulee']], true, $data['entity']);
			if($avoirid === false) return false;
			
			$facture_mat->type = 2;
			$facture_mat->fk_facture_source = $avoirid;
		}

		switchEntity($data['entity']);

		// Création des liens
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'], false, $data['entity'])) {
			// Mise à jour ou création de la facture
			if($facid > 0) {
				$res = $facture_mat->update($user);
				// Association à la bonne entity
				if ($facture_mat->entity != $data['entity']) $this->updateObjectEntity($facture_mat, $data['entity']);
				
				// Erreur : la mise à jour n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_mat->error);
					return false;
				} else {
					$this->nb_update++;
					TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($facture_mat), $facture_mat->id,'update',$data);
				}			
			} else {
				$res = $facture_mat->create($user);
				// Erreur : la création n'a pas marché
				if($res < 0) {
					$this->addError($ATMdb, 'ErrorWhileCreatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture_mat->error);
					return false;
				} else {
					// Association à la bonne entity
					if ($facture_mat->entity != $data['entity']) $this->updateObjectEntity($facture_mat, $data['entity']);
					$this->nb_create++;
					TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($facture_mat), $facture_mat->id,'create',$data);
				}
			}
			
			// Si l'affaire n'a pas de nature (INTERNE/EXTERNE), on le déduit du leaser
			if(empty($affaire->nature_financement)) {
				$affaire->nature_financement = $this->get_nature_by_leaser($ATMdb, $socid);
			}
			
			// Mise à jour de l'affaire
			$affaire->montant = $this->validateValue('total_ht',$data['total_ht']);	
			$affaire->save($ATMdb);
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($affaire), $affaire->getId(),'update',$data);
			
			// Création des liens entre affaire et matériel
			$TSerial = explode(' - ',$data['matricule']);
		
			foreach($TSerial as $serial) {
				$serial = trim($serial);
				
				$asset=new TAsset;
				if($asset->loadReference($ATMdb, $serial, $data['entity'])) {
					$asset->fk_soc = $affaire->fk_soc;
					// Association à la bonne entity
					$asset->entity = $affaire->entity;
					
					$asset->add_link($affaire->getId(),'affaire');
					$asset->add_link($facture_mat->id,'facture');
					
					$asset->save($ATMdb);
					TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($asset), $asset->getId(),'update',$data);
				}
				else {
					$this->addError($ATMdb, 'ErrorMaterielNotFound', $serial);
				}
			}
			
			// Création du dossier de financement si non existant
			$financement=new TFin_financement;
			if(!empty($data['reference_dossier_interne']) && !$financement->loadReference($ATMdb, $data['reference_dossier_interne'],'CLIENT', $data['entity'])) {
				$dossier = new TFin_dossier;
				if(!$dossier->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'], $data['entity'])) {
					if($dossier->addAffaire($ATMdb, $affaire->getId())) {
						// Association à la bonne entity
						$dossier->entity = $affaire->entity;
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
						TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($dossier), $dossier->getId(),'create',$data);
					} else {
						$this->addError($ATMdb, 'ErrorCreatingDossierOnThisAffaire', $data['code_affaire'], 'ERROR');
					}
				}
			} else if(!empty($data['reference_dossier_interne'])) { // Lien avec l'affaire sinon
				$dossier = new TFin_dossier;
				$dossier->load($ATMdb, $financement->fk_fin_dossier);
				// Association à la bonne entity
				$dossier->entity = $affaire->entity;
						
				$addlink = true;
				//Gestion des Adjonction
				//Avant de faire la liaison
				//- si un dossier "-adj" est déjà lié à l'affaire, alors on ne fait pas de lien
				//- si un dossier initiale est déjà lié à l'affaire, on ne lie pas le dossier "-adj" à celle-ci
				
				//foreach($dossier->TLien as $i => $TFin_dossier_affaire){
				if(strpos(strtoupper($dossier->reference),'ADJ') !== FALSE) $addlink = false;
				if(strpos($dossier->reference,$data['reference_dossier_interne']) !== FALSE) $addlink = false;
				//}
				/*echo $data['reference_dossier_interne'].'<br>';
				pre($dossier,true);exit;*/
				if($addlink) $dossier->addAffaire($ATMdb, $affaire->getId());
				$dossier->save($ATMdb);
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($dossier), $dossier->getId(),'update',$data);
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
		//$facture_mat->add_object_linked('affaire', $affaire->getId());
		
		//Vérification si lien affaire => facture matériel déjà existant
		$ATMdb->Execute("SELECT rowid FROM ".MAIN_DB_PREFIX."element_element WHERE sourcetype = 'affaire' AND targettype = 'facture' AND fk_target = ".$facture_mat->id);
		
		if($ATMdb->Get_line()){
			$this->addError($ATMdb, 'ErrorCreatingLinkAffaireFactureMaterielAlreadyExist', $data['code_affaire']." => ".$facture_mat->ref, 'WARNING');
		}
		else{
			// Création du lien facture matériel / affaire financement
			$facture_mat->add_object_linked('affaire', $affaire->getId());
		}
		
		// Actions spécifiques
		// On repasse en brouillon pour ajouter la ligne
		$facture_mat->set_draft($user);
		
		//On choisis le taux de tva en fonction de la date limite de règlement : 19.6% avant 2014, 20% après 2014
		if($data['date_lim_reglement'] < strtotime("2014-01-01"))
			$taux_tva = 19.6;
		else
			$taux_tva = 20;
		
		// On ajoute la ligne
		$facture_mat->addline('Matricule(s) '.$data['matricule'], $data['total_ht'], 1, $taux_tva);
		// Force la validation avec numéro de facture
		$facture_mat->validate($user, $data[$this->mapping['search_key']]); // Force la validation avec numéro de facture
		
		// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
		$facture_mat->date_lim_reglement = $data['date_lim_reglement'];
		$facture_mat->update($user, 0);
		TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($facture_mat), $facture_mat->id,'update',$data);
		
		return true;
	}

	public function getObjectEntity(&$object)
	{
		global $db;
		
		if (!empty($object->entity)) return $object->entity;
		
		$sql = 'SELECT entity FROM '.MAIN_DB_PREFIX.$object->table_element.' WHERE rowid = '.$object->id;
		$resql = $db->query($sql);
		if ($resql && ($r = $db->fetch_object($resql))) 
		{
			$object->entity = $r->entity;
			return $r->entity;
		}
		
		return -1;
	}
	
	public function updateObjectEntity(&$object, $fk_entity)
	{
		global $db;
		
		$sql = 'UPDATE '.MAIN_DB_PREFIX.$object->table_element.' SET entity = '.$fk_entity.' WHERE rowid = '.$object->id;
		$resql = $db->query($sql);
		
		if ($resql) return true;
		else return false;
	}

	function importLineFactureLocation(&$ATMdb, $data, &$TInfosGlobale) {
		global $user, $db;
		
		// On ne traite que les lignes ayant un code service qui nous intéresse
		if(! $this->checkCodeService($data['ref_service'])) return false;
		
		$firstLine = true;

		$facid = $TInfosGlobale['newfacture'][$data['entity'].'-'.$data[$this->mapping['search_key']]];

		if($facid){
			$facture_loc = new Facture($db);
			$facture_loc->fetch($facid);
	
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
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, 'Service', $fk_service,'create');
			
			//On choisis le taux de tva en fonction de la date limite de règlement : 19.6% avant 2014, 20% après 2014
			if($data['date_lim_reglement'] < strtotime("2014-01-01"))
				$taux_tva = 19.6;
			else
				$taux_tva = 20;
			
			// On ajoute la ligne
			$facture_loc->addline($data['libelle_ligne'], $data['pu'], $data['quantite'], $taux_tva,0,0,$fk_service, 0, '', '', 0, 0, '', 'HT', 0, 0, -1, 0, '', 0, 0, null, 0, $data['libelle_ligne']);
			// Force la validation avec numéro de facture
			$facture_loc->validate($user, $data[$this->mapping['search_key']]);
			
			// La validation entraine le recalcul de la date d'échéance de la facture, on remet celle fournie
			$facture_loc->date_lim_reglement = $data['date_lim_reglement'];
			$facture_loc->update($user, 0);
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($facture_loc), $facture_loc->id,'update',$data);
			
			// 2014.10.30 : Evolution pour stocker assurance, maintenance et loyer actualisé
			$facture_loc->fetchObjectLinked('','dossier');
			if(!empty($facture_loc->linkedObjectsIds['dossier'][0])) {
				$dossier = new TFin_dossier;
				$dossier->load($ATMdb, $facture_loc->linkedObjectsIds['dossier'][0], false);
				$dossier->load_affaire($ATMdb);
				
				if(!empty($dossier->TLien[0]->affaire) && ($dossier->TLien[0]->affaire->contrat == 'FORFAITGLOBAL' || $dossier->TLien[0]->affaire->contrat == 'INTEGRAL')) {
					if($this->checkCodeService($data['ref_service'], 'assurance')) {
						$dossier->financement->assurance_actualise = $data['total_ht'];
					}
					
					// Addition de différents SSC pour le calcul du montant prestation
					// Addition de différents SSC pour le calcul du loyer actualisé
					if($this->checkCodeService($data['ref_service'], 'presta')) {
						if($firstLine) {
							$dossier->financement->montant_prestation = $data['pu'];
							$firstLine = false;
						} else {
							$dossier->financement->montant_prestation+= $data['pu'];
						}
					}
				
					// Addition de différents SSC pour le calcul du loyer actualisé
					if($this->checkCodeService($data['ref_service'], 'loyer_actu')) {
						if($firstLine) {
							$dossier->financement->loyer_actualise = $data['pu'];
							$firstLine = false;
						} else {
							$dossier->financement->loyer_actualise+= $data['pu'];
						}
					}
					if(!empty($data['type_regul'])){
						$dossier->type_regul = $data['type_regul'];
					}
					
					$dossier->save($ATMdb);
				}
			}
		}
		else{
			return false;
		}
		
		return true;
	}

	function importLineFactureIntegrale(&$ATMdb, $data, &$TInfosGlobale) {
		global $db;
		//pre($data,true);

		$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], false, $data['entity']);
		if(empty($facid)) return false;

		$facture_loc = new Facture($db);
		$facture_loc->fetch($facid);
		
		if(empty($TInfosGlobale['integrale'][$facture_loc->ref])) {
			$facture_loc->fetchObjectLinked('','dossier');
			if(!empty($facture_loc->linkedObjectsIds['dossier'][0])) {
				//pre($facture_loc, true);
				$dossier = new TFin_dossier;
				$dossier->load($ATMdb, $facture_loc->linkedObjectsIds['dossier'][0],false);
				$dossier->load_affaire($ATMdb);
				
				// 2014.12.05 : on ne charge les données intégrale que si affaire de type intégral
				if(empty($dossier->TLien[0]->affaire) || $dossier->TLien[0]->affaire->contrat != 'INTEGRAL') {
					/*echo $facture_loc->id.'<br>';
					exit('la2');*/
					return false;
				}
				else{
					$TInfosGlobale['integrale'][$facture_loc->ref] = new TIntegrale();
					$TInfosGlobale['integrale'][$facture_loc->ref]->loadBy($ATMdb, $facture_loc->ref, $this->mapping['search_key']);
					//$TInfosGlobale['integrale'][$data[$this->mapping['search_key']]]->truc="llll".$data[$this->mapping['search_key']];
				}
				
			} else {
				return false;
			}

			//La première fois qu'on charge la facture, on reset les données intégrale
			$this->resetIntegrale($TInfosGlobale['integrale'][$facture_loc->ref]);
		}
		
		$integrale = &$TInfosGlobale['integrale'][$facture_loc->ref];
		//pre($integrale,true);exit("la");
		
		$integrale->facnumber = $facture_loc->ref;
		
		//Gère les frais de gestion lié à l'intégrale
		$this->importILFI_gestion($data,$integrale);
		
		$TRefSRVLabelCout = $this->checkCodeService('','label_cout',true);
		
		//Gère les copies NOIR
		$this->importILFI_noir($data,$integrale,$TRefSRVLabelCout);
		
		//Gère les copies COULEUR
		$this->importILFI_couleur($data,$integrale,$TRefSRVLabelCout);
		
		//pre($integrale,true);exit;
		
		$integrale->save($ATMdb);
		//pre($integrale,true);
		TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($integrale), $integrale->getId(),'update',$data);
	}

	function resetIntegrale(&$integrale){
		
		$integrale->vol_noir_engage = 0;
		$integrale->vol_noir_realise = 0;
		$integrale->vol_noir_facture = 0;
		$integrale->vol_coul_engage = 0;
		$integrale->vol_coul_realise = 0;
		$integrale->vol_coul_facture = 0;
		$integrale->cout_unit_noir = 0;
		$integrale->cout_unit_coul = 0;
		$integrale->fas = 0;
		$integrale->fass = 0;
		$integrale->frais_dossier = 0;
		$integrale->frais_bris_machine = 0;
		$integrale->frais_facturation = 0;
		$integrale->frais_dossier = 0;
		$integrale->frais_bris_machine = 0;
		$integrale->frais_facturation = 0;
		$integrale->total_ht_engage = 0;
		$integrale->total_ht_realise = 0;
		$integrale->total_frais = 0;
		$integrale->ecart = 0;
		
	}
	
	//Gère les frais de gestion lié à l'intégrale
	private function importILFI_gestion(&$data,&$integrale){
		
		// Gestion des frais divers
		// FASS
		if($this->checkCodeService($data['ref_service'], 'fass')) {
			if(empty($integrale->fass_somme)) { // Gestion FASS sur plusieurs lignes
				$integrale->fass	= $data['total_ht'];
				$integrale->fass_somme = true;
			} else {
				$integrale->fass	+= $data['total_ht'];
			}
		}
		// FAS
		//$TFAS = array('SSC101', 'SSC102', 'SSC106', 'SSC128');
		//if(in_array($data['ref_service'], $TFAS) 
		if ( $data['label_integrale'] == 'Frais d\'Accès au Service' 
			|| $data['label_integrale'] == 'Forfait d\'Accès au Service'
			|| $data['label_integrale'] == 'Forfait Frais d\'Accès au Service'
			|| strpos($data['label_integrale'], '(FAS)') !== false 
			|| substr($data['label_integrale'], -3) === 'FAS')
		{
		/*if(strpos($data['label_integrale'], '(FAS)') !== false || substr($data['label_integrale'], -3) === 'FAS'
			|| (strpos($data['label_integrale'],'Frais d\'Accès au Service') !== FALSE 
			|| strpos($data['label_integrale'],'Forfait d\'Accès au Service') !== FALSE)) {*/
			if(empty($integrale->fas_somme)) { // Gestion FAS sur plusieurs lignes
				$integrale->fas	= $data['total_ht'];
				$integrale->fas_somme = true;
			} else {
				$integrale->fas	+= $data['total_ht'];
			}
			
		}
		
		// Frais dossier
		if($this->checkCodeService($data['ref_service'], 'frais_dossier')) {
			$integrale->frais_dossier = $data['total_ht'];
		}
		// Frais bris de machine
		if($this->checkCodeService($data['ref_service'], 'assurance')) {
			$integrale->frais_bris_machine	= $data['total_ht'];
		}
		// Frais de facturation
		if($this->checkCodeService($data['libelle_ligne'], 'frais_facture')) {
			$integrale->frais_facturation	= $data['total_ht'];
		}
	} 
	
	//Gère les copies NOIR
	private function importILFI_noir(&$data,&$integrale,&$TRefSRVLabelCout){
		
		// ENGAGEMENT NOIR
		if($this->checkCodeService($data['ref_service'], 'eng_noir') && $data['total_ht'] != 0) {
			if(empty($integrale->materiel_noir)) {
				$integrale->materiel_noir = $data['matricule'];
				$integrale->vol_noir_engage = $data['quantite'];
				$integrale->vol_noir_realise = $data['quantite_integrale'];
				$integrale->vol_noir_facture = $data['quantite'];
			} else if($integrale->materiel_noir != $data['matricule'] && $data['total_ht'] != 0) {
				$integrale->materiel_noir = $data['matricule'];
				$integrale->vol_noir_engage+= $data['quantite'];
				$integrale->vol_noir_realise+= $data['quantite_integrale'];
				$integrale->vol_noir_facture+= $data['quantite'];
			}
			
			if($data['total_ht'] != 0)
				$integrale->cout_unit_noir = $data['cout_integrale'];
		}
		else{ // CAS DES AVOIRS, ON GARDE QUE LE TOTAL HT
			$integrale->total_ht_facture += $data['total_ht'];
		}
		
		// COPIE SUP NOIR
		if($this->checkCodeService($data['ref_service'], 'cop_sup_noir')) {
			$integrale->vol_noir_facture+= $data['quantite'];
		}
		// COPIE ECHUES NOIR
		if($this->checkCodeService($data['ref_service'], 'cop_ech_noir')) {
			$integrale->vol_noir_realise += $data['quantite_integrale'];
			$integrale->vol_noir_facture += $data['quantite'];
			
			if($data['total_ht'] != 0)
				$integrale->cout_unit_noir = $data['pu'];
		}
		
		// Enregistrement du détail du coût unitaire
		// Copies noires
		if($this->checkCodeService($data['ref_service'], 'couts_noir') && $data['pu'] > 0
			&& (stripos($data['label_integrale'], 'COPIES NB') || stripos($data['label_integrale'], 'COPIES N/B'))) {
			$integrale->{'cout_unit_noir_'.$TRefSRVLabelCout[$data['ref_service']]} = $data['pu'];
		}
		
	}

	//Gère les copies COULEUR
	private function importILFI_couleur(&$data,&$integrale,&$TRefSRVLabelCout){
		// ENGAGEMENT COULEUR
		if($this->checkCodeService($data['ref_service'], 'eng_coul') && $data['total_ht'] != 0) {
			if(empty($integrale->materiel_coul)) {
				$integrale->materiel_coul = $data['matricule'];
				$integrale->vol_coul_engage = $data['quantite'];
				$integrale->vol_coul_realise = $data['quantite_integrale'];
				$integrale->vol_coul_facture = $data['quantite'];
			} else if($integrale->materiel_coul != $data['matricule'] && $data['total_ht'] != 0) {
				$integrale->materiel_coul = $data['matricule'];
				$integrale->vol_coul_engage+= $data['quantite'];
				$integrale->vol_coul_realise+= $data['quantite_integrale'];
				$integrale->vol_coul_facture+= $data['quantite'];
			}
			
			if($data['total_ht'] != 0)
				$integrale->cout_unit_coul = $data['cout_integrale'];
		}
		// COPIE SUP COULEUR
		if($this->checkCodeService($data['ref_service'], 'cop_sup_coul')) {
			$integrale->vol_coul_facture+= $data['quantite'];
		}
		// COPIE ECHUES COULEUR
		if($this->checkCodeService($data['ref_service'], 'cop_ech_coul')) {
			$integrale->vol_coul_realise+= $data['quantite_integrale'];
			$integrale->vol_coul_facture+= $data['quantite'];
			
			if($data['total_ht'] != 0)
				$integrale->cout_unit_coul = $data['pu'];
		}
		
		// Enregistrement du détail du coût unitaire
		// Copies couleur
		if($this->checkCodeService($data['ref_service'], 'couts_coul') && $data['pu'] > 0 && stripos($data['label_integrale'], 'COPIES COULEUR')) {
			$integrale->{'cout_unit_coul_'.$TRefSRVLabelCout[$data['ref_service']]} = $data['pu'];
		}

	}

	function importLineLettrage(&$ATMdb, $data, $mode) {
		global $user, $db;
		
		// Recherche si facture existante dans la base
		$facid = $this->_recherche_facture($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], false, $data['entity']);
		if(!$facid) return false;
		
		// Construction de l'objet final
		$facture = new Facture($db);
		$facture->fetch($facid);
		
		// 3 cas possibles : lettree, non lettree (pas encore reglée), délettrée (rejet)
		if($mode == 'lettree') {
			$res = $facture->set_paid($user, '', $data['code_lettrage']);
		} else if ($mode == 'non_lettree') {
			$montant_total_ttc = $data['debit'] - $data['credit'];
			$facture->array_options['options_total_ttc'] = $montant_total_ttc;
			$facture->insertExtraFields();
			$facture->setPaymentMethods($data['mode_rglt']);
			$res = $facture->set_unpaid($user);
		} else if ($mode == 'delettree') {
			// On stocke le motif du rejet
			$facture->array_options['options_motif_impaye'] = $data['motif'];
			$facture->insertExtraFields();
			// On remet la facture en impayée
			$res = $facture->set_unpaid($user);
			// On met le dossier associé en "Incident de paiement"
			$facture->fetchObjectLinked(0,'dossier',$facid,'facture');
			if(!empty($facture->linkedObjectsIds['dossier'][0])) {
				$doss = new TFin_dossier();
				$doss->load($ATMdb, $facture->linkedObjectsIds['dossier'][0],false,false);
				$doss->load_financement($ATMdb);
				$doss->financement->incident_paiement = 'OUI';
				$doss->save($ATMdb);
			}
		}
		
		if($res < 0) {
			$this->addError($ATMdb, 'ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], 'ERROR', 2, $facture->error);
			return false;
		} else {
			$this->nb_update++;
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($facture), $facture->id,'update',$data);
		}

		return true;
	}
	
	function importSoldeClient(&$ATMdb, $data) {
		// Si solde à 0, on n'importe pas la donnée, inutile
		if(empty($data['solde'])) return false;
		
		// Recherche si tiers existant dans la base via code client Artis
		$socid = 0;
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array('code_client'=>$data['code_client']));
		if(count($TRes) == 1) $socid = $TRes[0];
		
		// Mise à jour du solde sur la fiche client en fonction de l'entité
		if($socid > 0) {
			$entity = $data['code_societe'];
			$solde = $data['solde'];
			
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'societe_solde (entity, fk_soc, solde) ';
			$sql.= 'VALUES ('.$entity.', '.$socid.', '.$solde.')';
			$res = $ATMdb->Execute($sql);
			
			// Erreur
			if($res < 0) {
				echo 'ERR '.$sql;
				return false;
			} else {
				$this->nb_create++;
			}			
		} else {
			return false;
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
		
		$fk_soc = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true, true, $data['entity']);
		if($fk_soc === false) return false;

		$a=new TFin_affaire;
		$a->loadReference($ATMdb, $data[$this->mapping['search_key']], false, $data['entity']);

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
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($a), $a->getId(),'update',$data);
		} else {
			$this->nb_create++;
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($a), $a->getId(),'create',$data);
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
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($asset), $asset->getId(),'update',$data);
			} else {
				$this->nb_create++;
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($asset), $asset->getId(),'create',$data);
			}
			
			$asset->entity = $data['entity'];
			
			$asset->save($ATMdb);
		}
			
		return true;
	}

	function importLineMaterielParc(&$ATMdb, $data) {
		if(strlen($data['matricule']) < 3) {			
			return false;
		} else {
			$asset=new TAsset;
			$asset->loadReference($ATMdb,$data['matricule'], $data['entity']);
			
			// Matériel existe déjà, vérification si c'est sur le même contrat et même entité
			if($asset->getId() > 0) {
				//pre($asset,true);
				$link = $asset->getLink('affaire');
				
				if(!empty($link->fk_document)) {
					$aff = new TFin_affaire();
					$aff->load($ATMdb, $link->fk_document, false);
					$aff->loadDossier($ATMdb);
					
					$ref = $aff->TLien[0]->dossier->financementLeaser->reference;
					
					if($ref != $data['reference']) {
						$msg = 'Matricule '.$data['matricule'].' trouvé mais associé au dossier '.$ref;
						$this->addError($ATMdb, 'AssetWithWrongDossier', $data['matricule'].' - '.$ref, 'WARNING');
					}
				}
				
			} else { // Création du matériel et rattachement au contrat
				$fin = new TFin_financement();
				$fin->loadReference($ATMdb, $data['reference'], $data['entity']);
				
				if($fin->getId() > 0) {
					$doss = new TFin_dossier();
					$doss->load($ATMdb, $fin->fk_fin_dossier, false, false);
					$doss->load_affaire($ATMdb);
					
					if($doss->TLien[0]->getId() > 0) {
						$asset->serial_number = $data['matricule'];
						$asset->add_link($doss->TLien[0]->affaire->getId(),'affaire');
						$asset->save($ATMdb);
						
						$msg = 'Matricule '.$data['matricule'].' créé et associé au dossier '.$data['reference'];
						$this->nb_create++;
					} else {
						$msg = 'Affaire non trouvée';
						$this->addError($ATMdb, 'ErrorAffaireNotFound', $data['reference']);
					}
				} else {
					$msg = 'Dossier '.$data['reference'].' non trouvé';
					$this->addError($ATMdb, 'ErrorDossierNotFound', $data['reference']);
				}
			}
		}
		//echo $msg;
		return true;
	}

	function importLineCommercial(&$ATMdb, $data, &$TInfosGlobales) { 
		if(empty($TInfosGlobales['user'][$data[$this->mapping['search_key']]])) {
			$fk_user = $this->_recherche_user($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
			if($fk_user === false) return false;
			if($fk_user === 0) return false;
			
			$TInfosGlobale['user'][$data[$this->mapping['search_key']]] = $fk_user;
		} else {
			$fk_user = $TInfosGlobales['user'][$data[$this->mapping['search_key']]];
		}
		
		if(empty($TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]])) {
			$fk_soc = $this->_recherche_client($ATMdb, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']], true, true, $data['entity']);
			if($fk_soc === false) return false;
			
			$TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]] = $fk_soc;
		} else {
			$fk_soc = $TInfosGlobales['societe'][$data[$this->mapping['search_key_client']]];
		}
		
		$c=new TCommercialCpro;
		$c->loadUserClient($ATMdb, $fk_user, $fk_soc,$data['type_activite_cpro']); // charge l'objet si existant
		
		$c->fk_soc = $fk_soc;
		$c->fk_user = $fk_user;

		$c->type_activite_cpro = $data['type_activite_cpro'];
		
		if($c->getId() > 0) {
			$this->nb_update++;
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($c), $c->getId(),'update',$data);
		} else {
			$this->nb_create++;
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($c), $c->getId(),'create',$data);
		}
		
		$c->save($ATMdb);
		
		//Suppression des autres liens commerciaux - sociétés
		if($c->fk_soc && $c->fk_user){
			$this->deleteSocieteCommerciauxLinks($ATMdb,$c);
		}

		$TInfosGlobales['commerciauxLinksId'][$c->getId()] = $c->getId();

		return true;
	}

	function importLineScore(&$ATMdb, $data) {
		global $user, $db;
		
		// Recherche si tiers existant dans la base
		$socid = $this->_recherche_client($ATMdb, $this->mapping['search_key'], $data[$this->mapping['search_key']], true, false, $data['entity']);
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
				TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($score), $score->getId(),'create',$data);
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
		if($affaire->loadReference($ATMdb, $data['code_affaire'], true, $data['entity'])) {
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
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($affaire), $affaire->getId(),'update',$data);
		} else {
			$this->addError($ATMdb, 'ErrorAffaireNotFound', $data['code_affaire'], 'WARNING');
		}
		
		if(!$found) {
			$doss = new TFin_dossier;
			if($doss->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'], true, $data['entity'])) { // Dossier existe, âs rattaché à l'affaire attendue
				$this->addError($ATMdb, 'InfoWrongAffaireForDossier', $data['code_affaire'], 'WARNING');
				if(!empty($doss->TLien[0])) {
					$affaire = &$doss->TLien[0]->affaire;
					$doss->addAffaire($ATMdb, $affaire->getId());
					$this->_save_dossier_init($ATMdb, $doss, $affaire, $data);
					$found = true;
					$this->nb_update++;
					TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($affaire), $affaire->getId(),'update',$data);
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
		global $user, $db;
		
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

	function importDossierInitLocPure(&$ATMdb, $data, &$TInfosGlobale) {
		$data['reference_dossier_interne'] = str_pad($data['reference_dossier_interne'], 8, '0', STR_PAD_LEFT);
		
		echo $data['reference_dossier_interne'].';';
		//pre($data,true);
		
		$d = new TFin_dossier();
		$d->loadReferenceContratDossier($ATMdb, $data['reference_dossier_interne'], true, $data['entity']);
		if($d->getId() == 0) {
		
			$fin = new TFin_financement();
			$fin->loadReference($ATMdb, $data['reference_dossier_interne'], 'CLIENT', $data['entity']);
			
			if($fin->getId() == 0) {
				echo 'ERR : dossier inconnu<br>';
				//$this->addError($ATMdb, 'ErrorDossierClientNotFound', $data['reference_dossier_interne']);
				return false;
			} else {
				$d->load($ATMdb, $fin->fk_fin_dossier, true);
			}
		}
		//pre($data,true);
		
		$data['duree'] /= $d->financement->getiPeriode();
		//echo date('d/m/Y H:i:s', $data['date_debut']).'<br>';
		
		$f1 = clone($d->financement);
		
		// Contrôle client
		if(!empty($d->TLien[0]->affaire)) {
			$a = &$d->TLien[0]->affaire;
			if($a->fk_soc > 0 && $a->societe->code_client != $data['code_client']) { // client ne correspond pas
				echo 'ERR : clients '.$data['code_client'].' / '.$a->societe->code_client.'<br>';
				//$this->addError($ATMdb, 'ErrorClientDifferent', $data['code_client']);
				return false;
			}
		}
		
		// On remplit la donnée si vide dans LB et non vide dans le fichier
		foreach ($data as $key => $value) {
			if(!empty($value)) {
				$d->financement->{$key} = $value;
				$d->financementLeaser->{$key} = $value;
			}
		}
		
		// On va chercher montant, échéance et durée dans le 2ème fichier
		if(!empty($a)) {
			foreach($TInfosGlobale['locpure'] as $line) {
				if(strpos($a->reference, $line[0]) !== false) {
					if(!empty($line[1]) && empty($d->financement->echeance)) {
						$d->financement->echeance = price2num($line[1]);
						$d->financementLeaser->echeance = $d->financement->echeance;
					}
					// Vu avec Damien, la durée est juste dans le 1er fichier
					/*if(!empty($line[2]) && empty($d->financement->duree)) {
						$d->financement->duree = price2num($line[2]);
						$d->financementLeaser->duree = $d->financement->duree;
					}*/
					if(!empty($line[4]) && empty($d->financement->montant)) {
						$d->financement->montant = price2num($line[4]);
						$d->financementLeaser->montant = $d->financement->montant;
					}
				}
			}
		}
		
		unset($d->financement->reference_dossier_interne);
		unset($d->financement->code_client);
		unset($d->financement->idLeaser);
		$f2 = $d->financement;
		
		if($f1 == $f2) {
			echo 'ERR : pas de changement<br>';
			return false;
		} else {
			echo 'OK : maj;';
			echo $f1->montant.' => '.$f2->montant.';';
			echo $f1->duree.' => '.$f2->duree.';';
			echo $f1->echeance.' => '.$f2->echeance.';';
			echo $f1->periodicite.' => '.$f2->periodicite.';';
			echo '<br>';
			//return false;
		}
		
		/*echo '<table><tr><td>';
		pre($f1,true);
		echo '</td><td>';
		pre($f2,true);
		echo '</td></tr></table>';*/
		
		$d->financementLeaser->reference = $d->financement->reference.'L';
		$d->financementLeaser->okPourFacturation = 'NON';
		$d->financementLeaser->fk_soc = 18495;
		$d->display_solde = false;
		//if($d->financement->reference == '04057118') pre($d, true);
		$d->save($ATMdb);
		
		$a = &$d->TLien[0]->affaire;
		$a->montant = $d->financement->montant;
		$a->save($ATMdb);
		$this->nb_update++;
				
		return true;
	}

	function checkData() {
		// Vérification cohérence des données
		
		return true;
	}
	
	function contructDataTab() {
		global $conf, $mc;
		// Construction du tableau de données
		$data = array();
		array_walk($this->current_line, 'trim');
		
		foreach($this->mapping['mapping'] as $k=>$field) {
			$data[$field] = $this->current_line[$k-1];
			$data[$field] = $this->validateValue($field,$data[$field]);
		}
		
		if(isset($this->current_line[9999])) $data['idLeaser'] = $this->current_line[9999];
		
		if(isset($this->mapping['more'])) $data = array_merge($data, $this->mapping['more']); // Ajout des valeurs autres

		if(empty($data['entity'])) $data['entity'] = $this->get_data_entity($data); // Entity gérée comme une colonne dans le fichier source

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

	function _recherche_facture(&$ATMdb, $key, $val, $errorNotFound = false, $entity=1) {
		global $TInfosGlobale;

		// On vérifie si la facture n'a pas déjà été trouvée
		if(!empty($TInfosGlobale['facture'][$entity.'-'.$val])) return $TInfosGlobale['facture'][$entity.'-'.$val];

		$entities = in_array($entity, $this->get_entity_groups()) ? $this->get_entity_groups() : array($entity);

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'facture';
		$sql.= ' WHERE '.$key.' LIKE \''.addslashes($val).'\'';
		if(!empty($entities)) $sql.= ' AND entity IN ('.implode(',', $entities).')';

		$TRes = TRequeteCore::_get_id_by_sql($ATMdb, $sql);
		
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

		$TInfosGlobale['facture'][$entity.'-'.$val] = $rowid;
		return $rowid;
	}
	
	function _recherche_dossier(&$ATMdb, $refDossier, $errorNotFound = false, $entity=1) {
		global $TInfosGlobale;

		// On vérifie si le dossier n'a pas déjà été trouvé
		if(isset($TInfosGlobale['dossier'][$entity.'-'.$refDossier])) return $TInfosGlobale['dossier'][$entity.'-'.$refDossier];

		$sql = 'SELECT d.rowid FROM '.MAIN_DB_PREFIX.'fin_dossier d';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (d.rowid = df.fk_fin_dossier)';
		$sql.= ' WHERE df.reference = \''.addslashes($refDossier).'\'';
		$sql.= ' AND df.type = \'CLIENT\'';
		$sql.= ' AND d.entity = '.$entity;

		$TRes = TRequeteCore::_get_id_by_sql($ATMdb, $sql);
		
		$rowid = 0;
		$num = count($TRes);
		if($num == 1) { // Enregistrement trouvé, mise à jour
			$rowid = $TRes[0];
		} else if($num > 1) { // Plusieurs trouvés, erreur
			$this->addError($ATMdb, 'ErrorMultipleDossierFound', $refDossier);
			$rowid = false;
		} else if($errorNotFound) {
			$this->addError($ATMdb, 'ErrorDossierNotFound', $refDossier);
			$rowid = false;
		}

		$TInfosGlobale['dossier'][$entity.'-'.$refDossier] = $rowid;
		return $rowid;
	}

	/**
	 * Détection du numéro d'entité Dolibarr en fonction du code organisation ARTIS
	 *
	 * @param $data Données de la ligne en cours d'import
	 * @return int Entité
	 */
	function get_data_entity(&$data) {
		$entity = 1;
		if($this->artis == 'ouest') { // Artis OUEST
			if($data['organisation'] == 'ABG') $entity = 5;
			elseif($data['organisation'] == 'CC') $entity = 7;
			elseif($data['organisation'] == 'QUADRA') $entity = 9;
		} else { // Artis AURA
			if($data['organisation'] == '005') $entity = 2;
			elseif($data['organisation'] == '010') $entity = 6;
			elseif($data['organisation'] == '012') $entity = 3;
			elseif($data['organisation'] == '015') $entity = 10;
			elseif($data['organisation'] == '016') $entity = 10;
		}
		
		return $entity;
	}

	function get_entity_groups($entity) {
		if(empty($entity)) {
			if($this->artis == 'ouest') {
				$entities = array(5,7,9); // Artis OUEST est utilisé par 5,7 et 9
			} else {
				$entities = array(1,2,3,6,10); // Artis AURA est utilisé par 1,2,3,6 et 10
			}
		} else {
			$entities = array($entity);
			// Regroupement spécifiques pour imports fichiers leaser
			$TGroups = array(
				array(3,10) // telecom + tdp
				,array(5,9,11) // ouest + quadra + qsigd
				,array(13,12,14,15) // sud + capea + bcmp + perret
			);

			foreach ($TGroups as $grp) {
				if(in_array($entity, $grp)) {
					$entities = $grp;
					break;
				}
			}
		}

		return $entities;
	}
	
	function get_nature_by_leaser(&$ATMdb, $socid) {
		global $db;
		$cat = new Categorie($db);
		$Tab = $cat->containing($socid, 1);
		foreach ($Tab as $leacat) {
			if($leacat->fk_parent == 2) { // On vérifie la sous-catégorie de "Type de financement"
				if(in_array($leacat->id, array(3, 4))) return 'INTERNE'; // Mandatée et Adossée = INTERNE
			}
		}
		return 'EXTERNE';
	}
	
	function checkCodeService($ref,$type='all',$retArray=false,$checkKeys=false) {
		$TArtisEst = array(
			'all' => array(
				'SSC101', 'SSC102', 'SSC106', '037004', '037003', '033741',
				'SSC109', 'SSC108', 'SSC104', 'SSC107', '018528', '020021',
				'SSC128', 'SSC151', 'SSC132', '055868'
			),
			'assurance' => array(
				'037004'
			),
			'presta' => array(
				'SSC124','SSC105','SSC054','SSC005','SSC015','SSC010',
				'SSC114','SSC004','SSC121','SSC014','SSC118','SSC008',
			),
			'loyer_actu' => array(
				'18528','20021','SSC101','SSC102','SSC106','33741',
				'SSC104'
			),
			'fass' => array(
				'SSC004', 'SSC005', 'SSC006', 'SSC025', 'SSC030', 'SSC032', 'SSC054', 'SSC113', 'SSC114', 'SSC115', 'SSC121',
				'SSC124', 'SSC127', 'SSC134', 'SSC135', 'SSC144', 'SSC195', 'SSC196', 'SSC197', 'SSC198', 'SSC199', 'SSC202',
				'SSC203', 'SSC204', 'SSC205', 'SSC207', 'SSC208', 'SSC212', 'SSC214', 'SSC218'
			),
			'frais_dossier' => array('037003'),
			'frais_facture' => array('FRAIS DE FACTURATION'),
			'eng_noir' => array('SSC015'),
			'cop_sup_noir' => array('SSC016'),
			'cop_ech_noir' => array('SSC017'),
			'couts_noir' => array('SSC005','SSC015','SSC102','SSC106'),
			'eng_coul' => array('SSC010'),
			'cop_sup_coul' => array('SSC011'),
			'cop_ech_coul' => array('SSC012'),
			'couts_coul' => array('SSC005','SSC010','SSC102','SSC106'),
			'label_cout' => array(	
				'SSC005'=>'mach',
				'SSC015'=>'tech', // Copies NB
				'SSC102'=>'loyer',
				'SSC106'=>'loyer',
				'SSC010'=>'tech' // Copies couleur
			)
		);
		$TArtisOuest = array(
			'all' => array(
				'000001', '000005', '000020', '000021', '000050', '000051',
				'000053', '000057', '000060', '007117', '010977', '010980',
				'013537', '013689', '013690', '013691',
				'013692', '013696', '013697', '013698'
				//'013828', '013829', '013686'
			),
			'assurance' => array('013687'),
			'presta' => array(),
			'loyer_actu' => array(),
			'fass' => array(),
			'frais_dossier' => array('013696'),
			'frais_facture' => array('FRAIS DE FACTURE'),
			'eng_noir' => array('013828'),
			'cop_sup_noir' => array('000053'),
			'cop_ech_noir' => array(),
			'couts_noir' => array('013686','013828','013689'),
			'eng_coul' => array('013829'),
			'cop_sup_coul' => array('000057'),
			'cop_ech_coul' => array(),
			'couts_coul' => array('013686','013829','013689'),
			'label_cout' => array(
				'013686'=>'mach',
				'013828'=>'tech', // Copies NB
				'013689'=>'loyer',
				'013829'=>'tech' // Copies couleur
			)
		);
		
		$src = $TArtisEst;
		if($this->artis == 'ouest') $src = $TArtisOuest;
		
		if($retArray) return $src[$type];
		return $checkKeys ? array_key_exists($ref, $src[$type]) : in_array($ref, $src[$type]);
	}

	function _recherche_client(&$ATMdb, $key, $val, $errorNotFound = false, $errorMultipleFound = true, $entity) {
		global $TInfosGlobale;

		// On vérifie si le tiers n'a pas déjà été trouvé
		if(!empty($TInfosGlobale['societe'][$entity.'-'.$val])) return $TInfosGlobale['societe'][$entity.'-'.$val];

		$entities = in_array($entity, $this->get_entity_groups()) ? $this->get_entity_groups() : array($entity);

		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe';
		$sql.= ' WHERE '.$key.' LIKE \''.addslashes($val).'\'';
		if(!empty($entities)) $sql.= ' AND entity IN ('.implode(',', $entities).')';

		$TRes = TRequeteCore::_get_id_by_sql($ATMdb, $sql);

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

		$TInfosGlobale['societe'][$entity.'-'.$val] = $rowid;
		return $rowid;
	}
	
	function _recherche_fournisseur(&$ATMdb, $key, $val, $errorNotFound = false, $errorMultipleFound = true, $entity=1) {
		global $TInfosGlobale;

		// On vérifie si le tiers n'a pas déjà été trouvé
		if(!empty($TInfosGlobale['societe'][$entity.'-'.$val])) return $TInfosGlobale['societe'][$entity.'-'.$val];

		$sql = 'SELECT sext.fk_object FROM '.MAIN_DB_PREFIX.'societe_extrafields sext';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON (s.rowid = sext.fk_object)';
		$sql.= ' WHERE '.$key.' LIKE \''.addslashes($val).'\'';
		if(!empty($entity)) $sql.= ' AND s.entity = '.$entity;
		
		$TRes = TRequeteCore::_get_id_by_sql($ATMdb, $sql, 'fk_object');

		$rowid = 0;
		$num = count($TRes);
		if($num == 1) { // Enregistrement trouvé
			$rowid = $TRes[0];
		} else if($num > 1) { // Plusieurs trouvés
			if($errorMultipleFound) {
				$this->addError($ATMdb, 'ErrorMultipleLeaserFound', $val);
				return false;
			} else {
				$rowid = $TRes;
			}
		} else if($errorNotFound) { // Non trouvé, erreur seulement si précisé
			$this->addError($ATMdb, 'ErrorLeaserNotFound', $val);
			return false;
		}

		$TInfosGlobale['societe'][$entity.'-'.$val] = $rowid;
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
	
	function deleteSocieteCommerciauxLinks(&$PDOdb,&$TCommercialCpro){
		global $conf;
		
		//$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc = ".$TCommercialCpro->fk_soc." AND fk_user != ".$TCommercialCpro->fk_user." AND type_activite_cpro = '".$TCommercialCpro->type_activite_cpro."'";
		// On supprime tous les commerciaux de l'entité sur laquelle on est (par défaut la 1)
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_commerciaux sc
				LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (s.rowid = sc.fk_soc) 
				WHERE sc.fk_soc = ".$TCommercialCpro->fk_soc." AND sc.fk_user != ".$TCommercialCpro->fk_user." AND sc.type_activite_cpro = '".$TCommercialCpro->type_activite_cpro."'
				AND s.entity = ".$conf->entity;
		//echo $sql.'<br>';
		$TIds = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
		//$TIds = $PDOdb->Get_All();
		//pre($TInfosGlobale['commerciauxLinksId'],true);exit;
		foreach($TIds as $id){
			$TCommercialCpro = new TCommercialCpro;
			$TCommercialCpro->load($PDOdb, $id);
			
			TImportHistorique::addHistory($ATMdb, $this->type_import, $this->filename, get_class($TCommercialCpro), $TCommercialCpro->getId(),'delete',array('Commercial'=>$TCommercialCpro->fk_user,'Societe'=>$TCommercialCpro->fk_soc));
			
			$TCommercialCpro->delete($PDOdb);
		}
	}
	
	function deleteCommerciauxLinks(&$PDOdb,&$TInfosGlobale){
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe_commerciaux";
		//echo $sql;
		$TIds = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."societe_commerciaux");
		//$TIds = $PDOdb->Get_All();
		//pre($TInfosGlobale['commerciauxLinksId'],true);exit;
		foreach($TIds as $id){
			
			if(!in_array($id,$TInfosGlobale['commerciauxLinksId'])){
				$TCommercialCpro = new TCommercialCpro;
				$TCommercialCpro->load($PDOdb, $id);
				
				TImportHistorique::addHistory($PDOdb, $this->type_import, $this->filename, get_class($TCommercialCpro), $TCommercialCpro->getId(),'delete',array('Commercial'=>$TCommercialCpro->fk_user,'Societe'=>$TCommercialCpro->fk_soc));
				
				$TCommercialCpro->delete($PDOdb);
			}
		}
	}
	
	// 2016.11.18 MKO : Fonction utilisée pour éviter d'importer des factures matériel non adressées à des fournisseur
	// Il s'agit dans ce cas de facture matériel facturée directement au client et qui correspondent à un contrat sans financement
	function getLeaserList(&$PDOdb,&$TInfosGlobale) {
		if(!empty($TInfosGlobale['TIdLeaser'])) return false;
		
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = 1";
		$TInfosGlobale['TIdLeaser'] = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
	}
	
	
	function classifyPaidAllInvoices(&$PDOdb) {
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture ';
		$sql.= 'SET paye = 1, fk_statut = 2 ';
		$sql.= 'WHERE paye = 0 ';
		$sql.= 'AND fk_statut = 1 ';
		//$sql.= 'AND type = 0 ';
		$sql.= 'AND entity IN ('.implode(',', $this->get_entity_groups()).')';
		
		$PDOdb->Execute($sql);
	}
}

class TImportHistorique extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_import_historique');
		parent::add_champs('fk_user_author,entity,fk_object','type=entier;index;');
		parent::add_champs('type_import,filename,type_object,action,hash','type=chaine;index;');
		parent::add_champs('date_import','type=date;index;');
		parent::start();
		parent::_init_vars();
	}
	
	static function addHistory(&$db,$type_import,$filename,$type_object,$fk_object,$action='', $data=array() , $mode_save = 'file') {
		global $conf, $user;
		
		if($mode_save == 'file') {
			if($type_object == 'Service') return false;
			$dir = dol_buildpath('/financement/').'log/'.$type_object.'/'; 
			@mkdir( $dir, 0777, true );
			$f1 = fopen($dir.$fk_object.'.log','a');
			fputs($f1, 
				date('Y-m-d H:i:s')."\t"
				.$user->id."\t"
				.$conf->entity."\t"
				.$type_import."\t"
				.$filename."\t"
				.$action."\t"
				.serialize($data)."\t"
				."\n"
			);
			
		}
		else {
				
			$h = new TImportHistorique;
		
			$h->fk_user_author = $user->id;
			$h->entity = $conf->entity;
			$h->date_import = time();
			
			$h->type_import = $type_import;
			$h->filename = $filename;
			$h->type_object = $type_object;
			$h->fk_object = $fk_object;
			$h->action = $action;
			
			$h->hash = md5($h->fk_object."?".$h->type_import."?".$h->filename."?".$h->type_object."?".$h->action."?".$h->date_import);
			
			if(!$h->loadBy($db,$h->hash, 'hash')){
				$h->save($db);
			}
				
		}			  
	 
	 
	}
	
	
}
?>
