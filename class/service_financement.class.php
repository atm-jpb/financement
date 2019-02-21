<?php

/**
 * Class used to call Lixbail Soap service
 * + used to call CM CIC Soap service
 */
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


dol_include_once('/financement/class/webservice/webservice.class.php');
dol_include_once('/financement/class/webservice/webservice.lixxbail.class.php');
dol_include_once('/financement/class/webservice/webservice.cmcic.class.php');
dol_include_once('/financement/class/webservice/webservice.grenke.class.php');
dol_include_once('/financement/class/webservice/webservice.bnp.class.php');


class ServiceFinancement {
	
	/**
	 * TODO in futur remove all attributes
	 */
	public $simulation;
	public $simulationSuivi;
	
	public $leaser;
	
	public $TMsg = array();
	public $TError = array();
	public $message_soap_returned = ''; // TODO in futur keep this one
	
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
	public function __construct(&$simulation, &$simulationSuivi, $debug=false)
	{
		/**
		 * TODO rewrite content to init attribute as $ws as instance of WebServiceLixxbail or WebServiceCmcic or WebServiceGrenke
		 */
		global $conf;
		
		$this->simulation = &$simulation;
		$this->simulationSuivi = &$simulationSuivi;
		
		$this->leaser = &$simulationSuivi->leaser;
		
		$this->debug = $debug;

		$this->activate = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVATE) ? true : false;
		$this->production = !empty($conf->global->FINANCEMENT_MODE_PROD) ? true : false;
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
		
		$ws = null;

		if ($this->leaser->array_options['options_edi_leaser'] == 'LIXXBAIL')
		{
			$ws = new WebServiceLixxbail($this->simulation, $this->simulationSuivi, $this->debug);
		}
		else if ($this->leaser->array_options['options_edi_leaser'] == 'CMCIC')
		{
			$ws = new WebServiceCmcic($this->simulation, $this->simulationSuivi, $this->debug);
		}
		else if ($this->leaser->array_options['options_edi_leaser'] == 'GRENKE')
		{
			$ws = new WebServiceGrenke($this->simulation, $this->simulationSuivi, $this->debug);
		}
		else if ($this->leaser->array_options['options_edi_leaser'] == 'BNP')
		{
			$ws = new WebServiceBnp($this->simulation, $this->simulationSuivi, $this->debug);
		}
		
		if ($ws !== null)
		{
			$res = $ws->run();
			return $res;
		}
		
		if ($this->debug) var_dump('DEBUG :: Function call(): # aucun traitement prévu');
		
		return false;
	}
	
} // End Class
