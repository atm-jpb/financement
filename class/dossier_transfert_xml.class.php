<?php

if(! class_exists('Societe')) dol_include_once('/societe/class/societe.class.php');
dol_include_once('/financement/class/dossier.class.php');

class TFinDossierTransfertXML extends TObjetStd {
	
	function __construct($fk_leaser, $transfert=false) {
		global $conf;
		
		$this->fk_leaser = $fk_leaser;
		$this->transfert = $transfert;
		
		$this->TLeaserTransfert = array(
			19483 => 'LIXXBAIL',
			21382 => 'CMCIC'
		);
		
		// Nom du leaser
		$this->leaser = $this->TLeaserTransfert[$fk_leaser];
		
		// Définition du chemin du fichier
		$this->filePath.= 'XML/' . $this->leaser . '/';
		$this->fileFullPath = DOL_DATA_ROOT . '/financement/';
		if($conf->entity > 1) $this->filePath.= $conf->entity . '/';
		$this->fileFullPath.= $this->filePath;
	}
	
	function transfertXML(&$PDOdb) {
		global $conf;
		
		if(empty($this->leaser)) return false;
		
		// Récupération des affaires
		$TAffaires = $this->getAffairesForXML($PDOdb);
		
		// Génération du fichier
		$filename = '';
		if($this->leaser == 'LIXXBAIL') {
			$filename = $this->genLixxbailXML($PDOdb, $TAffaires);
		} else if ($this->leaser == 'CMCIC') {
			$filename = $this->genCMCICXML($PDOdb, $TAffaires);
		}
		
		// Dépose via SFTP
		if(!empty($filename) && $this->transfert) {
			$dirname = $this->fileFullPath . $filename . '.xml';
			if(BASE_TEST) {
				exec('sh bash/lixxbailxml_test.sh '.$dirname);
			} else {
				exec('sh bash/lixxbailxml.sh '.$dirname);
			}
		}
		
		return $this->filePath . $filename . '.xml';
	}
	
	function getAffairesForXML(&$PDOdb){
		global $conf;
		
		$TAffaires = array();
		
		$sql = 'SELECT DISTINCT(fa.rowid) 
				FROM '.MAIN_DB_PREFIX.'fin_affaire as fa
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire as da ON (da.fk_fin_affaire = fa.rowid)
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement as df ON (df.fk_fin_dossier = da.fk_fin_dossier)
					LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON (s.rowid = df.fk_soc)
				WHERE fa.type_financement = "MANDATEE"
					AND df.type = "LEASER"
					AND s.rowid = '.$this->fk_leaser.'
					AND df.transfert = 1
					AND fa.entity = '.$conf->entity;
		
		$TIdAffaire = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
		
		foreach($TIdAffaire as $idAffaire){
			$affaire = new  TFin_affaire;
			$affaire->load($PDOdb, $idAffaire);
			$TAffaires[] = $affaire;
		}
	
		return $TAffaires;
	}
	
	function resetAllDossiersInXML(&$PDOdb){
		// Récupération des affaires
		$TAffaires = $this->getAffairesForXML($PDOdb);
		
		foreach($TAffaires as $affaire){

			foreach($affaire->TLien as $i => $TData ){
				$TData->dossier->financementLeaser->transfert = 0;
				$TData->dossier->save($PDOdb);
			}
		}
	}
	
/**********************************************************************************************************
 * LIXXBAIL
 **********************************************************************************************************/
	
	function genLixxbailXML(&$PDOdb, &$TAffaires,$andUpload=false){
		global $conf;
		
		$xml = new DOMDocument('1.0','UTF-8');
		$xml->formatOutput = true;

		$affairelist = $xml->createElement("affaireList");
		$affairelist = $xml->appendChild($affairelist);
		
		list($nomFichier,$name2,$refPartenaire,$numLot) = $this->getEnTeteByEntity();
		
		$affairelist->appendChild($xml->createElement("nomFich",$nomFichier));
		$affairelist->appendChild($xml->createElement("refExtPartenaire",$refPartenaire));
		$affairelist->appendChild($xml->createElement("numLot",$numLot));
		
		//Chargement des noeuds correspondant aux affaires
		foreach($TAffaires as $Affaire){
			$affaires = $this->_getAffairesXML($xml,$Affaire);
			if($andUpload){
				$Affaire->xml_date_transfert = time();
				$Affaire->xml_fic_transfert = $name2;
			}
			$Affaire->save($PDOdb);

			$affairelist->appendChild($affaires);
		}
		
		$chaine = $xml->saveXML();
		
		dol_mkdir($this->fileFullPath);
		file_put_contents($this->fileFullPath.$name2.'.xml', $chaine);
		
		return $name2;
	}
	
