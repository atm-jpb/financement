<?php

// TODO à mettre en commentaire ou à supprimer pour mise en prod

define('INC_FROM_CRON_SCRIPT',true);

require('../../config.php');

dol_include_once('/financement/class/service_financement.class.php');

$PDOdb = new TPDOdb;

$simulation = new TSimulation(true);
$simulation->load($PDOdb, $db, 18510);

//var_dump($conf->global->WEBSERVICES_KEY);
//exit;

$service = new ServiceFinancement($simulation, $simulation->TSimulationSuivi[209126]);

/*
$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');

array_to_xml($authentication, $xml_data);
$result = $xml_data->asXML('/home/pierrehenry/public_html/client/cprofin/test/authentification.xml');

var_dump($xml_data);
exit;
*/

function array_to_xml( $data, &$xml_data ) {
	foreach( $data as $key => $value ) {
		if( is_array($value) ) {
			if( is_numeric($key) ){
				$key = 'item'.$key; //dealing with <0/>..<n/> issues
			}
			$subnode = $xml_data->addChild($key);
			array_to_xml($value, $subnode);
		} else {
			$xml_data->addChild("$key",htmlspecialchars("$value"));
		}
	 }
}


$service->wsdl = 'http://localhost/public_html/client/cprofin/dolibarr/htdocs/custom/financement/script/webservice/scoring_server.php?wsdl';

// Call the WebService method and store its result in $result.
$authentication=array(
    'dolibarrkey'=>$conf->global->WEBSERVICES_KEY,
    'sourceapplication'=>'edi_cmcic',
    'login'=>'cmcic',
    'password'=>'cmcic'
);

$TParam = array(
	'partenaire' => array()
	,'client' => array()
	,'financement' => array()
);

try {
	$ns='http://'.$_SERVER['HTTP_HOST'].'/ns/';

	$soapClient = new nusoap_client($service->wsdl/*, $params_connection*/);
	
	$result = $soapClient->call('repondreDemandeCmCic', array('authentication'=>$authentication, 'TParam' => $TParam), $ns, '');

} catch (SoapFault $e) {
	var_dump($e);
	exit;
}

header("Content-type: text/html; charset=utf8");
print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'."\n";
echo '<html>'."\n";
echo '<head>';
echo '<title>WebService Test: callTest</title>';
echo '</head>'."\n";

echo '<body>'."\n";
echo 'NUSOAP_PATH='.NUSOAP_PATH.'<br>';

echo "<h2>Request:</h2>";
echo '<h4>Function</h4>';
echo 'callTest';
echo '<h4>SOAP Message</h4>';
echo '<pre>' . htmlspecialchars($soapClient->request, ENT_QUOTES) . '</pre>';

echo '<hr>';

echo "<h2>Response:</h2>";
echo '<h4>Result</h4>';
echo '<pre>';
print_r($result);
echo '</pre>';
echo '<h4>SOAP Message</h4>';
echo '<pre>' . $soapClient->response, ENT_QUOTES . '</pre>';


echo '<hr>';

echo "<h2>Response:</h2>";
echo '<h4>Result</h4>';
echo '<pre>';
print_r($result);
echo '</pre>';
echo '<h4>SOAP Message</h4>';
echo '<pre>' . htmlspecialchars($soapClient->response, ENT_QUOTES) . '</pre>';

echo '</body>'."\n";;
echo '</html>'."\n";;

exit;