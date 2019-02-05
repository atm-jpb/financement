<?php
/**
 * Class used to call Lixbail Soap service
 * + used to call CM CIC Soap service
 */
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;


use RobRichards\WsePhp\WSSESoap;
//use RobRichards\XMLSecLibs\XMLSecurityKey;

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require_once NUSOAP_PATH.'/nusoap.php';		// Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

dol_include_once('/financement/class/xmlseclibs/src/XMLSecEnc.php');
dol_include_once('/financement/class/xmlseclibs/src/XMLSecurityDSig.php');
dol_include_once('/financement/class/xmlseclibs/src/XMLSecurityKey.php');


dol_include_once('/financement/class/wse-php/WSASoap.php');
dol_include_once('/financement/class/wse-php/WSSESoap.php');
dol_include_once('/financement/class/wse-php/WSSESoapServer.php');

class ServiceFinancement {
	
	public $simulation;
	public $simulationSuivi;
	
	public $leaser;
	
	public $TMsg = array();
	public $TError = array();
	public $message_soap_returned = '';
	
	public $soapClient;
	public $result;
	
	public $debug;
	
	public $activate;
	public $production;
	
	public $wsdl;
	public $endpoint;
	
	/**
	 * Construteur
	 * 
	 * @param $simulation		object TSimulation			Simulation concerné par la demande
	 * @param $simulationSuivi	object TSimulationSuivi		Ligne de suivi qui fait l'objet de la demande
	 */
	public function __construct(&$simulation, &$simulationSuivi)
	{
		global $conf;
		
		$this->simulation = &$simulation;
		$this->simulationSuivi = &$simulationSuivi;
		
		$this->leaser = &$simulationSuivi->leaser;
		
		$this->debug = GETPOST('DEBUG');
		
		$this->activate = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVATE) ? true : false;
		$this->production = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVE_FOR_PROD) ? true : false;
	}
	
	private function printHeader()
	{
		header("Content-type: text/html; charset=utf8");
		print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'."\n";
		echo '<html>'."\n";
		echo '<head>';
		echo '<title>WebService Test: callTest</title>';
		echo '</head>'."\n";
		
		echo '<body>'."\n";
	}

	/**
	 * Fonction call
	 * 
	 * Ce charge de faire l'appel au bon webservice en fonction du leaser
	 * 
	 * return false if KO, true if OK
	 */
	public function call()
	{
		global $langs,$conf;
		
		if ($this->debug)
		{
			$this->printHeader();
			var_dump('DEBUG :: Function call(): leaser name = ['.$this->leaser->name.']');
		}
		
		// Si les appels ne sont pas actives alors return true
		if (!$this->activate)
		{
			if ($this->debug) var_dump('DEBUG :: Function call(): # appel webservice non actif');
			return true;
		}
		
		// TODO à revoir, peut être qu'un test sur code client ou mieux encore sur numéro SIRET
		if ($this->leaser->array_options['options_edi_leaser'] == 'LIXXBAIL')
		{
			return $this->callLixxbail();
		}
		else if ($this->leaser->array_options['options_edi_leaser'] == 'CMCIC')
		{
			return $this->callCMCIC();
		}
		
		if ($this->debug) var_dump('DEBUG :: Function call(): # aucun traitement prévu');
		
		return false;
	}
	
	/**
	 * Function callCMCIC
	 */
	private function callCMCIC()
	{
		global $conf,$langs;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CMCIC_PROD) ? $conf->global->FINANCEMENT_WSDL_CMCIC_PROD : 'https://www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CMCIC_RECETTE) ? $conf->global->FINANCEMENT_WSDL_CMCIC_RECETTE : 'https://uat-www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl';
		
		if ($this->debug) var_dump('DEBUG :: Function callCMCIC(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);
		
		$options = array(
			'exceptions'=>0
			,'location' => $this->wsdl
			,'trace' => 1
		  	,'soap_version' => SOAP_1_1
		  	,'connection_timeout' => 20
		  	,'cache_wsdl' => WSDL_CACHE_NONE
		  	,'user_agent' => 'MySoapCmCic'
		  	,'use' => SOAP_LITERAL
			,'keep_alive' => false
		);
		
		try {
			$this->soapClient = new MySoapCmCic($this->wsdl, $options);
			$this->soapClient->ServiceFinancement = $this;
			
			dol_syslog("WEBSERVICE SENDING CMCIC : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_CMCIC');
			
			$response = $this->soapClient->__soapCall('CreateDemFin', array());
  
			// TODO : issue de la doc => Dans l’éventualité où l’utilisateur est invalide, un message d’erreur est envoyé au partenaire
			if ($this->debug)
			{
				$this->printDebugSoapCall($response);
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			
			if (!empty($response->ResponseDemFin))
			{
				$this->message_soap_returned = $langs->trans($response->ResponseDemFin->ResponseDemFinShort->Rep_Statut_B2B->B2B_MSGRET);
				return true;
			}
			else
			{
				$this->message_soap_returned = $langs->trans('ServiceFinancementWrongReturn');
				return false;
			}
			
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_CMCIC');
			$this->printTrace($e); // exit fait dans la méthode
		}
	}
	
	
	
	
	/**
	 * Return only body
	 */
	public function getXmlForCmCic()
	{
		global $db,$mysoc,$conf;
		
		$u = new User($db);
		$u->fetch($this->simulation->fk_user_author);
		$dossier_origin = current($this->simulation->dossiers);
		
		$our_wsdl = $conf->global->FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC;
		if (empty($our_wsdl)) $our_wsdl = dol_buildpath('/financement/script/webservice/scoring_cmcic.php?wsdl', 2);
		
		$protocole_id = $this->getProtocolID();
		list($marqmat, $typmat) = $this->getMarqmatAndTypmat($protocole_id);
		
		$sirenCLIENT = substr($this->simulation->societe->idprof2, 0, 9);
		
		// Need pour avoir la fonction de calcul de la périodicité
		$f = new TFin_financement();
		$f->periodicite = $this->simulation->opt_periodicite;
		$dureeInMonth = $this->simulation->duree * $f->getiPeriode();
		// Spéficique CMCIC, maximum 22 T / 66 M
		if($dureeInMonth > 66) $dureeInMonth = 66;
		// Spéficique CMCIC, minimum 12 T / 36 M
		if($dureeInMonth < 36) $dureeInMonth = 36;
		// Montant minimum 800 €
		$montant = $this->simulation->montant;
		// Scoring par le montant leaser
		$montant += $this->simulationSuivi->surfact + $this->simulationSuivi->surfactplus;
		$montant = round($montant,2);
		if($montant < 800) $montant = 800;
		
		$xml = '
		<ns1:CreateDemFinRequest>
			<ns1:APP_Infos_B2B>
				<ns1:B2B_CLIENT>CPRO001</ns1:B2B_CLIENT>
				<ns1:B2B_TIMESTAMP>'.date('c').'</ns1:B2B_TIMESTAMP>
			</ns1:APP_Infos_B2B>
			<ns1:APP_CREA_Demande>
				<ns1:B2B_CTR_REN_ADJ></ns1:B2B_CTR_REN_ADJ>
				<ns1:B2B_ECTR_FLG>false</ns1:B2B_ECTR_FLG>
				<ns1:B2B_NATURE_DEMANDE>S</ns1:B2B_NATURE_DEMANDE>
				<ns1:B2B_REF_EXT>'.$this->simulation->reference.'</ns1:B2B_REF_EXT>
				<ns1:B2B_TYPE_DEMANDE>E</ns1:B2B_TYPE_DEMANDE>
			</ns1:APP_CREA_Demande>
			<ns1:Infos_Apporteur>
				<ns1:B2B_APPORTEUR_ID>'.$this->getApporteurId().'</ns1:B2B_APPORTEUR_ID>
				<ns1:B2B_PROT_ID>'.$protocole_id.'</ns1:B2B_PROT_ID>
				<ns1:B2B_VENDEUR_ID>'.$conf->global->FINANCEMENT_CMCIC_B2B_VENDEUR_ID.'</ns1:B2B_VENDEUR_ID>
				<ns1:B2B_VENDEUR_EMAIL>financement@cpro.fr</ns1:B2B_VENDEUR_EMAIL>
			</ns1:Infos_Apporteur>
			<ns1:Infos_Client>
				<ns1:B2B_SIREN>'.$sirenCLIENT.'</ns1:B2B_SIREN>
			</ns1:Infos_Client>
			<ns1:Infos_Financieres>
				<ns1:B2B_DUREE>'.$dureeInMonth.'</ns1:B2B_DUREE>
				<ns1:B2B_FREQ>'.$f->getiPeriode().'</ns1:B2B_FREQ>
				<ns1:B2B_MODPAIE>'.$this->getIdModeRglt($this->simulation->opt_mode_reglement).'</ns1:B2B_MODPAIE>
				<ns1:B2B_MT_DEMANDE>'.$montant.'</ns1:B2B_MT_DEMANDE>
				<ns1:B2B_NB_ECH>'.$dureeInMonth / $f->getiPeriode().'</ns1:B2B_NB_ECH>
				<ns1:B2B_MINERVAFPID>'.(($protocole_id == '0251') ? '983' : '9782').'</ns1:B2B_MINERVAFPID>
				<ns1:B2B_TERME>'.($this->simulation->opt_terme == 0 ? 2 : 1).'</ns1:B2B_TERME>
				<ns1:B2B_PVR>0</ns1:B2B_PVR>
			</ns1:Infos_Financieres>
			<ns1:Infos_Materiel>
				<ns1:B2B_MARQMAT>'.$marqmat.'</ns1:B2B_MARQMAT>
				<ns1:B2B_MT_UNIT>'.$montant.'</ns1:B2B_MT_UNIT>
				<ns1:B2B_QTE>1</ns1:B2B_QTE>
				<ns1:B2B_TYPMAT>'.$typmat.'</ns1:B2B_TYPMAT>
				<ns1:B2B_ETAT>N</ns1:B2B_ETAT>
			</ns1:Infos_Materiel>
			<ns1:APP_Reponse_B2B>
				<ns1:B2B_CLIENT_ASYNC>'.$our_wsdl.'</ns1:B2B_CLIENT_ASYNC>
				<ns1:B2B_INF_EXT>'.$this->simulation->reference.'</ns1:B2B_INF_EXT>
				<ns1:B2B_MODE>A</ns1:B2B_MODE>
			</ns1:APP_Reponse_B2B>
		</ns1:CreateDemFinRequest>
		';
		
		return $xml;
	}
	
	
	
	
	/**
	 * Function callLixxbail
	 */
	private function callLixxbail()
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
			$string_xml_body = $this->getXmlForLixxbail();
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
	
	
	/**
	 * Renvoi l'identifiant de l'apporteur d'affaire (extrafield "entity")
	 * @global type $db
	 * @return type
	 */
	public function getApporteurId()
	{
		global $db;
		
		$dao = new DaoMulticompany($db);
		$dao->fetch($this->simulation->entity);
		
		return $dao->array_options['options_cmcic_apporteur_id'];
	}
	/**
	 * Renvoie l'id de l'apporteur et l'id du protocole (conditionné actuellement à l'entité de la simulation)
	 */
	public function getProtocolID()
	{
		$name = $this->simulationSuivi->leaser->name;
		if (empty($name)) $this->simulationSuivi->leaser->nom;
		
		
		$id = '0251';
		if (preg_match('/MANDATEE|mandatée|MANDATÉE|mandatee/', $name)) $id = '0240';
		
		return $id;
	}
	
	public function getMarqmatAndTypmat()
	{
		$TMarque = array(
			'HP' => 'HP'
			,'KONICA MINOLTA' => 'KM'
			,'KYOCERA' => 'KYO'
			,'OCE' => 'OCE'
			,'OKI' => 'OKI'
			,'TOSHIBA' => 'TOS'
			,'CANON' => 'CAN'
		);

		$TType = array(
			663 => 'MATINFO' // Ensemble de matériels bureautique
			,117 => 'CONFINF' // Logiciels
			,107 => 'MATINFO' // Micro ordinateur
			,665 => 'PHOTOCO' // Photocopieur
			,113 => 'SERVEUR' // Serveur vocal
			,114 => 'IMPRIM' // Station
			,116 => 'TRACPLA' // Traceur
		);
		
		$m = !empty($TMarque[$this->simulation->marque_materiel]) ? $TMarque[$this->simulation->marque_materiel] : '';
		$t = !empty($TType[$this->simulation->fk_nature_bien]) ? $TType[$this->simulation->fk_nature_bien] : '';
		
		// On passe en dur les types et marques
		if($this->simulation->entity) {
			$t = 'PHOTOCO';
			$m = 'CAN';
		}
		
		return array($m, $t);
	}
	
	private function getXmlForLixxbail()
	{
		global $db, $conf, $mysoc;
		
		// Récupération configuration de l'entité de la simulation
        $old_conf = $conf;
        $old_mysoc = $mysoc;
        switchEntity($conf->entity);
		
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
		$sirenCPRO = substr($mysoc->idprof2,0,9);
		$nicCPRO = substr($mysoc->idprof2, -5, 5);
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
			               <v1:COMMERCIAL_EMAIL>'.$mysoc->email.'</v1:COMMERCIAL_EMAIL>
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

		switchEntity($old_conf->entity);
	
		return $xml;
	}
	
	public function getIdModeRglt($opt_mode_reglement)
	{
		global $langs;
		
		$TId = array();
		if (strpos($this->leaser->name, 'LIXXBAIL') !== false)
		{
			$TId = array(
				'CHQ' => 1
				,'PRE' => 2
				,'MDT' => 1 // Non géré pas cal&f
				,'VIR' => 1 // Non géré pas cal&f
			);	
		}
		else if ($this->leaser->array_options['options_edi_leaser'] == 'CMCIC')
		{
			$TId = array(
				'CHQ' => 'CHQ'
				,'PRE' => 'AP'
				,'MDT' => 'MDT'
				,'VIR' => 'VIR'
			);
		}
		
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
		
		if (strpos($this->leaser->name, 'LIXXBAIL') !== false)
		{
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
		}
		
		if (empty($TId[$opt_periodicite]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_periodicite', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_periodicite];
	}
	
	/** Cal&f
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

	/** Cal&f
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
	
	/** Cal&f
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

	
	
	private function printDebugSoapCall($response)
	{
		// on affiche la requete et la reponse
		echo '<br />';
		echo "<h2>Request:</h2>";
		echo '<h4>Function</h4>';
		echo 'call DemandeCreationLeasingGN';
		echo '<h4>SOAP Message</h4>';
		echo '<pre>' . htmlspecialchars($this->soapClient->__getLastRequest(), ENT_QUOTES) . '</pre>';

		echo '<hr>';

		echo "<h2>Response:</h2>";
		echo '<h4>Result</h4>';
		echo '<pre>';
		print_r($response);
		echo '</pre>';
		echo '<h4>SOAP Message</h4>';
		echo '<pre>' . htmlspecialchars($this->soapClient->__getLastResponse(), ENT_QUOTES) . '</pre>';

		echo '<hr>';

		echo '<br />';
		echo "<h2>Request realXML:</h2>";
		echo '<h4>Function</h4>';
		echo 'call DemandeCreationLeasingGN';
		echo '<h4>SOAP Message</h4>';
		echo '<pre>' . htmlspecialchars($this->soapClient->realXML, ENT_QUOTES) . '</pre>';

		echo '</body>'."\n";
		echo '</html>'."\n";
		exit;
	}
	
	private function printTrace($e)
	{
		echo '<b>Caught exception:</b> ',  $e->getMessage(), "\n"; 

		$trace = $e->getTrace();
		var_dump('ERROR TRACE 1: $trace[0]["args"][0] => ');

		echo '<pre>' . htmlspecialchars($trace[0]['args'][0], ENT_QUOTES) . '</pre>';

		var_dump('ERROR TRACE 2: $trace[0]["args"][1] => ');
		var_dump($trace[0]['args'][1]);


		var_dump('ERROR TRACE 3: $trace[0]["args"][1][0]->enc_value => ');
		echo '<pre>' . htmlspecialchars($trace[0]['args'][1][0]->enc_value, ENT_QUOTES) . '</pre>';


		var_dump('ERROR TRACE 8: $e');
		var_dump($e);

		echo ($e->__toString());
		exit;
	}
	
} // End Class


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
}

/**
 * Soap class for CMCIC
 */
class MySoapCmCic extends SoapClient
{
	public $ServiceFinancement;
	
	function __doRequest($request, $location, $saction, $version)
	{
		global $conf;
		
		// TODO Username & Password en conf
		$request = '
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.ge.com/capital/eef/france/extranet/service/wsdemande/document" xmlns:ns2="https://uat-www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl">
	<SOAP-ENV:Header>
		<ns2:Security>
			<UsernameToken>
				<Username>'.$conf->global->FINANCEMENT_CMCIC_USERNAME.'</Username>
				<Password>'.$conf->global->FINANCEMENT_CMCIC_PASSWORD.'</Password>
			</UsernameToken>
		</ns2:Security>
	</SOAP-ENV:Header>
	<SOAP-ENV:Body>
		'.$this->ServiceFinancement->getXmlForCmCic().'
	</SOAP-ENV:Body>
</SOAP-ENV:Envelope>';
		
		
		
		$this->realXML = $request;
//		$this->realXML = str_replace(array('SOAP-ENV', 'ns1', 'ns2:'), array('soapenv', 'doc', ''), $this->realXML);
//		$this->realXML = preg_replace('/ xmlns:ns2=".*"/', '', $this->realXML);
		
//		$this->realXML = str_replace('<soapenv:Body>', '<soapenv:Header/><soapenv:Body>', $this->realXML);
/*		$this->realXML = str_replace('<?xml version="1.0" encoding="UTF-8"?>'."\n", '', $this->realXML);
*/		
		$this->realXML = str_replace('<ns1:B2B_CTR_REN_ADJ></ns1:B2B_CTR_REN_ADJ>', '<ns1:B2B_CTR_REN_ADJ/>', $this->realXML);
		
		return parent::__doRequest($this->realXML, $location, $saction, $version);
	}
}