	function _getNumFournisseur(){
		
		switch (getEntity()) {
			case 1: //CPRO Impression
				return "M000355961";
				break;
			case 2: //CPRO Informatique
				return "M000317069";
				break;
			case 3: //CPRO Télécom
				return "M001155746";
				break;
			case 4: //Bougogne Copie
				return "M000355961";
				break;
			case 5: //ABG
				return "M000290985";
				break;
			case 7: //Copy Concept
				return "M000252940";
				break;
			case 9: //Quadra
				return "M000355473";
				break;
			case 12: //CAPEA
				return "M000317338";
				break;
			case 13: //BCMP
				return "M000393212";
				break;
			case 14: //Perret
				return "M000342697";
				break;
			default:
				return "M000355961";
				break;
		}
	}
	
	function getEnTeteByEntity(){
		
		$date = date('Ymd');
		$entity = getEntity();
		
		switch ($entity) {
			case 1: //CPRO Impression
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "CPROMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 2: //CPRO Informatique
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "CPROMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 3: //CPRO Télécom
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "AGTMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 4: //Bougogne Copie
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "CPROMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 5: //ABG
				$name2 = "FP_207_MA01_ABG".$entity."_".$date;
				$nomFichier = "ABGMA0".$entity."IMMA".$date;
				$refPartenaire = "ABGMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 7: //Copie concept
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "CYCPMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 9: //Quadra
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "QUABMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 12: //CAPEA
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "CAPEAMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 13: //BCMP
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "BCMPMA01";
				$numLot = "IMMA".date('ymd');
				break;
			case 14: //Perret
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "PERRMA01";
				$numLot = "IMMA".date('ymd');
				break;
			
			default:
				$name2 = "FP_207_MA01_CPRO".$entity."_".$date;
				$nomFichier = "CPROMA0".$entity."IMMA".$date;
				$refPartenaire = "CPROMA01";
				$numLot = "IMMA".date('ymd');
				break;
		}
		
		return array($nomFichier,$name2,$refPartenaire,$numLot);
	}
	
	function _getAffairesXML(&$xml,&$Affaire){
		
		$affaire = $xml->createElement("affaire");

		foreach($Affaire->TLien as $i => $Tdata){
			if($Affaire->TLien[$i]->dossier->financementLeaser->transfert == 1){
				
				$affaire->appendChild($xml->createElement("dateSignature",date("Y-m-d",$Affaire->date_affaire)));
				$affaire->appendChild($xml->createElement("numDossierDe",$Affaire->TLien[$i]->dossier->financementLeaser->reference));
				$affaire->appendChild($xml->createElement("siretClient",(!empty($Affaire->societe->idprof2)) ? $Affaire->societe->idprof2 : $Affaire->societe->idprof1 ));
				
				$elements = $this->_getElementsXML($xml,$Tdata,$i,$Affaire);
				$affaire->appendChild($elements);
			}
		}

		return $affaire;

	}
	
	function _getElementsXML(&$xml,&$Tdata,$i,&$Affaire){
		
		$element = $xml->createElement("element");
		//$element = $xml->appendChild($element);
		
		switch ($Tdata->dossier->financementLeaser->periodicite) {
			case 'TRIMESTRE':
				$periodicite = 3;
				break;
			case 'MOIS':
				$periodicite = 1;
				break;
			case 'ANNEE':
				$periodicite = 12;
				break;
			case 'SEMESTRE':
				$periodicite = 6;
				break;
		}
		
		$element->appendChild($xml->createElement("noElement",$i+1));
		$element->appendChild($xml->createElement("periodicite",$periodicite));
		$element->appendChild($xml->createElement("codeTaxe","10"));
		$element->appendChild($xml->createElement("terme",substr($Tdata->dossier->financementLeaser->TTerme[$Tdata->dossier->financementLeaser->terme],0,1)));
		$element->appendChild($xml->createElement("datePremEch",date("Y-m-d",$Tdata->dossier->financementLeaser->date_debut)));
		
		$TAssetId = array();

		$serial_numbers ='';
		$TDesignation = array();
		
		//Si au moin un bien (équipement) lié à l'affaire
		if(count($Affaire->TAsset)){
			foreach($Affaire->TAsset as $a => $assetLink){
				$serial_numbers = $this->_getSerialNumbersBienXML($serial_numbers,$assetLink->asset->serial_number);
				$TDesignation = $this->_getDesignationBienXML($TDesignation,$assetLink);
				$AssetId = $assetLink->asset->getId();
			}
		}
		else{
			$serial_numbers = "Cf facture materiel";
			$TDesignation = array("Bien manquant");
		}

		$bien = $this->_getBiensXML($xml,$Affaire->TAsset[0],$Affaire,0,$serial_numbers,$TDesignation,$Tdata->dossier->financementLeaser);
		$element->appendChild($bien);
		
		$paliers = $this->_getPaliersXML($xml,$Tdata->dossier->financementLeaser,$Affaire,$AssetId,0);
		$commande = $this->_getCommandeXML($xml,$Affaire->TAsset,$Affaire);

		$element->appendChild($paliers);
		$element->appendChild($commande);

		return $element;
	}

