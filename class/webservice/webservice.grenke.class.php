<?php

class WebServiceGrenke extends WebService 
{
	public function run()
	{
		global $conf,$langs;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CMCIC_PROD) ? $conf->global->FINANCEMENT_WSDL_CMCIC_PROD : 'https://www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CMCIC_RECETTE) ? $conf->global->FINANCEMENT_WSDL_CMCIC_RECETTE : 'https://uat-www.espacepartenaires.cmcic-leasing.fr/imanageB2B/ws/dealws.wsdl';
		
		if ($this->debug) var_dump('DEBUG :: Function callCMCIC(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);
		
	}
	
	public function getXml()
	{
		
	}
}
