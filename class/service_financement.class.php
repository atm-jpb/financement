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
	
	public $activate;
	public $production;
	
	public $wsdl;
	
	/**
	 * Construteur
	 * 
	 * @param $simulation		object TSimulation			Simulation concerné par la demande
	 * @param $simulationSuivi	object TSimulationSuivi		Ligne de suivi qui fait l'objet de la demande
	 */
	public function ServiceFinancement(&$simulation, &$simulationSuivi)
	{
		global $conf;
		
		$this->simulation = &$simulation;
		$this->simulationSuivi = &$simulationSuivi;
		
		$this->leaser = &$simulationSuivi->leaser;
		
		$this->TMsg = array();
		$this->TError = array();
		
		$this->debug = GETPOST('DEBUG');
		
		$this->activate = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVATE) ? true : false;
		$this->production = !empty($conf->global->FINANCEMENT_WEBSERVICE_ACTIVE_FOR_PROD) ? true : false;
	}
	
	/**
	 * Fonction call
	 * 
	 * Ce charge de faire l'appel au bon webservice en fonction du leaser
	 * 
	 * return false if KO, true if OK
	 */
	public function call()
	{
		global $langs,$conf;
		
		// Si les appels ne sont pas actives alors return true
		if (!$this->activate) return true;
		
		// TODO à revoir, peut être qu'un test sur code client ou mieux encore sur numéro SIRET
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
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CALF_PROD) ? $conf->global->FINANCEMENT_WSDL_CALF_PROD : 'https://archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CALF_RECETTE) ? $conf->global->FINANCEMENT_WSDL_CALF_RECETTE : 'https://hom-archipels.ca-lf.com/archplGN/';
		
		$TParam = $this->_getTParamLixxbail();
		
		if ($this->debug) var_dump('DEBUG :: Function callLixxbail(): leaser name = ['.$this->leaser->name.']', 'wsdl = '.$wsdl, 'TParam =v', $TParam);
		
		if (!empty($this->TError))
		{
			if ($this->debug) var_dump('DEBUG :: Function callLixxbail(): error catch =v', $this->TError);
			return false;
		}
		
		try {
			// TODO uncomment to call webservice
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
	
	public function getCodePeriodiciteFinancement($opt_periodicite)
	{
		global $langs;
		
		if (strcmp($this->leaser->name, 'LIXXBAIL') === 0)
		{
			/**
			 * Autre valeurs possible
			 * Code		Libellé
			 * B		BIMESTRIEL
			 * H		HEBDOMADAIRE
			 * J		JOURNALIER
			 * Q		QUADRIMESTRIEL
			 * X		SAISONNIER
			 * 9		INDETERMINE 
			 */	
			
			$TId = array(
				'ANNEE' => 'A'
				,'SEMESTRE' => 'S'
				,'TRIMESTRE' => 'T'
				,'MOIS' => 'M'
			);
		}
		
		if (empty($TId[$opt_periodicite]))
		{
			$this->TError[] = $langs->trans('webservice_financement_error_periodicite', $opt_mode_reglement);
			return false;
		}
		
		return $TId[$opt_periodicite];
	}
	
	/**
	 * TODO à construire
	 */
	public function getIdCategorieBien()
	{
		/*
		CAT_ID	LIB_CAT			NAT_ID	LIB_NAT								MRQ_ID	LIB_MRQ
		11		INFORMATIQUE	107		Micro ordinateur					16		GENERIQUE
		11		INFORMATIQUE	107		Micro ordinateur					2366	TOSHIBA
		11		INFORMATIQUE	113		Serveur vocal						16		GENERIQUE
		11		INFORMATIQUE	114		Station								16		GENERIQUE
		11		INFORMATIQUE	114		Station								1132	HEWLETT PACKARD
		11		INFORMATIQUE	114		Station								2366	TOSHIBA
		11		INFORMATIQUE	116		Traceur								16		GENERIQUE
		11		INFORMATIQUE	116		Traceur								556		CANON
		11		INFORMATIQUE	116		Traceur								1132	HEWLETT PACKARD
		11		INFORMATIQUE	117		Logiciels							16		GENERIQUE
		11		INFORMATIQUE	117		Logiciels							1132	HEWLETT PACKARD
		26		BUREAUTIQUE		663		Ensemble de matériels bureautique	16		GENERIQUE
		26		BUREAUTIQUE		663		Ensemble de matériels bureautique	556		CANON
		26		BUREAUTIQUE		663		Ensemble de matériels bureautique	1132	HEWLETT PACKARD
		26		BUREAUTIQUE		663		Ensemble de matériels bureautique	2059	RISO
		26		BUREAUTIQUE		663		Ensemble de matériels bureautique	2366	TOSHIBA
		26		BUREAUTIQUE		665		Photocopieur						16		GENERIQUE
		26		BUREAUTIQUE		665		Photocopieur						556		CANON
		26		BUREAUTIQUE		665		Photocopieur						1794	OCE
		26		BUREAUTIQUE		665		Photocopieur						2059	RISO
		26		BUREAUTIQUE		665		Photocopieur						2366	TOSHIBA
		*/
		
		return $this->simulation->fk_categorie_bien;
	}

	public function getIdNatureBien()
	{
		return $this->simulation->fk_nature_bien;
	}
	
	public function getIdMarqueBien()
	{
		/* Valeurs possibles
		16		GENERIQUE
		2366	TOSHIBA
		1132	HEWLETT PACKARD
		556		CANON
		2059	RISO
		1794	OCE
		*/
		$TCodeId = array(
			'CANON' => 556
			,'DELL' => ''
			,'KONICA MINOLTA' => ''
			,'KYOCERA' => ''
			,'LEXMARK' => ''
			,'HEWLETT-PACKARD' => 1132
			,'OCE' => 1794
			,'OKI' => ''
			,'SAMSUNG' => ''
			,'TOSHIBA' => 2366
		);
		
		if (!empty($TCodeId[$this->simulation->marque_materiel])) return $TCodeId[$this->simulation->marque_materiel];
		else return '';
	}
	
	/**
	 * TODO à construire
	 */
	public function getCodeProduit()
	{
		/*
		Code 	Libellé							Type	Libellé type produit
		produit	produit	 						produit	
		
		LOCF	Location						STAN	Standard
		LOCF	Location						CESS	Cession de contrat (sans prestation)
		LOCF	Location						LMAF	Location mandatée fichier
		LOA		Location avec Option d'Achat	PROF	LOA professionnelle
		*/
		
		return 'LOCF';
	}
	
	/**
	 * TODO à combiner avec $this->getCodeProduit()
	 */
	public function getTypeProduit()
	{
		return 'STAN';
	}
	
	/**
	 * Function to prepare data to send to Lixxbail
	 */
	private function _getTParamLixxbail()
	{
		global $mysoc;
		
		$mode_reglement_id = $this->getIdModeRglt($this->simulation->opt_mode_reglement);
		$periodicite_code = $this->getCodePeriodiciteFinancement($this->simulation->opt_periodicite);
		
		$pct_vr = $this->simulation->pct_vr;
		$mt_vr = $this->simulation->mt_vr;
		
		if (!empty($pct_vr) && !empty($mt_vr)) $pct_vr = ''; // Si les 2 sont renseignés alors je garde que le montant
		
		$TParam = array(
			'PARTENAIRE' => array( // 1..1
					'SIREN_PARTENAIRE' => $mysoc->idprof1 // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 9 *
					,'NIC_PARTENAIRE' => substr($mysoc->idprof2, -5, 5) // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 5 *
					,'COMMERCIAL_EMAIL' => $this->simulationSuivi->user->email // TODO vérifier si on doit prendre l'email du user associé à la simulation et non celui du suivi // format d'une adresse email *
					,'REF_EXT' => $this->simulation->reference // chaîne de caractères alphanumérique de 20 caractères max *
			)
			,'BIEN' => array( // 1..1
					'CATEGORIE_BIEN' => $this->getIdCategorieBien() // numérique entier sur 10 positions max. Cf. onglet 'Référentiel de biens C'PRO' *
					,'NATURE_BIEN' => $this->getIdNatureBien() // numérique entier sur 10 positions max. Cf. onglet 'Référentiel de biens C'PRO' *
					,'MARQUE_BIEN' => $this->getIdMarqueBien() // numérique entier sur 10 positions max. Cf. onglet 'Référentiel de biens C'PRO' *
					,'ANNEE_BIEN' => date('Y') // numérique entier sur 4 positions *
					,'ETAT_BIEN' => 'NEUF' // 'NEUF' OU 'OCCA' *
					,'QTE_BIEN' => 1 // numérique entier *
					,'MT_HT_BIEN' => $this->simulation->montant // numérique décimal (. comme séparateur décimal) *
					,'PAYS_DESTINATION_BIEN' => !empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR' // code ISO2 (2 positions). Pour France, 'FR'. *
					,'FOURNISSEUR_SIREN' => $mysoc->idprof1 // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 9 *
					,'FOURNISSEUR_NIC' => substr($mysoc->idprof2, -5, 5) // Toujours entité à partir de laquelle on score // numérique entier de longueur fixe 5 *
			)
			,'BIEN_COMPL' => array( // 1..n
				/*0 => array(
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
				)*/
			)
			,'CLIENT' => array( // 1..1
					'CLIENT_SIREN' => $mysoc->idprof1 // Toujours entité à partir de laquelle on score *
					,'CLIENT_NIC' => substr($mysoc->idprof2, -5, 5) // Toujours entité à partir de laquelle on score
			)
			,'FINANCEMENT' => array( // 1..1
					'CODE_PRODUIT' => $this->getCodeProduit() // chaîne de caractères alphanumérique de 8 caractères max. Cf. onglet 'Produit' *
					,'TYPE_PRODUIT' => $this->getTypeProduit() // chaîne de caractères alphanumérique de 8 caractères max. Cf. onglet 'Produit' *
					,'MT_FINANCEMENT_HT' => $this->simulation->montant // numérique décimal (. comme séparateur décimal) *
					,'PCT_VR' => $pct_vr // Doit être saisie par CPro - Pourcentage de la valeur résiduelle. L'élément est exclusif de l'élément MT_VR.
					,'MT_VR' => $mt_vr // Doit être saisie par CPro - Montant de la valeur résiduelle, en euros. L'élément est exclusif de l'élément PCT_VR.
					,'TYPE_REGLEMENT' => $mode_reglement_id // *
					,'MT_PREMIER_LOYER' => '' // NO
					,'DUREE_FINANCEMENT' => $this->simulation->duree // *
					,'PERIODICITE_FINANCEMENT' => $periodicite_code // chaîne de caractères alphanumérique de 3 caractères max. Cf. onglet 'Périodicité de financement' *
					,'TERME_FINANCEMENT' => $this->simulation->opt_terme == 1 ? 'A' : 'E' // 4 char. échu ou à échoir *
					,'NB_FRANCHISE' => '' // NO
					,'NATURE_FINANCEMENT' => 'STD' // NO - Voir si saisie par CPro
					,'DATE_DEMANDE_FINANCEMENT' => date('Y-m-dTH:i:s') // format YYYY-MM-DDThh:mm:ss *
			)
		);
		
		return $TParam;
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
	
	// TODO comment for prod
	public function createXmlFileOfParam()
	{
		$TParam = $this->_getTParamLixxbail();
		 
		$xml_data = new SimpleXMLElement('<?xml version="1.0"?><data></data>');
		self::array_to_xml($TParam,$xml_data);
		$result = $xml_data->asXML('/var/www/demande_de_financement.xml');
	}
	
	public static function array_to_xml( $data, &$xml_data ) {
	    foreach( $data as $key => $value ) {
	        if( is_array($value) ) {
	            if( is_numeric($key) ){
	                $key = 'item'.$key; //dealing with <0/>..<n/> issues
	            }
	            $subnode = $xml_data->addChild($key);
	            self::array_to_xml($value, $subnode);
	        } else {
	            $xml_data->addChild("$key",htmlspecialchars("$value"));
	        }
	     }
	}

} // End Class
