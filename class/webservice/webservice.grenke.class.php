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
		  	,'soap_version' => SOAP_1_2
		  	,'connection_timeout' => 20
		  	,'cache_wsdl' => WSDL_CACHE_NONE
//		  	,'user_agent' => 'MySoapCmCic'
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
			$this->soapClient = new SoapClient($this->wsdl, $options);
//			$this->soapClient->ServiceFinancement = $this;
			
//			var_dump($this->soapClient->__getFunctions());exit;
			dol_syslog("WEBSERVICE SENDING GRENKE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_GRENKE');
			
			
//			$string_xml_body = $this->getXml();
//			$soap_var_body = new SoapVar($string_xml_body, XSD_ANYXML, null, null, null);
//			$response = $this->soapClient->addLeaseRequestWithLogin($soap_var_body);

//			var_dump($this->getXml());exit;			
			/** @var addLeaseRequestWithLoginResponse $response */
			$response = $this->soapClient->addLeaseRequestWithLogin($this->getXml());
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
		
//		var_dump($mysoc);exit;
//		var_dump($this->simulation);exit;
		
		$res = array(
			'user' => array(
				'partner' => '257-00049'
				,'login' => 'financement@cpro.fr'
				,'password' => ''
			)
			,'leaseRequest' => array(
				'lessee'=> array(
					'person' => array(
						'name' => $this->simulation->societe->nom
						,'address' => array(
							'street' => substr($this->simulation->societe->address, 0, 50) // max 50 char
							,'complement' => substr($this->simulation->societe->address, 51, 50) // max 50 char
							,'country' => (!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR') // country code
							,'postCode' => $this->simulation->societe->zip // max 5 char
							,'city' => $this->simulation->societe->town // max 50 char
						)
						,'communication' => array(
							'phone' => $this->simulation->societe->phone // max 50 char
							,'phone2' => '' // max 50 char
							,'email' => $this->simulation->societe->email // max 50 char
							,'fax' => $this->simulation->societe->fax // max 50 char
						)
						,'identifications' => array(
							'Identification' => array(
								'type' => '' // SIREN ?
								,'id' => $this->simulation->societe->idprof1 // $mysoc ?
							)
						)
						,'creditAgencyIdentifications' => array() // ???
					)
				)
				,'articles' => array(
					'Article' => array(
						'price' => $this->simulation->montant // double
						,'type' => '1.11.1' // Copying machine
						,'description' => $this->simulation->type_materiel // max 50 char
						,'producer' => 'Canon' // max 50 char
					)
				)
				
				,'calculation' => array(
					// [(Acquisition value–initial payment)*leasing factor/100]*[1+(monthly extra charge/100)]=monthly instalment
					'installment' => $this->simulation->echeance // double (Monthly leasing instalment you have calculated based on the provided conditions lists.)
					,'contractDuration' => $dureeInMonth // short (Leasing period in terms of months)
				)
				,'initialPayment' => 0.00 // double
				,'commission' => 0.00// double
				,'residualValue' => $this->simulation->vr // double
				,'estimatedDeliveryDate' => date('Y-m-d', $this->simulation->date_demarrage) // Date (format non précisé)
				,'paymentInfo' => array(
					'accountInfo' => array(
						'bankCode' => '' // max 10 char
						,'bankName' => '' // max 50 char
						,'accountHolder:' => '' // max 50 char
						,'accountNumber' => '' // max 40 char
						,'iban' => '' // max 34 char
						,'bic' => '' // max 11 char
					)
					,'directDebit' => true // boolean
					,'paymentInterval' => $f->getiPeriode() // or 'quarterly' (short = default:quarterly = 3)
					,'invoiceRecipient'=> array(
						'address' => array(
							'street' => substr($mysoc->address, 0, 50) // max 50 char
							,'complement' => substr($mysoc->address, 51) // max 50 char
							,'country' => (!empty($mysoc->country_code) ? $mysoc->country_code : 'FR') // country code
							,'postCode' => $mysoc->zip // max 5 char
							,'city' => $mysoc->town // max 50 char
							,'name' => $mysoc->name
						)
						,'communication' => array(
							'phone' => $mysoc->phone // max 50 char
							,'phone2' => '' // max 50 char
							,'email' => $mysoc->email // max 50 char
							,'fax' => $mysoc->fax // max 50 char
						)
						,'identifications:' => array(
							'Identification' => array(
								'type' => '' // SIREN ?
								,'id' => $mysoc->idprof1
							)
						)
						,'creditAgencyIdentifications' => array() // ???
					)
				)
				,'currency' => 'EUR'
				,'tax' => 0.00
				,'maintenanceCost' => 0.00
			)
		);
		
		return $res;
	}
}
