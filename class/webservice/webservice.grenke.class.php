<?php

class WebServiceGrenke extends WebService 
{
	/** @var bool $update_status */
	public $update_status;

	public function __construct(&$simulation, &$simulationSuivi, $debug = false, $update_status=false)
	{
		parent::__construct($simulation, $simulationSuivi, $debug);
		
		$this->update_status = $update_status;
	}
	
	public function run()
	{
		global $conf,$langs;

		$oldconf = $conf;
		switchEntity($this->simulation->entity);
		
		//$this->debug = true;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_GRENKE_PROD) ? $conf->global->FINANCEMENT_WSDL_GRENKE_PROD : '';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_GRENKE_RECETTE) ? $conf->global->FINANCEMENT_WSDL_GRENKE_RECETTE : 'https://uatleasingapifr.grenke.net/mainservice.asmx?WSDL';
		
		if ($this->debug) var_dump('DEBUG :: Function run(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);
		
		$options = array(
			'exceptions'=>0
			,'location' => $this->wsdl
			,'trace' => 1
		  	,'soap_version' => SOAP_1_1
		  	,'connection_timeout' => 20
		  	,'cache_wsdl' => WSDL_CACHE_NONE
		  	,'user_agent' => 'MySoapGrenke'
//		  	,'use' => SOAP_LITERAL
			,'keep_alive' => false
			,'stream_context' => stream_context_create(array('ssl' => array(
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			)))
		);
		
//		var_dump($this->wsdl);
		try {
			$this->soapClient = new MySoapGrenke($this->wsdl, $options);

//			var_dump($this->soapClient->__getFunctions());exit;
			dol_syslog("WEBSERVICE SENDING GRENKE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_GRENKE');
			
			/** @var LeaseRequestStatus $response */
			$string_xml_body = $this->getXml();
			$soap_var_body = new SoapVar($string_xml_body, XSD_ANYXML, 'http://www.w3.org/2001/XMLSchema', null, null);

			if ($this->update_status) $response = $this->soapClient->getLeaseRequestStatusWithLogin($soap_var_body);
			else $response = $this->soapClient->addLeaseRequestWithLogin($soap_var_body);
			
//			var_dump($response);exit;
			
			// TODO : issue de la doc => Dans l’éventualité où l’utilisateur est invalide, un message d’erreur est envoyé au partenaire
			if ($this->debug)
			{
				$this->printDebugSoapCall($response);
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			if (get_class($response) == 'SoapFault')
			{
				if (!empty($this->simulationSuivi->commentaire)) $this->simulationSuivi->commentaire.= "\n";
				$this->simulationSuivi->commentaire.= $response->getMessage();
				return false;
			}

			// Envoie d'une demande
			else if (!empty($response->addLeaseRequestWithLoginResult->leaseRequestId))
			{
				// besoin de sauvegarder 'leaseRequestID' dans notre objet '$this->simulationSuivi'
				$this->simulationSuivi->leaseRequestID = $response->addLeaseRequestWithLoginResult->leaseRequestId;
				$this->simulationSuivi->commentaire = $langs->trans($response->addLeaseRequestWithLoginResult->status);

				return true;
			}
			// MAJ du statut
			else if (!empty($response->getLeaseRequestStatusWithLoginResult->leaseRequestId))
			{
				$this->simulationSuivi->commentaire.= $langs->trans($response->getLeaseRequestStatusWithLoginResult->status);

				switch ($response->getLeaseRequestStatusWithLoginResult->status)
				{
					case 'pending':
						$this->simulationSuivi->statut = 'WAIT';
						break;
					case 'approved':
					case 'order':
					case 'contract':
						$this->simulationSuivi->statut = 'OK';

						// Get ref and PDF
						$this->getRefAndDoc();

						break;
					case 'cancelled':
						$this->simulationSuivi->statut = 'KO';

						// Get ref and PDF
						$this->getRefAndDoc();

						break;
					default:
						$this->simulationSuivi->statut = 'ERR'; // case unknown
						break;
				}

				$this->simulationSuivi->save($this->PDOdb);
				
				return true;
			}
			else
			{
				if (!empty($this->simulationSuivi->commentaire)) $this->simulationSuivi->commentaire.= "\n";
				$this->simulationSuivi->commentaire.= $langs->trans('ServiceFinancementWrongReturn');
				return false;
			}
			
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_GRENKE');
			parent::caughtError($e);
		}

        switchEntity($oldconf->entity);
		
		return false;
	}
	
	/**
	 * 
	 * @global Societe $mysoc
	 * @global type $conf
	 * @return string
	 */
	public function getXml()
	{
		global $mysoc,$conf,$db;
		
		$f = new TFin_financement();
		$f->periodicite = $this->simulation->opt_periodicite;
		$dureeInMonth = $this->simulation->duree * $f->getiPeriode();
		$echeanceInMonth = round($this->simulation->echeance / $f->getiPeriode(),2);
		
		$paymentInterval = 'quarterly'; // valeur possible : 'quarterly', 'monthly'
		$estimatedDeliveryDate = date('c', $this->simulation->date_demarrage); // contient 0 si vide...
		
		if ($this->update_status)
		{
			$xml = '
				<getLeaseRequestStatusWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
					'.$this->getUserSection().'
					<leaseRequestID>'.$this->simulationSuivi->leaseRequestID.'</leaseRequestID>
				</getLeaseRequestStatusWithLogin>
			';
		}
		else
		{
			$xml = '
						<addLeaseRequestWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
							'.$this->getUserSection().'
							<leaseRequest>
								<lessee>
									<person xsi:type="LegalPerson">
										<address>
											<street>'.substr($this->simulation->societe->address, 0, 50).'</street>
											<complement>'.substr($this->simulation->societe->address, 51, 50).'</complement>
											<country>'.( (!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR') ).'</country>
											<postCode>'.$this->simulation->societe->zip.'</postCode>
											<city>'.$this->simulation->societe->town.'</city>
										</address>
										<communication>
											<phone>'.$this->simulation->societe->phone.'</phone>
											<email>'.$this->simulation->societe->email.'</email>
											<fax>'.$this->simulation->societe->fax.'</fax>
										</communication>
										<name>'.$this->simulation->societe->nom.'</name>
									</person>
									<customerID>'.$this->simulation->societe->idprof1.'</customerID>
								</lessee>
								<articles>
									<Article>
										<price>'.$this->simulation->montant.'</price>
										<type>1.11.1</type>
										<description>'.$this->simulation->type_materiel.'</description>
										<producer>Canon</producer>
									</Article>
								</articles>
								<paymentInfo>
									<directDebit>true</directDebit>
									<paymentInterval>'.$paymentInterval.'</paymentInterval>
								</paymentInfo>
								<initialPayment>0</initialPayment>
								<residualValue>0</residualValue>
								<commission>0</commission>
								<estimatedDeliveryDate>'.$estimatedDeliveryDate.'</estimatedDeliveryDate>
								<currency>EUR</currency>
								<tax>0</tax>
								<maintenanceCost>0</maintenanceCost>
								<calculation>
									<installment>'.$echeanceInMonth.'</installment>
									<contractDuration>'.$dureeInMonth.'</contractDuration>
								</calculation>
							</leaseRequest>
						</addLeaseRequestWithLogin>

			';
		}
		
		return $xml;
	}

	private function getRefAndDoc()
	{
		global $db,$conf;

		$options = array(
			'exceptions'=>0
			,'location' => $this->wsdl
			,'trace' => 1
			,'soap_version' => SOAP_1_1
			,'connection_timeout' => 20
			,'cache_wsdl' => WSDL_CACHE_NONE
			,'user_agent' => 'MySoapGrenke'
			,'keep_alive' => false
			,'stream_context' => stream_context_create(array('ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)))
		);

		$xml = '
			<getDocumentWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
				'.$this->getUserSection().'
				<leaseRequestID>'.$this->simulationSuivi->leaseRequestID.'</leaseRequestID>
			</getDocumentWithLogin>
		';

		try {
			$this->soapClient = new MySoapGrenke($this->wsdl, $options);

			dol_syslog("WEBSERVICE GET DOC GRENKE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_GRENKE');

			$soap_var_body = new SoapVar($xml, XSD_ANYXML, 'http://www.w3.org/2001/XMLSchema', null, null);
			$response = $this->soapClient->getDocumentWithLogin($soap_var_body);

			if (!empty($response->getDocumentWithLoginResult))
			{
				$this->simulationSuivi->numero_accord_leaser = $response->getDocumentWithLoginResult->proposalID;

				$dir = $this->simulation->getFilePath();
				$subdir = '/'.$this->simulationSuivi->leaser->array_options['options_edi_leaser'];
				dol_mkdir($dir.$subdir);
				if (file_exists($dir.$subdir))
				{
					$pdf_decoded = $response->getDocumentWithLoginResult->data;
					$pdf = fopen($dir.$subdir.'/'.dol_sanitizeFileName($this->simulation->reference).'_grenke.pdf', 'w');
					fwrite($pdf, $pdf_decoded);
					fclose($pdf);
				}

				return true;
			}
			else
			{
				$this->simulationSuivi->commentaire.= "\nErreur sur la récupération de la référence et du PDF";
			}

		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_GRENKE');
			parent::caughtError($e);
		}

		return false;
	}

	private function getUserSection()
    {
        global $conf;

        // Code dépendant du type de demande (CESSION / MANDATEE)
        $codePartner = $conf->global->FINANCEMENT_GRENKE_CODE_CESSION;
        if(strpos($this->simulationSuivi->leaser->name, 'MANDATEE')) $codePartner = $conf->global->FINANCEMENT_GRENKE_CODE_MANDATEE;

        $xml = '<user>
					<login>'.$conf->global->FINANCEMENT_GRENKE_USERNAME.'</login>
					<partner>'.$codePartner.'</partner>
					<password>'.$conf->global->FINANCEMENT_GRENKE_PASSWORD.'</password>
				</user>';

        return $xml;
    }
}


class MySoapGrenke extends SoapClient
{
	function __doRequest($request, $location, $saction, $version)
	{
//		$request = preg_replace('/<\/?SOAP-ENV:Body.*>/', '', $request);
//		$request = preg_replace('/<\/?SOAP-ENV:Envelope.*>/', '', $request);
//		echo '<pre>' . htmlspecialchars($request, ENT_QUOTES) . '</pre>';
//		exit;
		
		return parent::__doRequest($request, $location, $saction, $version);
	}
}
