<?php

use Luracast\Restler\RestException;

define('INC_FROM_CRON_SCRIPT', true);
require_once __DIR__.'/../config.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

/**
 * API class for financement
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class financement extends DolibarrApi
{
    /**
     * @var TPDOdb $PDOdb {@type TPDOdb}
     */
    protected $PDOdb;

    /**
     * @var TFin_dossier $dossier {@type TFin_dossier}
     */
    public $dossier;

    /**
     * @var TSimulation $simulation {@type TSimulation}
     */
    public $simulation;

    /**
     * Constructor
     *
     */
    function __construct() {
        global $db, $langs;
        $langs->load('financement@financement');

        $this->db = $db;
        $this->PDOdb = new TPDOdb;
        $this->dossier = new TFin_dossier;
        $this->simulation = new TSimulation;
    }

    /**
     * Get contracts
     *
     * Get a list of contracts
     *
     * @param   int     $id             Id of contract
     * @param   string  $customerCode   Customer code related to contract
     * @param   string  $idprof2        Professional Id 2 of customer (SIRET)
     * @param   string  $entity         Entities of contract to search (comma separated)
     * @param   int     $ongoing        1 to only get ongoing contract, 0 otherwise
     * @return  array
     *
     * @url     GET /contract
     * @throws  RestException
     */
    public function getContract($id = null, $customerCode = null, $idprof2 = null, $entity = '1', $ongoing = 1) {
        if(is_null($id) && is_null($customerCode) && is_null($idprof2)) throw new RestException(400, 'No filter found');

        $TEntity = explode(',', $entity);
        foreach($TEntity as $e) if(! is_numeric($e)) throw new RestException(400, 'Wrong value for entity filter');

        $TDossier = array();
        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');
            $this->dossier->load_affaire($this->PDOdb);
            $this->dossier->TLien[0]->affaire->loadEquipement($this->PDOdb);

            $TDossier[] = $this->dossier;
        }
        else if(! is_null($customerCode)) {
            $TDossier = TFin_dossier::getContractFromThirdpartyInfo($this->PDOdb, $customerCode, null, $entity, $ongoing);
        }
        else {  // Id prof. 2
            $socstatic = new Societe($this->db);
            $socstatic->idprof2 = $idprof2;
            $socstatic->country_code = 'FR';
            if($socstatic->id_prof_check(2, $socstatic) < 0) throw new RestException(400, 'Incorrect value for idprof2 parameter');

            $TDossier = TFin_dossier::getContractFromThirdpartyInfo($this->PDOdb, null, $idprof2, $entity, $ongoing);
        }

        foreach($TDossier as &$dossier) $this->_cleanObjectDatas($dossier);

        return $TDossier;
    }

    /**
     * Get payments for one contract
     *
     * Get a list of payments for one contract
     *
     * @param int    $id        Id of contract
     * @param string $reference Reference of contract
     * @param int    $entity    Entity of contract to calculate payments
     * @return  array
     *
     * @throws RestException
     * @url     GET /payments
     */
    public function getPayments($id = null, $reference = null, $entity = 1) {
        if(is_null($id) && is_null($reference)) throw new RestException(400, 'No filter found');

        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');
        }
        else {
            $res = $this->dossier->loadReference($this->PDOdb, $reference, false, $entity);
            if($res === false) throw new RestException(404, 'Contract not found');
        }
        $this->dossier->load_affaire($this->PDOdb);

        $TRes = array();
        for($i = 1 ; $i <= $this->dossier->financement->duree ; $i++) {
            $TRes[] = $this->dossier->getSolde($this->PDOdb, 'SRCPRO', $i);
        }

        return $TRes;
    }

    function _cleanObjectDatas($object) {
        parent::_cleanObjectDatas($object);

        $object->affaire = $object->TLien[0]->affaire;
        $object->nature_financement = $object->affaire->nature_financement;
        $object->type_contrat = $object->affaire->contrat;
        $object->type_financement = $object->affaire->type_financement;
        $object->TAsset = $object->affaire->TAsset;

        $object->TEquipement = array();
        foreach($object->TAsset as $assetLink) {
            unset($assetLink->asset->table, $assetLink->asset->TChamps, $assetLink->asset->TConstraint, $assetLink->asset->TList, $assetLink->asset->assetType);
            $object->TEquipement[] = $assetLink->asset;
        }
        unset($object->TAsset);

        unset($object->table, $object->TChamps, $object->TConstraint, $object->TList);
        unset($object->affaire->table, $object->affaire->TChamps, $object->affaire->TConstraint, $object->affaire->TList, $object->affaire->TAsset);
        unset($object->affaire->societe);
        unset($object->financement->table, $object->financement->TChamps, $object->financement->TConstraint, $object->financement->TList);
        unset($object->financementLeaser->table, $object->financementLeaser->TChamps, $object->financementLeaser->TConstraint, $object->financementLeaser->TList);
        unset($object->TLien);

        return $object;
    }
}