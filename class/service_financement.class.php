<?php

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
	public function ServiceFinancement(&$simulation, &$simulationSuivi)
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
		
		if ($this->debug) var_dump('DEBUG :: Function call(): # aucun traitement prévu');
		
		return false;
	}
	
	/**
	 * Function callLixxbail
	 */
	public function callLixxbail()
	{
		global $conf,$langs;
		
		$this->wsdl = dol_buildpath('/financement/files/DemandeCreationLeasingGNV1.wsdl', 2);
		
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
	
	    if (is_int($precision) && $precision >= 0 && $precision <= 6) {
	        $total = (string) $time['sec'] . '.' . str_pad((string) $time['usec'], 6, '0', STR_PAD_LEFT);
	        $total_rounded = bcadd($total, '0.' . str_repeat('0', $precision) . '5', $precision);
	        @list($integer, $fraction) = explode('.', $total_rounded);
	        $format = $precision == 0
	            ? "Y-m-d\TH:i:s\Z"
	            : "Y-m-d\TH:i:s.".$fraction."\Z";
	        return gmdate($format, $integer);
	    }
	
	    return false;
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
		
		$mode_reglement_id = $this->getIdModeRglt($this->simulation->opt_mode_reglement);
		$periodicite_code = $this->getCodePeriodiciteFinancement($this->simulation->opt_periodicite);
		
		$pct_vr = $this->simulation->pct_vr;
		$mt_vr = $this->simulation->mt_vr;
		
		if (!empty($pct_vr) && !empty($mt_vr)) $pct_vr = 0; // Si les 2 sont renseignés alors je garde que le montant
		//<soap1:Calf_Header_GN xmlns:soap1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" correlationId="12345" wsu:Id="id-11"/></soap:Header>
		$xml = '
			
				<v1:DemandeCreationLeasingGN xmlns:v1="http://referentiel.ca.fr/Services/calf/DemandeCreationLeasingGN/V1/">
			         <v1:Request>
			            <v1:PARTENAIRE>
			               <v1:SIREN_PARTENAIRE>'.$mysocentity->idprof1.'</v1:SIREN_PARTENAIRE>
			               <v1:NIC_PARTENAIRE>'.substr($mysocentity->idprof2, -5, 5).'</v1:NIC_PARTENAIRE>
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
			               <v1:MT_HT_BIEN>'.$this->simulation->montant.'</v1:MT_HT_BIEN>
			               <v1:PAYS_DESTINATION_BIEN>'.(!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR').'</v1:PAYS_DESTINATION_BIEN>
			               <v1:FOURNISSEUR_SIREN>'.$mysocentity->idprof1.'</v1:FOURNISSEUR_SIREN>
			               <v1:FOURNISSEUR_NIC>'.substr($mysocentity->idprof2, -5, 5).'</v1:FOURNISSEUR_NIC>
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
			               <v1:CLIENT_SIREN>'.$this->simulation->societe->idprof1.'</v1:CLIENT_SIREN>
			               <v1:CLIENT_NIC>'.substr($this->simulation->societe->idprof2, -5, 5).'</v1:CLIENT_NIC>
			            </v1:CLIENT>
			            <v1:FINANCEMENT>
			               <v1:CODE_PRODUIT>'.$this->getCodeProduit().'</v1:CODE_PRODUIT>
			               <v1:TYPE_PRODUIT>'.$this->getTypeProduit().'</v1:TYPE_PRODUIT>
			               <v1:MT_FINANCEMENT_HT>'.$this->simulation->montant.'</v1:MT_FINANCEMENT_HT>
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
		
	/*	
		$xml = '
		<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope">
			<soap:Header>
				<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-AADF8AB63251581EB0147879497421813">MIIDNzCCAh8CBFap/TswDQYJKoZIhvcNAQELBQAwYDELMAkGA1UEBhMCRlIxDzANBgNVBAgMBkZyYW5jZTESMBAGA1UEBwwJTW9udHJvdWdlMQ0wCwYDVQQKDARDQUxGMQwwCgYDVQQLDANEU0kxDzANBgNVBAMMBlRlc3RLTTAeFw0xNjAxMjgxMTM2MjdaFw0xNzAxMjcxMTM2MjdaMGAxCzAJBgNVBAYTAkZSMQ8wDQYDVQQIDAZGcmFuY2UxEjAQBgNVBAcMCU1vbnRyb3VnZTENMAsGA1UECgwEQ0FMRjEMMAoGA1UECwwDRFNJMQ8wDQYDVQQDDAZUZXN0S00wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCjTjAdw4loiKpZpaynp0naI7xs05eF875nRbcgzSJPzCPgIpGjWpqp6B5I2u9lZ0UO/aH3moJTlRBV31JM1ak0z5vGIxBdxhZXme/P5UrAuxXFm0idv7tPo4zpR3SowxxVawWRMYCs2n+PPBgH1nB4pWcEm8+HMhUgGkTriSkiUMsEDVLQIfwxB25R28MbwsD4O3N25nZRLN8cZfRZcsbt5X0nKFvAbd00Xa8Wu5mr2NNm4kK/idFYmoqkLum1TCavHkdHpPr4TjP0uGF+052bgXbcKEn9WHvy+oa3SeXRyQ0v0Cxv9MBgZKH/wiEeZrdl9lVwZco+R8b3qj2VP06zAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAKGfSliI9P28Up9oyPUSNenG4pL4r5QtiiHXrK1VBB8VZwDNDJDJWSp9v8AwKMsvG/7e+tdM/XswL1LeYXOcaf58NioiWxJqEM5nqGs5fKbEVSGcCBT/STUXBL0nqLyARXpHAhsbSiWkmntFNLu1Ui9lQa0v7jva7A2433YoJ25KmtGzEP5edybC4fGFXCUTb2BXTvTFb0v5Z0TnsA5fz2SDmy7q4o+QXOVvEwc0HWmdVmF9e75VRaCdOPvRgihWGKKyUt4UWI+g0wQqBwyi6CkQ5S8PygbZvLo7ANx48Du5z3zPQkwPbw8VQ58DKE7ymXj5gUuHXCDQ06qgABp85BA=</wsse:BinarySecurityToken>
				
				<ds:Signature Id="SIG-15" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#TS-13"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsse soap" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>ECakHODQE+p39yQjRUMZWY3f0w6+DTvwxWDy64ogALc=</ds:DigestValue></ds:Reference>
					<ds:Reference URI="#id-14">
						<ds:Transforms>
							<ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/>
							</ds:Transform>
						</ds:Transforms>
						<ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
						<ds:DigestValue>zrAWMSNJbylm8lAZ2QnrZQm51uHoEbqgGuJ/TTV5aX4=</ds:DigestValue>
					</ds:Reference>
						<ds:Reference URI="#id-11"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
							<ds:DigestValue>F7mHeYVmI6LhNv51JMK9wXLCVeLE7AsN5bh9ehslPqY=</ds:DigestValue>
							</ds:Reference>
							</ds:SignedInfo>
							<ds:SignatureValue>b3ZRZ+1SpeKrE5ciVvPHRgu/lFXEjVQiKDw9SOa6mQwzjUM4G2W53kVxqeXy5aJvOAawEWYv6Gvl
						P0YxEr9No/vBzgjaS0JoQr753+/YLDZoPUav9vuh+aifUiL7g9i41GBwyYysN2hctoerM9IOptLp
						hQOCsL7zJZfwNhTYZp89dwtgWkQoT1L5MltNYpWkHiLYluW9lXzV+t0V8PFJgZNn/U/ZqMqiy6wl
						NFxTtvsuFlGJA75+3v0VRKvNkzKuyHdMwjx/25I4SZbFVS/L7JcTiK6aaV6K14GiL2yrnfRKUTWM
						6bEpGLNTTCRG8WpdbpeObz0PKv7zgE9MSxrTTw==</ds:SignatureValue>
						
						<ds:KeyInfo Id="KI-AADF8AB63251581EB0147879497421814">
					
						<wsse:SecurityTokenReference wsu:Id="STR-AADF8AB63251581EB0147879497421815">
							<wsse:Reference URI="#X509-AADF8AB63251581EB0147879497421813" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/>
						</wsse:SecurityTokenReference>
					</ds:KeyInfo>
				</ds:Signature>
				
				<wsu:Timestamp wsu:Id="TS-13">
					<wsu:Created>2016-11-10T16:22:54.171Z</wsu:Created>
					<wsu:Expires>2016-11-11T00:42:54.171Z</wsu:Expires>
				</wsu:Timestamp>
			
			</wsse:Security>
					
			<soap1:Calf_Header_GN correlationId="bve_cpro_12345" wsu:Id="id-11" xmlns:soap1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"/></soap:Header>
			<soap:Body wsu:Id="id-14" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"><v1:DemandeCreationLeasingGN xmlns:v1="http://referentiel.ca.fr/Services/calf/DemandeCreationLeasingGN/V1/">
		         <v1:Request>
		            <v1:PARTENAIRE>
		               <v1:SIREN_PARTENAIRE>381228386</v1:SIREN_PARTENAIRE>
		               <v1:NIC_PARTENAIRE>00102</v1:NIC_PARTENAIRE>
		               <v1:COMMERCIAL_EMAIL>d.ferrazzi@cpro.fr</v1:COMMERCIAL_EMAIL>
		               <v1:REF_EXT>BVE_CPRO_1110_002</v1:REF_EXT>
		            </v1:PARTENAIRE>
		            <v1:BIEN>
		               <v1:CATEGORIE_BIEN>U</v1:CATEGORIE_BIEN>
		               <v1:NATURE_BIEN>U01C</v1:NATURE_BIEN>
		               <v1:MARQUE_BIEN>C098</v1:MARQUE_BIEN>
		               <v1:ANNEE_BIEN>2015</v1:ANNEE_BIEN>
		               <v1:ETAT_BIEN>NEUF</v1:ETAT_BIEN>
		               <v1:QTE_BIEN>1</v1:QTE_BIEN>
		               <v1:MT_HT_BIEN>30000</v1:MT_HT_BIEN>
		               <v1:PAYS_DESTINATION_BIEN>FR</v1:PAYS_DESTINATION_BIEN>
		               <v1:FOURNISSEUR_SIREN>381228386</v1:FOURNISSEUR_SIREN>
		               <v1:FOURNISSEUR_NIC>00102</v1:FOURNISSEUR_NIC>
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
		               <v1:CLIENT_SIREN>780129987</v1:CLIENT_SIREN>
		               <v1:CLIENT_NIC>03591</v1:CLIENT_NIC>
		            </v1:CLIENT>
		            <v1:FINANCEMENT>
		               <v1:CODE_PRODUIT>LOCF</v1:CODE_PRODUIT>
		               <v1:TYPE_PRODUIT>STAN</v1:TYPE_PRODUIT>
		               <v1:MT_FINANCEMENT_HT>30000</v1:MT_FINANCEMENT_HT>
		               <!--v1:PCT_VR>0.15</v1:PCT_VR-->
		               <v1:MT_VR>0.15</v1:MT_VR>
		               <v1:TYPE_REGLEMENT>2</v1:TYPE_REGLEMENT>
		               <!--v1:MT_PREMIER_LOYER>3000</v1:MT_PREMIER_LOYER-->
		               <v1:DUREE_FINANCEMENT>36</v1:DUREE_FINANCEMENT>
		               <v1:PERIODICITE_FINANCEMENT>M</v1:PERIODICITE_FINANCEMENT>
		               <v1:TERME_FINANCEMENT>A</v1:TERME_FINANCEMENT>
		               <v1:NB_FRANCHISE>2</v1:NB_FRANCHISE>
		               <v1:NATURE_FINANCEMENT/>
		               <v1:DATE_DEMANDE_FINANCEMENT>2016-06-21T16:00:52</v1:DATE_DEMANDE_FINANCEMENT>
		            </v1:FINANCEMENT>
		         </v1:Request>
	      </v1:DemandeCreationLeasingGN></soap:Body></soap:Envelope>
		';
		*/
		return $xml;
	}

	private function getHeaderLixxbail()
	{
/*		$header = '
		<wsse:Security>
			<wsu:Timestamp wsu:Id="TS-55">
				<wsu:Created>'.$this->iso_8601_utc_time(3).'</wsu:Created>
				<wsu:Expires>'.$this->iso_8601_utc_time(3, 240).'</wsu:Expires>
			</wsu:Timestamp>
			<ds:Signature Id="SIG-15">
				<ds:SignatureValue>MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCjTjAdw4loiKpZpaynp0naI7xs05eF875nRbcgzSJPzCPgIpGjWpqp6B5I2u9lZ0UO/aH3moJTlRBV31JM1ak0z5vGIxBdxhZXme/P5UrAuxXFm0idv7tPo4zpR3SowxxVawWRMYCs2n+PPBgH1nB4pWcEm8+HMhUgGkTriSkiUMsEDVLQIfwxB25R28MbwsD4O3N25nZRLN8cZfRZcsbt5X0nKFvAbd00Xa8Wu5mr2NNm4kK/idFYmoqkLum1TCavHkdHpPr4TjP0uGF+052bgXbcKEn9WHvy+oa3SeXRyQ0v0Cxv9MBgZKH/wiEeZrdl9lVwZco+R8b3qj2VP06zAgMBAAECggEAZ9se0J79cUSRCehKUGkcl6VofNFoKOFlsunsV+j9rEAIhM+XTYsel3WuZOkPnK67hZgZ/Iz/20YOmH4pKgIr1RE/YRgFnY2PwfB9Sfrpun6AjyZ9XQ2Fg1VhFS7Da1yCVXR1muwfiE6BF0fBhKKE7sVKKe0pYzKfqsXqFN0rEs3FBQ+uBg6I2uw4PrjT/7vZelAAxrBrqxCCjncoXvzN7FUZIBjOxOTd+G4sClFxzx3CZNvAdWFwy+b5D0d92T9AxWYO5/L8Hyd2Q3ruYWaG6pla8J7ERCX87e6tKB/dea9hS6JOcYyJMbQgafDM96aoykIg6+N9WoIbdMWQ199fWQKBgQDzHZLM2r4AG9QI4NhYycltKxrK4VOALnQq8NK9W2q6/o87Img378JiQ4hPsnTDAcQgQQws78tFeClxx0Okon9oVXKFjaR7Sq0hWezwZnuzTWw/pthwNcLjfZ3mUIgUBDG5mb8xZYdLliou7SasyleQrQ1v9NGK27l02ffbYn5e3wKBgQCr9c4hUHtZoBylA24jLyR+SSdYyXSz8Dgpm9/TxtlXjn43UuXfRSu8aI/p9/ZmVSyKZZKizfH+AjaB0BjOoqYog442AqPbFEYUb3XdFbvKlHIvwgf2mvwCgoVS+WnyIEJ3W7GH2FHcaDcuXzk3h794396/mP9j7MIi7SYcXXcOrQKBgEPYP+xdKuK64Vws6xM0FLsbaVmuse+2hwKovBbN2SYf/fahrnXVuehUMkkTYxQ8fPAHVw9/R7m2Q9KVqiHamzWRiukMUxd5CoGhJ8cawnCSLaBBvmrqBd4YYyUv2hnD5eCGsF1nmO8WE+WOltlnijI8qOBScNuQX9vlLA8UGHH/AoGASk952XmvJGcFmeWmlbvMmGpCf6LnNM8tZgW/LwRybdzc/EltnxOEN/IzptcJ+uT5z4DfYk1/MtZ/+Y8U+U7eYQmgzgRMDONw+WnFVFoNAhkuUycVS+Nj3i3LMbUorIJ2VqAgUuUPUyESH4706eNWwgR0fPW//82Tg4ZZ/s4BIi0CgYEAh2mPTUvcHPKqpXsGoAf9+v8o2CKwork5yO051N6R3bDl+2cetESfJ3sOvDRB6o0Wm2/Wzw3hJF2/rB9ZIm3Xk/j841sniTt9A0sX92p8oIw357AmA6j5vrtZTI+4PHq/MkQE8TBeRmY02ua/JO/Wq3vWsbeCK/Cd+HQqy5UK+/Q=</ds:SignatureValue>
			</ds:Signature>
		</wsse:Security>
		';*/
	//<ds:SignatureValue>MIIDNzCCAh8CBFap/TswDQYJKoZIhvcNAQELBQAwYDELMAkGA1UEBhMCRlIxDzANBgNVBAgMBkZyYW5jZTESMBAGA1UEBwwJTW9udHJvdWdlMQ0wCwYDVQQKDARDQUxGMQwwCgYDVQQLDANEU0kxDzANBgNVBAMMBlRlc3RLTTAeFw0xNjAxMjgxMTM2MjdaFw0xNzAxMjcxMTM2MjdaMGAxCzAJBgNVBAYTAkZSMQ8wDQYDVQQIDAZGcmFuY2UxEjAQBgNVBAcMCU1vbnRyb3VnZTENMAsGA1UECgwEQ0FMRjEMMAoGA1UECwwDRFNJMQ8wDQYDVQQDDAZUZXN0S00wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCjTjAdw4loiKpZpaynp0naI7xs05eF875nRbcgzSJPzCPgIpGjWpqp6B5I2u9lZ0UO/aH3moJTlRBV31JM1ak0z5vGIxBdxhZXme/P5UrAuxXFm0idv7tPo4zpR3SowxxVawWRMYCs2n+PPBgH1nB4pWcEm8+HMhUgGkTriSkiUMsEDVLQIfwxB25R28MbwsD4O3N25nZRLN8cZfRZcsbt5X0nKFvAbd00Xa8Wu5mr2NNm4kK/idFYmoqkLum1TCavHkdHpPr4TjP0uGF+052bgXbcKEn9WHvy+oa3SeXRyQ0v0Cxv9MBgZKH/wiEeZrdl9lVwZco+R8b3qj2VP06zAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAKGfSliI9P28Up9oyPUSNenG4pL4r5QtiiHXrK1VBB8VZwDNDJDJWSp9v8AwKMsvG/7e+tdM/XswL1LeYXOcaf58NioiWxJqEM5nqGs5fKbEVSGcCBT/STUXBL0nqLyARXpHAhsbSiWkmntFNLu1Ui9lQa0v7jva7A2433YoJ25KmtGzEP5edybC4fGFXCUTb2BXTvTFb0v5Z0TnsA5fz2SDmy7q4o+QXOVvEwc0HWmdVmF9e75VRaCdOPvRgihWGKKyUt4UWI+g0wQqBwyi6CkQ5S8PygbZvLo7ANx48Du5z3zPQkwPbw8VQ58DKE7ymXj5gUuHXCDQ06qgABp85BA=</ds:SignatureValue>
	$header = '
		<wsse:Security>
			<wsu:Timestamp wsu:Id="TS-55">
				<wsu:Created>'.$this->iso_8601_utc_time(3).'</wsu:Created>
				<wsu:Expires>'.$this->iso_8601_utc_time(3, 7200).'</wsu:Expires>
			</wsu:Timestamp>
			
		</wsse:Security>
	';
	
//	$header = '<env1:Calf_Header_GN xmlns:env1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" correlationId="12345" wsu:Id="id-11"/>';

	$header = <<<EOT
	<wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
	<wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-AD489CB5D4E5C55BA6147921413300895">MIIDNzCCAh8CBFap/TswDQYJKoZIhvcNAQELBQAwYDELMAkGA1UEBhMCRlIxDzANBgNVBAgMBkZyYW5jZTESMBAGA1UEBwwJTW9udHJvdWdlMQ0wCwYDVQQKDARDQUxGMQwwCgYDVQQLDANEU0kxDzANBgNVBAMMBlRlc3RLTTAeFw0xNjAxMjgxMTM2MjdaFw0xNzAxMjcxMTM2MjdaMGAxCzAJBgNVBAYTAkZSMQ8wDQYDVQQIDAZGcmFuY2UxEjAQBgNVBAcMCU1vbnRyb3VnZTENMAsGA1UECgwEQ0FMRjEMMAoGA1UECwwDRFNJMQ8wDQYDVQQDDAZUZXN0S00wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCjTjAdw4loiKpZpaynp0naI7xs05eF875nRbcgzSJPzCPgIpGjWpqp6B5I2u9lZ0UO/aH3moJTlRBV31JM1ak0z5vGIxBdxhZXme/P5UrAuxXFm0idv7tPo4zpR3SowxxVawWRMYCs2n+PPBgH1nB4pWcEm8+HMhUgGkTriSkiUMsEDVLQIfwxB25R28MbwsD4O3N25nZRLN8cZfRZcsbt5X0nKFvAbd00Xa8Wu5mr2NNm4kK/idFYmoqkLum1TCavHkdHpPr4TjP0uGF+052bgXbcKEn9WHvy+oa3SeXRyQ0v0Cxv9MBgZKH/wiEeZrdl9lVwZco+R8b3qj2VP06zAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAKGfSliI9P28Up9oyPUSNenG4pL4r5QtiiHXrK1VBB8VZwDNDJDJWSp9v8AwKMsvG/7e+tdM/XswL1LeYXOcaf58NioiWxJqEM5nqGs5fKbEVSGcCBT/STUXBL0nqLyARXpHAhsbSiWkmntFNLu1Ui9lQa0v7jva7A2433YoJ25KmtGzEP5edybC4fGFXCUTb2BXTvTFb0v5Z0TnsA5fz2SDmy7q4o+QXOVvEwc0HWmdVmF9e75VRaCdOPvRgihWGKKyUt4UWI+g0wQqBwyi6CkQ5S8PygbZvLo7ANx48Du5z3zPQkwPbw8VQ58DKE7ymXj5gUuHXCDQ06qgABp85BA=</wsse:BinarySecurityToken><ds:Signature Id="SIG-AD489CB5D4E5C55BA6147921413300899" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><ds:SignedInfo><ds:CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:CanonicalizationMethod><ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/><ds:Reference URI="#TS-AD489CB5D4E5C55BA6147921413300694"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="wsse soap" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>iM1PD7B7Wi7ASg1jdX405WY/IN7G6RVi8RUjQPekpcw=</ds:DigestValue></ds:Reference><ds:Reference URI="#id-AD489CB5D4E5C55BA6147921413300898"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>++dWWyPjTH1k3sU6Qz7M0DgOUOkop4OPDKGjwWJd854=</ds:DigestValue></ds:Reference><ds:Reference URI="#id-11"><ds:Transforms><ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"><ec:InclusiveNamespaces PrefixList="soap" xmlns:ec="http://www.w3.org/2001/10/xml-exc-c14n#"/></ds:Transform></ds:Transforms><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/><ds:DigestValue>0oeYiDU20NlsVR5Apm6LI1LtEHfjOswsninoKnoxZ6E=</ds:DigestValue></ds:Reference></ds:SignedInfo><ds:SignatureValue>ddYygnEqiaVgKAt4B0NHSvbnRWpwORpWdm74EEPWkz/1q7JmHUOyERBBPcZ3oROuo5C7OhEVOBsy
Ywo0est1MGETNfcPxCnwNJH9rI3Ydy8Eu/6HdP8POS5fB5efsGVmnsoLZbHqLLKa4dGY8CTI6TAR
PhNvvLj/5PyHVpz/DBFHa47elrWT0ChypVf++GBiqZLLsyxPklD5Yyw4vuRKQMy6Q4iNzyZwXFrE
CQt0TUoni6vvGucaJb3VdyMnW4X/cs9XVOqKklXbahoH/+vwRlx/UjrpIDwkVhe/s3TXtOeBqFOg
W3qQqjU4uVVTMYowOouAGyNLym3jMvjtpFzBfQ==</ds:SignatureValue>

	<ds:KeyInfo Id="KI-AD489CB5D4E5C55BA6147921413300896">
		<wsse:SecurityTokenReference wsu:Id="STR-AD489CB5D4E5C55BA6147921413300897">
			<wsse:Reference URI="#X509-AD489CB5D4E5C55BA6147921413300895" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/>
		</wsse:SecurityTokenReference>
	</ds:KeyInfo>
</ds:Signature>

<wsu:Timestamp wsu:Id="TS-AD489CB5D4E5C55BA6147921413300694">
	<wsu:Created>2016-11-15T12:48:53.006Z</wsu:Created>
	<wsu:Expires>2016-11-15T21:08:53.006Z</wsu:Expires>
</wsu:Timestamp>

</wsse:Security>

<soap1:Calf_Header_GN correlationId="12345" wsu:Id="id-11" xmlns:soap1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd"/>
EOT;
	
	
		return $header;
	}
	
	private function signHeaderLixxbail()
	{
		$timestamp_id = 'id-'.rand(10,30);
		/*
		 * Balises devant avoir un id (à voir si obligatoire)
		 *  - Timestamp
		 *  - Body
		 *  - calf Header GN
		 * 
		 * Attention au <BinarySecurityToken> balise non présente
		 * <wsse:BinarySecurityToken EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" wsu:Id="X509-AD489CB5D4E5C55BA6147921413300895">MIIDNzCCAh8CBFap/TswDQYJKoZIhvcNAQELBQAwYDELMAkGA1UEBhMCRlIxDzANBgNVBAgMBkZyYW5jZTESMBAGA1UEBwwJTW9udHJvdWdlMQ0wCwYDVQQKDARDQUxGMQwwCgYDVQQLDANEU0kxDzANBgNVBAMMBlRlc3RLTTAeFw0xNjAxMjgxMTM2MjdaFw0xNzAxMjcxMTM2MjdaMGAxCzAJBgNVBAYTAkZSMQ8wDQYDVQQIDAZGcmFuY2UxEjAQBgNVBAcMCU1vbnRyb3VnZTENMAsGA1UECgwEQ0FMRjEMMAoGA1UECwwDRFNJMQ8wDQYDVQQDDAZUZXN0S00wggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQCjTjAdw4loiKpZpaynp0naI7xs05eF875nRbcgzSJPzCPgIpGjWpqp6B5I2u9lZ0UO/aH3moJTlRBV31JM1ak0z5vGIxBdxhZXme/P5UrAuxXFm0idv7tPo4zpR3SowxxVawWRMYCs2n+PPBgH1nB4pWcEm8+HMhUgGkTriSkiUMsEDVLQIfwxB25R28MbwsD4O3N25nZRLN8cZfRZcsbt5X0nKFvAbd00Xa8Wu5mr2NNm4kK/idFYmoqkLum1TCavHkdHpPr4TjP0uGF+052bgXbcKEn9WHvy+oa3SeXRyQ0v0Cxv9MBgZKH/wiEeZrdl9lVwZco+R8b3qj2VP06zAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAKGfSliI9P28Up9oyPUSNenG4pL4r5QtiiHXrK1VBB8VZwDNDJDJWSp9v8AwKMsvG/7e+tdM/XswL1LeYXOcaf58NioiWxJqEM5nqGs5fKbEVSGcCBT/STUXBL0nqLyARXpHAhsbSiWkmntFNLu1Ui9lQa0v7jva7A2433YoJ25KmtGzEP5edybC4fGFXCUTb2BXTvTFb0v5Z0TnsA5fz2SDmy7q4o+QXOVvEwc0HWmdVmF9e75VRaCdOPvRgihWGKKyUt4UWI+g0wQqBwyi6CkQ5S8PygbZvLo7ANx48Du5z3zPQkwPbw8VQ58DKE7ymXj5gUuHXCDQ06qgABp85BA=</wsse:BinarySecurityToken>
		 * 
		 * La balise <ds:KeyInfo> doit contenir (la URI de Reference pointe vers l'id de <BinarySecurityToken>):
		 * 	<wsse:SecurityTokenReference wsu:Id="STR-AD489CB5D4E5C55BA6147921413300897">
				<wsse:Reference URI="#X509-AD489CB5D4E5C55BA6147921413300895" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/>
			</wsse:SecurityTokenReference>
		 * 
		 */
		
		// Load the XML to be signed
		$doc = new DOMDocument(1.0, 'utf-8' );
		$node = $doc->createElement('wsse:Security');
		//$node = $doc->createElementNS('wsse',  'wsse:Security');
		$node->setAttribute('xmlns:wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
		$node->setAttribute('xmlns:wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');
		$security = $doc->appendChild($node);
		
		
		$timestamp = $doc->createElement('wsu:Timestamp');
		$timestamp->setAttribute('wsu:Id', $timestamp_id);
		$security->appendChild($timestamp);
		
		
		$created = $doc->createElement('wsu:Created');
		$created->nodeValue = $this->iso_8601_utc_time(3);
		$timestamp->appendChild($created);
		
		
		$expires = $doc->createElement('wsu:Expires');
		$expires->nodeValue = $this->iso_8601_utc_time(3, 7200);
		$timestamp->appendChild($expires);
		
		
		/*
		var_dump($doc->saveXML(), $doc->documentElement);
		exit;
		*/
		
		new WSSESoap($doc);
		exit;
		
		$options = array('force_uri' => $timestamp_id);
		
		//$doc->loadXML($xml_to_sign);

		// Create a new Security object 
		$objDSig = new XMLSecurityDSig();
		// Use the c14n exclusive canonicalization
		$objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
		// Sign using SHA-256
		$objDSig->addReference(
		    $doc, 
		    XMLSecurityDSig::SHA256, 
		   // array('http://www.w3.org/2000/09/xmldsig#enveloped-signature')
		   array('http://www.w3.org/2001/10/xml-exc-c14n#')
		   ,$options
		);
		
		// Create a new (private) Security key
		$objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, array('type'=>'private'));
		// Load the private key
		$objKey->loadKey(dol_buildpath('/financement/crt/CALF/key.pem'), TRUE);
		/* 
		If key has a passphrase, set it using 
		$objKey->passphrase = '<passphrase>';
		*/
		
		// Sign the XML file
		$objDSig->sign($objKey);
		
		// Add the associated public key to the signature
		//$objDSig->add509Cert(file_get_contents(dol_buildpath('/financement/crt/CALF/cert.pem')));
		
		
		// Append the signature to the XML
		$objDSig->appendSignature($doc->documentElement);
		
		
		// Save the signed XML
		//$xml = $doc->saveXML();
		
		// PHP DomDocument output without <?xml version=“1.0” encoding=“UTF-8” => http://stackoverflow.com/questions/5706086/php-domdocument-output-without-xml-version-1-0-encoding-utf-8
		$xml = '';
		foreach ($doc->childNodes as $node) {
		   $xml .=  $doc->saveXML($node).PHP_EOL;
		}


		$xml .= '
			<ds:KeyInfo Id="KI-AD489CB5D4E5C55BA6147921413300896">
				<wsse:SecurityTokenReference wsu:Id="STR-AD489CB5D4E5C55BA6147921413300897">
					<wsse:Reference URI="#X509-AD489CB5D4E5C55BA6147921413300895" ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/>
				</wsse:SecurityTokenReference>
			</ds:KeyInfo>
		';

	/*echo '<pre>' . htmlspecialchars($xml, ENT_QUOTES) . '</pre>';
		exit;*/

		
		$xml .= '<env1:Calf_Header_GN xmlns:env1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" correlationId="12345" wsu:Id="id-11"/>';

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
				//,'MDT' => 0 // Non géré pas cal&f
				//,'VIR' => 0 // Non géré pas cal&f
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
			
			/*$xml = '
			<Request>
	            <PARTENAIRE>
	               <SIREN_PARTENAIRE>'.$mysoc->idprof1.'</SIREN_PARTENAIRE>
	               <NIC_PARTENAIRE>'.substr($mysoc->idprof2, -5, 5).'</NIC_PARTENAIRE>
	               <COMMERCIAL_EMAIL>'.$this->simulationSuivi->user->email.'</COMMERCIAL_EMAIL>
	               <REF_EXT>'.$this->simulation->reference.'</REF_EXT>
	            </PARTENAIRE>
	            <BIEN>
	               <CATEGORIE_BIEN>'.$this->getIdCategorieBien().'</CATEGORIE_BIEN>
	               <NATURE_BIEN>'.$this->getIdNatureBien().'</NATURE_BIEN>
	               <MARQUE_BIEN>'.$this->getIdMarqueBien().'</MARQUE_BIEN>
	               <ANNEE_BIEN>'.date('Y').'</ANNEE_BIEN>
	               <ETAT_BIEN>NEUF</ETAT_BIEN>
	               <QTE_BIEN>1</QTE_BIEN>
	               <MT_HT_BIEN>'.$this->simulation->montant.'</MT_HT_BIEN>
	               <PAYS_DESTINATION_BIEN>'.(!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR').'</PAYS_DESTINATION_BIEN>
	               <FOURNISSEUR_SIREN>'.$mysoc->idprof1.'</FOURNISSEUR_SIREN>
	               <FOURNISSEUR_NIC>'.substr($mysoc->idprof2, -5, 5).'</FOURNISSEUR_NIC>
	            </BIEN>
	            <!--1 or more repetitions:-->
	            <BIEN_COMPL>
	               <!--CATEGORIE_BIEN_COMPL>U</CATEGORIE_BIEN_COMPL>
	               <NATURE_BIEN_COMPL>U03C</NATURE_BIEN_COMPL>
	               <MARQUE_BIEN_COMPL>T046</MARQUE_BIEN_COMPL>
	               <ANNEE_BIEN_COMPL>2016</ANNEE_BIEN_COMPL>
	               <ETAT_BIEN_COMPL>NEUF</ETAT_BIEN_COMPL>
	               <MT_HT_BIEN_COMPL>1000.01</MT_HT_BIEN_COMPL>
	               <QTE_BIEN_COMPL>2</QTE_BIEN_COMPL-->
	            </BIEN_COMPL> 
	            <CLIENT>
	               <CLIENT_SIREN>'.$this->simulation->societe->idprof1 .'</CLIENT_SIREN>
	               <CLIENT_NIC>'.substr($this->simulation->societe->idprof2, -5, 5).'</CLIENT_NIC>
	            </CLIENT>
	            <FINANCEMENT>
	               <CODE_PRODUIT>'.$this->getCodeProduit().'</CODE_PRODUIT>
	               <TYPE_PRODUIT>'.$this->getTypeProduit().'</TYPE_PRODUIT>
	               <MT_FINANCEMENT_HT>'.$this->simulation->montant.'</MT_FINANCEMENT_HT>
	               <PCT_VR>'.$pct_vr.'</PCT_VR>
	               <MT_VR>'.$mt_vr.'</MT_VR>
	               <TYPE_REGLEMENT>'.$mode_reglement_id.'</TYPE_REGLEMENT>
	               <MT_PREMIER_LOYER>0</MT_PREMIER_LOYER>
	               <DUREE_FINANCEMENT>'.$this->simulation->duree.'</DUREE_FINANCEMENT>
	               <PERIODICITE_FINANCEMENT>'.$periodicite_code.'</PERIODICITE_FINANCEMENT>
	               <TERME_FINANCEMENT>'.($this->simulation->opt_terme == 1 ? 'A' : 'E').'</TERME_FINANCEMENT>
	               <NB_FRANCHISE>0</NB_FRANCHISE>
	               <NATURE_FINANCEMENT>STD</NATURE_FINANCEMENT>
	               <DATE_DEMANDE_FINANCEMENT>'.date('Y-m-d').'T'.date('H:i:s').'</DATE_DEMANDE_FINANCEMENT>
	            </FINANCEMENT>
	         </Request>
			';
			*/
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

} // End Class


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
		$token = $objWSSE->addBinaryToken(file_get_contents('/etc/apache2/ssl/cert.preprod.crt'));
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