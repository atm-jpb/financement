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
	
	private function printHeader()
	{
		header("Content-type: text/html; charset=utf8");
		print '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">'."\n";
		echo '<html>'."\n";
		echo '<head>';
		echo '<title>WebService Test: callTest</title>';
		echo '</head>'."\n";
		
		echo '<body>'."\n";
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
		
		if ($this->debug)
		{
			$this->printHeader();
			var_dump('DEBUG :: Function call(): leaser name = ['.$this->leaser->name.']');
		}
		
		// Si les appels ne sont pas actives alors return true
		if (!$this->activate)
		{
			if ($this->debug) var_dump('DEBUG :: Function call(): # appel webservice non actif');
			return true;
		}
		
		// TODO à revoir, peut être qu'un test sur code client ou mieux encore sur numéro SIRET
		if (strcmp($this->leaser->name, 'LIXXBAIL') === 0)
		{
			return $this->callLixxbail();
		}
		
		if ($this->debug) var_dump('DEBUG :: Function call(): # aucun traitement prévu');
		
		return false;
	}
	
	public function callLixxbail()
	{
		global $langs;
		
		// Production ou Test
		if ($this->production) $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CALF_PROD) ? $conf->global->FINANCEMENT_WSDL_CALF_PROD : 'https://archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1';
		else $this->wsdl = !empty($conf->global->FINANCEMENT_WSDL_CALF_RECETTE) ? $conf->global->FINANCEMENT_WSDL_CALF_RECETTE : 'https://hom-archipels.ca-lf.com/archplGN/ws/DemandeCreationLeasingGNV1';
		
		// TODO remove si on utilise bien la methode ->setHeaders();
		$this->target_ns = 'http://referentiel.ca.fr/Services/calf/DemandeCreationLeasingGN/V1/';
		
		if ($this->debug) var_dump('DEBUG :: Function callLixxbail(): Production = '.json_encode($this->production).' ; WSDL = '.$this->wsdl.' ; namespace = '.$this->target_ns);
		
		$TParam = $this->_getTParamLixxbail();
		
		if ($this->debug) var_dump('DEBUG :: TParam =v', $TParam);
		
		if (!empty($this->TError))
		{
			if ($this->debug) var_dump('DEBUG :: error catch =v', $this->TError);
			return false;
		}
		
		$string_xml_header = $this->getHeaderLixxbail();
		
		//echo '<pre>'.htmlspecialchars($string_xml_header).'</pre>';exit;
		try {
			$this->soapClient = new nusoap_client($this->wsdl, 'wsdl');
			// TODO remplacer la ligne précédente par celle-ci pour afficher le xml
			//$this->soapClient = new nusoap_client($this->wsdl, '');
			
			$this->soapClient->namespaces['SOAP-ENV'] = 'http://www.w3.org/2003/05/soap-envelope';
			unset($this->soapClient->namespaces['SOAP-ENC']);// = '';
			unset($this->soapClient->namespaces['xsd']);// = '';
			unset($this->soapClient->namespaces['xsi']);// = '';
			
			$this->soapClient->soap_defencoding = 'UTF-8';
			
			// Besoin du dernier paramètre à vide
			$this->result = $this->soapClient->call('DemandeCreationLeasingGN', $TParam, $this->target_ns, '', $string_xml_header, null, 'rpc', '');
			
			if ($this->debug)
			{
				// on affiche la requete et la reponse
				echo '<br />';
				echo "<h2>Request:</h2>";
				echo '<h4>Function</h4>';
				echo 'call DemandeCreationLeasingGN';
				echo '<h4>SOAP Message</h4>';
				echo '<pre>' . htmlspecialchars($this->soapClient->request, ENT_QUOTES) . '</pre>';
				
				echo '<hr>';
				
				echo "<h2>Response:</h2>";
				echo '<h4>Result</h4>';
				echo '<pre>';
				print_r($this->result);
				echo '</pre>';
				echo '<h4>SOAP Message</h4>';
				echo '<pre>' . htmlspecialchars($this->soapClient->response, ENT_QUOTES) . '</pre>';
				
				echo '</body>'."\n";
				echo '</html>'."\n";
				exit;
			}

			$this->TMsg[] = $langs->trans('webservice_financement_msg_scoring_send', $this->leaser->name);
			
			return true;
		} catch (SoapFault $e) {
			var_dump($e);
			exit;
		}
	}

	private function getHeaderLixxbail()
	{
		$header = ''.
/*<?xml version="1.0" encoding="UTF-8"?>*/
'<xsd:schema targetNamespace="http://referentiel.ca.fr/Services/calf/DemandeCreationLeasingGN/V1/" elementFormDefault="qualified">
	<xsd:element name="Calf_Header_GN" type="tns:Calf_Header_GN">
		<xsd:annotation>
			<xsd:documentation>En-tête utilisé entre Calf et CPro</xsd:documentation>
		</xsd:annotation>
	</xsd:element>

	<xsd:complexType name="Calf_Header_GN">
		<xsd:annotation>
			<xsd:documentation>
				Schéma des données de contexte niveau groupe utilisées dans les échanges.
				On trouve des données qui sont issues du contexte transmis par la structure hôte à l\'application qui initie l\'appel de service ainsi que des données calculées par l\'application : 

				Identifiant de l\'occurrence du processus.
			</xsd:documentation>
		</xsd:annotation>

		<xsd:attribute name="correlationId" use="required">
			<xsd:annotation>
				<xsd:documentation>
					Identifiant de corrélation des traces. Constitué de la concaténation de trois chaînes:
					l\'identifiant de l\'entité qui génère l\'identifiant de corrélation, formaté selon la norme CAM0303 (UOM),
					un UUID V3, valeur sur 128 bits, construit à partir d\'une information aléatoire et haché MD5,
					un UUID V4 (nombre aléatoire), valeur sur 128 bits.
				 	la représentation sous forme de chaîne de caractères d\'un UUID suit un format bien précis dont la longueur est 36 caractères. La longueur totale de l\'identifiant de correlation est donc de 77 caractères (5+36+36).</xsd:documentation>
			</xsd:annotation>
			<xsd:simpleType>
				<xsd:restriction base="xsd:string">
					<xsd:length value="77"/>
				</xsd:restriction>
			</xsd:simpleType>
		</xsd:attribute>
	</xsd:complexType>

</xsd:schema>
		';
		
		
		$header = '

<soap1:Calf_Header_GN xmlns:soap1="http://referentiel.ca.fr/SoapHeaderV1" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd" correlationId="12345" wsu:Id="id-11"/>

		';
		
		return $header;
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
	
	/** Cal&f
	 *	CAT_ID	LIB_CAT
	 *	2		INFORMATIQUE
	 *	U		BUREAUTIQUE
	 */
	public function getIdCategorieBien()
	{
		$label = $this->getCategoryLabel($this->simulation->fk_categorie_bien);
		
		if ($label == 'INFORMATIQUE') return 2;
		elseif ($label == 'BUREAUTIQUE') return 'U';
		else return '';
	}

	private function getCategoryLabel($fk_categorie_bien)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_categorie_bien WHERE cat_id = '.$fk_categorie_bien;
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			$this->simulation->category_label = $row->label;
			return $row->label;
		}
		
		return '';
	}
	/** Cal&f
	 * NAT_ID	LIB_NAT
	 * 209B	Micro ordinateur
	 * 215B	Serveur vocal
	 * 216B	Station							
	 * 218C	Traceur							
	 * 219Q	Logiciels						
	 * U01C	Ensemble de matériels bureautique
	 * U03C	Photocopieur
	 */
	public function getIdNatureBien()
	{
		$label = $this->getNatureLabel($this->simulation->fk_nature_bien);
		
		switch ($label) {
			case 'Micro ordinateur':
				return '209B';
				break;
			case 'Serveur vocal':
				return '215B';
				break;
			case 'Station':
				return '216B';
				break;
			case 'Traceur':
				return '218C';
				break;
			case 'Logiciels':
				return '219Q';
				break;
			case 'Ensemble de matériels bureautique':
				return 'U01C';
				break;
			case 'Photocopieur':
				return 'U03C';
				break;
		}
		
		return '';
	}
	
	private function getNatureLabel($fk_nature_bien)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_nature_bien WHERE nat_id = '.$fk_nature_bien;
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			$this->simulation->nature_label = $row->label;
			return $row->label;
		}
		
		return '';
	}
	
	/** Cal&f
	 * MRQ_ID	LIB_MRQ
	 * Z999	GENERIQUE
	 * T046	TOSHIBA
	 * H113	HEWLETT PACKARD
	 * C098	CANON
	 * R128	RISO
	 * 0034	OCE
	 */
	public function getIdMarqueBien()
	{
		$label = $this->getMarqueLabel($this->simulation->marque_materiel);
		
		switch ($label) {
			case 'GENERIQUE':
				return 'Z999';
				break;
			case 'TOSHIBA':
				return 'T046';
				break;
			case 'HEWLETT PACKARD':
				return 'H113';
				break;
			case 'CANON':
				return 'C098';
				break;
			case 'RISO':
				return 'R128';
				break;
			case 'OCE':
				return '0034';
				break;
		}
		
		return '';
	}
	
	private function getMarqueLabel($fk_marque_materiel)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_marque_materiel WHERE code = "'.$fk_marque_materiel.'"';
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			$this->simulation->marque_label = $row->label;
			return $row->label;
		}
		
		return '';
	}

	/**
	 * TODO à construire
	 * Code 	Libellé
	 * produit	produit
	 * 
	 * LOCF 	Location
	 * LOA		Location avec Option d'Achat
	 */
	public function getCodeProduit()
	{
		return 'LOCF';
	}
	
	/**
	 * TODO à combiner avec $this->getCodeProduit()
	 * Type		Libellé type produit
	 * produit
	 * 
	 * STAN		Standard
	 * CESS		Cession de contrat (sans prestation)
	 * LMAF		Location mandatée fichier
	 * PROF		LOA professionnelle
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
		
		if (!empty($pct_vr) && !empty($mt_vr)) $pct_vr = 0; // Si les 2 sont renseignés alors je garde que le montant
		
		/*$TParam = array(
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
/*			)
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
					,'MT_PREMIER_LOYER' => 0 // NO
					,'DUREE_FINANCEMENT' => $this->simulation->duree // *
					,'PERIODICITE_FINANCEMENT' => $periodicite_code // chaîne de caractères alphanumérique de 3 caractères max. Cf. onglet 'Périodicité de financement' *
					,'TERME_FINANCEMENT' => $this->simulation->opt_terme == 1 ? 'A' : 'E' // 4 char. échu ou à échoir *
					,'NB_FRANCHISE' => '' // NO
					,'NATURE_FINANCEMENT' => 'STD' // NO - Voir si saisie par CPro
					,'DATE_DEMANDE_FINANCEMENT' => date('Y-m-dTH:i:s') // format YYYY-MM-DDThh:mm:ss *
			)
		);*/
		

		$xml = '
		<ns1:Request>
            <ns1:PARTENAIRE>
               <ns1:SIREN_PARTENAIRE>'.$mysoc->idprof1.'</ns1:SIREN_PARTENAIRE>
               <ns1:NIC_PARTENAIRE>'.substr($mysoc->idprof2, -5, 5).'</ns1:NIC_PARTENAIRE>
               <ns1:COMMERCIAL_EMAIL>'.$this->simulationSuivi->user->email.'</ns1:COMMERCIAL_EMAIL>
               <ns1:REF_EXT>'.$this->simulation->reference.'</ns1:REF_EXT>
            </ns1:PARTENAIRE>
            <ns1:BIEN>
               <ns1:CATEGORIE_BIEN>'.$this->getIdCategorieBien().'</ns1:CATEGORIE_BIEN>
               <ns1:NATURE_BIEN>'.$this->getIdNatureBien().'</ns1:NATURE_BIEN>
               <ns1:MARQUE_BIEN>'.$this->getIdMarqueBien().'</ns1:MARQUE_BIEN>
               <ns1:ANNEE_BIEN>'.date('Y').'</ns1:ANNEE_BIEN>
               <ns1:ETAT_BIEN>NEUF</ns1:ETAT_BIEN>
               <ns1:QTE_BIEN>1</ns1:QTE_BIEN>
               <ns1:MT_HT_BIEN>'.$this->simulation->montant.'</ns1:MT_HT_BIEN>
               <ns1:PAYS_DESTINATION_BIEN>'.(!empty($this->simulation->societe->country_code) ? $this->simulation->societe->country_code : 'FR').'</ns1:PAYS_DESTINATION_BIEN>
               <ns1:FOURNISSEUR_SIREN>'.$mysoc->idprof1.'</ns1:FOURNISSEUR_SIREN>
               <ns1:FOURNISSEUR_NIC>'.substr($mysoc->idprof2, -5, 5).'</ns1:FOURNISSEUR_NIC>
            </ns1:BIEN>
            <!--1 or more repetitions:-->
            <ns1:BIEN_COMPL>
               <!--ns1:CATEGORIE_BIEN_COMPL>U</ns1:CATEGORIE_BIEN_COMPL>
               <ns1:NATURE_BIEN_COMPL>U03C</ns1:NATURE_BIEN_COMPL>
               <ns1:MARQUE_BIEN_COMPL>T046</ns1:MARQUE_BIEN_COMPL>
               <ns1:ANNEE_BIEN_COMPL>2016</ns1:ANNEE_BIEN_COMPL>
               <ns1:ETAT_BIEN_COMPL>NEUF</ns1:ETAT_BIEN_COMPL>
               <ns1:MT_HT_BIEN_COMPL>1000.01</ns1:MT_HT_BIEN_COMPL>
               <ns1:QTE_BIEN_COMPL>2</ns1:QTE_BIEN_COMPL-->
            </ns1:BIEN_COMPL> 
            <ns1:CLIENT>
               <ns1:CLIENT_SIREN>'.$this->simulation->societe->idprof1 .'</ns1:CLIENT_SIREN>
               <ns1:CLIENT_NIC>'.substr($this->simulation->societe->idprof2, -5, 5).'</ns1:CLIENT_NIC>
            </ns1:CLIENT>
            <ns1:FINANCEMENT>
               <ns1:CODE_PRODUIT>'.$this->getCodeProduit().'</ns1:CODE_PRODUIT>
               <ns1:TYPE_PRODUIT>'.$this->getTypeProduit().'</ns1:TYPE_PRODUIT>
               <ns1:MT_FINANCEMENT_HT>'.$this->simulation->montant.'</ns1:MT_FINANCEMENT_HT>
               <ns1:PCT_VR>'.$pct_vr.'</ns1:PCT_VR>
               <ns1:MT_VR>'.$mt_vr.'</ns1:MT_VR>
               <ns1:TYPE_REGLEMENT>'.$mode_reglement_id.'</ns1:TYPE_REGLEMENT>
               <ns1:MT_PREMIER_LOYER>0</ns1:MT_PREMIER_LOYER>
               <ns1:DUREE_FINANCEMENT>'.$this->simulation->duree.'</ns1:DUREE_FINANCEMENT>
               <ns1:PERIODICITE_FINANCEMENT>'.$periodicite_code.'</ns1:PERIODICITE_FINANCEMENT>
               <ns1:TERME_FINANCEMENT>'.($this->simulation->opt_terme == 1 ? 'A' : 'E').'</ns1:TERME_FINANCEMENT>
               <ns1:NB_FRANCHISE>0</ns1:NB_FRANCHISE>
               <ns1:NATURE_FINANCEMENT>STD</ns1:NATURE_FINANCEMENT>
               <ns1:DATE_DEMANDE_FINANCEMENT>'.date('Y-m-d').'T'.date('H:i:s').'</ns1:DATE_DEMANDE_FINANCEMENT>
            </ns1:FINANCEMENT>
         </ns1:Request>
		';
		
		//return $TParam;
		return $xml;
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
