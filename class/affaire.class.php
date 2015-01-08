<?php

class TFin_affaire extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'fin_affaire');
		parent::add_champs('reference,nature_financement,contrat,type_financement,type_materiel','type=chaine;');
		parent::add_champs('date_affaire','type=date;');
		parent::add_champs('fk_soc,entity','type=entier;index;');
		parent::add_champs('montant,solde','type=float;');
		
		parent::_init_vars();
		parent::start();
		
		$this->TLien=array();
		$this->TCommercial=array();
		$this->TAsset=array();
		$this->contrat = 'LOCSIMPLE';
		
		$this->TContrat=array(
			'LOCSIMPLE'=>$langs->trans('LocSimple')
			,'FORFAITGLOBAL'=>$langs->trans('ForfaitGlobal')
			,'INTEGRAL'=>$langs->trans('Integral')
		);
		$this->TTypeFinancement=array(
			'PURE'=>'Location Pure'
			,'ADOSSEE'=>'Location adossée'
			,'MANDATEE'=>'Location mandatée'
			,'FINANCIERE'=>'Location financière'
		);
		
		$this->TTypeMateriel=array(); 
		$this->TNatureFinancement=array(
			'INTERNE'=>'Interne'
			,'EXTERNE'=>'Externe'
		);
		
		$this->somme_dossiers=0;
	}
	
	function load(&$ATMdb, $id, $annexe=true) {
		global $db;
		$res = parent::load($ATMdb, $id);
		
		// Chargement du client
		$this->societe = new Societe($db);
		$this->societe->fetch($this->fk_soc);
		
		if($annexe) {
			$this->loadDossier($ATMdb);
			$this->loadCommerciaux($ATMdb);
			$this->loadEquipement($ATMdb);
		}
		
		$this->calculSolde();
		
		return $res;
	}
	function loadCommerciaux(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_affaire_commercial',array('fk_fin_affaire'=>$this->getId()));
		
		foreach($Tab as $i=>$id) {
			$this->TCommercial[$i]=new TFin_affaire_commercial;
			$this->TCommercial[$i]->load($db, $id);
			
		}
	}
	function loadEquipement(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'asset_link',array('fk_document'=>$this->getId(), 'type_document'=>'affaire'));
		
		foreach($Tab as $i=>$id) {
			$this->TAsset[$i]=new TAssetLink;
			$this->TAsset[$i]->load($db, $id, true);
			
		}
	}
	function calculSolde() {
		$this->somme_dossiers =0;
		foreach($this->TLien as &$lien) {
			
			$this->somme_dossiers += $lien->dossier->montant;
		}
		$this->solde = $this->montant - $this->somme_dossiers;
	}
	function loadDossier(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_dossier_affaire',array('fk_fin_affaire'=>$this->getId()));
		
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->dossier->load($db, $this->TLien[$i]->fk_fin_dossier, false);

		}
		
		$this->calculSolde();
	}
	function getSolde(&$ATMdb, $type='SRBANK') {
		
		$solde=0;
		foreach($this->TLien as $link) {
			
			$solde+=$link->dossier->getSolde($ATMdb, $type);
			
		}

		return $solde;

	}
	function delete(&$db) {
		parent::delete($db);
		$db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $this->getId(), 'fk_fin_affaire' );
	}
	function save(&$db) {
		global $conf, $user;
		
		if(!$user->rights->financement->affaire->write) return false;
		
		$this->calculSolde();
		$this->entity = $conf->entity;
		
		
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->fk_fin_affaire = $this->getId();
			$lien->save($db);
			// Sauvegarde du dossier pour mise à jour si changement de classification
			$lien->dossier->save($db);
		}
		foreach($this->TAsset as &$lien) {
			$lien->fk_document = $this->getId();	
			$lien->save($db);
		}
		foreach($this->TCommercial as &$lien) {
			$lien->fk_fin_affaire = $this->getId();	
			$lien->save($db);
		}
	}
	function deleteDossier(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_dossier==$id) {
				$db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				$this->calculSolde();
				return true;
				
			}
		}		 
		
		return false;
	}
	function addDossier(&$db, $id=null, $reference=null) {
		$dossier =new TFin_dossier;
		
		if((!is_null($id) && $dossier->load($db, $id)) 
		|| (!is_null($reference)  && $dossier->loadReference($db, $reference))) {
			/*
			 * Le dossier existe liaison
			 */
			//print_r($this->TLien);
			foreach($this->TLien as $k=>$lien) {
				if($lien->fk_fin_dossier==$dossier->getId()) {return false;}
			}		 
			
			$i = count($this->TLien); 
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->fk_fin_affaire = $this->rowid;
			$this->TLien[$i]->fk_fin_dossier = $dossier->rowid;  

			$this->TLien[$i]->dossier= $dossier;
			
		//	print_r($this->TLien[$i]);
			$this->calculSolde();
			return true;
		}
		else {
			//exit('Echec');
			return false;
		}
		
	}
	function deleteEquipement(&$db, $id) {
		foreach($this->TAsset as $k=>&$lien) {
			if($lien->asset->getId()==$id && $lien->fk_document==$this->getId() && $lien->type_document=='affaire') {
				$lien->delete($db);
				unset($this->TAsset[$k]);
				return true;

			}
		}		 
		
		return false;
	}
	function addEquipement(&$db, $id) {
		foreach($this->TAsset as $k=>&$asset) {
			if($asset->getId()==$id) {return false;}
		}		 
		 
		$asset =new TAsset;
		$asset->load($db, $id); 
		$i = $asset->add_link($this->getId(), 'affaire'); 
		$asset->save($db);

		$asset->TLink[$i]->asset = $asset ;

		$this->TAsset[]=$asset->TLink[$i];

		return true;		
	}
	
	function deleteCommercial(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_dossier==$id) {
				$db->dbdelete(MAIN_DB_PREFIX.'fin_affaire_commercial', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				return true;
			}
		}		 
		
		return false;
	}
	function addCommercial(&$db, $id) {
		foreach($this->TCommercial as $k=>$lien) {
			if($lien->fk_user==$id) {return false;}
		}
		
		$i = count($this->TCommercial); 
		$this->TCommercial[$i]=new TFin_affaire_commercial;
		$this->TCommercial[$i]->fk_fin_affaire = $this->getId();
		$this->TCommercial[$i]->fk_user = $id;

		return true;

	}
	
	function loadReference(&$db, $reference,$annexe=false) {
		
		$db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference='".$reference."'");
		if($db->Get_line()) {
			return $this->load($db, $db->Get_field('rowid'), $annexe);
		}
		else {
			return false;
		}
		
	}
	
	function getAffairesForXML(&$ATMdb,$leasername = 'LIXXBAIL (MANDATE)'){
		
		$TAffaires = array();
		
		$sql = 'SELECT fa.rowid 
				FROM '.MAIN_DB_PREFIX.'fin_affaire as fa
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire as da ON (da.fk_fin_affaire = fa.rowid)
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement as df ON (df.fk_fin_dossier = da.fk_fin_dossier)
					LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON (s.rowid = df.fk_soc)
				WHERE fa.type_financement = "MANDATEE"
					AND df.type = "LEASER"
					AND s.nom = "'.$leasername.'"
					AND df.transfert = 1';
		
		$TIdAffaire = TRequeteCore::_get_id_by_sql($ATMdb, $sql);
		
		foreach($TIdAffaire as $idAffaire){
			
			$affaire = new  TFin_affaire;
			$affaire->load($ATMdb, $idAffaire);
			
			$TAffaires[] = $affaire;
		}
	
		return $TAffaires;
	}	
	
	function genLixxbailXML(&$TAffaires){
		
		$date = date('Ymd');
		$name = "CPROMA01IMMA".$date;

		$xml = new DOMDocument('1.0','UTF-8');
		$xml->formatOutput = true;

		$affairelist = $xml->createElement("affaireList");
		$affairelist = $xml->appendChild($affairelist);

		$affairelist->appendChild($xml->createElement("nomFich",$name));
		$affairelist->appendChild($xml->createElement("refExtPartenaire","CPROMA01"));
		$affairelist->appendChild($xml->createElement("numLot","IMMA".date('ymd')));
		
		//Chargement des noeuds correspondant aux affaires
		foreach($TAffaires as $Affaire){
			$affaires = $this->_getAffairesXML($xml,$Affaire);

			$affairelist->appendChild($affaires);
		}
		
		$name2 = "FP_207_MA01_CPRO_".$date;
		
		$chaine = $xml->saveXML();
		dol_mkdir(DOL_DATA_ROOT.'/financement/XML/Lixxbail/');
		file_put_contents(DOL_DATA_ROOT.'/financement/XML/Lixxbail/'.$name2.'.xml', $chaine);
		
		return $name2;
	}
	
	function resetAllDossiersInXML(&$ATMdb,&$TAffaires){
		
		foreach($TAffaires as $affaire){

			foreach($affaire->TLien as $i => $TData ){
				$TData->dossier->financementLeaser->transfert = 0;
				$TData->dossier->save($ATMdb);
			}
		}
	}
	
	function uploadXMLOnLeaserServer($host,$user,$directory,$dirname,$filename){
		
		$destination = $directory.$filename;
		
		try
		{
			//pre("1");exit;
		    $sftp = new SFTPConnection($host, 22);
		    $sftp->login($user);
		    $sftp->uploadFile($dirname,$destination);

		}
		catch (Exception $e)
		{
		    echo $e->getMessage() . "\n";exit;
		}
	}
	
	function _getAffairesXML(&$xml,&$Affaire){
		
		$affaire = $xml->createElement("affaire");

		$affaire->appendChild($xml->createElement("dateSignature",date("Y-m-d",$Affaire->date_affaire)));
		$affaire->appendChild($xml->createElement("numDossierDe",$Affaire->TLien[0]->dossier->financementLeaser->reference));
		$affaire->appendChild($xml->createElement("siretClient",(!empty($Affaire->societe->idprof2)) ? $Affaire->societe->idprof2 : $Affaire->societe->idprof1 ));

		//pre($Affaire,true);exit;

		foreach($Affaire->TLien as $i => $Tdata){
			$elements = $this->_getElementsXML($xml,$Tdata,$i,$Affaire);
			$affaire->appendChild($elements);
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
		
		/*foreach($Affaire->TAsset as $a => $assetLink){
			$bien = $this->_getBiensXML($xml,$assetLink,$Affaire,$a);
			$AssetId = $assetLink->asset->getId();
			$element->appendChild($bien);
		}*/
		$serial_numbers ='';
		$TDesignation = array();
		foreach($Affaire->TAsset as $a => $assetLink){
			$serial_numbers = $this->_getSerialNumbersBienXML($serial_numbers,$assetLink->asset->serial_number);
			$TDesignation = $this->_getDesignationBienXML($TDesignation,$assetLink);
			$AssetId = $assetLink->asset->getId();
		}
		
		$bien = $this->_getBiensXML($xml,$Affaire->TAsset[0],$Affaire,$a,$serial_numbers,$TDesignation);
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
		
		if($tailleDesignation1B >= 30){
			if($tailleDesignation1C >= 30){
				$TDesignation[0] = substr(substr($TDesignation[0], 0,-3),0,30);
				$TDesignation[1] = substr(substr($TDesignation[1], 0,-3),0,30);
				return $TDesignation;
			}
			else{
				$TDesignation[1] .= $product->label.' - ';
			}
		}
		else{
			$TDesignation[0] .= $product->label." - ";
		}
		
		$TDesignation[0] = substr(substr($TDesignation[0], 0,-3),0,30);
		$TDesignation[1] = substr(substr($TDesignation[1], 0,-3),0,30);
		
		return $TDesignation;
	}
	
	function _getSerialNumbersBienXML(&$serial_numbers,&$serial_number){
		
		$serial_numbers .= $serial_number.' - ';
		
		if($serial_numbers >= 30){
			return substr(substr($serial_numbers,0,-3),0,30);
		}
		else{
			$serial_numbers .= $product->label;
		}
		
		return substr(substr($serial_numbers,0,-3),0,30);
	}

	function _getFactureXML(&$assetLink,&$Affaire){
		global $db;
		
		$ATMdb = new TPDOdb;
		
		//Récupération de la facture client de l'équipement associé à l'affaire
		$sql = "SELECT al1.fk_document 
				FROM ".MAIN_DB_PREFIX."asset_link as al1
					LEFT JOIN ".MAIN_DB_PREFIX."asset_link as al2 ON (al2.fk_asset = al1.fk_asset)
				WHERE al1.fk_asset = ".$assetLink->asset->getId()." AND al1.type_document = 'facture'
					AND al2.type_document = 'affaire' AND al2.fk_document = ".$Affaire->getId();

		$TIdFacture = TRequeteCore::get_keyval_by_sql($ATMdb, $sql, OBJETSTD_MASTERKEY, "fk_document");

		if(!empty($TIdFacture[0])) {
			$facture = new Facture($db);
			$facture->fetch($TIdFacture[0]);
		}
		
		$ATMdb->close();
		
		return $facture;
	}
	
	function _getBiensXML(&$xml,&$assetLink,&$Affaire,$a,$serial_numbers='',$TDesignation=array()){
		global $db;
		
		dol_include_once('/product/class/product.class.php');
		dol_include_once('/compta/facture/class/facture.class.php');

		$product = new Product($db);
		$product->fetch($assetLink->asset->fk_product);
		
		$nbAsset = count($Affaire->TAsset);
		
		$facture = $this->_getFactureXML($assetLink,$Affaire);

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
		
		$designation = htmlentities($product->label, ENT_NOQUOTES, 'UTF-8');
	    $designation = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $designation);
	    $designation = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $designation); // pour les ligatures e.g. '&oelig;'
	    $designation = preg_replace('#&[^;]+;#', '', $designation); // supprime les autres caractères
		
		//$bien->appendChild($xml->createElement("designation1",substr(strtoupper(($designation)),0,30)));
		$bien->appendChild($xml->createElement("designation1","ensemble de copieurs"));
		$bien->appendChild($xml->createElement("designation1B",$TDesignation[0]));
		$bien->appendChild($xml->createElement("designation1C",$TDesignation[1]));
		/*$des = $bien->appendChild($xml->createElement("designation1"));
		$des->appendChild($xml->createCDATASection(substr($product->label,0,30)));*/
		
		//$bien->appendChild($xml->createElement("noSerie",$assetLink->asset->serial_number));
		$bien->appendChild($xml->createElement("noSerie",$serial_numbers));
		$bien->appendChild($xml->createElement("immatriculable","NON"));
		$bien->appendChild($xml->createElement("codeAssietteTheorique","U03C"));
		
		//On divise le montant total HT de la facture par le nombre de bien
		//Seul pb, les arrondies risquent de faussé les montants donc pour le dernier bien ajouté montan = total HT - somme des montants des bien précédent
		/*if($a+1 == $nbAsset){
			//echo $Affaire->totalBien;exit;
			$bien->appendChild($xml->createElement("montant",round(($facture->total_ht - $Affaire->totalBien),2)));
		}
		else{
			$Affaire->totalBien += round(($facture->total_ht / $nbAsset),2);
			$bien->appendChild($xml->createElement("montant",round(($facture->total_ht / $nbAsset),2)));
		}*/
		$bien->appendChild($xml->createElement("montant",round(($facture->total_ht),2)));
		
		return $bien;
	}
	
	function _getPaliersXML(&$xml,&$financementLeaser,&$Affaire,&$AssetId,$j){
		global $db;
		
		$palier = $xml->createElement("palier");
		//$palier = $xml->appendChild($palier);
		
		$ATMdb = new TPDOdb;
		
		//Récupération de la facture client de l'équipement associé à l'affaire
		$sql = "SELECT al1.fk_document 
				FROM ".MAIN_DB_PREFIX."asset_link as al1
					LEFT JOIN ".MAIN_DB_PREFIX."asset_link as al2 ON (al2.fk_asset = al1.fk_asset)
				WHERE al1.fk_asset = ".$AssetId." AND al1.type_document = 'facture'
					AND al2.type_document = 'affaire' AND al2.fk_document = ".$Affaire->getId();

		$TIdFacture = TRequeteCore::get_keyval_by_sql($ATMdb, $sql, OBJETSTD_MASTERKEY, "fk_document");

		if(!empty($TIdFacture[0])) {
			$facture = new Facture($db);
			$facture->fetch($TIdFacture[0]);
		}
		
		$ATMdb->close();
		
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
		$palier->appendChild($xml->createElement("mtVnf",$Affaire->montant));
		$palier->appendChild($xml->createElement("pourcVnf",(($Affaire->montant * 100) / $facture->total_ht)));

		return $palier;
	}
	
	function _getCommandeXML(&$xml,&$TAsset,&$Affaire){
		
		$commande = $xml->createElement("commande");

		//pre($TAsset[0]->asset->serial_number);exit;
		$commande->appendChild($xml->createElement("noCommande",((count($TAsset) > 1) ? date('dmY') : $TAsset[0]->asset->serial_number)));
		$commande->appendChild($xml->createElement("fournisseur","M000355961"));

		//foreach($TAsset as $a=>$assetLink){
				
			$commandeLig = $this->_getCommandeLigXML($xml,$assetLink,$Affaire,$a);
			$commande->appendChild($commandeLig);
		//}

		return $commande;
	}
	
	function _getCommandeLigXML(&$xml, &$assetLink,&$Affaire,$a){
		
		dol_include_once('/compta/facture/class/facture.class.php');
		
		$facture = $this->_getFactureXML($assetLink,$Affaire);
		
		$commandeLig = $xml->createElement("commandeLig");
		
		$commandeLig->appendChild($xml->createElement("immobilisation",$a+1));
		$commandeLig->appendChild($xml->createElement("codeTypeLigne","ABIE"));
		
		/*$nbAsset = count($Affaire->TAsset);
		
		if($a+1 == $nbAsset){
			//echo $Affaire->totalBien;exit;
			$commandeLig->appendChild($xml->createElement("mtHt",round(($facture->total_ht - $Affaire->totalHt),2)));
		}
		else{
			$Affaire->totalHt += round(($facture->total_ht / $nbAsset),2);
			$commandeLig->appendChild($xml->createElement("mtHt",round(($facture->total_ht / $nbAsset),2)));
		}*/
		
		$commandeLig->appendChild($xml->createElement("mtHt",round($facture->total_ht,2)));
		$commandeLig->appendChild($xml->createElement("codeTaxe","10"));
		
		/*if($a+1 == $nbAsset){
			//echo $Affaire->totalBien;exit;
			$commandeLig->appendChild($xml->createElement("mtTaxe",round(($facture->total_tva - $Affaire->totalTva),2)));
			$commandeLig->appendChild($xml->createElement("mtTTC",round(($facture->total_ttc - $Affaire->totalTtc),2)));
		}
		else{
			$Affaire->totalTva += round(($facture->total_tva / $nbAsset),2);
			$Affaire->totalTtc += round(($facture->total_ttc / $nbAsset),2);
			$commandeLig->appendChild($xml->createElement("mtTaxe",round(($facture->total_tva / $nbAsset),2)));
			$commandeLig->appendChild($xml->createElement("mtTTC",round(($facture->total_ttc / $nbAsset),2)));
		}*/
		
		$commandeLig->appendChild($xml->createElement("mtTaxe",round($facture->total_tva,2)));
		$commandeLig->appendChild($xml->createElement("mtTTC",round($facture->total_ttc,2)));
		
		return $commandeLig;
	}
}

