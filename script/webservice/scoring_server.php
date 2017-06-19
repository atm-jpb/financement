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
        ,'coeff_dossier' => array('name'=>'coeff_dossier','type'=>'xsd:double') // pourcentage au format numérique décimal (. comme séparateur décimal)
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
    	'partenaire' => array('name'=>'partenaire','type'=>'tns:linePartenaire','minOccurs' => '1','maxOccurs' => '1')
		,'client' => array('name'=>'client','type'=>'tns:lineClient','minOccurs' => '1','maxOccurs' => '1')
		,'financement' => array('name'=>'financement','type'=>'tns:lineFinancement','minOccurs' => '1','maxOccurs' => '1')
    )
);

// 5 styles: RPC/encoded, RPC/literal, Document/encoded (not WS-I compliant), Document/literal, Document/literal wrapped
// Style merely dictates how to translate a WSDL binding to a SOAP message. Nothing more. You can use either style with any programming model.
// http://www.ibm.com/developerworks/webservices/library/ws-whichwsdl/
$styledoc='rpc';       // rpc/document (document is an extend into SOAP 1.0 to support unstructured messages)
$styleuse='encoded';   // encoded/literal/literal wrapped
// Better choice is document/literal wrapped but literal wrapped not supported by nusoap.

// Register WSDL
$server->register(
    'repondreDemande',
    // Entry values
    array('authentication'=>'tns:authentication','TReponse'=>'tns:TReponse'),
    // Exit values
    array('result'=>'tns:result','date'=>'xsd:dateTime','timezone'=>'xsd:string'),
    $ns,
    $ns.'#repondreDemande',
    $styledoc,
    $styleuse,
    'WS retour suite à une réponse de demande de financement'
);


function repondreDemande($authentication, $TReponse)
{
	global $db,$conf,$langs;

	dol_syslog("WEBSERVICE ".date('Y-m-d H:i:s')." Function: repondreDemande login=".$authentication['login']);
	//dol_syslog("WEBSERVICE AUTH TAB : ".print_r($authentification,true), LOG_ERR);
	dol_syslog("WEBSERVICE REP TAB : ".print_r($TReponse,true), LOG_ERR);

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
		$errorcode='BAD_PARAMETERS'; $errorlabel="Indice 'partenaire' ou 'client' ou 'financement' manquant";
	}

	if (! $error)
	{
		if (empty($fuser->rights)) $fuser->getrights();

		if (!empty($fuser->rights->financement->webservice->repondre_demande))
		{
			$fuser->fetch_optionals();
			if (!empty($fuser->array_options['options_fk_leaser_webservice']))
			{
				dol_include_once('/financement/class/simulation.class.php');
				dol_include_once('/financement/class/score.class.php');
				dol_include_once('/financement/class/dossier.class.php');
				dol_include_once('/financement/class/dossier_integrale.class.php');
				dol_include_once('/financement/class/affaire.class.php');
				dol_include_once('/financement/class/grille.class.php');
	
				$PDOdb = new TPDOdb;
				$simulation = new TSimulation;
				$reference_simulation = $TReponse['partenaire']['ref_ext'];
				
				$TId = TRequeteCore::get_id_from_what_you_want($PDOdb, $simulation->get_table(), array('reference'=>$reference_simulation));
				if (!empty($TId[0]))
				{
					$simulation->load($PDOdb, $db, $TId[0]);
					if ($simulation->getId() > 0)
					{
						if (strcmp($simulation->societe->idprof1, $TReponse['client']['client_siren']) === 0)
						{
							
							$found = false;
							foreach ($simulation->TSimulationSuivi as &$simulationSuivi)
							{
								if ($simulationSuivi->leaser->array_options['options_edi_leaser'] == $fuser->array_options['options_fk_leaser_webservice'])
								{
									$found = true;
									break;
								}
							}
							
							if ($found)
							{
								$statut = $TReponse['financement']['statut'];
								$commentaire = $TReponse['financement']['commentaire_statut'];
								$numero_accord = $TReponse['financement']['num_dossier'];
								$coeff = $TReponse['financement']['coeff_dossier'];
								
								$action = _getAction($fuser, $statut); // return accepter || refuser || attente
								if ($action != 'attente')
								{
									$simulationSuivi->doAction($PDOdb, $simulation, $action);
								}
								
								$Tab = array('commentaire'=>$commentaire, 'numero_accord_leaser'=>$numero_accord, 'coeff_leaser'=>$coeff);
								$simulationSuivi->set_values($Tab);
								$simulationSuivi->save($PDOdb);
								
								$extra_label = '';
								if ($action == 'accepter') $extra_label = ' Demande enregistrée comme étant "Acceptée"';
								elseif ($action == 'refuser') $extra_label = ' Demande enregistrée comme étant "Refusée"';
								elseif ($action == 'attente') $extra_label = ' Demande enregistrée comme étant en "Attente"';
								
								$objectresp = array('result'=>array('result_code'=>'OK', 'result_label'=>'Statut mis à jour.'.$extra_label));
							}
							else
							{
								$error++;
								$errorcode='ERROR_SUIVI_NOT_FOUND'; $errorlabel='Impossible de répondre à la demande, car non trouvée dans le suivi leaser simulation';
							}
						}
						else
						{
							$error++;
							$errorcode='CLIENT_SIREN_NOT_EQUAL'; $errorlabel='Le numéro SIREN du client associé au dossier est différent de celui fournis';
						}
					}
					else
					{
						$error++;
						$errorcode='NUM_DOSSIER_NOT_FOUND'; $errorlabel='La référence dossier ne correspond à aucune simulation';
					}
				}
				else
				{
					$error++;
					$errorcode='NUM_DOSSIER_EMPTY'; $errorlabel='Aucune référence communiquée';
				}
				
			}
			else
			{
				$error++;
				$errorcode='MISSING_CONFIGURATION'; $errorlabel='Configuration du compte utilisateur manquante. Le compte n\'est pas associé à un leaser';
			}
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

	$date = new DateTime();
	$objectresp['date'] = $date->format('Y-m-d H:i:s');
	$objectresp['timezone'] = $date->getTimezone()->getName();
	
	dol_syslog("WEBSERVICE RESULT TAB : ".print_r($objectresp,true), LOG_ERR);

	return $objectresp;
}

function _getAction(&$fuser, $statut)
{
	$action = 'attente';
	
	// TODO à faire évoluer si l'utilisateur du webservice change et n'est plus uniquement CAL&F (Lixxbail)
	switch (strtolower($statut)) {
		case 'etude':
			$action = 'attente';
			break;
			
		case 'accepte':
			$action = 'accepter';
			break;
			
		case 'refuse':
			$action = 'refuser';
			break;
			
		case 'errtech':
		case 'errcomm':
		case 'errbareme':
		case 'errassbien':
		case 'errinfcont':
		case 'errloyer':
		case 'errfonc':
			$action = 'erreur';
			break;
	}
	
	return $action;
}

// Return the results.
$server->service(file_get_contents("php://input"));
