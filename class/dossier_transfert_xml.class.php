<?php

if(! class_exists('Societe')) dol_include_once('/societe/class/societe.class.php');
dol_include_once('/financement/class/dossier.class.php');

abstract class TFinDossierTransfertXML extends TObjetStd {

//    const TLeaserTransfert = array(
//			19483 => 'LIXXBAIL',
//			21382 => 'CMCIC'
//		);

	function __construct($transfert=false) {
		global $conf;

		$this->transfert = $transfert;

		// Définition du chemin du fichier
		$this->filePath.= 'XML/' . $this->leaser . '/';
		$this->fileFullPath = $conf->financement->multidir_output[$conf->entity].'/'.$this->filePath;
	}

    /**
     * Function that need to be override by children
     */
    abstract function generate(&$PDOdb, &$TAffaires,$andUpload=false);

    /**
     * Function that need to be override by children
     */
	function transfertXML(&$PDOdb) {
		if(empty($this->leaser)) return false;

		// Récupération des affaires
		$TAffaires = $this->getAffairesForXML($PDOdb);

		// Génération du fichier
		$filename = $this->generate($PDOdb, $TAffaires);

        // Dépose via SFTP
		if(!empty($filename) && $this->transfert) {
			$this->upload($filename);
		}

		return $this->filePath . $filename . '.xml';
    }

    abstract function upload($filename);

	function getAffairesForXML(&$PDOdb){
		global $conf;
		
		$TAffaires = array();
		
		$sql = 'SELECT DISTINCT(fa.rowid) 
				FROM '.MAIN_DB_PREFIX.'fin_affaire as fa
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire as da ON (da.fk_fin_affaire = fa.rowid)
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement as df ON (df.fk_fin_dossier = da.fk_fin_dossier)
					LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON (s.rowid = df.fk_soc)
				WHERE fa.type_financement = "MANDATEE"
					AND df.type = "LEASER"
					AND s.rowid = '.static::fk_leaser.'
					AND df.transfert = 1
					AND fa.entity = '.$conf->entity;
		
		$TIdAffaire = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
		
		foreach($TIdAffaire as $idAffaire){
			$affaire = new  TFin_affaire;
			$affaire->load($PDOdb, $idAffaire);
			$TAffaires[] = $affaire;
		}
	
		return $TAffaires;
	}

	function resetAllDossiersInXML(&$PDOdb){
		// Récupération des affaires
		$TAffaires = $this->getAffairesForXML($PDOdb);
		
		foreach($TAffaires as $affaire){

			foreach($affaire->TLien as $i => $TData ){
				$TData->dossier->financementLeaser->transfert = 0;
				$TData->dossier->save($PDOdb);
			}
		}
	}

    static function create($fk_leaser, $transfert = false) {
        if(! class_exists('TFinTransfertCMCIC')) dol_include_once('/financement/class/dossier_transfert_xml_cmcic.class.php');
        if(! class_exists('TFinTransfertLixxbail')) dol_include_once('/financement/class/dossier_transfert_xml_lixxbail.class.php');

        switch($fk_leaser) {
            case TFinTransfertLixxbail::fk_leaser:
                $obj = new TFinTransfertLixxbail($transfert);
                break;
            case TFinTransfertCMCIC::fk_leaser:
                $obj = new TFinTransfertCMCIC($transfert);
                break;
        }

        return $obj;
    }
}