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

        dol_syslog("WEBSERVICE SENDING FRANFINANCE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_FRANFINANCE');

        $res = $this->CallAPI(
            'POST'
            ,$this->wsdl
            ,$data
        );

        if ($res)
        {
            $this->simulationSuivi->numero_accord_leaser = '';
            $this->simulationSuivi->commentaire = '';

//            var_dump($this->curl->header_size, $this->curl->header, $this->curl->body);
//            var_dump(json_decode($this->curl->body));
            $resp = json_decode($this->curl->body);

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

        $this->curl->result = curl_exec($curl);

        if (!$this->curl->result)
            $this->curl->error = curl_error($curl);

        $this->curl->header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $this->curl->header = substr($this->curl->result, 0, $this->curl->header_size);
        $this->curl->body = substr($this->curl->result, $this->curl->header_size);

        curl_close($curl);

        return $this->curl->result;
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
        $json = '{';

        $json.= '"media": "WSFL",';
        $json.= '"loginVendeur": "WSCPRO1",';
        // bloc demande
        $json.= '"demande": {';
        $json.= '"duree": "'.$this->getDuree().'",';
        $json.= '"montant": "'.$this->simulation->montant.'",';
        $json.= '"nature": "LF",';
        $json.= '"numeroSiren": "'.$sirenClient.'",';

        // bloc Plan Financement dans demande
        $json.= '"blocPlanFinancement": {';
        $json.= '"premierLoyer": "0",';
        $json.= '"codeAmortissement": "L",';
        $json.= '"vr": "'.$this->simulation->pct_vr.'"';
        $json.= '},'; // fin bloc plan

        // bloc matériel dans demande
        $json.= '"blocMateriel": {';
        $json.= '"codeInseeMateriel": "300121",';
        $json.= '"materielOccasion": "false",';
        //$json.= '"anneeMateriel": "'.date("Y").'",';
        $json.= '"codeNiveauUtilisationMateriel": "NEU",';
        $json.= '"codeNiveauOptionsMateriel": "NEU",';
        $json.= '"nombreMateriel": "1"';
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
		$xml = "";
		
		return $xml;
	}
}
