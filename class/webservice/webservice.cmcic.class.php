<?php

class WebServiceCmcic extends WebService 
{
	public function run()
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
			
			if ($this->isUpdateCall()) $response = $this->soapClient->__soapCall('UpdateDemFin', array());
			else $response = $this->soapClient->__soapCall('CreateDemFin', array());
  
			// TODO : issue de la doc => Dans l’éventualité où l’utilisateur est invalide, un message d’erreur est envoyé au partenaire
			if ($this->debug)
			{
				$this->printDebugSoapCall($response);
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			
			if (!empty($response->ResponseDemFin))
			{
				$this->simulationSuivi->commentaire = $langs->trans($response->ResponseDemFin->ResponseDemFinShort->Rep_Statut_B2B->B2B_MSGRET);
				return true;
			}
			else
			{
				$this->simulationSuivi->commentaire = $langs->trans('ServiceFinancementWrongReturn');
				return false;
			}
			
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_CMCIC');
			parent::caughtError($e);
		}

		return false;
	}
	
	/**
	 * Return only body
	 */
	public function getXml()
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

		$callType = ($this->isUpdateCall()) ? 'UpdateDemFinRequest' : 'CreateDemFinRequest';
		
		$xml = '
		<ns1:'.$callType.'>
			<ns1:APP_Infos_B2B>
				<ns1:B2B_CLIENT>CPRO001</ns1:B2B_CLIENT>
				<ns1:B2B_TIMESTAMP>'.date('c').'</ns1:B2B_TIMESTAMP>
			</ns1:APP_Infos_B2B>
			<ns1:APP_CREA_Demande>
				<ns1:B2B_CTR_REN_ADJ></ns1:B2B_CTR_REN_ADJ>
				<ns1:B2B_ECTR_FLG>false</ns1:B2B_ECTR_FLG>
				<ns1:B2B_NATURE_DEMANDE>S</ns1:B2B_NATURE_DEMANDE>
				<ns1:B2B_REF_EXT>'.$this->simulation->reference.'</ns1:B2B_REF_EXT>
				<ns1:B2B_TYPE_DEMANDE>E</ns1:B2B_TYPE_DEMANDE>';
		
		if ($this->isUpdateCall())
		{
			$xml.= '
				<ns1:B2B_NOWEB>'.$this->simulationSuivi->b2b_noweb.'</ns1:B2B_NOWEB>
				<ns1:B2B_NODEF>'.$this->simulationSuivi->b2b_nodef.'</ns1:B2B_NODEF>
			';
		}
		
		$xml.= '
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
		</ns1:'.$callType.'>
		';
		
		return $xml;
	}
	
	public function isUpdateCall()
	{
		// si B2B_NOWEB et B2B_NODEF sont fournis ce sera un update (UpdateDemFinRequest)
		return (!empty($this->simulationSuivi->b2b_noweb) && !empty($this->simulationSuivi->b2b_nodef));
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
	
	public function getIdModeRglt($opt_mode_reglement)
	{
		global $langs;
		
		$TId = array(
			'CHQ' => 'CHQ'
			,'PRE' => 'AP'
			,'MDT' => 'MDT'
			,'VIR' => 'VIR'
		);
		
		if (empty($TId[$opt_mode_reglement]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_mode_reglement', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_mode_reglement];
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
		'.$this->ServiceFinancement->getXml().'
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