<?php

if(! class_exists('Societe')) dol_include_once('/societe/class/societe.class.php');
if(! class_exists('TFinDossierTransfertXML')) dol_include_once('/financement/class/dossier_transfert_xml.class.php');
dol_include_once('/financement/class/dossier.class.php');

class TFinTransfertBNP extends TFinDossierTransfertXML {
    const CSV_DELIMITER = ';';
    const fileExtension = '.csv';
    const fk_leaser = 20113;
    protected $leaser = 'BNP';

	function __construct($transfert=false) {
        parent::__construct($transfert);
    }

    function upload($filename) {
	    global $conf;

        $dirname = $this->fileFullPath.$filename.self::fileExtension;
        if(empty($conf->global->FINANCEMENT_MODE_PROD)) {
            exec('sh bash/bnpxml_test.sh '.$dirname);
        } else {
            exec('sh bash/bnpxml.sh '.$dirname);
        }
    }

	function generate(&$PDOdb, &$TAffaires, $andUpload=false){
		global $conf, $db;

		if(empty($conf->global->FINANCEMENT_MODE_PROD)) $name = 'UATFRCPROBNPADLC_'.date('Ymd_His');
        else $name = 'IN_PRDFRCPROBNPADLC_'.date('Ymd_His');

        if(! file_exists($this->fileFullPath)) dol_mkdir($this->fileFullPath);
        $f = fopen($this->fileFullPath.'/'.$name.self::fileExtension, 'w');

        $THead = array(
            'PRO_NB',           // Tiers apporteur
            'CON_CLI_ID',       // Référence contrat C'Pro
            'REQ_NB',           // N° demande
            'FIN_PRO_LB',       // Produit financier
            'MAT_COD_LB',       // Code matériel
            'HT_AM',            // Montant HT
            'VR_AM',            // VR montant HT
            'RENT_AM',          // Montant loyer HT
            'DUR_TM',           // Durée (en mois)
            'PER_NB',           // Périodicité
            'RENT_NB',          // Nb de loyer
            'SIG_DT',           // Date signature contrat
            'DEL_DT',           // Date livraison
            'FIR_DT',           // Date 1ere échéance
            'MAT_DES_LB',       // Description matériel
            'MAT_TYP_LB',       // Type matériel
            'MAT_MOD_LB',       // Modèle matériel
            'MAT_SER_NB',       // N° série matériel
            'SIRET_NB',         // SIRET client
            'IBAN_NB',          // IBAN client
            'SIG_LAST_NAME',    // Nom signataire
            'SIG_FIR_NAME',     // Prénom signataire
            'SIG_ROL_LB',       // Fonction signataire
            'SIG_BIR_DT',       // Date de naissance signataire
            'INV_NB',           // N° facture
            'INV_DT'            // Date facture
        );
        $res = fputcsv($f, $THead, self::CSV_DELIMITER);

		foreach($TAffaires as $affaire) {
		    $descMateriel = $serialNumber = '';
            if(empty($affaire->TAsset)) $affaire->loadEquipement($PDOdb);

            if(! empty($affaire->TAsset[0])) {
                $asset = $affaire->TAsset[0]->asset;
                $serialNumber = $asset->serial_number;

                $p = new Product($db);
                if(! empty($asset->fk_product)) {
                    $p->fetch($asset->fk_product);
                    $descMateriel = $p->label;
                }
            }
		    $dossier = $affaire->TLien[0]->dossier;
            $TInvoice = $this->getFactureMat($affaire->rowid);
            $client = new Societe($db);
            $client->fetch($affaire->fk_soc);

			$codeFamille = 'H'; // Bureautique (même codes famille, matériel et marque quand dans l'EDI
			$codeMateriel = '';
			$codeMarque = '';
			if($dossier->entity == 2) { // info
				$codeMateriel = '30021204';
				$codeMarque = '321';
			} else if(in_array($dossier->entity, array(3, 30))) { // telecom et veodis
				$codeMateriel = '322020';
				$codeMarque = 'D51';
			} else {
				$codeMateriel = '300121';
				$codeMarque = '335';
			}

            // Une ligne par facture ?
            foreach($TInvoice as $fk_invoice) {
                $invoice = new Facture($db);
                $invoice->fetch($fk_invoice);

                $TData = array(
                    $this->getTiersApporteur($dossier->entity),
                    $dossier->financement->reference,
                    $dossier->financementLeaser->reference,
                    '024',  // Produit financier
					$codeMateriel,
                    str_replace('.', ',', $dossier->financementLeaser->montant),
                    $dossier->financementLeaser->reste,
                    str_replace('.', ',', $dossier->financementLeaser->echeance),
                    $dossier->financementLeaser->duree * $dossier->financementLeaser->getiPeriode(),
                    $dossier->financementLeaser->getiPeriode(),
                    $dossier->financementLeaser->duree,  // Nombre de loyer
                    '',  // Date signature contrat
                    '',  // Date livraison
                    date('d/m/Y', $dossier->financementLeaser->date_prochaine_echeance),
                    $descMateriel,
					'',  // Type matériel
					'',  // Modèle matériel
                    $serialNumber,
                    $client->idprof2,
                    '',  // IBAN
                    '',  // Nom signataire
                    '',  // Prénom signataire
                    '',  // Fonction signataire
                    '',  // Date de naissance signataire
                    $invoice->ref,  // N° facture
                    date('d/m/Y', $invoice->date),  // Date facture
                );

                $res = fputcsv($f, $TData, self::CSV_DELIMITER);
            }

            if($andUpload) {
                $affaire->xml_date_transfert = time();
                $affaire->xml_fic_transfert = $name;
                $affaire->save($PDOdb);
            }
		}

		fclose($f);

		return $name;
	}

	function getTiersApporteur($entity) {
        $code = '';
        if(empty($entity)) return $code;

        global $db;

        $sql = 'SELECT code_prescripteur_bnp';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'entity_extrafields';
        $sql.= ' WHERE fk_object = '.$entity;

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        if($obj = $db->fetch_object($resql)) {
            $code = $obj->code_prescripteur_bnp;
        }

        return $code;
    }

    function getFactureMat($fk_affaire) {
	    global $db;

	    $TRes = array();
	    if(empty($fk_affaire)) return $TRes;

	    $sql = 'SELECT fk_target';
	    $sql.= ' FROM '.MAIN_DB_PREFIX.'element_element';
	    $sql.= " WHERE sourcetype = 'affaire'";
        $sql.= ' AND fk_source = '.$fk_affaire;
	    $sql.= " AND targettype = 'facture'";

	    $resql = $db->query($sql);
	    if(! $resql) {
	        dol_print_error($db);
	        exit;
        }

	    while($obj = $db->fetch_object($resql)) {
	        $TRes[] = $obj->fk_target;
        }

	    return $TRes;
    }
}
