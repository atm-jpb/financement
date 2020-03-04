<?php

if(! class_exists('Societe')) dol_include_once('/societe/class/societe.class.php');
dol_include_once('/financement/class/dossier.class.php');

abstract class TFinDossierTransfertXML extends TObjetStd {
    const fileExtension = '.xml';
    public $transfert;
    public $filePath;
    public $fileFullPath;

	function __construct($transfert = false) {
		global $conf;

		$this->transfert = $transfert;

		// Définition du chemin du fichier
		$this->filePath.= 'XML/' . $this->leaser . '/';
		$this->fileFullPath = $conf->financement->multidir_output[$conf->entity].'/'.$this->filePath;
	}

    /**
     * Function that need to be override by children
     */
    abstract function generate(&$PDOdb, &$TAffaires, $andUpload = false);

    /**
     * @param   TPDOdb    $PDOdb
     * @param   array     $TAffaireId
     * @return  string
     */
	function transfertXML(&$PDOdb, $TAffaireId = array()) {
		if(empty($this->leaser)) return false;

		// Récupération des affaires
		$TAffaires = $this->getAffairesForXML($PDOdb, $TAffaireId);

		// Génération du fichier
		$filename = $this->generate($PDOdb, $TAffaires);

        // Dépose via SFTP
		if(!empty($filename) && $this->transfert) {
			$this->upload($filename);
		}

		return $this->filePath . $filename . static::fileExtension;
    }

    /**
     * Function that need to be override by children
     */
    abstract function upload($filename);

    /**
     * @param   TPDOdb  $PDOdb
     * @param   array   $TAffaireId
     * @return  array
     */
	function getAffairesForXML(&$PDOdb, $TAffaireId){
	    global $conf;
		$TAffaires = array();

		if(empty($TAffaireId)) {
            $sql = 'SELECT DISTINCT(fa.rowid)
				FROM '.MAIN_DB_PREFIX.'fin_affaire as fa
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire as da ON (da.fk_fin_affaire = fa.rowid)
					LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement as df ON (df.fk_fin_dossier = da.fk_fin_dossier)
					LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON (s.rowid = df.fk_soc)
				WHERE fa.type_financement = "MANDATEE"
					AND df.type = "LEASER"
					AND s.rowid = '.static::fk_leaser.'
					AND df.transfert = '.TFin_financement::STATUS_TRANSFER_YES.'
					AND fa.entity = '.$conf->entity.'
				ORDER BY df.fk_fin_dossier DESC';

            $TAffaireId = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
        }

		foreach($TAffaireId as $idAffaire){
			$affaire = new  TFin_affaire;
			$affaire->load($PDOdb, $idAffaire);
			$TAffaires[] = $affaire;
		}
	
		return $TAffaires;
	}

    /**
     * @param   TPDOdb  $PDOdb
     * @param   array   $TAffaireId
     */
    function resetAllDossiersInXML(&$PDOdb, $TAffaireId) {
        // Récupération des affaires
        $TAffaires = $this->getAffairesForXML($PDOdb, $TAffaireId);

        foreach($TAffaires as $affaire) {
            foreach($affaire->TLien as $i => $TData) {
                $TData->dossier->financementLeaser->transfert = 0;
                $TData->dossier->save($PDOdb);
            }
        }
    }

    /**
     * @param   int     $fk_leaser
     * @param   bool    $transfert
     * @return  TFinDossierTransfertXML
     */
    static function create($fk_leaser, $transfert = false) {
        if(! class_exists('TFinTransfertCMCIC')) dol_include_once('/financement/class/dossier_transfert_xml_cmcic.class.php');
        if(! class_exists('TFinTransfertLixxbail')) dol_include_once('/financement/class/dossier_transfert_xml_lixxbail.class.php');
        if(! class_exists('TFinTransfertBNP')) dol_include_once('/financement/class/dossier_transfert_xml_bnp.class.php');

        switch($fk_leaser) {
            case TFinTransfertLixxbail::fk_leaser:
                $obj = new TFinTransfertLixxbail($transfert);
                break;
            case TFinTransfertCMCIC::fk_leaser:
                $obj = new TFinTransfertCMCIC($transfert);
                break;
            case TFinTransfertBNP::fk_leaser:
                $obj = new TFinTransfertBNP($transfert);
                break;
        }

        return $obj;
    }
}