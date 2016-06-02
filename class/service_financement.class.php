<?php

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

class ServiceFinancement {
	
	public $simulation;
	public $simulationSuivi;
	
	public $leaser;
	
	public $TMsg;
	public $TError;
	
	public $soapClient;
	public $result;
	
	public $debug;
	
	public $wsdl;
	
	public function ServiceFinancement(&$simulation, &$simulationSuivi)
	{
		$this->simulation = &$simulation;
		$this->simulationSuivi = &$simulationSuivi;
		
		$this->leaser = &$simulationSuivi->leaser;
		
		$this->TMsg = array();
		$this->TError = array();
		
		$this->debug = GETPOST('DEBUG');
	}
	
	public function call()
	{
		if (strcmp($this->leaser->name, 'LIXXBAIL') === 0)
		{
			return $this->callLixxbail();
		}
		
		if ($this->debug) var_dump('DEBUG :: Function call(): leaser name = ['.$this->leaser->name.'] # aucun traitement prévu');
		
		return false;
	}
	
	public function callLixxbail()
	{
		global $langs;
		
		$this->wsdl = '';
		$TParam = $this->_getTParamLixxbail();
		
		if ($this->debug) var_dump('DEBUG :: Function callLixxbail(): leaser name = ['.$this->leaser->name.']', 'wsdl = '.$wsdl, 'TParam =v', $TParam);
		
		if (!empty($this->TError))
		{
			if ($this->debug) var_dump('DEBUG :: Function callLixxbail(): error catch =v', $this->TError);
			return false;
		}
		
		try {
			// $this->soapClient = new nusoap_client($this->wsdl);
			// $this->result = $this->soapClient->call('CreateDemFin', $TParam);
			// on affiche la requete
			// print($this->soapClient->request);

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			return true;
		} catch (SoapFault $e) {
			var_dump($e);
			exit;
		}
	}
	
	public function getIdModeRglt($opt_mode_reglement)
	{
		global $langs;
		
		$TId = array();
		if (strcmp($this->leaser->name, 'LIXXBAIL') === 0)
		{
			$TId = array(
				'CHQ' => 1
				,'PRE' => 2
				//,'MDT' => 0 // Non géré pas cal&f
				//,'VIR' => 0 // Non géré pas cal&f
			);	
		}
		
		if (empty($TId[$opt_mode_reglement]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_mode_reglement', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_mode_reglement];
	}
	
	public function getIdPeriodiciteFinancement($opt_periodicite)
	{
		global $langs;
		
		if (strcmp($this->leaser->name, 'LIXXBAIL') === 0)
		{
			$TId = array(
				'ANNEE' => '1'
				,'SEMESTRE' => '2'
				,'TRIMESTRE' => '4'
				//,'BIMESTRIEL' => '6' // Non utilisé dans financement
				,'MOIS' => '12'
			);
		}
		
		if (empty($TId[$opt_periodicite]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_periodicite', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_periodicite];
	}
	
	private function _getTParamLixxbail()
	{
		global $mysoc;
		
		$mode_reglement_id = $this->getIdModeRglt($this->simulation->opt_mode_reglement);
		$periodicite_id = $this->getIdPeriodiciteFinancement($this->simulation->opt_periodicite);
		
		$TParam = array(
			'PARTENAIRE' => array( // 1..1
				0 => array(
					'SIREN_PARTENAIRE' => $mysoc->idprof1 // Partenaire = CPRO
					,'NIC_PARTENAIRE' => $mysoc->idprof2 // Partenaire = CPRO
					,'COMMERCIAL_EMAIL' => $this->simulationSuivi->user->email // TODO vérifier si on doit prendre l'email du user associé à la simulation et non celui du suivi
					,'REF_EXT' => $this->simulation->reference
				)
			)
			,'BIEN' => array( // 1..1
				0 => array(
					'CATEGORIE_BIEN' => '' // *
					,'NATURE_BIEN' => '' // *
					,'MARQUE_BIEN' => '' // *
					,'ANNEE_BIEN' => date('Y')
					,'ETAT_BIEN' => 'NEUF'
					,'QTE_BIEN' => 1
					,'MT_HT_BIEN' => $this->simulation->montant
					,'PAYS_DESTINATION_BIEN' => !empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR'
					,'FOURNISSEUR_SIREN' => $mysoc->idprof1 // Toujours CPRO
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
					'CLIENT_SIREN' => $mysoc->idprof1 // Toujours CPRO
					,'CLIENT_NIC' => $mysoc->idprof2 // Toujours CPRO
				)
			)
			,'FINANCEMENT' => array( // 1..1
				0 => array(
					'CODE_PRODUIT' => ''
					,'TYPE_PRODUIT' => ''
					,'MT_FINANCEMENT_HT' => ''
					,'PCT_VR' => ''
					,'MT_VR' => ''
					,'TYPE_REGLEMENT' => $mode_reglement_id
					,'MT_PREMIER_LOYER' => '' // NO
					,'DUREE_FINANCEMENT' => $this->simulation->duree
					,'PERIODICITE_FINANCEMENT' => $periodicite_id
					,'TERME_FINANCEMENT' => $this->simulation->opt_terme == 1 ? 'A' : 'E' // 4 char. échu ou à échoir
					,'NB_FRANCHISE' => '' // NO
					,'NATURE_FINANCEMENT' => 'STD'
					,'DATE_DEMANDE_FINANCEMENT' => date('Y-m-d H:i:s')
				)
			)
		);
	}


	// TODO à mettre en commentaire ou à supprimer pour la prod (actuellement utilisé pas le fichier scoring_client.php qui fait appel à notre propres webservice)
	public function callTest(&$authentication, &$TParam)
	{
		try {
			$ns='http://'.$_SERVER['HTTP_HOST'].'/ns/';
			
			$this->soapClient = new nusoap_client($this->wsdl/*, $params_connection*/);
			$this->result = $this->soapClient->call('repondreDemande', array('authentication'=>$authentication, 'TParam' => $TParam), $ns, '');
			
			return true;
		} catch (SoapFault $e) {
			var_dump($e);
			exit;
		}
	}

} // End Class
