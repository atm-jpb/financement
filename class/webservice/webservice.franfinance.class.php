<?php

class WebServiceFranfinance extends WebService
{

    function CallAPI($method, $url, $data = false)
    {
        global $conf;
        $curl = curl_init();
        //var_dump(array($method, $url, $data));

        switch ($method)
        {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);

                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }

        // Optional Authentication:

        $headr = array();
        $headr[] = 'Content-length: '.strlen($data);
        $headr[] = 'Content-type: application/json';

        curl_setopt($curl, CURLOPT_HTTPHEADER,$headr);

        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_USERPWD, $conf->global->FINANCEMENT_FRANFINANCE_USERNAME.":".$conf->global->FINANCEMENT_FRANFINANCE_PASSWORD);

        curl_setopt($curl, CURLOPT_HEADER, true);

        curl_setopt($curl, CURLOPT_VERBOSE, true);

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);

        $result = curl_exec($curl);

        if (!$result)
            trigger_error(curl_error($curl));

        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($result, 0, $header_size);
        $body = substr($result, $header_size);

        var_dump($header_size, $header, $body);
//        echo '<pre>'; print_r($header);

        curl_close($curl);

        return $result;
    }

	public function run()
	{
		global $conf,$langs;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_FRANFINANCE_PROD) ? $conf->global->FINANCEMENT_WSDL_FRANFINANCE_PROD : 'https://www-homo.flashlease.com/ws_acq/services/acquerirDemande';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_FRANFINANCE_RECETTE) ? $conf->global->FINANCEMENT_WSDL_FRANFINANCE_RECETTE : 'https://www-homo.flashlease.com/ws_acq/services/acquerirDemande';

		if ($this->debug) var_dump('DEBUG :: Function callFRANFINANCE(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);

        $data = $this->getBody();
//        var_dump($data);
//		$data = '{
//	"media": "WSFL",
//	"loginVendeur": "WSCPRO1",
//	"demande": {
//		"duree": "26",
//		"montant": "25000",
//		"nature": "LF",
//		"numeroSiren": "552120222",
//		"blocPlanFinancement": {
//			"premierLoyer": "0",
//			"codeAmortissement": "L",
//              "vr": "0"
//		},
//          "blocMateriel": {
//	          "codeInseeMateriel": "300212",
//	          "materielOccasion": "true",
//	          "anneeMateriel": "2016",
//               "codeNiveauUtilisationMateriel": "NEU",
//               "codeNiveauOptionsMateriel": "NEU",
//               "nombreMateriel": "10"
//		}
//	}
//
//}';
//        var_dump($data);
exit;
		var_dump(
		    $this->CallAPI(
		        'POST'
                ,$this->wsdl
                ,$data
            )
        );

		exit;

        $context = stream_context_create(
            array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            ));

		$options = array(
			'exceptions'=>1
			,'location' => 'https://www-homo.flashlease.com/ws_acq' //$this->wsdl
			,'trace' => 1
		  	,'soap_version' => SOAP_1_2
		  	,'connection_timeout' => 20
//		  	,'cache_wsdl' => WSDL_CACHE_NONE
		  	,'user_agent' => 'MySoapFranfinance'
//		  	,'use' => SOAP_LITERAL
			,'keep_alive' => false
            ,"stream_context" => $context
//            ,'authentication' => SOAP_AUTHENTICATION_BASIC
//            ,'Username' => $conf->global->FINANCEMENT_FRANFINANCE_USERNAME
//            ,'Password' => $conf->global->FINANCEMENT_FRANFINANCE_PASSWORD
            ,'http' => array(
                'header' => "Content-type: application/json\r\n"
                ,'SOAPAction' => '/services/acquerirDemande'
            )
		);

		try {
			$this->soapClient = new MySoapFranfinance('https://www-homo.flashlease.com', $options);

            var_dump($this->soapClient); exit;
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

		var_dump('FIN');
		exit;

		return false;
	}

	/**
	 * Return json content of the request
	 */
	public function getBody()
    {

        print '<pre>';
        var_dump($this->simulation); exit;

        $sirenClient = $this->simulation->societe->idprof1;
        if (empty($sirenClient)) $sirenClient = substr($this->simulation->societe->idprof2, 0, 9);
        $json = '{';

        $json.= '"media": "WSFL",';
        $json.= '"loginVendeur": "WSCPRO1",';
        // bloc demande
        $json.= '"demande": {';
        $json.= '"duree": "'.$this->getDuree().'",';
        $json.= '"montant": "'.$this->simulation->montant.'",';
        $json.= '"nature": "LF",'; // ??
        $json.= '"numeroSiren": "'.$sirenClient.'",';

        // bloc Plan Financement dans demande
        $json.= '"blocPlanFinancement": {';
        $json.= '"premierLoyer": "0",';
        $json.= '"codeAmortissement": "L",';
        $json.= '"vr": "'.$this->simulation->pct_vr.'"';
        $json.= '},'; // fin bloc plan

        // bloc matériel dans demande
        $json.= '"blocMateriel": {';
        $json.= '"codeInseeMateriel": "300212",'; // TODO à éclaircir
        $json.= '"materielOccasion": "false",';
        //$json.= '"anneeMateriel": "'.date("Y").'",';
        $json.= '"codeNiveauUtilisationMateriel": "NEU",';
        $json.= '"codeNiveauOptionsMateriel": "NEU",';
        $json.= '"nombreMateriel": "1"'; // ?
        $json.= '}'; // fin matériel

        $json.= '}'; // fin demande
        $json.= '}'; // fin corp de requête

        return $json;
    }


    /**
     * Retourne la duree de financement en mois
     */
    public function getDuree()
    {
        $duree = 0;

        switch ($this->simulation->opt_periodicite)
        {
            case 'MOIS':
                $duree = $this->simulation->duree;
                break;

            case 'TRIMESTRE':
                $duree = $this->simulation->duree * 3;
                break;

            case 'SEMESTRE':
                $duree = $this->simulation->duree * 6;
                break;

            case 'ANNEE':
                $duree = $this->simulation->duree * 12;
                break;
        }

        return $duree;
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
 * Soap class for FranFinance
 */
class MySoapFranfinance extends SoapClient
{
	public $ServiceFinancement;
	
	function __doRequest($request, $location, $saction, $version)
	{
		global $conf;
		
		// TODO Username & Password en conf
		$request = '
		{
            "media": "WSFL",
            "loginVendeur": "WSCPRO1",
            "demande": {
              "duree": "26",
              "montant": "25000",
              "nature": "LF",
              "numeroSiren": "552120222",
              "blocPlanFinancement": {
    		        "premierLoyer": "0",
     	            "codeAmortissement": "L",
     	            "vr": "0"
                },
	          "blocMateriel": {
		            "codeInseeMateriel": "300212",
                    "materielOccasion": "true",
                    "anneeMateriel": "2016",
                    "codeNiveauUtilisationMateriel": "NEU",
                    "codeNiveauOptionsMateriel": "NEU",
                    "nombreMateriel": "10"            
              }
            }
        }';
		
		
		
		$this->realXML = $request;
//		$this->realXML = str_replace(array('SOAP-ENV', 'ns1', 'ns2:'), array('soapenv', 'doc', ''), $this->realXML);
//		$this->realXML = preg_replace('/ xmlns:ns2=".*"/', '', $this->realXML);
		
//		$this->realXML = str_replace('<soapenv:Body>', '<soapenv:Header/><soapenv:Body>', $this->realXML);
/*		$this->realXML = str_replace('<?xml version="1.0" encoding="UTF-8"?>'."\n", '', $this->realXML);
*/		
		$this->realXML = str_replace('<ns1:B2B_CTR_REN_ADJ></ns1:B2B_CTR_REN_ADJ>', '<ns1:B2B_CTR_REN_ADJ/>', $this->realXML);
		print $request; exit;
//		return parent::__doRequest($this->realXML, $location, $saction, $version);
	}
}