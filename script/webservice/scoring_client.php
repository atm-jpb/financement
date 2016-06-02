<?php

// TODO à mettre en commentaire ou à supprimer pour mise en prod

define('INC_FROM_CRON_SCRIPT',true);

require('../../config.php');

dol_include_once('/financement/class/service_financement.class.php');

$service = new ServiceFinancement($simulation, $simulation->TSimulationSuivi[85600]);
$service->wsdl = 'http://localhost/client/cpro/fin/htdocs/custom/financement/script/webservice/scoring_server.php';

// Call the WebService method and store its result in $result.
$authentication=array(
    'dolibarrkey'=>$conf->global->WEBSERVICES_KEY,
    'sourceapplication'=>'DEMO',
    'login'=>'admin',
    'password'=>'todo_changeme',
    'entity'=>'1');

$TParam = array(
	'partenaire' => array()
	,'client' => array()
	,'financement' => array()
);

$service->callTest($authentication, $TParam);

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
echo '<pre>' . htmlspecialchars($service->soapClient->request, ENT_QUOTES) . '</pre>';

echo '<hr>';

echo "<h2>Response:</h2>";
echo '<h4>Result</h4>';
echo '<pre>';
print_r($service->result);
echo '</pre>';
echo '<h4>SOAP Message</h4>';
echo '<pre>' . htmlspecialchars($service->soapClient->response, ENT_QUOTES) . '</pre>';

echo '</body>'."\n";;
echo '</html>'."\n";;

exit;