	function _getDesignationBienXML(&$TDesignation,&$assetLink){
		global $db;
		
		dol_include_once('/product/class/product.class.php');
		
		$product = new Product($db);
		$product->fetch($assetLink->asset->fk_product);
		
		$tailleDesignation1B = strlen($TDesignation[0]);
		$tailleDesignation1C = strlen($TDesignation[1]);
		
		if($tailleDesignation1B >= 1){
			if($tailleDesignation1C >= 1){
				$TDesignation[0] = substr($TDesignation[0],0,-3);
				$TDesignation[1] = substr($TDesignation[1],0,-3);
				return $TDesignation;
			}
			else{
				$TDesignation[1] .= $product->label.' - ';
			}
		}
		else{
			$TDesignation[0] .= $product->label." - ";
		}
		
		$TDesignation[0] = substr($TDesignation[0],0,-3);
		$TDesignation[1] = substr($TDesignation[1],0,-3);
		
		return $TDesignation;
	}
	
	function _getSerialNumbersBienXML(&$serial_numbers,&$serial_number){
		
		$serial_numbers .= $serial_number.' - ';
		
		if(strlen($serial_numbers) <= 30){
			$serial_numbers .= $product->label;
		}
		else{
			return substr($serial_numbers,0,-3);
		}
		
		return $serial_numbers;
	}

	function _getFactureXML(&$assetLink,&$Affaire){
		global $db;
		
		$PDOdb = new TPDOdb;

		//Récupération de la facture client de l'équipement associé à l'affaire
		$sql = "SELECT al1.fk_document 
				FROM ".MAIN_DB_PREFIX."asset_link as al1
					LEFT JOIN ".MAIN_DB_PREFIX."asset_link as al2 ON (al2.fk_asset = al1.fk_asset)
				WHERE al1.fk_asset = ".$assetLink->asset->getId()." AND al1.type_document = 'facture'
					AND al2.type_document = 'affaire' AND al2.fk_document = ".$Affaire->getId();

		$TIdFacture = TRequeteCore::get_keyval_by_sql($PDOdb, $sql, OBJETSTD_MASTERKEY, "fk_document");

		if(!empty($TIdFacture[0])) {
			$facture = new Facture($db);
			$facture->fetch($TIdFacture[0]);
		}
		
		$PDOdb->close();
		
		return $facture;
	}
	
