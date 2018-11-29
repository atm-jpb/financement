<?php

if(! class_exists('Societe')) dol_include_once('/societe/class/societe.class.php');
if(! class_exists('TFinDossierTransfertXML')) dol_include_once('/financement/class/dossier_transfert_xml.class.php');
dol_include_once('/financement/class/dossier.class.php');

class TFinTransfertCMCIC extends TFinDossierTransfertXML {

    const fk_leaser = 21382;
    protected $leaser = 'CMCIC';

	function __construct($transfert=false) {
        parent::__construct($transfert);
    }

    // TODO: Adapt to CMCIC !
    function upload($filename) {
//        $dirname = $this->fileFullPath . $filename . '.xml';
//        if(BASE_TEST) {
//            exec('sh bash/lixxbailxml_test.sh '.$dirname);
//        } else {
//            exec('sh bash/lixxbailxml.sh '.$dirname);
//        }
    }

	function generate(&$PDOdb, &$TAffaires,$andUpload=false){
		global $conf, $db;
		
		$xml = new DOMDocument('1.0','UTF-8');
		$xml->formatOutput = true;
		
		//Chargement des noeuds correspondant aux affaires
		//print '<pre>';
		foreach($TAffaires as $Affaire){
			$dossier = $Affaire->TLien[0]->dossier;	// Possible car 1 affaire = 1 dossier
			$fin = $dossier->financement;
			$finLeaser = $dossier->financementLeaser;

			$socLeaser = new Societe($db);
			$socLeaser->fetch($finLeaser->fk_soc);
			$leaserName = substr($socLeaser->name, 0, 32);	// Limité à 32 caractères

			$affairelist = $xml->createElement("CONTRAT");
			$affairelist = $xml->appendChild($affairelist);

			$affairelist->appendChild($xml->createElement("NO_AFFAIRE_CM", $finLeaser->reference));
			$affairelist->appendChild($xml->createElement("NO_AFFAIRE_PARTENAIRE", $fin->reference));
			$affairelist->appendChild($xml->createElement("NOM_LEASER", $leaserName));

			$affairelist->appendChild($xml->createElement("FLG_COSME", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_ASSVIE", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_GARANTIE", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_DERO_ASSMAT", 'N'));

			// Schéma financier
			$data_schema_fin = $this->getSchemaFinData($xml, $dossier);
			$affairelist->appendChild($data_schema_fin);

			// Solde contrat
//			$data_schema_fin = $this->getSoldeContratData($xml, $dossier);
//			$affairelist->appendChild($data_schema_fin);

			// Locataire
			$data_schema_fin = $this->getLocataireData($xml, $dossier);
			$affairelist->appendChild($data_schema_fin);

			// Marche public - Facultatif
//			$data_schema_fin = $this->getMarchePublicData($xml, $dossier);
//			$affairelist->appendChild($data_schema_fin);

			// COSME
//			$data_schema_fin = $this->getCOSMEData($xml, $dossier);
//			$affairelist->appendChild($data_schema_fin);

			// Assurance vie
//			$data_schema_fin = $this->getAssuranceVieData($xml, $dossier);
//			$affairelist->appendChild($data_schema_fin);

			// Factures
			$data_schema_fin = $this->getFactureData($xml, $Affaire);
			$affairelist->appendChild($data_schema_fin);

			if($andUpload){
				$Affaire->xml_date_transfert = time();
				$Affaire->xml_fic_transfert = $name2;
			}
			$Affaire->save($PDOdb);
		}
		
		$chaine = $xml->saveXML();
		
		$name2 = 'test';
		dol_mkdir($this->fileFullPath);
		file_put_contents($this->fileFullPath.$name2.'.xml', $chaine);
		
		return $name2;
	}

	function getSchemaFinData(&$xml, $dossier) {
		$finLeaser = $dossier->financementLeaser;
        $periode = $finLeaser->getiPeriode();

		$schema_financier = $xml->createElement('SCHEMA_FINANCIER');
        $TData = array(
            'DUREE_MOIS' => $finLeaser->duree*$periode,
            'PERIODICITE' => $periode,
            'TERME' => ($finLeaser->terme == 0 ? 2 : 1),            // La banque veut : échu => 2, à échoir => 1
            'DATE_ML' => date('d/m/Y', $finLeaser->date_debut),     // TODO: à adapter si intercalaire!
            'MTACPTCLI' => price2num($finLeaser->loyer_intercalaire)
        );

        foreach($TData as $code => $value) {
            $schema_financier->appendChild($xml->createElement($code, $value));
        }

		return $schema_financier;
	}

	function getSoldeContratData(&$xml, $dossier) {
		$solde_contrat = $xml->createElement('SOLDE_CONTRAT');
        $TData = array(
            'NOCTROA' => 0,     // Num dossier
            'MTSOLDCTR' => price2num(0),   // ???
            'NSOLDDCPT' => 0000    // ???
        );

        foreach($TData as $code => $value) {
            $solde_contrat->appendChild($xml->createElement($code, $value));  // Num dossier
        }

		return $solde_contrat;
	}

	function getLocataireData(&$xml, $dossier) {
        global $db, $mysoc;

		$fin = $dossier->financement;
        $siret = $soc_name = '';
        if($fin->fk_soc == 1) {
            $siret = $mysoc->idprof2;
            $soc_name = $mysoc->name;
        }
        else {
            $socClient = new Societe($db);
            $socClient->fetch($fin->fk_soc);

            $siret = $socClient->idprof2;
            $soc_name = $socClient->name;
        }

		$solde_contrat = $xml->createElement('LOCATAIRE');
        $TData = array(
            'SIRET_LOC' => $siret,
            'DENO_LOC' => substr($soc_name, 0, 32),    // 32 Caractères MAX !
            'BIC_IBAN' => 0,    // BIC.'_'.IBAN
            'N_TIT' => 0,
            'DT_SIGN_CLI' => 0
        );

        foreach($TData as $code => $value) {
            $solde_contrat->appendChild($xml->createElement($code, $value));  // Num dossier
        }

		return $solde_contrat;
	}

	function getMarchePublicData(&$xml, $dossier) {
		$elem = $xml->createElement('MARCHE_PUBLIC');
        $TData = array(

        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getCOSMEData(&$xml, $dossier) {
		$elem = $xml->createElement('COSME');
        $TData = array(
            'DATE_COSME' => 'N'
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getAssuranceVieData(&$xml, $dossier) {
		$elem = $xml->createElement('ASSURANCE_VIE');
        $TData = array(
            'TYPE_ASSVIE' => 'N',
            'NOM_ASSVIE' => 'N',
            'PRENOM_ASSVIE' => 'N',
            'N_RUE_ASSVIE' => 'N',
            'RUE_ASSVIE' => 'N',
            'C_POSTAL_ASSVIE' => 'N',
            'VILLE_ASSVIE' => 'N',
            'DATE_NAISSANCE' => 'N'
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getFactureData(&$xml, $affaire) {
        $dossier = $affaire->TLien[0]->dossier;
		$finLeaser = $dossier->financementLeaser;
        $facture = $affaire->loadFactureMat();

		$elem = $xml->createElement('FACTURE');
        $TData = array(
            'NOFACEXT' => substr($facture->ref, 0, 20),  // Num facture matériel liée à l'affaire
            'DTFACEXT' => date('d/m/Y', $facture->date),  // Date facture
            'TEMFACPV' => 'N',  // ???
            'TYPFACFOU' => ($facture->type == Facture::TYPE_CREDIT_NOTE ? 'AFAVOIR' : 'FFFacture')  // Type facture
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

        // Montants factures
        $truc = $this->getMontantFactureData($xml, $dossier, $facture);
        $elem->appendChild($truc);

        // Montants factures
        $truc = $this->getFacturantData($xml, $dossier);
        $elem->appendChild($truc);

        // Matériel
        foreach($affaire->TAsset as $assetLink) {
            $truc = $this->getMaterielData($xml, $assetLink, $dossier);
            $elem->appendChild($truc);
        }

		return $elem;
	}

	function getMontantFactureData(&$xml, $dossier, $facture) {
		$finLeaser = $dossier->financementLeaser;

		$elem = $xml->createElement('MONTANTS_FAC');
        $TData = array(
            'MTESCFOU' => 0,
            'MTHTFAC' => price2num($facture->total_ht),   // Montant HT
            'MTTVAFAC' => price2num($facture->total_tva),  // Montant TVA
            'MTRSTAFF' => price2num($facture->total_ttc),  // Montant TTC
            'CDDEV' => 'EUR'
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getFacturantData(&$xml, $dossier) {
        global $mysoc;
		$finLeaser = $dossier->financementLeaser;

		$elem = $xml->createElement('FACTURANT');
        $TData = array(
            'SIRETFCT' => $mysoc->idprof2,  // Siret mysoc de l'entité (idprof2)
            'TAUXTVAFOU' => price2num(20),
            'NOTVAIN' => 'N'    // TVA Mysoc
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getMaterielData(&$xml, $assetLink, $dossier) {
//        $dossier = $affaire->TLien[0]->dossier;

		$elem = $xml->createElement('MATERIEL');

        // Détails Matériel
        $truc = $this->getMaterielDetailsData($xml, $assetLink);
        $elem->appendChild($truc);

        // Livraison Matériel
        $truc = $this->getLivraisonMaterielData($xml, $dossier);
        $elem->appendChild($truc);

        // Maintenance Matérielle
//        $truc = $this->getMaintenanceMatData($xml, $dossier);
//        $elem->appendChild($truc);

		return $elem;
	}

	function getMaterielDetailsData(&$xml, $assetLink) {
        global $db;
        $p = new Product($db);
        $p->fetch($assetLink->asset->fk_product);

		$elem = $xml->createElement('DETAIL_MAT');
        $TData = array(
            'LIB_MAT' => substr($p->label, 0, 60),
            'NOSEROBJ' => substr($assetLink->asset->serial_number, 0, 20),
            'MTHTUNIT' => price2num(0),
            'MTFTECG' => 0,
            'REFEXTFOU' => 'N',
//            'COMM_FIN' => 'N',    // Facultatif
            'LOYER_HT' => price2num(0)
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getLivraisonMaterielData(&$xml, $dossier) {
		$finLeaser = $dossier->financementLeaser;

		$elem = $xml->createElement('LIVRAISON_MAT');
        $TData = array(
//            'DTPV' => 'N',    // Facultatif
            'CDTYPPV' => 'N',
            'SIRET_LIV' => 'N',
            'N_RUE_LIV' => 'N',
            'RUE_1_LIV' => 'N',
            'RUE_2_LIV' => 'N',
            'C_POSTAL_LIV' => 'N',
            'VILLE_LIV' => 'N',
            'DATE_LIV' => 'N'
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getMaintenanceMatData(&$xml, $dossier) {
		$finLeaser = $dossier->financementLeaser;

		$elem = $xml->createElement('MAINTENANCE_MAT');
        $TData = array(
            'MTHT_MAIN' => 'N',
            'SIRET_MAIN' => 'N',
            'FLG_INDEX' => 'N'
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}
}