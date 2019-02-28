<?php

class WebServiceBnp extends WebService 
{
	/** @var string $local_cert */
	public $local_cert;
	
	/** @var TPDOdb $PDOdb */
	public $PDOdb;
	
	/** @var bool $update_status */
	public $update_status;


	public function __construct(&$simulation, &$simulationSuivi, $debug = false, $update_status=false)
	{
		global $conf;
		
		parent::__construct($simulation, $simulationSuivi, $debug);
		
		if (defined('BNP_TEST') && BNP_TEST) $this->production = false;
		
		// Production ou Test
		if ($this->production)
		{
			$this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_BNP_PROD) ? $conf->global->FINANCEMENT_WSDL_BNP_PROD : dol_buildpath('/financement/files/EDI_BNP_demandeFinancement_PROD.wsdl');
			$this->local_cert = "/usr/share/ca-certificates/extra/CPRO-BPLS-Prod.crt";
		}
		else
		{
			$this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_BNP_RECETTE) ? $conf->global->FINANCEMENT_WSDL_BNP_RECETTE : dol_buildpath('/financement/files/EDI_BNP_demandeFinancement_TEST.wsdl');
			$this->local_cert = "/usr/share/ca-certificates/extra/CPRO-BPLS-recette.crt";
		}
		
		$this->update_status = $update_status;
		$this->PDOdb = new TPDOdb;
	}
	
	public function run()
	{
		global $langs;
		
		if ($this->debug) var_dump('DEBUG :: Function callCMCIC(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; endpoint = '.$this->endpoint);
		
		$options = array(
			'local_cert'=> $this->local_cert
			,'trace'=>1
			,'stream_context' => stream_context_create(array(
				'ssl' => array(
					'verify_peer' => false,
					'allow_self_signed' => true
				)
			))	
		);
		
		try {
			$this->soapClient = new SoapClient($this->wsdl, $options);
			
//			var_dump($this->soapClient->__getFunctions());exit;
			dol_syslog("WEBSERVICE SENDING GRENKE : ".$this->simulation->reference, LOG_ERR, 0, '_EDI_GRENKE');
			
			if ($this->update_status)
			{
				$TconsulterSuivisDemandesRequest['consulterSuivisDemandesRequest'] = $this->_getBNPDataTabForConsultation();
				$response = $this->soapClient->__call('consulterSuivisDemandes',$TconsulterSuivisDemandesRequest);
			}
			else
			{
				$TtransmettreDemandeFinancementRequest = array();
				$TtransmettreDemandeFinancementRequest['transmettreDemandeFinancementRequest'] = $this->getXml();
				$response = $this->soapClient->__call('transmettreDemandeFinancement',$TtransmettreDemandeFinancementRequest);
			}
			
			// TODO : issue de la doc => Dans l’éventualité où l’utilisateur est invalide, un message d’erreur est envoyé au partenaire
			if ($this->debug)
			{
				$this->printDebugSoapCall($response);
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			if ($this->update_status)
			{
				$this->traiteBNPReponseSuivisDemande($response);
				return true;
			}
			else
			{
				if (!empty($response->numeroDemandeProvisoire))
				{
					$this->simulationSuivi->numero_accord_leaser = $response->numeroDemandeProvisoire;
					$this->simulationSuivi->commentaire = $langs->trans('ServiceFinancementCallDone');

					return true;
				}
				else
				{
					$this->simulationSuivi->commentaire = $langs->trans('ServiceFinancementWrongReturn');

					return false;
				}
			}
			
		} catch (SoapFault $e) {
			dol_syslog("WEBSERVICE ERROR : ".$e->getMessage(), LOG_ERR, 0, '_EDI_BNP');
			if ($this->debug) $this->printTrace($e); // exit fait dans la méthode

			$errorLabel = '';
			if (!empty($e->detail->retourErreur->erreur))
			{
				if(count($e->detail->retourErreur->erreur))
				{
					$errorLabel = 'ERREUR SCORING BNP : <br>';
					if(is_array($e->detail->retourErreur->erreur))
					{
						foreach($e->detail->retourErreur->erreur as $ObjError) $errorLabel .= $ObjError->message.'<br>';
					}
					else $errorLabel .= $e->detail->retourErreur->erreur->message;
				}
			}
			else //Erreur sur le formalisme envoyé
			{
				if(count($e->detail->ValidationError))
				{
					$errorLabel = 'ERREUR FORMAT SCORING BNP : <br>';
					if(is_array($e->detail->ValidationError))
					{
						foreach($e->detail->ValidationError as $error) $errorLabel .= $error.'<br>';
					}
					else $errorLabel .= $e->detail->ValidationError;
				}
			}

			if (!empty($this->simulationSuivi->commentaire)) $this->simulationSuivi->commentaire.= "\n";
			$this->simulationSuivi->commentaire = $errorLabel;
		}

		return false;
	}
	
	public function getXml()
	{
		global $db;
		$entity = new DaoMulticompany($db);
		$entity->fetch($this->simulation->entity);
		
		$TData = array();
		
		//Tableau Prescripteur
		$TPrescripteur = array(
			'prescripteurId' => $entity->array_options['options_code_prescripteur_bnp']
		);

		$TData['prescripteur'] = $TPrescripteur;
		$TData['numeroDemandePartenaire'] = $this->simulation->reference;
		//$TData['numeroDemandeProvisoire'] = '';
		$TData['codeFamilleMateriel'] = 'H'; //H = Bureautique OU T = Informatique, BUREAUTIQUE par défaut car score uniquement pour du bureautique
		
		//Tableau Client
		$TClient = $this->_getBNPDataTabClient();
		$TData['client'] = $TClient;
		
		//Tableau Matériel (Equipement)
		$TMateriel = $this->_getBNPDataTabMateriel();
		$TData['materiel'] = $TMateriel;
		
		//Tableau Financement
		$TFinancement = $this->_getBNPDataTabFinancement();
		$TData['financement'] = $TFinancement;
		
		/*$TPrestation = array(
			'prestation' => array(
				'codeTypePrestation' => ''
				,'montantPrestation' => ''
			)
		);
		$TData['Prestations'] = $TPrestation;*/

		//$TData['commentairesPartenaire'] = '';
		
		return $TData;
	}
	
	function _getBNPDataTabClient()
	{
		$typeClient = $this->simulation->getLabelCategorieClient();
		if($typeClient == "administration") $codeTypeClient = 3;
		elseif($typeClient == "entreprise") $codeTypeClient = 4;
		else $codeTypeClient = 0; //Général
		
		$siretCLIENT = $this->simulation->societe->idprof2;
		if(empty($siretCLIENT)) $siretCLIENT = $this->simulation->societe->idprof1;
		
		$TTrans = array(
			'  ' => ' ',
			'.' => '',
			"'" => '',
		);
		$nomCLIENT = strtr($this->simulation->societe->name, $TTrans);
		$nomCLIENT = substr($nomCLIENT, 0, 50);
		
		$TClient = array(
			'idNationnalEntreprise' => $siretCLIENT
			,'codeTypeClient' => $codeTypeClient
			,'codeFormeJuridique' => '5499' //TODO
			,'raisonSociale' => $nomCLIENT
			//,'specificiteClientPays' => array(
				//'specificiteClientFrance' => array(
					//'dirigeant' => array(
						//'codeCivilite' => ''
						//,'nom' => ''
						//,'prenom' => ''
						//,'dateNaissance' => ''
					//)
				//)
			//)
			,'adresse' => array(
				'adresse' => 'A'//substr(str_replace($arraySearch,$arrayToReplace,preg_replace("/\n|\ -\ |[\,\ ]{1}/", ' ', $this->simulation->societe->address)),0,31)
				//,'adresseComplement' => ''
				,'codePostal' => $this->simulation->societe->zip
				,'ville' => strtr($this->simulation->societe->town, $TTrans)
			)
		);
		
		return $TClient;
	}
	
	public function _getBNPDataTabMateriel(){
		
		$TCodeMarque = array(
			'CANON' => '335'
			,'DELL' => '344'
			,'KONICA MINOLTA' => '571'
			,'KYOCERA' => '347'
			,'LEXMARK' => '341'
			,'HEWLETT-PACKARD' => '321'
			,'HP' => '321'
			,'OCE' => '336'
			,'OKI' => '930'
			,'SAMSUNG' => 'F80'
			,'TOSHIBA' => '331'
		);
		
		if($this->simulation->entity == 2) { // info
			$codeMat = '30021204';
			$codeMarque = '321';
		} else if($this->simulation->entity == 3) { // telecom
			$codeMat = '322020';
			$codeMarque = 'D51';
		} else {
			$codeMat = '300121';
			$codeMarque = '335';
		}
		
		// Montant minimum 1000 €
		$montant = $this->simulation->montant;
		// Scoring par le montant leaser
		$montant += $this->simulationSuivi->surfact + $this->simulationSuivi->surfactplus;
		$montant = round($montant,2);
		if($montant < 1000) $montant = 1000;
		
		$TMateriel = array(
			'codeMateriel' => $codeMat //Photocopieur
			,'codeEtatMateriel' => 'N'
			,'prixDeVente' => $montant
			//,'prixTarif' => ''
			//,'anneeFabrication' => ''
			,'codeMarque' => $codeMarque //909 = Divers informatique, 910 = Divers bureautique
			//,'type' => ''
			//,'modele' => ''
			//,'dateDeMiseEnCirculation' => ''
			//,'nombreHeuresUtilisation' => ''
			//,'kilometrage' => ''
		);
		
		return $TMateriel;
	}
	
	function _getBNPDataTabFinancement()
	{
		$codeCommercial = '02'; //02 par défaut; 23 = Top Full; 2Q = Secteur Public
		$codeFinancier = '021';
		$codeTypeCalcul = 'L';
		
		// Durée et périodicité recalculée car Cession peut être en trimestre ou mois, mais mandaté uniquement en trimestre
		$periodicite = 'TRIMESTRE';
		if($this->_getBNPType() == 'CESSION') {
			if($this->simulation->opt_periodicite == 'MOIS') {
				$periodicite = 'MOIS';
			}
		}
		
		$fin_temp = new TFin_financement;
		$fin_temp->periodicite = $this->simulation->opt_periodicite;
		$p1 = $fin_temp->getiPeriode();
		$fin_temp->periodicite = $periodicite;
		$p2 = $fin_temp->getiPeriode();
		
		$duree = $this->simulation->duree / ($p2 / $p1);
		
		// Montant minimum 1000 €
		$montant = $this->simulation->montant;
		// Scoring par le montant leaser
		$montant += $this->simulationSuivi->surfact + $this->simulationSuivi->surfactplus;
		$montant = round($montant,2);
		if($montant < 1000) $montant = 1000;
		
		$TFinancement = array(
			'codeTypeCalcul' => $codeTypeCalcul
			,'typeFinancement' => array(
				'codeProduitFinancier' => $codeFinancier //021 = Location Financière ; 024 = Location mantadée
				,'codeProduitCommercial' => $codeCommercial 
			)
			,'codeBareme' => $this->_getBNPBareme()
			,'montantFinance' => $montant
			//,'codeTerme' => ''
			//,'valeurResiduelle' => array(
				//'montant'=> ''
				//,'pourcentage'=>''
				//,'periodicite'=>''
			//)
			//,'presenceFranchiseDeLoyer' => ''
			,'paliersDeLoyer' => array(
				'palierDeLoyer' => array(
					'nombreDeLoyers' => $duree
					,'periodicite' => $p2
					//,'montantLoyers' => ''
					//,'poidsDuPalier' => ''
				)
			)
		);
		
		return $TFinancement;
	}
	
	public function _getBNPType()
	{
		if(strpos($this->simulationSuivi->leaser->name, 'BNP PARIBAS LEASE GROUP MANDATE') !== false)
			return 'MANDATE';
		if(strpos($this->simulationSuivi->leaser->name, 'BNP PARIBAS LEASE GROUP') !== false)
			return 'CESSION';
	}
	
	//CF drive -> Barème pour webservice CPRO.xlsx
	public function _getBNPBareme()
	{
		$codeBareme = '';
		
		if($this->_getBNPType() == 'CESSION') {
			if($this->simulation->entity == 2) { // informatique
				$codeBareme = '00011681';
				if($this->simulation->opt_periodicite == 'MOIS') {
					$codeBareme = '00011680';
				}
			} else if($this->simulation->entity == 3) { // telecom
				$codeBareme = '00011684';
			} else {
				$codeBareme = (!$this->production) ? '00004048' : '00011657';
				if($this->simulation->opt_periodicite == 'MOIS') {
					$codeBareme = (!$this->production) ? '00004028' : '00011658';
				}
			}
		} else {
			if($this->simulation->entity == 3) { // telecom
				$codeBareme = '00013540';
			} else {
				$codeBareme = (!$this->production) ? '00004050' : '00006710';
			}
		}
		
		return $codeBareme;
	}

	
	function _getBNPDataTabForConsultation()
	{
		global $db;

		$num_accord_leaser = $this->simulationSuivi->numero_accord_leaser;
		
		$entity = new DaoMulticompany($db);
		$entity->fetch($this->simulationSuivi->entity);
		
		$TData = array();
		
		//Tableau Prescripteur
		$TPrescripteur = array(
			'prescripteurId' => $entity->array_options['options_code_prescripteur_bnp']
		);

		$TData['prescripteur'] = $TPrescripteur;
		
		$numDdeKey = (substr($num_accord_leaser,0,3) == '000') ? 'numeroDemandeProvisoire' : 'numeroDemandeDefinitif';
		
		//Tableau Numéro demande
		$TNumerosDemande = array(
			'numeroIdentifiantDemande' => array(
				$numDdeKey => $num_accord_leaser
			)
		);
		
		$TData['numerosDemande'] = $TNumerosDemande;
//		pre($TData,true);exit;
		
		//Tableau Rapport Suivi
		/*$TRapportSuivi = $this->_getBNPDataTabRapportSuivi();

		$TData['rapportSuivi'] = $TRapportSuivi;*/

		return $TData;
	}
	
	public function traiteBNPReponseSuivisDemande(&$TreponseSuivisDemandes)
	{
		//Statut spécifique retourné par BNP
		$TCodeStatut = array(
			'E1' => 'OK'
			,'E2' => 'KO'
			,'E3' => 'WAIT'
			,'E4' => 'SS'
			,'E5' => 'MEL'
		);
		
		$simulation = new TSimulation;
		$simulation->load($this->PDOdb, $this->simulationSuivi->fk_simulation);
		
		$suiviDemande = $TreponseSuivisDemandes->rapportSuivi->suiviDemande;
		
		if ($suiviDemande->numeroDemandeProvisoire == $this->simulationSuivi->numero_accord_leaser || $suiviDemande->numeroDemandeDefinitif == $this->simulationSuivi->numero_accord_leaser)
		{
			if (!empty($suiviDemande->numeroDemandeDefinitif)) { // Tant que l'on a pas de numéro définitif de demande on ne fait rien
				$this->simulationSuivi->statut = $TCodeStatut[$suiviDemande->etat->codeStatutDemande];
				$this->simulationSuivi->commentaire = $suiviDemande->etat->libelleStatutDemande;
				$this->simulationSuivi->numero_accord_leaser = $suiviDemande->numeroDemandeDefinitif;
			
				switch ($this->simulationSuivi->statut) {
					case 'OK':
						$this->simulationSuivi->coeff_leaser = ($suiviDemande->financement->montantLoyerPrincial / $suiviDemande->financement->montantFinance) * 100;
						if ($simulation->accord != 'OK') $this->simulationSuivi->doActionAccepter($this->PDOdb,$simulation);
						break;
					case 'KO':
						if ($simulation->accord != 'KO') $this->simulationSuivi->doActionRefuser($this->PDOdb,$simulation);
						break;
					default:
						$this->simulationSuivi->save($this->PDOdb);
						break;
				}
			}
		}
	}
}