	function _getBiensXML(&$xml,&$assetLink,&$Affaire,$a,$serial_numbers='',$TDesignation=array(),$financementleaser){
		global $db;
		
		dol_include_once('/product/class/product.class.php');
		dol_include_once('/compta/facture/class/facture.class.php');
			
		//Si on a un équipement de lié
		if($assetLink->asset->fk_product){
			/*$product = new Product($db);
			$product->fetch($assetLink->asset->fk_product);*/
			$facture = $this->_getFactureXML($assetLink,$Affaire);
			$nbAsset = count($Affaire->TAsset);
		}
		else{
			$nbAsset = 1;
		}
		

		$bien = $xml->createElement("bien");
		//$bien = $xml->appendChild($bien);
		
		$TAssietteTheorique = array(
				'209B' => 'INFORMATIQUE - micro ordinateur',
				'204B' => 'INFORMATIQUE - ensemble matériels informatique',
				'U06C' => 'BUREAUTIQUE - télécopieur',
				'U01C' => 'BUREAUTIQUE - ensemble matériels bureautique',
				'216B' => 'INFORMATIQUE - station',
				'U07C' => 'BUREAUTIQUE - machine traitement du courrier',
				'212B' => 'INFORMATIQUE - portable',
				'206C' => 'INFORMATIQUE - imprimante',
				'214B' => 'INFORMATIQUE - scanner informatique',
				'219Q' => 'INFORMATIQUE - logiciels',
				'218C' => 'INFORMATIQUE - traceur',
				'208C' => 'INFORMATIQUE - imprimante laser',
				'U03C' => 'BUREAUTIQUE - photocopieur',
				'211C' => 'INFORMATIQUE - onduleur',
				'144C' => 'IMPRIMERIE - traceur',
				'130G' => 'IMPRIMERIE - plieuse',
				'V09Q' => 'TELECOMMUNICATIONS,VIDEO,AUDIO - installation téléphonique',
			);
		
		$bien->appendChild($xml->createElement("immobilisation",$a+1));
		$trans = array('&'=>'et');
		
		foreach($TDesignation as $k => $designation){
			$designation = htmlentities($designation, ENT_NOQUOTES, 'UTF-8');
		    $designation = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $designation);
		    $designation = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $designation); // pour les ligatures e.g. '&oelig;'
		    $designation = preg_replace('#&[^;]+;#', '', $designation); // supprime les autres caractères
		    $designation = strtoupper($designation);
		    
		    $TDesignation[$k] = substr($designation,0,30);
		}
		
		if($nbAsset === 1){
			$bien->appendChild($xml->createElement("designation1",$TDesignation[0]));
		}
		else{
			$bien->appendChild($xml->createElement("designation1","ENSEMBLE DE COPIEURS"));
			$bien->appendChild($xml->createElement("designation1B",$TDesignation[0]));
			$bien->appendChild($xml->createElement("designation1C",$TDesignation[1]));
		}

		$bien->appendChild($xml->createElement("noSerie",strtoupper(substr($serial_numbers,0,30))));
		$bien->appendChild($xml->createElement("immatriculable","NON"));
		$bien->appendChild($xml->createElement("codeAssietteTheorique","U03C"));

		$bien->appendChild($xml->createElement("montant",round($financementleaser->montant,2)));
		
		return $bien;
	}
	
	function _getPaliersXML(&$xml,&$financementLeaser,&$Affaire,&$AssetId,$j){
		global $db;
		
		$palier = $xml->createElement("palier");
		//$palier = $xml->appendChild($palier);
		
		$PDOdb = new TPDOdb;
		
		//Récupération de la facture client de l'équipement associé à l'affaire
		$sql = "SELECT al1.fk_document 
				FROM ".MAIN_DB_PREFIX."asset_link as al1
					LEFT JOIN ".MAIN_DB_PREFIX."asset_link as al2 ON (al2.fk_asset = al1.fk_asset)
				WHERE al1.fk_asset = ".$AssetId." AND al1.type_document = 'facture'
					AND al2.type_document = 'affaire' AND al2.fk_document = ".$Affaire->getId();

		$TIdFacture = TRequeteCore::get_keyval_by_sql($PDOdb, $sql, OBJETSTD_MASTERKEY, "fk_document");

		if(!empty($TIdFacture[0])) {
			$facture = new Facture($db);
			$facture->fetch($TIdFacture[0]);
		}
		
		$PDOdb->close();
		
		switch ($financementLeaser->periodicite) {
			case 'TRIMESTRE':
				$periodicite = 3;
				break;
			case 'MOIS':
				$periodicite = 1;
				break;
			case 'ANNEE':
				$periodicite = 12;
				break;
			case 'SEMESTRE':
				$periodicite = 6;
				break;
		}
		
		$palier->appendChild($xml->createElement("no",$j+1));
		$palier->appendChild($xml->createElement("nbre",$financementLeaser->duree));
		$palier->appendChild($xml->createElement("montant",$financementLeaser->echeance));
		$palier->appendChild($xml->createElement("terme",substr($financementLeaser->TTerme[$financementLeaser->terme],0,1)));
		$palier->appendChild($xml->createElement("periodicite",$periodicite));
		//$palier->appendChild($xml->createElement("mtVnf",$Affaire->montant));
		$palier->appendChild($xml->createElement("mtVnf",round($financementLeaser->montant,2)));
		
		//$palier->appendChild($xml->createElement("pourcVnf",round((($Affaire->montant * 100) / $facture->total_ht),2)));
		$palier->appendChild($xml->createElement("pourcVnf",100));

		return $palier;
	}
	
	function _getCommandeXML(&$xml,&$TAsset,&$Affaire){
		
		$commande = $xml->createElement("commande");

		//pre($TAsset[0]->asset->serial_number);exit;
		//$commande->appendChild($xml->createElement("noCommande",((count($TAsset) > 1) ? date('dmY') : $TAsset[0]->asset->serial_number)));
		$commande->appendChild($xml->createElement("noCommande",strtoupper(substr($Affaire->reference,0,10))));
		$commande->appendChild($xml->createElement("fournisseur",$this->_getNumFournisseur()));

		//foreach($TAsset as $a=>$assetLink){
				
			$commandeLig = $this->_getCommandeLigXML($xml,$TAsset[0],$Affaire,$a);
			$commande->appendChild($commandeLig);
		//}

		return $commande;
	}
	
	function _getCommandeLigXML(&$xml, &$assetLink,&$Affaire,$a){
		
		dol_include_once('/compta/facture/class/facture.class.php');
		
		if($assetLink->asset->fk_product){
			$facture = $this->_getFactureXML($assetLink,$Affaire);
			$total_ht = $facture->total_ht;
			$total_tva = $facture->total_tva;
			$total_ttc = $facture->total_ttc;
		}
		else{
			$total_ht = $Affaire->montant;
			$total_tva = ($Affaire->montant * 20 / 100);
			$total_ttc = $total_ht + $total_tva;
		}
		
		$total_ht = round($Affaire->TLien[0]->dossier->financementLeaser->montant, 2);
		$total_tva = round($total_ht * 0.20, 2);
		$total_ttc = round($total_ht + $total_tva, 2);
		
		$commandeLig = $xml->createElement("commandeLig");
		
		$commandeLig->appendChild($xml->createElement("immobilisation",$a+1));
		$commandeLig->appendChild($xml->createElement("codeTypeLigne","ABIE"));
		
		$commandeLig->appendChild($xml->createElement("mtHt",round($total_ht,2)));
		$commandeLig->appendChild($xml->createElement("codeTaxe","10"));
		
		$commandeLig->appendChild($xml->createElement("mtTaxe",round($total_tva,2)));
		$commandeLig->appendChild($xml->createElement("mtTTC",round($total_ttc,2)));
		
		return $commandeLig;
	}


