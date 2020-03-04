<?php
/**
 * SCORING POUR FRANFINANCE
 */

chdir(__DIR__);

define('INC_FROM_CRON_SCRIPT',true);
define('NOCSRFCHECK', true);

// This is to make Dolibarr working with Plesk
set_include_path($_SERVER['DOCUMENT_ROOT'].'/htdocs');

require('../../config.php');


require_once NUSOAP_PATH.'/nusoap.php';		// Include SOAP
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ws.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

// TODO inclure les class nécessaire pour le scoring
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

dol_syslog("WEBSERVICE CALL : start calling webservice FRANFINANCE", LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');

$langs->setDefaultLang('fr_FR');
$langs->load("main");

$authentication['login'] = $_SERVER['PHP_AUTH_USER'];
$authentication['password'] = $_SERVER['PHP_AUTH_PW'];
$authentication['dolibarrkey'] = $conf->global->WEBSERVICES_KEY;
$ResponseDemFin = file_get_contents("php://input");

print json_encode(DiffusionDemande($authentication, $ResponseDemFin));
function DiffusionDemande($authentication, $ResponseDemFin)
{

    global $db,$conf,$dolibarr_main_authentication,$langs;
    $dolibarr_main_authentication='dolibarr';

    $langs->load('financement@financement');

    if (is_string($ResponseDemFin))
    {
        $ResponseDemFin = json_decode($ResponseDemFin, true);
    }

    dol_syslog("1. WEBSERVICE DiffusionDemande called", LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
    dol_syslog("2. WEBSERVICE ResponseDemFin=".print_r($ResponseDemFin, true), LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
    dol_syslog("2. WEBSERVICE ResponseDemFin=".print_r($authentication, true), LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');

    dol_include_once('/financement/class/simulation.class.php');
    dol_include_once('/financement/class/score.class.php');
    dol_include_once('/financement/class/dossier.class.php');
    dol_include_once('/financement/class/dossier_integrale.class.php');
    dol_include_once('/financement/class/affaire.class.php');
    dol_include_once('/financement/class/grille.class.php');

    $error = 0;

    if (!empty($authentication['entity'])) $conf->entity=$authentication['entity'];

    $fuser=check_authentication($authentication,$error,$result_code,$result_label);
    if (!empty($error)) dol_syslog("WEBSERVICE error=".print_r($result_code, true), LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
    if (empty($error))
    {
        // traitement du retour de franfinance

        if (empty($fuser->rights)) $fuser->getrights();
        if (empty($fuser->array_options)) $fuser->fetch_optionals();

        $objectresp = array();

        $PDOdb = new TPDOdb;
        $suivi = new TSimulationSuivi;

        // validation de l'utilisateur
        if (empty($fuser->rights->financement->webservice->repondre_demande))
        {
            $error++;
            $result_code='PERMISSION_DENIED';
            $result_label='L\'utilisateur n\'a pas les permissions suffisantes pour cette requête';
            dol_syslog("WEBSERVICE error=".$result_code, LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
        }
        /*else if (empty($fuser->array_options['options_fk_leaser_webservice']))
        {
            $error++;
            $result_code='MISSING_CONFIGURATION';
            $result_label='Configuration du compte utilisateur manquante. Le compte n\'est pas associé à un leaser';
        }*/

        if (empty($error)) // user connecté et qualifié
        {
            $numdemande = $ResponseDemFin['numeroDemande'];
            $datedemande = $ResponseDemFin['datedemande'];
            $siren = $ResponseDemFin['numeroSIREN'];
            $montant = $ResponseDemFin['montant'];
            $duree = $ResponseDemFin['duree'];
            $codeDecision = $ResponseDemFin['codeDecision'];
            $commentaire = $ResponseDemFin['commentaireDecision'];
            $validite = $ResponseDemFin['dateValiditeDecision'];

            if (empty($numdemande))
            {
                $error++;
                $result_code = "MISSING_REQUEST_NUMBER";
                $result_label = "Numéro de demande non-fourni";
                dol_syslog("WEBSERVICE error=".$result_code, LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
            }
            elseif (empty($codeDecision) || !in_array($codeDecision,array('ACC', 'ASI', 'RFR', 'RFD', 'ANO')))
            {
                $error++;
                $result_code = "MISSING_CODE_DECISION";
                $result_label = "Code décision invalide";
                dol_syslog("WEBSERVICE error=".$result_code, LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
            }

            if (empty($error))
            {
                // récupération du suivi à partir du numéro de demande et du fk_leaser
                $TId = TRequeteCore::get_id_from_what_you_want($PDOdb, $suivi->get_table(), array('numero_accord_leaser'=>$numdemande));
                if (!empty($TId[0]))
                {
                    $suivi->load($PDOdb, $TId[0]);

                    if (!empty($suivi->commentaire)) $suivi->commentaire.= "\n";
                    $suivi->commentaire.= $commentaire;

                    switch ($codeDecision)
                    {
                        case 'ACC': // ok
                        case 'ASI': // ok
                            $suivi->doAction($PDOdb, $suivi->simulation, 'accepter');
                            break;

                        case 'RFR': // ko
                        case 'RFD': // ko
                            $suivi->doAction($PDOdb, $suivi->simulation, 'refuser');
                            break;

                        case 'ANO': // err
                            $suivi->doAction($PDOdb, $suivi->simulation, 'erreur');
                            break;
                    }

                    $result_code = 'SUCCESS';
                    $result_label = '"suivi leaser" mis à jour';

                }
                else
                {
                    $error++;
                    $result_code = "REQUEST_NUMBER_NOT_FOUND";
                    $result_label= 'Le numéro de demande fourni n\'a pas été trouvé';
                    dol_syslog("WEBSERVICE error=".$result_code, LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');
                }
            }
        }

    }
    else dol_syslog("WEBSERVICE FRANFINANCE error connecting user", LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');

    dol_syslog("WEBSERVICE Create DateTime=".$result_code, LOG_INFO, 0, '_EDI_SCORING_FRANFINANCE');
    $date = new DateTime();
    dol_syslog("WEBSERVICE Create2 DateTime=".$result_code, LOG_INFO, 0, '_EDI_SCORING_FRANFINANCE');

    $objectresp['date'] = $date->format('Y-m-d H:i:s');
    $objectresp['timezone'] = $date->getTimezone()->getName();
    $objectresp['result'] = array('result_code' => $result_code, 'result_label' => $result_label);

    dol_syslog("3. WEBSERVICE DiffusionDemande return = ".print_r($objectresp,true), LOG_ERR, 0, '_EDI_SCORING_FRANFINANCE');

    return $objectresp;
}
