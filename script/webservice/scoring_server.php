<?php

define('INC_FROM_CRON_SCRIPT',true);

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require('../../config.php');


require_once NUSOAP_PATH.'/nusoap.php';		// Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

// TODO inclure les class nécessaire pour le scoring
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

dol_syslog("FINANCEMENT SOAP : start calling webservice");

$langs->load("main");

// Create the soap Object
$server = new nusoap_server();
$server->soap_defencoding='UTF-8';
$server->decode_utf8=false;
$ns='http://'.$_SERVER['HTTP_HOST'].'/ns/';
$server->configureWSDL('WebServicesDolibarrScoring',$ns);
$server->wsdl->schemaTargetNamespace=$ns;


// Define WSDL Authentication object
$server->wsdl->addComplexType(
    'authentication',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'dolibarrkey' => array('name'=>'dolibarrkey','type'=>'xsd:string'),
    	'login' => array('name'=>'login','type'=>'xsd:string'),
        'password' => array('name'=>'password','type'=>'xsd:string'),
        'entity' => array('name'=>'entity','type'=>'xsd:string')
    )
);
// Define WSDL Return object
$server->wsdl->addComplexType(
    'result',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'result_code' => array('name'=>'result_code','type'=>'xsd:string'),
        'result_label' => array('name'=>'result_label','type'=>'xsd:string'),
    )
);

// Define other specific objects
$server->wsdl->addComplexType(
    'linePartenaire',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'ref_ext' => array('name'=>'ref_ext','type'=>'xsd:string') // O - Numéro de dossier C'PRO - chaîne de caractères alphanumérique de 20 caractères max
    )
);
$server->wsdl->addComplexType(
    'lineClient',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'client_siren' => array('name'=>'client_siren','type'=>'xsd:int') // O - Numéro SIREN du client de C'PRO - numérique entier de longueur fixe 9
        ,'client_nic' => array('name'=>'client_nic','type'=>'xsd:string') // NO - NIC du client de C'PRO - chaîne de caractères de longueur fixe 5 composée exclusivement de chiffres
    )
);
$server->wsdl->addComplexType(
    'lineFinancement',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'statut' => array('name'=>'statut','type'=>'xsd:string') // O - Statut du dossier - chaîne de caractères alphanumérique de 8 caractères max cf. tableau ci-dessous pour valeurs autorisées
	        												  // ATTENTE || ACCEPTE || REFUSE || AJOURNE || SANSUIAU || SANSUISA || ANNULE
        ,'commentaire_statut' => array('name'=>'commentaire_statut','type'=>'xsd:string') // NO - Commentaire additionnel sur le statut positionné sur le dossier - chaîne de caractères alphanumérique de 250 caractères max cf. tableau ci-dessous pour valeurs autorisées
        													// Rapprochez-vous de votre contact commercial || Rapprochez-vous de votre contact commercial || Attente retour client CAL&F || Délai de validité de l'accord dépassé || Dossier sans suite || Dossier annulé
        ,'num_dossier' => array('name'=>'num_dossier','type'=>'xsd:string') // O - Numéro de dossier CAL&F - chaîne de caractères alphanumérique de 13 caractères max
		,'date_demande_financement' => array('name'=>'date_demande_financement','type'=>'xsd:dateTime') // O - Date et heure de la demande de financement. - format YYYY-MM-DDThh:mm:ss
		,'date_reponse_financement' => array('name'=>'date_reponse_financement','type'=>'xsd:dateTime') // O - Date et heure de la réponse à la demande de financement. - format YYYY-MM-DDThh:mm:ss
    )
);
$server->wsdl->addComplexType(
    'TReponse',
    'complexType',
    'struct',
    'all',
    '',
    array(
    	'partenaire' => array('name'=>'ref_ext','type'=>'tns:linePartenaire','minOccurs' => '1','maxOccurs' => '1')
		,'client' => array('name'=>'client','type'=>'tns:lineClient','minOccurs' => '1','maxOccurs' => '1')
		,'financement' => array('name'=>'financement','type'=>'tns:lineFinancement','minOccurs' => '1','maxOccurs' => '1')
    )
);


// Register WSDL
$server->register(
    'repondreDemande',
    // Entry values
    array('authentication'=>'tns:authentication','TReponse'=>'tns:TReponse'),
    // Exit values
    array('result'=>'tns:result'),
    $ns,
    $ns.'#repondreDemande',
    $styledoc,
    $styleuse,
    'WS mise à jour du statut d\'une demande de financement'
);


function repondreDemande($authentication, $TReponse)
{
	global $db,$conf,$langs;

	dol_syslog("Function: repondreDemande login=".$authentication['login']);

	if ($authentication['entity']) $conf->entity=$authentication['entity'];

    // Init and check authentication
    $objectresp=array();
    $errorcode='';$errorlabel='';
    $error=0;
    $fuser=check_authentication($authentication,$error,$errorcode,$errorlabel);
    // Check parameters
	if (! $error && (empty($TReponse['partenaire']) || empty($TReponse['client']) || empty($TReponse['financement'])))
	{
		$error++;
		$errorcode='BAD_PARAMETERS'; $errorlabel="Indice 'partenaire' ou 'client' ou 'financement' manquant.";
	}

	if (! $error)
	{
		$fuser->getrights();

		// TODO appliquer le bon droit
		if ($fuser->rights->facture->lire)
		{
			// TODO faire le traitement pour maj du scoring cf. Voir avec Gauthier
			
			$objectresp = array('result'=>array('result_code'=>'OK', 'result_label'=>'Statut mis à jour'));
		}
		else
		{
			$error++;
			$errorcode='PERMISSION_DENIED'; $errorlabel='User does not have permission for this request';
		}
	}

	if ($error)
	{
		$objectresp = array('result'=>array('result_code' => $errorcode, 'result_label' => $errorlabel));
	}

	return $objectresp;
}

// Return the results.
$server->service(file_get_contents("php://input"));