/**********************************************************************************************************
 * CM CIC BAIL
 **********************************************************************************************************/
	
	function genCMCICXML(&$PDOdb, &$TAffaires,$andUpload=false){
		global $conf, $db;
		
		$xml = new DOMDocument('1.0','UTF-8');
		$xml->formatOutput = true;
		
		//Chargement des noeuds correspondant aux affaires
		//print '<pre>';
		foreach($TAffaires as $Affaire){
			$dossier = $Affaire->TLien[0]->dossier;	// Possible car 1 affaire = 1 dossier
			$fin = $dossier->financement;
			$finLeaser = $dossier->financementLeaser;
			//var_dump($finLeaser->duree);exit;

			$socLeaser = new Societe($db);
			$socLeaser->fetch($finLeaser->fk_soc);
			$leaserName = substr($socLeaser->name, 0, 32);	// Limité à 32 caractères

			$affairelist = $xml->createElement("CONTRAT");
			$affairelist = $xml->appendChild($affairelist);

			$affairelist->appendChild($xml->createElement("NO_AFFAIRE_CM", $finLeaser->reference));
			$affairelist->appendChild($xml->createElement("NO_AFFAIRE_PARTENAIRE", $fin->reference));
			$affairelist->appendChild($xml->createElement("NOM_LEASER", $leaserName));

			$affairelist->appendChild($xml->createElement("FLG_COSME", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_ASSVIE", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_GARANTIE", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_DERO_ASSMAT", 'N'));

			$schema_financier = $xml->createElement('SCHEMA_FINANCIER');
			$schema_financier->appendChild($xml->createElement('DUREE_MOIS', $finLeaser->duree*$finLeaser->getiPeriode()));
			$affairelist->appendChild($schema_financier);

			$affaires = $this->_getAffairesXML($xml,$Affaire);
			if($andUpload){
				$Affaire->xml_date_transfert = time();
				$Affaire->xml_fic_transfert = $name2;
			}
			$Affaire->save($PDOdb);

			$affairelist->appendChild($affaires);
		}
		
		$chaine = $xml->saveXML();
		
		$name2 = 'test';
		dol_mkdir($this->fileFullPath);
		file_put_contents($this->fileFullPath.$name2.'.xml', $chaine);
		
		return $name2;
	}
}