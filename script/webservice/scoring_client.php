<?php

define('INC_FROM_CRON_SCRIPT',true);

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require('../../config.php');


require_once NUSOAP_PATH.'/nusoap.php';		// Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

global $mysoc;
dol_include_once('/financement/class/simulation.class.php');
				dol_include_once('/financement/class/score.class.php');
				dol_include_once('/financement/class/dossier.class.php');
				dol_include_once('/financement/class/dossier_integrale.class.php');
				dol_include_once('/financement/class/affaire.class.php');
				dol_include_once('/financement/class/grille.class.php');
	
$PDOdb = new TPDOdb;

$simulation = new TSimulation;
$simulation->load($PDOdb, $db, 6164);
$simulationSuivi = new TSimulationSuivi;


var_dump($simulation);
exit;

$TParam = array(
	'PARTENAIRE' => array( // 1..1
		0 => array(
			'SIREN_PARTENAIRE' => $mysoc->idprof1
			,'NIC_PARTENAIRE' => $mysoc->tva_intra
			,'COMMERCIAL_EMAIL' => $simulationSuivi->user->email // TODO vérifier si on doit prendre l'email du user associé à la simulation et non celui du suivi
			,'REF_EXT' => $simulation->reference
		)
	)
	,'BIEN' => array( // 1..1
		0 => array(
			'CATEGORIE_BIEN' => ''
			,'NATURE_BIEN' => $simulation->type_materiel
			,'MARQUE_BIEN' => $simulation->marque_materiel
			,'ANNEE_BIEN' => ''
			,'ETAT_BIEN' => ''
			,'QTE_BIEN' => ''
			,'MT_HT_BIEN' => $simulation->montant
			,'PAYS_DESTINATION_BIEN' => !empty($simulation->societe->country_code) ? $simulation->societe->country_code : 'FR'
			,'FOURNISSEUR_SIREN' => ''
		)
	)
	,'BIEN_COMPL' => array( // 1..n
		0 => array(
			'CATEGORIE_BIEN_COMPL' => '' // NO
			,'NATURE_BIEN_COMPL' => '' // NO
			,'MARQUE_BIEN_COMPL' => '' // NO
			,'ANNEE_BIEN_COMPL' => '' // NO
			,'ETAT_BIEN_COMPL' => '' // NO
			,'MT_HT_BIEN_COMPL' => '' // NO
			,'QTE_BIEN_COMPL' => '' // NO
		)
		,1 => array(
			'CATEGORIE_BIEN_COMPL' => ''
			,'NATURE_BIEN_COMPL' => ''
			,'MARQUE_BIEN_COMPL' => ''
			,'ANNEE_BIEN_COMPL' => ''
			,'ETAT_BIEN_COMPL' => ''
			,'MT_HT_BIEN_COMPL' => ''
			,'QTE_BIEN_COMPL' => ''
		)
	)
	,'CLIENT' => array( // 1..1
		0 => array(
			'CLIENT_SIREN' => $simulation->societe->idprof1
			,'CLIENT_NIC' => $simulation->societe->tva_intra // NO
		)
	)
	,'FINANCEMENT' => array( // 1..1
		0 => array(
			'CODE_PRODUIT' => ''
			,'TYPE_PRODUIT' => ''
			,'MT_FINANCEMENT_HT' => ''
			,'PCT_VR' => ''
			,'MT_VR' => ''
			,'TYPE_REGLEMENT' => $simulation->opt_mode_reglement
			,'MT_PREMIER_LOYER' => '' // NO
			,'DUREE_FINANCEMENT' => $simulation->duree
			,'PERIODICITE_FINANCEMENT' => $simulation->opt_periodicite
			,'TERME_FINANCEMENT' => $simulation->opt_terme == 1 ? 'AECH' : 'ECHU' // 4 char. échu ou à échoir
			,'NB_FRANCHISE' => '' // NO
			,'NATURE_FINANCEMENT' => $simulation->type_financement
			,'DATE_DEMANDE_FINANCEMENT' => date('Y-m-d H:i:s')
		)
	)
);



/*
$wsdl = dol_buildpath('/financement/files/demandeFinancement.wsdl', 2);
$params = array(
	'local_cert'=>"/usr/share/ca-certificates/extra/CPRO-BPLS-recette.crt"
	,'trace'=>1
	,'stream_context' => stream_context_create(array(
		    'ssl' => array(
		        'verify_peer' => false,
		        'allow_self_signed' => true
			)
	))
);

$client = new SoapClient($wsdl, $params);
*/


/*
$result = $client->call('demandeFinancement', $TParam);

if ($result['result']['result_code'] != 'OK')
{
	echo $result['result']['result_code'].'<br />'.$result['result']['result_label']; 
	exit;
}
else
{
	$invoice = $result['invoice'];
}
// on affiche la requete
//print($client->request);
*/

/*

if(BNP_TEST){
			$soapWSDL = dol_buildpath('/financement/files/demandeFinancement.wsdl',2);
		}
		else{
			$soapWSDL = BNP_WSDL_URL; // https://leaseoffersu.leasingsolutions.bnpparibas.fr:4444/ExtranetEuroWS/services/demandeDeFinancementService/demandeDeFinancementWSDLv1.wsdl
		}

		try{
			$soap = new SoapClient($soapWSDL,array(
									'local_cert'=>"/usr/share/ca-certificates/extra/CPRO-BPLS-recette.crt"
									,'trace'=>1
									,'stream_context' => stream_context_create(array(
										    'ssl' => array(
										        'verify_peer' => false,
										        'allow_self_signed' => true
										    )
										))						
			));

		}
		catch(SoapFault $e) {
			var_dump($e);
			exit;
		}*/
