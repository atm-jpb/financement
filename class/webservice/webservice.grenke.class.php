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
				$this->message_soap_returned = $response->getMessage();
				return false;
			}

			// TODO voir comment est l'objet de retour...
			else if (!empty($response->leaseRequestId))
			{
				$this->message_soap_returned = $langs->trans($response->status);
				
				// Si nous sommes sur un appel de addLeaseRequestWithLogin()
				if (!$this->update_status)
				{
					// TODO besoin de sauvegarder 'leaseRequestID' dans notre objet '$this->simulationSuivi'
//					$this->simulationSuivi->leaseRequestID = $response->leaseRequestId;
//					$this->simulationSuivi->commentaire = $response->status;
				}
				else
				{
//					switch ($response->status)
//					{
//						case 'pending':
//							$this->simulationSuivi->statut = 'WAIT';
//							break;
//						case 'approved':
//						case 'order':
//						case 'contract':
//							$this->simulationSuivi->statut = 'OK';
//							break;
//						case 'cancelled':
//							$this->simulationSuivi->statut = 'KO';
//							break;
//						default:
//							$this->simulationSuivi->statut = 'ERR'; // case unknown
//							break;
//					}
				}

				$this->simulationSuivi->save($this->PDOdb);
				
				return true;
			}
			else
			{
				$this->message_soap_returned = $langs->trans('ServiceFinancementWrongReturn');
				return false;
			}
			
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_GRENKE');
			$this->printTrace($e); // exit fait dans la méthode
		}
		
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
		
		$paymentInterval = 'quarterly'; // valeur possible : 'quarterly', 'monthly'
		$estimatedDeliveryDate = date('c', $this->simulation->date_demarrage); // contient 0 si vide... 
		
		$entity = new DaoMulticompany($db);
		$entity->fetch($this->simulation->entity);
		
		if ($this->update_status)
		{
			$xml = '
				<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
					<s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
						<getLeaseRequestStatusWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
							<user>
								<login>'.$conf->global->FINANCEMENT_GRENKE_USERNAME.'</login>
								<partner>'.$entity->array_options['options_code_partner_grenke'].'</partner>
								<password>'.$conf->global->FINANCEMENT_GRENKE_PASSWORD.'</password>
							</user>
							<leaseRequestID>'.$this->simulationSuivi->leaseRequestID.'</leaseRequestID>
						</getLeaseRequestStatusWithLogin>
					</s:Body>
				</s:Envelope>
			';
		}
		else
		{
//			$xml = '
//				<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
//					<s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
//						<addLeaseRequestWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
//							<user>
//								<login>'.$conf->global->FINANCEMENT_GRENKE_USERNAME.'</login>
//								<partner>'.$entity->array_options['options_code_partner_grenke'].'</partner>
//								<password>'.$conf->global->FINANCEMENT_GRENKE_PASSWORD.'</password>
//							</user>
//							<leaseRequest>
//								<lessee>
//									<person xsi:type="LegalPerson">
//										<address>
//											<street>'.substr($this->simulation->societe->address, 0, 50).'</street>
//											<complement>'.substr($this->simulation->societe->address, 51, 50).'</complement>
//											<country>'.( (!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR') ).'</country>
//											<postCode>'.$this->simulation->societe->zip.'</postCode>
//											<city>'.$this->simulation->societe->town.'</city>
//										</address>
//										<communication>
//											<phone>'.$this->simulation->societe->phone.'</phone>
//											<email>'.$this->simulation->societe->email.'</email>
//											<fax>'.$this->simulation->societe->fax.'</fax>
//										</communication>
//										<name>'.$this->simulation->societe->nom.'</name>
//										<!--<nameComplement>NameComplement of LegalPerson</nameComplement>
//										<legalForm>1</legalForm>
//										<foundationDate>0001-01-01T00:00:00</foundationDate>
//										<contact>
//											<gender>male</gender>
//											<surname>Musterfrau</surname>
//											<forename>Maxime</forename>
//										</contact-->
//									</person>
//									<customerID/>
//								</lessee>
//								<articles>
//									<Article>
//										<price>'.$this->simulation->montant.'</price>
//										<type>1.11.1</type>
//										<description>'.$this->simulation->type_materiel.'</description>
//										<producer>Canon</producer>
//									</Article>
//								</articles>
//								<paymentInfo>
//									<accountInfo>
//										<accountHolder>Maximilia Musterfrau</accountHolder>
//										<iban>DE89370400440532013000</iban>
//									</accountInfo>
//									<directDebit>true</directDebit>
//									<paymentInterval>'.$paymentInterval.'</paymentInterval>
//								</paymentInfo>
//								<initialPayment>0</initialPayment>
//								<residualValue>'.$this->simulation->vr.'</residualValue>
//								<commission>0</commission>
//								<estimatedDeliveryDate>'.$estimatedDeliveryDate.'</estimatedDeliveryDate>
//								<currency>EUR</currency>
//								<tax>0</tax>
//								<maintenanceCost>0</maintenanceCost>
//								<calculation>
//									<installment>'.$this->simulation->echeance.'</installment>
//									<contractDuration>'.$dureeInMonth.'</contractDuration>
//								</calculation>
//							</leaseRequest>
//						</addLeaseRequestWithLogin>
//					</s:Body>
//				</s:Envelope>
//
//			';
			
			$xml = '
						<addLeaseRequestWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
							<user>
								<login>'.$conf->global->FINANCEMENT_GRENKE_USERNAME.'</login>
								<partner>'.$entity->array_options['options_code_partner_grenke'].'</partner>
								<password>'.$conf->global->FINANCEMENT_GRENKE_PASSWORD.'</password>
							</user>
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
										<!--<nameComplement>NameComplement of LegalPerson</nameComplement>
										<legalForm>1</legalForm>
										<foundationDate>0001-01-01T00:00:00</foundationDate>
										<contact>
											<gender>male</gender>
											<surname>Musterfrau</surname>
											<forename>Maxime</forename>
										</contact-->
									</person>
									<customerID/>
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
									<accountInfo>
										<accountHolder>Maximilia Musterfrau</accountHolder>
										<iban>DE89370400440532013000</iban>
									</accountInfo>
									<directDebit>true</directDebit>
									<paymentInterval>'.$paymentInterval.'</paymentInterval>
								</paymentInfo>
								<initialPayment>0</initialPayment>
								<residualValue>'.$this->simulation->vr.'</residualValue>
								<commission>0</commission>
								<estimatedDeliveryDate>'.$estimatedDeliveryDate.'</estimatedDeliveryDate>
								<currency>EUR</currency>
								<tax>0</tax>
								<maintenanceCost>0</maintenanceCost>
								<calculation>
									<installment>'.$this->simulation->echeance.'</installment>
									<contractDuration>'.$dureeInMonth.'</contractDuration>
								</calculation>
							</leaseRequest>
						</addLeaseRequestWithLogin>

			';
		}
		
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