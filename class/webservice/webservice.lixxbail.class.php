<?php

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RobRichards\WsePhp\WSSESoap;

dol_include_once('/financement/class/xmlseclibs/src/XMLSecEnc.php');
dol_include_once('/financement/class/xmlseclibs/src/XMLSecurityDSig.php');
dol_include_once('/financement/class/xmlseclibs/src/XMLSecurityKey.php');
dol_include_once('/financement/class/wse-php/WSASoap.php');
dol_include_once('/financement/class/wse-php/WSSESoap.php');
dol_include_once('/financement/class/wse-php/WSSESoapServer.php');

class WebServiceLixxbail extends WebService 
{
	public function run()
	{
		global $conf,$langs;
		
		$this->wsdl = dol_buildpath('/financement/files/DemandeCreationLeasingGNV1.wsdl');
		
		// Production ou Test
		if ($this->production) $this->endpoint = !empty($conf->global->FINANCEMENT_ENDPOINT_CALF_PROD) ? $conf->global->FINANCEMENT_ENDPOINT_CALF_PROD : 'https://archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1';
		else $this->endpoint = !empty($conf->global->FINANCEMENT_ENDPOINT_CALF_RECETTE) ? $conf->global->FINANCEMENT_ENDPOINT_CALF_RECETTE : 'https://hom-archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1';
		
		
		if ($this->debug) var_dump('DEBUG :: Function callLixxbail(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);
		
		// TODO normalement ce if sert à rien => à delete
		if (!empty($this->TError))
		{
			if ($this->debug) var_dump('DEBUG :: error catch =v', $this->TError);
			return false;
		}
		
		
		$options = array(
			'exceptions'=>0
			,'trace' => 1
			,'location' =>  $this->endpoint
		  	,'soap_version' => SOAP_1_2
		  	//,'local_cert' => dol_buildpath('/financement/crt/CALF/cert.pem')
		  	,'connection_timeout' => 20
		  	,'cache_wsdl' => WSDL_CACHE_NONE
		  	,'user_agent' => 'MySoapClient'
		  	
		);
			
			
		try {
			$this->soapClient = new MySoapClient($this->wsdl, $options);
			$this->soapClient->ref_ext = $this->simulation->reference;
			
			dol_syslog("WEBSERVICE SENDING LIXXBAIL : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_CALF');
			
			//$response = $this->soapClient->DemandeCreationLeasingGN($TParam);
			$string_xml_body = $this->getXml();
			$soap_var_body = new SoapVar($string_xml_body, XSD_ANYXML, null, null, null);
			$response = $this->soapClient->DemandeCreationLeasingGN($soap_var_body);
  
			if ($this->debug)
			{
				$this->printDebugSoapCall($response);
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			// TODO récupérer le message exact de la réponse pour le mettre dans ->message_soap_returned
			// afin de savoir si la demande a bien été prise en compte
			// use $response
			// nécessite de serialiser le retour et de faire un dolibarr_set_const pour connaitre maintenant le format exact du retour car l'url de test n'est plus opérationnelle
//global $db;
//dolibarr_set_const($db, 'SERVICE_FINANCEMENT_LIXXBAIL_RESPONSE', serialize($obj_response), 'chaine', 0, '', $conf->entity);
			
			$this->message_soap_returned = $langs->trans('ServiceFinancementCallDone');
//			$this->message_soap_returned = $langs->trans('ServiceFinancementWrongReturn');
			
			return true;
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_CALF');
			$this->printTrace($e); // exit fait dans la méthode
		}
	}
	
	public function getXml()
	{
		global $db;
		
		// Récupération configuration de l'entité de la simulation
		$confentity = new Conf();
		$confentity->entity = $this->simulation->entity;
		$confentity->setValues($db);
		
		$mysocentity=new Societe($db);
		$mysocentity->setMysoc($confentity);
		
		// Need pour avoir la fonction de calcul de la périodicité
		$f = new TFin_financement();
		$f->periodicite = $this->simulation->opt_periodicite;
		$dureeInMonth = $this->simulation->duree * $f->getiPeriode();
		// Spéficique CALF, maximum 21 T / 63 M
		if($dureeInMonth > 63) $dureeInMonth = 63;
		// Spéficique CALF, minimum 8 T / 21 M
		if($dureeInMonth < 24) $dureeInMonth = 24;
		// Montant minimum 1000 €
		$montant = $this->simulation->montant;
		// Scoring par le montant leaser
		$montant += $this->simulationSuivi->surfact + $this->simulationSuivi->surfactplus;
		$montant = round($montant,2);
		if($montant < 1000) $montant = 1000;
		
		$mode_reglement_id = $this->getIdModeRglt($this->simulation->opt_mode_reglement);
		$periodicite_code = $this->getCodePeriodiciteFinancement($this->simulation->opt_periodicite);
		
		$pct_vr = $this->simulation->pct_vr;
		$mt_vr = $this->simulation->mt_vr;
		
		// SIRET / NIC
		$sirenCPRO = substr($mysocentity->idprof2,0,9);
		$nicCPRO = substr($mysocentity->idprof2, -5, 5);
		$sirenCLIENT = substr($this->simulation->societe->idprof2, 0, 9);
		$nicCLIENT = strlen($this->simulation->societe->idprof2) == 14 ? substr($this->simulation->societe->idprof2, -5, 5) : '';
		$nicCLIENT = ''; // On envoie vide car depuis correction des SIRET si on envoie pas le bon établissement, LIXXBAIL renvoie une erreur
		
		if (!empty($pct_vr) && !empty($mt_vr)) $pct_vr = 0; // Si les 2 sont renseignés alors je garde que le montant
		//<soap1:Calf_Header_GN xmlns:soap1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" correlationId="12345" wsu:Id="id-11"/></soap:Header>
		$xml = '
			
				<v1:DemandeCreationLeasingGN xmlns:v1="http://referentiel.ca.fr/Services/calf/DemandeCreationLeasingGN/V1/">
			         <v1:Request>
			            <v1:PARTENAIRE>
			               <v1:SIREN_PARTENAIRE>'.$sirenCPRO.'</v1:SIREN_PARTENAIRE>
			               <v1:NIC_PARTENAIRE>'.$nicCPRO.'</v1:NIC_PARTENAIRE>
			               <v1:COMMERCIAL_EMAIL>'.$mysocentity->email.'</v1:COMMERCIAL_EMAIL>
			               <v1:REF_EXT>'.$this->simulation->reference.'</v1:REF_EXT>
			            </v1:PARTENAIRE>
			            <v1:BIEN>
			               <v1:CATEGORIE_BIEN>'.$this->getIdCategorieBien().'</v1:CATEGORIE_BIEN>
			               <v1:NATURE_BIEN>'.$this->getIdNatureBien().'</v1:NATURE_BIEN>
			               <v1:MARQUE_BIEN>'.$this->getIdMarqueBien().'</v1:MARQUE_BIEN>
			               <v1:ANNEE_BIEN>'.date('Y').'</v1:ANNEE_BIEN>
			               <v1:ETAT_BIEN>NEUF</v1:ETAT_BIEN>
			               <v1:QTE_BIEN>1</v1:QTE_BIEN>
			               <v1:MT_HT_BIEN>'.$montant.'</v1:MT_HT_BIEN>
			               <v1:PAYS_DESTINATION_BIEN>'.(!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR').'</v1:PAYS_DESTINATION_BIEN>
			               <v1:FOURNISSEUR_SIREN>'.$sirenCPRO.'</v1:FOURNISSEUR_SIREN>
			               <v1:FOURNISSEUR_NIC>'.$nicCPRO.'</v1:FOURNISSEUR_NIC>
			            </v1:BIEN>
			            <!--1 or more repetitions:-->
			            <v1:BIEN_COMPL>
			            <!--   <v1:CATEGORIE_BIEN_COMPL>U</v1:CATEGORIE_BIEN_COMPL>
			               <v1:NATURE_BIEN_COMPL>U03C</v1:NATURE_BIEN_COMPL>
			               <v1:MARQUE_BIEN_COMPL>T046</v1:MARQUE_BIEN_COMPL>
			               <v1:ANNEE_BIEN_COMPL>2016</v1:ANNEE_BIEN_COMPL>
			               <v1:ETAT_BIEN_COMPL>NEUF</v1:ETAT_BIEN_COMPL>
			               <v1:MT_HT_BIEN_COMPL>1000.01</v1:MT_HT_BIEN_COMPL>
			               <v1:QTE_BIEN_COMPL>2</v1:QTE_BIEN_COMPL>
			            -->
			            </v1:BIEN_COMPL> 
			            <v1:CLIENT>
			               <v1:CLIENT_SIREN>'.$sirenCLIENT.'</v1:CLIENT_SIREN>
			               <v1:CLIENT_NIC>'.$nicCLIENT.'</v1:CLIENT_NIC>
			            </v1:CLIENT>
			            <v1:FINANCEMENT>
			               <v1:CODE_PRODUIT>'.$this->getCodeProduit().'</v1:CODE_PRODUIT>
			               <v1:TYPE_PRODUIT>'.$this->getTypeProduit().'</v1:TYPE_PRODUIT>
			               <v1:MT_FINANCEMENT_HT>'.$montant.'</v1:MT_FINANCEMENT_HT>
			               <v1:PCT_VR>'.$pct_vr.'</v1:PCT_VR>
			               <v1:MT_VR>'.$mt_vr.'</v1:MT_VR>
			               <v1:TYPE_REGLEMENT>'.$mode_reglement_id.'</v1:TYPE_REGLEMENT>
			               <v1:MT_PREMIER_LOYER>0</v1:MT_PREMIER_LOYER>
			               <v1:DUREE_FINANCEMENT>'.$dureeInMonth.'</v1:DUREE_FINANCEMENT>
			               <v1:PERIODICITE_FINANCEMENT>'.$periodicite_code.'</v1:PERIODICITE_FINANCEMENT>
			               <v1:TERME_FINANCEMENT>'.($this->simulation->opt_terme == 1 ? 'A' : 'E').'</v1:TERME_FINANCEMENT>
			               <v1:NB_FRANCHISE>0</v1:NB_FRANCHISE>
			               <v1:NATURE_FINANCEMENT>STD</v1:NATURE_FINANCEMENT>
			               <v1:DATE_DEMANDE_FINANCEMENT>'.date('Y-m-d').'T'.date('H:i:s').'</v1:DATE_DEMANDE_FINANCEMENT>
			            </v1:FINANCEMENT>
			         </v1:Request>
				</v1:DemandeCreationLeasingGN>
		';
	
		return $xml;
	}

	public function getIdModeRglt($opt_mode_reglement)
	{
		global $langs;
		
		$TId = array(
			'CHQ' => 1
			,'PRE' => 2
			,'MDT' => 1 // Non géré pas cal&f
			,'VIR' => 1 // Non géré pas cal&f
		);
		
		if (empty($TId[$opt_mode_reglement]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_mode_reglement', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_mode_reglement];
	}
	
	public function getCodePeriodiciteFinancement($opt_periodicite)
	{
		global $langs;
		
		/**
		 * Autre valeurs possible
		 * Code		Libellé
		 * B		BIMESTRIEL
		 * H		HEBDOMADAIRE
		 * J		JOURNALIER
		 * Q		QUADRIMESTRIEL
		 * X		SAISONNIER
		 * 9		INDETERMINE 
		 */	

		$TId = array(
			'ANNEE' => 'A'
			,'SEMESTRE' => 'S'
			,'TRIMESTRE' => 'T'
			,'MOIS' => 'M'
		);
		
		if (empty($TId[$opt_periodicite]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_periodicite', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_periodicite];
	}
	
	/** 
	 * Cal&f
	 *	CAT_ID	LIB_CAT
	 *	2		INFORMATIQUE
	 *	U		BUREAUTIQUE
	 */
	public function getIdCategorieBien()
	{
		if($this->simulation->entity == 3) return 'V';
		if($this->simulation->entity == 2) return '2';
		return 'U';
	}
	
	/** 
	 * Cal&f
	 * NAT_ID	LIB_NAT
	 * 209B	Micro ordinateur
	 * 215B	Serveur vocal
	 * 216B	Station							
	 * 218C	Traceur							
	 * 219Q	Logiciels						
	 * U01C	Ensemble de matériels bureautique
	 * U03C	Photocopieur
	 */
	public function getIdNatureBien()
	{
		if($this->simulation->entity == 3) return 'V08Q';
		
		// On envoie photocopieur systématiquement
		return 'U03C';
		
		$label = $this->getNatureLabel($this->simulation->fk_nature_bien);
		
		switch ($label) {
			case 'Micro ordinateur':
				return '209B';
				break;
			case 'Serveur vocal':
				return '215B';
				break;
			case 'Station':
				return '216B';
				break;
			case 'Traceur':
				return '218C';
				break;
			case 'Logiciels':
				return '219Q';
				break;
			case 'Ensemble de matériels bureautique':
				return 'U01C';
				break;
			case 'Photocopieur':
				return 'U03C';
				break;
		}
		
		return '';
	}
	
	private function getNatureLabel($fk_nature_bien)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_nature_bien WHERE nat_id = '.$fk_nature_bien;
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			$this->simulation->nature_label = $row->label;
			return $row->label;
		}
		
		return '';
	}
	
	/** 
	 * Cal&f
	 * MRQ_ID	LIB_MRQ
	 * Z999	GENERIQUE
	 * T046	TOSHIBA
	 * H113	HEWLETT PACKARD
	 * C098	CANON
	 * R128	RISO
	 * 0034	OCE
	 */
	public function getIdMarqueBien()
	{
		// Envoi GENERIQUE car marque dépend de nature et catégorie
		return 'Z999';
		
		$label = $this->getMarqueLabel($this->simulation->marque_materiel);
		
		switch ($label) {
			case 'GENERIQUE':
				return 'Z999';
				break;
			case 'TOSHIBA':
				return 'T046';
				break;
			case 'HP':
				return 'H113';
				break;
			case 'CANON':
				return 'C098';
				break;
			case 'RISO':
				return 'R128';
				break;
			case 'OCE':
				return '0034';
				break;
		}
		
		return '';
	}
	
	private function getMarqueLabel($fk_marque_materiel)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_marque_materiel WHERE code = "'.$fk_marque_materiel.'"';
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			$this->simulation->marque_label = $row->label;
			return $row->label;
		}
		
		return '';
	}
	
	/**
	 * TODO à construire
	 * Code 	Libellé
	 * produit	produit
	 * 
	 * LOCF 	Location
	 * LOA		Location avec Option d'Achat
	 */
	public function getCodeProduit()
	{
		return 'LOCF';
	}
}

/**
 * Becareful, this class is use only for callLixxbail
 */
class MySoapClient extends SoapClient
{
	function __doRequest($request, $location, $saction, $version)
	{
		$doc = new DOMDocument('1.0');
		$doc->loadXML($request);
		
		$doc->ref_ext = $this->ref_ext;
		
		$objWSSE = new WSSESoap($doc);
		
		/* timestamp expires after five minutes */
		$objWSSE->addTimestamp(4000);
		
		/* create key object, set passphrase and load key */
		$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
		//$objKey->passphrase = 'My password.';
		$objKey->loadKey('/etc/apache2/ssl/cert.key', TRUE);
	
		/* sign message */
		$options = array('algorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256');
		$objWSSE->signAllHeaders = true; // Obligatoire car j'ajoute la balise "Calf_Header_GN" dans l'objet via ma méthode privée "locateSecurityHeader", du coup la librairie n'est pas utilisable pour les autres
		$objWSSE->signSoapDoc($objKey, $options);
		
		/* add certificate */
		$token = $objWSSE->addBinaryToken(file_get_contents('/etc/apache2/ssl/cert.prod.crt'));
		$objWSSE->attachTokentoSig($token);
		
		// this DOES print the header
		// echo $objWSSE->saveXML();
		
		/*echo '<pre>' . htmlspecialchars($objWSSE->saveXML(), ENT_QUOTES) . '</pre>';
		exit;*/
		
		$this->realXML = $objWSSE->saveXML();
		$this->realXML = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->realXML);
		
		
		return parent::__doRequest($this->realXML, $location, $saction, $version);
	}
	
	/**
	 * TODO à combiner avec $this->getCodeProduit()
	 * Type		Libellé type produit
	 * produit
	 * 
	 * STAN		Standard
	 * CESS		Cession de contrat (sans prestation)
	 * LMAF		Location mandatée fichier
	 * PROF		LOA professionnelle
	 */
	public function getTypeProduit()
	{
		if(strpos($this->leaser->name, 'LIXXBAIL MANDATE') !== false)
			return 'LMAF';
		if(strpos($this->leaser->name, 'LIXXBAIL') !== false)
			return 'CESS';
	}
}