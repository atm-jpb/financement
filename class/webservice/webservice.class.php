<?php

abstract class WebService
{
	/** @var MySoapClient|MySoapCmCic $soapClient */
	public $soapClient;
	
	/** @var string $wsdl */
	public $wsdl;
	
	/** @var string $endpoint */
	public $endpoint;
	
	/** @var boolean $debug */
	public $debug=false;
	
	/** @var boolean $production */
	public $production;
	
	/** @var boolean $activate */
	public $activate;
	
	/** @var strin[] $TMsg */
	public $TMsg;
	
	/** @var string[] $TError */
	public $TError;
	
	/** @var TSimulation $simulation */
	public $simulation;
	
	/** @var TSimulationSuivi $simulationSuivi */
	public $simulationSuivi;
	
	/** @var Societe $leaser */
	public $leaser;
	
	/** @var TPDOdb $PDOdb */
	public $PDOdb;
	
	abstract public function run();
	abstract public function getXml();
	
	/**
	 * @param TSimulation $simulation
	 * @param TSimulationSuivi $simulationSuivi
	 */
	public function __construct(&$simulation, &$simulationSuivi, $debug=false)
	{
		global $conf;
		
		$this->simulation = &$simulation;
		$this->simulationSuivi = &$simulationSuivi;
		
		$this->leaser = &$simulationSuivi->leaser;
		
		$this->debug = $debug;
		
		$this->activate = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVATE) ? true : false;
		$this->production = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVE_FOR_PROD) ? true : false;
		
		$this->PDOdb = new TPDOdb;
	}
	
	protected function printHeader()
	{
		header("Content-type: text/html; charset=utf8");
		print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'."\n";
		echo '<html>'."\n";
		echo '<head>';
		echo '<title>WebService Test: callTest</title>';
		echo '</head>'."\n";
		
		echo '<body>'."\n";
	}
	
	protected function printDebugSoapCall($response)
	{
		// on affiche la requete et la reponse
		echo '<br />';
		echo "<h2>Request:</h2>";
		echo '<h4>Function</h4>';
		echo 'call Create Leasing';
		echo '<h4>SOAP Message</h4>';
		echo '<pre>' . htmlspecialchars($this->soapClient->__getLastRequest(), ENT_QUOTES) . '</pre>';

		echo '<hr>';

		echo "<h2>Response:</h2>";
		echo '<h4>Result</h4>';
		echo '<pre>';
		print_r($response);
		echo '</pre>';
		echo '<h4>SOAP Message</h4>';
		echo '<pre>' . htmlspecialchars($this->soapClient->__getLastResponse(), ENT_QUOTES) . '</pre>';

		echo '<hr>';

		echo '<br />';
		echo "<h2>Request realXML:</h2>";
		echo '<h4>Function</h4>';
		echo 'call Create Leasing';
		echo '<h4>SOAP Message</h4>';
		echo '<pre>' . htmlspecialchars($this->soapClient->realXML, ENT_QUOTES) . '</pre>';

		echo '</body>'."\n";
		echo '</html>'."\n";
		exit;
	}
	
	protected function printTrace($e)
	{
		echo '<b>Caught exception:</b> ',  $e->getMessage(), "\n"; 

		$trace = $e->getTrace();
		var_dump('ERROR TRACE 1: $trace[0]["args"][0] => ');

		echo '<pre>' . htmlspecialchars($trace[0]['args'][0], ENT_QUOTES) . '</pre>';

		var_dump('ERROR TRACE 2: $trace[0]["args"][1] => ');
		var_dump($trace[0]['args'][1]);


		var_dump('ERROR TRACE 3: $trace[0]["args"][1][0]->enc_value => ');
		echo '<pre>' . htmlspecialchars($trace[0]['args'][1][0]->enc_value, ENT_QUOTES) . '</pre>';


		var_dump('ERROR TRACE 8: $e');
		var_dump($e);

		echo ($e->__toString());
		exit;
	}
	
}
