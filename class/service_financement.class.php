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
	
	public $TMsg;
	public $TError;
	
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
		
		$this->TMsg = array();
		$this->TError = array();
		
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

			dol_syslog("WEBSERVICE SENDING CMCIC : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_CMCIC');
	
			$TParam = $this->getTParamForCMCIC();
			$response = $this->soapClient->__soapCall('CreateDemFin', $TParam);
  
			// TODO : issue de la doc => Dans l’éventualité où l’utilisateur est invalide, un message d’erreur est envoyé au partenaire
	
			if ($this->debug)
			{
				// on affiche la requete et la reponse
				echo '<br />';
				echo "<h2>Request:</h2>";
				echo '<h4>Function</h4>';
				echo 'call CreateDemFinRequest';
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
				echo 'call CreateDemFinRequest';
				echo '<h4>SOAP Message</h4>';
				echo '<pre>' . htmlspecialchars($this->soapClient->realXML, ENT_QUOTES) . '</pre>';
				
				
				
				echo '</body>'."\n";
				echo '</html>'."\n";
				exit;
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			return true;
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_CMCIC');
			
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
		
		//$TParam = $this->_getTParamLixxbail(true);
		//if ($this->debug) var_dump('DEBUG :: TParam =v', $TParam);
		
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

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			return true;
		} catch (SoapFault $e) {
				
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_CALF');
			
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
	}

			
	function iso_8601_utc_time($precision = 0, $decale = 0)
	{
		$time = gettimeofday();

		$time['sec'] += $decale;

		if (is_int($precision) && $precision >= 0 && $precision <= 6)
		{
			$total = (string) $time['sec'].'.'.str_pad((string) $time['usec'], 6, '0', STR_PAD_LEFT);
			$total_rounded = bcadd($total, '0.'.str_repeat('0', $precision).'5', $precision);
			@list($integer, $fraction) = explode('.', $total_rounded);
			$format = $precision == 0 ? "Y-m-d\TH:i:s" : "Y-m-d\TH:i:s.".$fraction."";
			return gmdate($format, $integer);
		}

		return false;
	}
	
	private function getTParamForCMCIC()
	{
		global $db,$mysoc,$conf;
		
		$frequence = 1;
		if ($this->simulation->opt_periodicite == 'TRIMESTRE') $frequence = 3;
		else if ($this->simulation->opt_periodicite == 'SEMESTRE') $frequence = 6;
		else if ($this->simulation->opt_periodicite == 'ANNEE') $frequence = 12;
		
		$u = new User($db);
		$u->fetch($this->simulation->fk_user_author);
		$dossier_origin = current($this->simulation->dossiers);
		
		$our_wsdl = $conf->global->FINANCEMENT_OUR_WSDL_GIVE_TO_CMCIC;
		if (empty($our_wsdl)) $our_wsdl = dol_buildpath('/financement/script/webservice/scoring_server.php?wsdl', 2);
		
		$protocole_id = $this->getProtocolID();
		list($marqmat, $typmat) = $this->getMarqmatAndTypmat($protocole_id);
		
		$TParam = array(
			'APP_Infos_B2B' => array(
				'B2B_CLIENT' => 'CPRO001' // TODO à déterminer [char 10]*
				,'B2B_TIMESTAMP' => date('c') // Date au format ISO 8601 (2004-02-12T15:19:21+00:00)
			)
			,'APP_CREA_Demande' => array(
				'B2B_CTR_REN_ADJ' => !empty($this->simulation->opt_adjonction) ? $dossier_origin->num_contrat : ''
				,'B2B_ECTR_FLG' => false
				,'B2B_NATURE_DEMANDE' => !empty($this->simulation->opt_adjonction) ? 'A' : 'S'
				,'B2B_TYPE_DEMANDE' => 'E' // *
			)
			,'Infos_Apporteur' => array(
				'B2B_APPORTEUR_ID' => $this->getApporteurId() // [char 9]* TODO à vérifier
				,'B2B_PROT_ID' => $protocole_id // [char 4]* TODO à vérifier
				,'B2B_VENDEUR_EMAIL' => $u->email // Si vide alors il faut renseigner B2B_VENDEUR_ID
			)
			,'Infos_Client' => array(
				'B2B_SIREN' => $mysoc->idprof1 // [char 9]*
			)
			,'Infos_Financieres' => array(
				'B2B_FREQ' => $frequence
				,'B2B_NB_ECH' => $this->simulation->duree
				,'B2B_MODPAIE' => $this->getIdModeRglt($this->simulation->opt_mode_reglement) // *
				,'B2B_MT_DEMANDE' => $this->simulation->montant
			
				,'B2B_MINERVAFPID' => ($protocole_id == '0251') ? '983' : '9782'
				// Dolibarr [echu = 0; à échoir = 1] et CMCIC [echu = 2; à échoir = 1] 
				,'B2B_TERME' => $this->simulation->opt_terme == 0 ? 2 : 1
			)
			,'Infos_Materiel' => array(
				'B2B_MARQMAT' => $marqmat // * TODO à vérifier
				,'B2B_TYPMAT' => $typmat // * TODO à vérifier
				,'B2B_MT_UNIT' => $this->simulation->montant // *
				,'B2B_QTE' => 1 // *
				,'B2B_ETAT' => 'N' // *
			)
			,'APP_Reponse_B2B' => array(
				'B2B_CLIENT_ASYNC' => $our_wsdl // wsdl du module financement (/financement/script/webservice/scoring_server.php) *
				,'B2B_INF_EXT' => $this->simulation->reference // *
				,'B2B_MODE' => 'A' // Toujours "A" *
			)
		);
		
		return array($TParam);
	}
	
	/**
	 * Renvoi l'identifiant de l'apporteur d'affaire (extrafield "entity")
	 * @global type $db
	 * @return type
	 */
	private function getApporteurId()
	{
		global $db;
		
		$dao = new DaoMulticompany($db);
		$dao->fetch($this->simulation->entity);
		
		return $dao->array_options['options_cmcic_apporteur_id'];
	}
	/**
	 * Renvoie l'id de l'apporteur et l'id du protocole (conditionné actuellement à l'entité de la simulation)
	 */
	private function getProtocolID()
	{
		$name = $this->simulationSuivi->leaser->name;
		if (empty($name)) $this->simulationSuivi->leaser->nom;
		
		
		$id = '0251';
		if (preg_match('/MANDATEE|mandatée|MANDATÉE|mandatee/', $name)) $id = '0240';
		
		return $id;
	}
	
	private function getMarqmatAndTypmat()
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
		
		return array($m, $t);
	}
	
	private function getXmlForLixxbail()
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
		$label = $this->getCategoryLabel($this->simulation->fk_categorie_bien);
		
		if ($label == 'INFORMATIQUE') return 2;
		elseif ($label == 'BUREAUTIQUE') return 'U';
		else return '';
	}

	private function getCategoryLabel($fk_categorie_bien)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_categorie_bien WHERE cat_id = '.$fk_categorie_bien;
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			$this->simulation->category_label = $row->label;
			return $row->label;
		}
		
		return '';
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
	
	/**
	 * Function to prepare data to send to Lixxbail
	 * Used only for test
	 */
	private function _getTParamLixxbail($as_array=false)
	{
		global $mysoc;
		
		$mode_reglement_id = $this->getIdModeRglt($this->simulation->opt_mode_reglement);
		$periodicite_code = $this->getCodePeriodiciteFinancement($this->simulation->opt_periodicite);
		
		$pct_vr = $this->simulation->pct_vr;
		$mt_vr = $this->simulation->mt_vr;
		
		if (!empty($pct_vr) && !empty($mt_vr)) $pct_vr = 0; // Si les 2 sont renseignés alors je garde que le montant
		$aa = substr($mysoc->idprof2, -5, 5);
		//var_dump(substr($mysoc->idprof2, -5, 5), $mysoc->idprof2, $aa);exit;
		if ($as_array)
		{
			$TParam = array('Request' => array(
				'PARTENAIRE' => array( // 1..1
						'SIREN_PARTENAIRE' => $mysoc->idprof1 // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 9 *
						,'NIC_PARTENAIRE' => substr($mysoc->idprof2, -5, 5) // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 5 *
						,'COMMERCIAL_EMAIL' => $this->simulationSuivi->user->email // TODO vérifier si on doit prendre l'email du user associé à la simulation et non celui du suivi // format d'une adresse email *
						,'REF_EXT' => $this->simulation->reference // chaîne de caractères alphanumérique de 20 caractères max *
				)
				,'BIEN' => array( // 1..1
						'CATEGORIE_BIEN' => $this->getIdCategorieBien() // numérique entier sur 10 positions max. Cf. onglet 'Référentiel de biens C'PRO' *
						,'NATURE_BIEN' => $this->getIdNatureBien() // numérique entier sur 10 positions max. Cf. onglet 'Référentiel de biens C'PRO' *
						,'MARQUE_BIEN' => $this->getIdMarqueBien() // numérique entier sur 10 positions max. Cf. onglet 'Référentiel de biens C'PRO' *
						,'ANNEE_BIEN' => date('Y') // numérique entier sur 4 positions *
						,'ETAT_BIEN' => 'NEUF' // 'NEUF' OU 'OCCA' *
						,'QTE_BIEN' => 1 // numérique entier *
						,'MT_HT_BIEN' => $this->simulation->montant // numérique décimal (. comme séparateur décimal) *
						,'PAYS_DESTINATION_BIEN' => !empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR' // code ISO2 (2 positions). Pour France, 'FR'. *
						,'FOURNISSEUR_SIREN' => $mysoc->idprof1 // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 9 *
						,'FOURNISSEUR_NIC' => substr($mysoc->idprof2, -5, 5) // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 5 *
				)
				,'BIEN_COMPL' => array( // 1..n
					0 => array()
					/*0 => array(
						'CATEGORIE_BIEN_COMPL' => '' // NO
						,'NATURE_BIEN_COMPL' => '' // NO
						,'MARQUE_BIEN_COMPL' => '' // NO
						,'ANNEE_BIEN_COMPL' => '' // NO
						,'ETAT_BIEN_COMPL' => '' // NO
						,'MT_HT_BIEN_COMPL' => '' // NO
						,'QTE_BIEN_COMPL' => '' // NO
					)
					,1 => array(
						'CATEGORIE_BIEN_COMPL' => ''
						,'NATURE_BIEN_COMPL' => ''
						,'MARQUE_BIEN_COMPL' => ''
						,'ANNEE_BIEN_COMPL' => ''
						,'ETAT_BIEN_COMPL' => ''
						,'MT_HT_BIEN_COMPL' => ''
						,'QTE_BIEN_COMPL' => ''
					)*/
				)
				,'CLIENT' => array( // 1..1
						'CLIENT_SIREN' => $this->simulation->societe->idprof1 // Toujours entité à partir de laquelle on score *
						,'CLIENT_NIC' => substr($this->simulation->societe->idprof2, -5, 5) // Toujours entité à partir de laquelle on score
				)
				,'FINANCEMENT' => array( // 1..1
						'CODE_PRODUIT' => $this->getCodeProduit() // chaîne de caractères alphanumérique de 8 caractères max. Cf. onglet 'Produit' *
						,'TYPE_PRODUIT' => $this->getTypeProduit() // chaîne de caractères alphanumérique de 8 caractères max. Cf. onglet 'Produit' *
						,'MT_FINANCEMENT_HT' => $this->simulation->montant // numérique décimal (. comme séparateur décimal) *
						,'PCT_VR' => $pct_vr // Doit être saisie par CPro - Pourcentage de la valeur résiduelle. L'élément est exclusif de l'élément MT_VR.
						,'MT_VR' => $mt_vr // Doit être saisie par CPro - Montant de la valeur résiduelle, en euros. L'élément est exclusif de l'élément PCT_VR.
						,'TYPE_REGLEMENT' => $mode_reglement_id // *
						,'MT_PREMIER_LOYER' => 0 // NO
						,'DUREE_FINANCEMENT' => $this->simulation->duree // *
						,'PERIODICITE_FINANCEMENT' => $periodicite_code // chaîne de caractères alphanumérique de 3 caractères max. Cf. onglet 'Périodicité de financement' *
						,'TERME_FINANCEMENT' => $this->simulation->opt_terme == 1 ? 'A' : 'E' // 4 char. échu ou à échoir *
						,'NB_FRANCHISE' => 0 // NO
						,'NATURE_FINANCEMENT' => 'STD' // NO - Voir si saisie par CPro
						,'DATE_DEMANDE_FINANCEMENT' => date('Y-m-d').'T'.date('H:i:s') // format YYYY-MM-DDThh:mm:ss *
				)
			));
			//var_dump($TParam[0]['Request']['BIEN']);exit;
			
			return $TParam;
		}
		else
		{
			$xml = '
			<ns1:Request>
	            <ns1:PARTENAIRE>
	               <ns1:SIREN_PARTENAIRE>'.$mysoc->idprof1.'</ns1:SIREN_PARTENAIRE>
	               <ns1:NIC_PARTENAIRE>'.substr($mysoc->idprof2, -5, 5).'</ns1:NIC_PARTENAIRE>
	               <ns1:COMMERCIAL_EMAIL>'.$this->simulationSuivi->user->email.'</ns1:COMMERCIAL_EMAIL>
	               <ns1:REF_EXT>'.$this->simulation->reference.'</ns1:REF_EXT>
	            </ns1:PARTENAIRE>
	            <ns1:BIEN>
	               <ns1:CATEGORIE_BIEN>'.$this->getIdCategorieBien().'</ns1:CATEGORIE_BIEN>
	               <ns1:NATURE_BIEN>'.$this->getIdNatureBien().'</ns1:NATURE_BIEN>
	               <ns1:MARQUE_BIEN>'.$this->getIdMarqueBien().'</ns1:MARQUE_BIEN>
	               <ns1:ANNEE_BIEN>'.date('Y').'</ns1:ANNEE_BIEN>
	               <ns1:ETAT_BIEN>NEUF</ns1:ETAT_BIEN>
	               <ns1:QTE_BIEN>1</ns1:QTE_BIEN>
	               <ns1:MT_HT_BIEN>'.$this->simulation->montant.'</ns1:MT_HT_BIEN>
	               <ns1:PAYS_DESTINATION_BIEN>'.(!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR').'</ns1:PAYS_DESTINATION_BIEN>
	               <ns1:FOURNISSEUR_SIREN>'.$mysoc->idprof1.'</ns1:FOURNISSEUR_SIREN>
	               <ns1:FOURNISSEUR_NIC>'.substr($mysoc->idprof2, -5, 5).'</ns1:FOURNISSEUR_NIC>
	            </ns1:BIEN>
	            <!--1 or more repetitions:-->
	            <ns1:BIEN_COMPL>
	               <!--ns1:CATEGORIE_BIEN_COMPL>U</ns1:CATEGORIE_BIEN_COMPL>
	               <ns1:NATURE_BIEN_COMPL>U03C</ns1:NATURE_BIEN_COMPL>
	               <ns1:MARQUE_BIEN_COMPL>T046</ns1:MARQUE_BIEN_COMPL>
	               <ns1:ANNEE_BIEN_COMPL>2016</ns1:ANNEE_BIEN_COMPL>
	               <ns1:ETAT_BIEN_COMPL>NEUF</ns1:ETAT_BIEN_COMPL>
	               <ns1:MT_HT_BIEN_COMPL>1000.01</ns1:MT_HT_BIEN_COMPL>
	               <ns1:QTE_BIEN_COMPL>2</ns1:QTE_BIEN_COMPL-->
	            </ns1:BIEN_COMPL> 
	            <ns1:CLIENT>
	               <ns1:CLIENT_SIREN>'.$this->simulation->societe->idprof1 .'</ns1:CLIENT_SIREN>
	               <ns1:CLIENT_NIC>'.substr($this->simulation->societe->idprof2, -5, 5).'</ns1:CLIENT_NIC>
	            </ns1:CLIENT>
	            <ns1:FINANCEMENT>
	               <ns1:CODE_PRODUIT>'.$this->getCodeProduit().'</ns1:CODE_PRODUIT>
	               <ns1:TYPE_PRODUIT>'.$this->getTypeProduit().'</ns1:TYPE_PRODUIT>
	               <ns1:MT_FINANCEMENT_HT>'.$this->simulation->montant.'</ns1:MT_FINANCEMENT_HT>
	               <ns1:PCT_VR>'.$pct_vr.'</ns1:PCT_VR>
	               <ns1:MT_VR>'.$mt_vr.'</ns1:MT_VR>
	               <ns1:TYPE_REGLEMENT>'.$mode_reglement_id.'</ns1:TYPE_REGLEMENT>
	               <ns1:MT_PREMIER_LOYER>0</ns1:MT_PREMIER_LOYER>
	               <ns1:DUREE_FINANCEMENT>'.$this->simulation->duree.'</ns1:DUREE_FINANCEMENT>
	               <ns1:PERIODICITE_FINANCEMENT>'.$periodicite_code.'</ns1:PERIODICITE_FINANCEMENT>
	               <ns1:TERME_FINANCEMENT>'.($this->simulation->opt_terme == 1 ? 'A' : 'E').'</ns1:TERME_FINANCEMENT>
	               <ns1:NB_FRANCHISE>0</ns1:NB_FRANCHISE>
	               <ns1:NATURE_FINANCEMENT>STD</ns1:NATURE_FINANCEMENT>
	               <ns1:DATE_DEMANDE_FINANCEMENT>'.date('Y-m-d').'T'.date('H:i:s').'</ns1:DATE_DEMANDE_FINANCEMENT>
	            </ns1:FINANCEMENT>
	         </ns1:Request>
			';
			
			return $xml;
		}

	}


	// TODO à mettre en commentaire ou à supprimer pour la prod (actuellement utilisé pas le fichier scoring_client.php qui fait appel à notre propres webservice)
	public function callTest(&$authentication, &$TParam)
	{
		try {
			$ns='http://'.$_SERVER['HTTP_HOST'].'/ns/';
			
			$this->soapClient = new nusoap_client($this->wsdl/*, $params_connection*/);
			$this->result = $this->soapClient->call('repondreDemande', array('authentication'=>$authentication, 'TParam' => $TParam), $ns, '');
			
			return true;
		} catch (SoapFault $e) {
			var_dump($e);
			exit;
		}
	}
	
	// TODO comment for prod
	/*
	public function createXmlFileOfParam()
	{
		$TParam = $this->_getTParamLixxbail();
		 
		$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');
		self::array_to_xml($TParam,$xml_data);
		$result = $xml_data->asXML('/var/www/demande_de_financement.xml');
	}
	
	public static function array_to_xml( $data, &$xml_data ) {
	    foreach( $data as $key => $value ) {
	        if( is_array($value) ) {
	            if( is_numeric($key) ){
	                $key = 'item'.$key; //dealing with <0/>..<n/> issues
	            }
	            $subnode = $xml_data->addChild($key);
	            self::array_to_xml($value, $subnode);
	        } else {
	            $xml_data->addChild("$key",htmlspecialchars("$value"));
	        }
	     }
	}
	*/

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

class MySoapCmCic extends SoapClient
{
	function __doRequest($request, $location, $saction, $version)
	{
		$this->realXML = $request;
		$this->realXML = str_replace(array('SOAP-ENV', 'ns1'), array('soapenv', 'doc'), $this->realXML);
		
		$this->realXML = str_replace('<soapenv:Body>', '<soapenv:Header/><soapenv:Body>', $this->realXML);
		/*$this->realXML = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->realXML);*/
		
		return parent::__doRequest($this->realXML, $location, $saction, $version);
	}
}