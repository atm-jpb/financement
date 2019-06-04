<?php

class WebServiceFranfinance extends WebService
{

	public function run()
	{
		global $conf,$langs;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_FRANFINANCE_PROD) ? $conf->global->FINANCEMENT_WSDL_FRANFINANCE_PROD : 'https://www-homo.flashlease.com/ws_acq/services/acquerirDemande';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_FRANFINANCE_RECETTE) ? $conf->global->FINANCEMENT_WSDL_FRANFINANCE_RECETTE : 'https://www-homo.flashlease.com/ws_acq/services/acquerirDemande';

		if ($this->debug) var_dump('DEBUG :: Function callFRANFINANCE(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);

        $data = $this->getBody();
        $data_json = json_encode($data);

        // @see http://www.robertprice.co.uk/robblog/posting-json-to-a-web-service-with-php/
        // @see https://stackoverflow.com/questions/15076819/file-get-contents-ignoring-verify-peer-false
        $options = array(
            'http' => array(
                'protocol_version' => 1.1
                ,'method' => 'POST'
                ,'header' =>
                    "Content-Type: application/json\r\n".
                    "Content-Length: ".strlen($data_json)."\r\n".
                    "Accept: */*\r\n".
                    "Connection: close\r\n".
                    "Authorization: Basic ".base64_encode($conf->global->FINANCEMENT_FRANFINANCE_USERNAME.":".$conf->global->FINANCEMENT_FRANFINANCE_PASSWORD)."\r\n"
                ,'content' => $data_json
                ,"timeout" => (float)10.0
            )
            ,'ssl'=>array(
                'allow_self_signed' => true
                ,'verify_peer' => false
                ,'verify_peer_name' => false
            )
        );

        $context  = stream_context_create($options);
        $res = file_get_contents($this->wsdl,null, $context);
//var_dump($res);exit;
        dol_syslog("WEBSERVICE SENDING FRANFINANCE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_FRANFINANCE');

        if ($res)
        {
            $this->simulationSuivi->numero_accord_leaser = '';
            $this->simulationSuivi->commentaire = '';

            $resp = json_decode($res);
            if ($resp->code == 201)
            {
                $this->simulationSuivi->numero_accord_leaser = $resp->numeroDemande;
                $this->simulationSuivi->b2b_nodef = $resp->numeroDemande;
                return true;
            }
            else
            {
                $this->simulationSuivi->status = 'ERR';
                $this->simulationSuivi->commentaire = $resp->message;
                return false;
            }

        }

        $this->simulationSuivi->commentaire = $this->curl->error;
		return false;
	}

	/**
	 * Return json content of the request
	 */
	public function getBody()
    {

//        print '<pre>';
//        var_dump($this->simulation); exit;
        $sirenClient = $this->simulation->societe->idprof1;
        if (empty($sirenClient)) $sirenClient = substr($this->simulation->societe->idprof2, 0, 9);

        $data = new stdClass();
        $data->media = 'WSFL';
        $data->loginVendeur = 'WSCPRO1';

        $data->demande = new stdClass();
        $data->demande->duree = $this->getDuree();
        $data->demande->montant = $this->simulation->montant;
        $data->demande->nature = 'LF';
        $data->demande->numeroSiren = $sirenClient;

        $data->demande->blocPlanFinancement = new stdClass();
        $data->demande->blocPlanFinancement->premierLoyer = '0';
        $data->demande->blocPlanFinancement->codeAmortissement = 'L';
        $data->demande->blocPlanFinancement->vr = $this->simulation->pct_vr;

        $data->demande->blocMateriel = new stdClass();
        $data->demande->blocMateriel->codeInseeMateriel = '300121';
        $data->demande->blocMateriel->materielOccasion = false;
//        $data->demande->blocMateriel->anneeMateriel = date("Y");
        $data->demande->blocMateriel->codeNiveauUtilisationMateriel = 'NEU';
        $data->demande->blocMateriel->codeNiveauOptionsMateriel = 'NEU';
        $data->demande->blocMateriel->nombreMateriel = 1;

        return $data;
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
		$xml = "";
		
		return $xml;
	}
}
