<?php

class WebServiceGrenke extends WebService 
{
	public function run()
	{
		global $conf,$langs;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_GRENKE_PROD) ? $conf->global->FINANCEMENT_WSDL_GRENKE_PROD : '';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_GRENKE_RECETTE) ? $conf->global->FINANCEMENT_WSDL_GRENKE_RECETTE : 'https://uatleasingapifr.grenke.net/mainservice.asmx?WSDL';
		
		if ($this->debug) var_dump('DEBUG :: Function callCMCIC(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);
		
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
//			$this->soapClient = new MySoapCmCic($this->wsdl, $options);
			$this->soapClient = new MySoapGrenke($this->wsdl, $options);
//			$this->soapClient->ServiceFinancement = $this;
			
//			var_dump($this->soapClient->__getFunctions());exit;
			dol_syslog("WEBSERVICE SENDING GRENKE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_GRENKE');
			
			
//			$string_xml_body = $this->getXml();
//			$soap_var_body = new SoapVar($string_xml_body, XSD_ANYXML, null, null, null);
//			$response = $this->soapClient->addLeaseRequestWithLogin($soap_var_body);

//			var_dump($this->getXml());exit;			
			/** @var addLeaseRequestWithLoginResponse $response */
			$string_xml_body = $this->getXml();
			$soap_var_body = new SoapVar($string_xml_body, XSD_ANYXML, null, null, null);
			$response = $this->soapClient->addLeaseRequestWithLogin($soap_var_body);
//  var_dump($response);exit;
			
			// TODO : issue de la doc => Dans l’éventualité où l’utilisateur est invalide, un message d’erreur est envoyé au partenaire
			if ($this->debug)
			{
				$this->printDebugSoapCall($response);
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			// TODO voir comment est l'objet de retour...
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
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_GRENKE');
			$this->printTrace($e); // exit fait dans la méthode
		}
		
//		exit('FIN');
	}
	
	public function getXml()
	{
		global $mysoc;
		
		$f = new TFin_financement();
		$f->periodicite = $this->simulation->opt_periodicite;
		$dureeInMonth = $this->simulation->duree * $f->getiPeriode();
		
		$paymentInterval = 'quarterly'; // valeur possible : 'quarterly', 'monthly'
		$estimatedDeliveryDate = date('c', $this->simulation->date_demarrage); // contient ) si vide... 
		
		$xml = '
			<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
				<s:Body xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
					<addLeaseRequestWithLogin xmlns="http://grenkeleasing.com/gfs.api.server.extern">
						<user>
							<login>financement@cpro.fr</login>
							<partner>257-00049</partner>
							<password></password>
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
									<!--<nameComplement>NameComplement of LegalPerson</nameComplement-->
									<!--<legalForm>1</legalForm-->
									<!--<foundationDate>0001-01-01T00:00:00</foundationDate-->
									<!--<contact>
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
				</s:Body>
			</s:Envelope>

		';
		
		return $xml;
	}
}


class MySoapGrenke extends SoapClient
{
	function __doRequest($request, $location, $saction, $version)
	{
		echo '<pre>' . htmlspecialchars($request, ENT_QUOTES) . '</pre>';
		
		exit;
		
		return parent::__doRequest($this->realXML, $location, $saction, $version);
	}
}