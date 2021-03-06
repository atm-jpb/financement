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

    function upload($filename) {
	    global $conf;

        $dirname = $this->fileFullPath.$filename.static::fileExtension;
        if(empty($conf->global->FINANCEMENT_MODE_PROD)) {
            exec('sh bash/cmcicxml_test.sh '.$dirname);
        } else {
            exec('sh bash/cmcicxml.sh '.$dirname);
        }
    }

	function generate(&$PDOdb, &$TAffaires, $andUpload=false){
		global $conf, $db;

		if(empty($conf->global->FINANCEMENT_MODE_PROD)) $name2 = 'UATFRCPROCMCICADLC_'.date('Ymd-His');
        else $name2 = 'PRDFRCPROCMCICADLC_'.date('Ymd-His');

		$xml = new DOMDocument('1.0','UTF-8');
		$xml->formatOutput = true;

        $title = $xml->appendChild($xml->createElement('ADLC'));

		//Chargement des noeuds correspondant aux affaires
		foreach($TAffaires as $Affaire) {
			$dossier = $Affaire->TLien[0]->dossier;	// Possible car 1 affaire = 1 dossier
			$fin = $dossier->financement;
			$finLeaser = $dossier->financementLeaser;
            $refFinLeaser = $finLeaser->reference;
            if(strlen($refFinLeaser) == 6) {
                $refFinLeaser .= '600';
            }

			$socLeaser = new Societe($db);
			$socLeaser->fetch($finLeaser->fk_soc);
			$leaserName = substr($socLeaser->name, 0, 32);	// Limité à 32 caractères

			$affairelist = $xml->createElement("CONTRAT");
			$affairelist = $title->appendChild($affairelist);

			$affairelist->appendChild($xml->createElement("NO_AFFAIRE_CM", $refFinLeaser));
			$affairelist->appendChild($xml->createElement("NO_AFFAIRE_PARTENAIRE", $fin->reference));
			$affairelist->appendChild($xml->createElement("NOM_LEASER", $leaserName));

			$affairelist->appendChild($xml->createElement("FLG_COSME", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_ASSVIE", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_GARANTIE", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_DERO_ASSMAT", 'N'));
			$affairelist->appendChild($xml->createElement("FLG_INDEX", 'N'));

			// Schéma financier
			$data_schema_fin = $this->getSchemaFinData($xml, $dossier);
			$affairelist->appendChild($data_schema_fin);

			// Solde contrat
//			$data_schema_fin = $this->getSoldeContratData($xml, $dossier);
//			$affairelist->appendChild($data_schema_fin);

			// Locataire
			$data_schema_fin = $this->getLocataireData($xml, $Affaire);
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

            $title->appendChild($affairelist);
		}


		$chaine = $xml->saveXML();

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
            'DATE_ML' => date('Y-m-d', $finLeaser->date_debut),     // TODO: à adapter si intercalaire!
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

	function getLocataireData(&$xml, $affaire) {
        global $db, $conf, $mysoc;

        // Mandatée, le locataire est CPRO
        $siret = $mysoc->idprof2;
        $soc_name = $mysoc->name;

        dol_include_once('/compta/bank/class/account.class.php');
        $acc = new Account($db);
        $acc->fetch($conf->global->FACTURE_RIB_NUMBER);

		$solde_contrat = $xml->createElement('LOCATAIRE');
        $TData = array(
            'SIRET_LOC' => $siret,
            'DENO_LOC' => substr($soc_name, 0, 32),    // 32 Caractères MAX !
            'BIC_IBAN' => $acc->bic . '_' . $acc->iban,    // BIC.'_'.IBAN
            'N_TIT' => $acc->proprio,
            'DT_SIGN_CLI' => '2019-01-01'
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
        $facture = $affaire->loadFactureMat();

		$elem = $xml->createElement('FACTURE');
        $TData = array(
            'NOFACEXT' => substr($facture->ref, 0, 20),  // Num facture matériel liée à l'affaire
            'DTFACEXT' => date('Y-m-d', $facture->date),  // Date facture
            'TEMFACPV' => 'N',  // ???
            'TYPFACFOU' => ($facture->type == Facture::TYPE_CREDIT_NOTE ? 'AFAVOIR' : 'FFFacture')  // Type facture
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

        // Montants factures
        $truc = $this->getMontantFactureData($xml, $dossier);
        $elem->appendChild($truc);

        // Facturant
        $truc = $this->getFacturantData($xml, $affaire);
        $elem->appendChild($truc);

        // Matériel
        foreach($affaire->TAsset as $assetLink) {
            $truc = $this->getMaterielData($xml, $assetLink, $dossier, $facture, $affaire);
            $elem->appendChild($truc);
            break; // On n'envoie que le 1er matériel
        }

		return $elem;
	}

	function getMontantFactureData(&$xml, $dossier) {
		$montant_ht = $dossier->financementLeaser->montant;
		$montant_ttc = round($montant_ht * 1.20,2);
		$montant_tva = $montant_ttc - $montant_ht;

		$elem = $xml->createElement('MONTANTS_FAC');
        $TData = array(
            'MTESCFOU' => 0,
            'MTHTFAC' => price2num($montant_ht),   // Montant HT
            'MTTVAFAC' => price2num($montant_tva),  // Montant TVA
            'MTRSTAFF' => price2num($montant_ttc),  // Montant TTC
            'CDDEV' => 'EUR'
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

    function getFacturantData(&$xml, $affaire) {
        global $mysoc;
        $siret = $mysoc->idprof2;

        $elem = $xml->createElement('FACTURANT');
        $TData = array(
            'SIRETFCT' => $siret,  // Siret de l'entité identifiée par le préfixe de la référence de l'affaire
            'TAUXTVAFOU' => price2num(20),
            'NOTVAIN' => 'N'    // TVA Mysoc
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

        return $elem;
    }

	function getMaterielData(&$xml, $assetLink, $dossier, $facture, $affaire) {
        $elem = $xml->createElement('MATERIEL');

        // Détails Matériel
        $truc = $this->getMaterielDetailsData($xml, $assetLink, $dossier, $facture);
        $elem->appendChild($truc);

        // Livraison Matériel
        $truc = $this->getLivraisonMaterielData($xml, $facture, $affaire);
        $elem->appendChild($truc);

        // Maintenance Matérielle
//        $truc = $this->getMaintenanceMatData($xml, $dossier);
//        $elem->appendChild($truc);

		return $elem;
	}

	function getMaterielDetailsData(&$xml, $assetLink, $dossier, $facture) {
        global $db;
        $p = new Product($db);
        $p->fetch($assetLink->asset->fk_product);

		$elem = $xml->createElement('DETAIL_MAT');
        $TData = array(
            'LIBMAT' => substr($p->label, 0, 60),
            'NOSEROBJ' => substr($assetLink->asset->serial_number, 0, 20),
            'MTHTUNIT' => price2num($dossier->financementLeaser->montant),
            'MTFTECG' => 0,
            'REFEXTFOU' => 'N',
//            'COMM_FIN' => 'N',    // Facultatif
            'LOYER_HT' => price2num($dossier->financementLeaser->echeance)
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getLivraisonMaterielData(&$xml, $facture, $affaire) {
		$elem = $xml->createElement('LIVRAISON_MAT');

		$client = $affaire->societe;
		$address = preg_replace("/\r/", '', $client->address);
		$add = explode("\n", $address);
		$rue1 = $rue2 = '';
		if(!empty($add[0])) $rue1 = substr($add[0], 0, 35);
        if(!empty($add[1])) $rue2 = substr($add[1], 0, 40);
        $TData = array(
//            'DTPV' => 'N',    // Facultatif
            'CDTYPPV' => ' ',   // Un espace est un blanc, donc on met un blanc
            'SIRET_LIV' => $client->idprof2,
            'N_RUE_LIV' => '',
            'RUE_1_LIV' => $rue1,
            'RUE_2_LIV' => $rue2,
            'C_POSTAL_LIV' => $client->zip,
            'VILLE_LIV' => $client->town,
            'DATE_LIV' => date('Y-m-d', $facture->date)
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}

	function getMaintenanceMatData(&$xml, $dossier) {
		$elem = $xml->createElement('MAINTENANCE_MAT');
        $TData = array(
            'MTHT_MAIN' => 'N',
            'SIRET_MAIN' => 'N',
        );

        foreach($TData as $code => $value) {
            $elem->appendChild($xml->createElement($code, $value));
        }

		return $elem;
	}
}
