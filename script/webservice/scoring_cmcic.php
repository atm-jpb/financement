<?php
/**
 * SCORING POUR CMCIC
 */

chdir(__DIR__);

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

dol_syslog("WEBSERVICE CALL : start calling webservice", LOG_ERR, 0, '_EDI_SCORING_CMCIC');

$langs->setDefaultLang('fr_FR');
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
        'client_siren' => array('name'=>'client_siren','type'=>'xsd:string') // O - Numéro SIREN du client de C'PRO - numérique entier de longueur fixe 9
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
	'ReturnRespDemFinRequest',
	array('authentication'=>'tns:authentication','ResponseDemFinShort'=>'tns:ResponseDemFinShort','ResponseDemFinComplete'=>'tns:ResponseDemFinComplete'),
	array('result'=>'tns:result','date'=>'xsd:dateTime','timezone'=>'xsd:string'),
	$ns,
    $ns.'#ReturnRespDemFin',
    $styledoc,
    $styleuse,
    'WS retour de ReturnRespDemFinRequest'
);


function ReturnRespDemFinRequest($authentication, $ResponseDemFinShort, $ResponseDemFinComplete)
{
	global $db,$conf,$dolibarr_main_authentication,$langs;
	$dolibarr_main_authentication='dolibarr';

	$langs->load('financement@financement');
	
	dol_syslog("1. WEBSERVICE ReturnRespDemFinRequest called", LOG_ERR, 0, '_EDI_SCORING_CMCIC');
	dol_syslog("2. WEBSERVICE ResponseDemFinShort=".print_r($ResponseDemFinShort, true), LOG_ERR, 0, '_EDI_SCORING_CMCIC');
	
	dol_include_once('/financement/class/simulation.class.php');
	dol_include_once('/financement/class/score.class.php');
	dol_include_once('/financement/class/dossier.class.php');
	dol_include_once('/financement/class/dossier_integrale.class.php');
	dol_include_once('/financement/class/affaire.class.php');
	dol_include_once('/financement/class/grille.class.php');

	$error = 0;
	
	if (!empty($authentication['entity'])) $conf->entity=$authentication['entity'];
	
	$fuser=check_authentication($authentication,$error,$result_code,$result_label);
	if (empty($error))
	{
		if (empty($fuser->rights)) $fuser->getrights();
		if (empty($fuser->array_options)) $fuser->fetch_optionals();
		
		$objectresp = array();

		$PDOdb = new TPDOdb;
		$simulation = new TSimulation;
		
		if (empty($fuser->rights->financement->webservice->repondre_demande))
		{
			$error++;
			$result_code='PERMISSION_DENIED';
			$result_label='L\'utilisateur n\'a pas les permissions suffisantes pour cette requête';
		}
		else if (empty($fuser->array_options['options_fk_leaser_webservice']))
		{
			$error++;
			$result_code='MISSING_CONFIGURATION';
			$result_label='Configuration du compte utilisateur manquante. Le compte n\'est pas associé à un leaser';
		}

		
		if (empty($error))
		{
			$ref_simulation = $ResponseDemFinShort['Rep_Statut_B2B']['B2B_INF_EXT'];
			//$ref_simulation = $ResponseDemFinComplete['REP_Demande']['B2B_REF_EXT'];
					
			$TId = TRequeteCore::get_id_from_what_you_want($PDOdb, $simulation->get_table(), array('reference'=>$ref_simulation));
			if (!empty($TId[0]))
			{
				$simulation->load($PDOdb, $TId[0]);

				$found = false;
				foreach ($simulation->TSimulationSuivi as &$simulationSuivi)
				{
					if ($simulationSuivi->leaser->array_options['options_edi_leaser'] == 'CMCIC')
//					if ($simulationSuivi->leaser->array_options['options_edi_leaser'] == $fuser->array_options['options_fk_leaser_webservice'])
					{
						$found = true;
						break;
					}
				}

				if ($found)
				{
					
					if ($ResponseDemFinShort['Rep_Statut_B2B']['B2B_CDRET'] != 0)
					{
						$error++;
						$result_code='B2B_CDRET';
						$result_label='Prise en compte du code d\'erreur';
						
						if (!empty($simulationSuivi->commentaire)) $simulationSuivi->commentaire.= "\n";
						$simulationSuivi->commentaire.= '['.$ResponseDemFinShort['Rep_Statut_B2B']['B2B_CDRET'].'] '.$langs->trans($ResponseDemFinShort['Rep_Statut_B2B']['B2B_MSGRET']);
						
						$simulationSuivi->doAction($PDOdb, $simulation, 'erreur');
					}
					
					
					if (empty($error))
					{
						$statut = $ResponseDemFinComplete['Decision_Demande']['B2B_CD_STATUT'];
						dol_syslog('2.1 $ResponseDemFinComplete[Decision_Demande][B2B_CD_STATUT]='.$statut, LOG_ERR, 0, '_EDI_SCORING_CMCIC');
						
						// TODO à corriger car le calcul est faux
						$coeff = $ResponseDemFinComplete['Infos_Financieres']['B2B_MT_LOYER'] * 100 / $ResponseDemFinComplete['Infos_Financieres']['B2B_MT_DEMANDE'];
						dol_syslog('2.2 $coeff='.$coeff, LOG_ERR, 0, '_EDI_SCORING_CMCIC');
						
						if (!empty($simulationSuivi->commentaire)) $simulationSuivi->commentaire.= "\n";
						$simulationSuivi->commentaire.= $langs->trans($ResponseDemFinComplete['Decision_Demande']['B2B_CD_STATUT']);
						if(!empty($ResponseDemFinComplete['Infos_Statut']['B2B_INFOS_STATUT'])) {
							$simulationSuivi->commentaire.= ' : ' . $ResponseDemFinComplete['Infos_Statut']['B2B_INFOS_STATUT'];
						}
						$simulationSuivi->coeff_leaser = $coeff;
						$simulationSuivi->numero_accord_leaser = $ResponseDemFinComplete['REP_Demande']['B2B_NODEF'];
						
						dol_syslog('2.3 $ResponseDemFinComplete[Decision_Demande][B2B_CD_STATUT]='.$ResponseDemFinComplete['Decision_Demande']['B2B_CD_STATUT'], LOG_ERR, 0, '_EDI_SCORING_CMCIC');
						dol_syslog('2.3 $ResponseDemFinComplete[Decision_Demande][B2B_CD_STATUT]='.$langs->trans($ResponseDemFinComplete['Decision_Demande']['B2B_CD_STATUT']), LOG_ERR, 0, '_EDI_SCORING_CMCIC');
						

						if ($statut == 'Status.APPROVED') $simulationSuivi->doAction($PDOdb, $simulation, 'accepter');
						else if ($statut == 'Status.REJECTED') $simulationSuivi->doAction($PDOdb, $simulation, 'refuser');
						else $simulationSuivi->save($PDOdb);
						
						if (!empty($ResponseDemFinComplete['REP_AccordPDF_B2B']))
						{
							$dir = $simulation->getFilePath();
							$subdir = '/'.$simulationSuivi->leaser->array_options['options_edi_leaser'];
							dol_mkdir($dir.$subdir);
							if (file_exists($dir.$subdir))
							{
								// TODO changer le nom car ne doit pas être visible avec le PDF de la simulation
								$pdf_decoded = base64_decode($ResponseDemFinComplete['REP_AccordPDF_B2B']);
								$pdf = fopen($dir.$subdir.'/'.dol_sanitizeFileName($simulation->reference).'_minerva.pdf', 'w');
								fwrite($pdf, $pdf_decoded);
								fclose($pdf);
							}
						}

						$result_code = 'SUCCESS';
						$result_label = '"suivi leaser" mis à jour';
					}
					
	
				}
				else
				{
					$error++;
					$result_code = 'ERROR_SUIVI_NOT_FOUND';
					$result_label = 'Dossier trouvé mais aucun "suivi leaser" CMCIC';
				}
			}
			else
			{
				$error++;
				$result_code = 'NUM_DOSSIER_UNKNOWN';
				$result_label = 'Référence dossier inconnu';
			}
		}
	}
	
	$date = new DateTime();
	$objectresp['date'] = $date->format('Y-m-d H:i:s');
	$objectresp['timezone'] = $date->getTimezone()->getName();
	$objectresp['result'] = array('result_code' => $result_code, 'result_label' => $result_label);
	
	dol_syslog("3. WEBSERVICE ReturnRespDemFinRequest return = ".print_r($objectresp,true), LOG_ERR, 0, '_EDI_SCORING_CMCIC');
	
	return $objectresp;
}

// Return the results.
$server->service(file_get_contents("php://input"));