class TFin_affaire_commercial extends TObjetStd {
	function __construct() { /* declaration */

		parent::set_table(MAIN_DB_PREFIX.'fin_affaire_commercial');
		parent::add_champs('fk_user,fk_fin_affaire','type=entier;index;');

		parent::_init_vars();
		parent::start();
		
	}
}

class SFTPConnection
{
    private $connection;
    private $sftp;

    public function __construct($host, $port=22)
    {

        $this->connection = ssh2_connect($host, $port);
        if (! $this->connection)
            throw new Exception("Could not connect to $host on port $port.");

    }

    public function login($username)
    {
        if (! ssh2_auth_pubkey_file($this->connection,$username, "/var/www/.ssh/id_rsa.pub","/var/www/.ssh/id_rsa"))
            throw new Exception("Could not authenticate with publickey");

        $this->sftp = ssh2_sftp($this->connection);
        if (! $this->sftp)
            throw new Exception("Could not initialize SFTP subsystem.");
    }

    public function uploadFile($local_file, $remote_file)
    {
        $sftp = $this->sftp;
        $stream = fopen("ssh2.sftp://$sftp$remote_file", 'w');

        if (! $stream)
            throw new Exception("Could not open file: $remote_file");

        $data_to_send = file_get_contents($local_file);
        if ($data_to_send === false)
            throw new Exception("Could not open local file: $local_file.");

        if (fwrite($stream, $data_to_send) === false)
            throw new Exception("Could not send data from file: $local_file.");

        fclose($stream);
    }
}

