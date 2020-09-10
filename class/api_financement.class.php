<?php

use Luracast\Restler\RestException;

define('INC_FROM_CRON_SCRIPT', true);
require_once __DIR__.'/../config.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/api_thirdparties.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/conformite.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');

/**
 * API class for financement
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Financement extends DolibarrApi
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

    /** @var Conformite $conformite */
    public $conformite;

    /**
     * Constructor
     *
     */
    public function __construct() {
        global $db, $langs;
        $langs->load('financement@financement');

        $this->db = $db;
        $this->PDOdb = new TPDOdb;
        $this->dossier = new TFin_dossier;
        $this->conformite = new Conformite;
        $this->simulation = new TSimulation;
    }

    /**
     * Get contracts
     *
     * Get a list of contracts
     *
     * @param   int     $id             Id of contract
     * @param   string  $reference      Reference of contract
     * @param   string  $customerCode   Customer code related to contract
     * @param   string  $idprof2        Professional Id 2 of customer (SIRET)
     * @param   string  $entity         Entity of contract to search
     * @param   int     $ongoing        1 to only get ongoing contract, 0 otherwise
     * @return  array|TFin_dossier
     *
     * @url     GET /contract
     * @throws  RestException
     */
    public function getContract($id = null, $reference = null, $customerCode = null, $idprof2 = null, $entity = null, $ongoing = 1) {
        if(is_null($id) && is_null($reference) && is_null($customerCode) && is_null($idprof2)) throw new RestException(400, 'No filter found');

        if(! is_null($entity)) {
            $TEntity = $this->getEntityFromCristal($entity);
            if(empty($TEntity)) throw new RestException(400, 'Wrong value for entity filter');
        }

        $TDossier = array();
        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');
            $this->dossier->load_affaire($this->PDOdb);
            $this->dossier->TLien[0]->affaire->loadEquipement($this->PDOdb);

            self::formatData($this->dossier);
            return $this->_cleanObjectDatas($this->dossier);
        }
        else if(! is_null($reference)) {
            $res = $this->dossier->loadReference($this->PDOdb, $reference, false, $TEntity);
            if($res === false) throw new RestException(404, 'Contract not found');
            $this->dossier->load_affaire($this->PDOdb);

            self::formatData($this->dossier);
            return $this->_cleanObjectDatas($this->dossier);
        }
        else if(! is_null($customerCode)) {
            $TDossier = TFin_dossier::getContractFromThirdpartyInfo($this->PDOdb, $customerCode, null, implode(',', $TEntity), $ongoing);
        }
        else {  // Id prof. 2
            $socstatic = new Societe($this->db);
            $socstatic->idprof2 = $idprof2;
            $socstatic->country_code = 'FR';
            if($socstatic->id_prof_check(2, $socstatic) < 0) throw new RestException(400, 'Incorrect value for idprof2 parameter');

            $TDossier = TFin_dossier::getContractFromThirdpartyInfo($this->PDOdb, null, $idprof2, implode(',', $TEntity), $ongoing);
        }

        foreach($TDossier as $dossier) {
            self::formatData($dossier);
            $this->_cleanObjectDatas($dossier);
        }

        return $TDossier;
    }

    /**
     * Get payments for one contract
     *
     * Get a list of payments for one contract
     *
     * @param int    $id        Id of contract
     * @param string $reference Reference of contract
     * @param string $entity    Entity of contract to calculate payments
     * @return  array
     *
     * @throws RestException
     * @url     GET /payments
     */
    public function getPayments($id = null, $reference = null, $entity = null) {
        if(is_null($id) && is_null($reference)) throw new RestException(400, 'No filter found');

        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');
        }
        else {
            $TEntity = $this->getEntityFromCristal($entity);
            if(empty($TEntity)) throw new RestException(400, 'Wrong value for entity filter');

            $res = $this->dossier->loadReference($this->PDOdb, $reference, false, $TEntity);
            if($res === false) throw new RestException(404, 'Contract not found');
        }
        $this->dossier->load_affaire($this->PDOdb);

        $TRes = array();
        if($this->dossier->nature_financement == 'EXTERNE') return $TRes;

        $e = $this->dossier->echeancier($PDOdb, 'CLIENT', 1, true, false);
        $iPeriodeClient = $this->dossier->financement->getiPeriode();
        $displaySolde = $this->dossier->get_display_solde();
        $soldePerso = round($this->dossier->calculSoldePerso($this->PDOdb), 2);

        for($i = 1 ; $i <= $this->dossier->financement->duree ; $i++) {
            $solde = $this->dossier->getSolde($this->PDOdb, 'SRCPRO', $i);
            $TDateStart = explode('/', $e['ligne'][$i-1]['date']);
            $date_start = mktime(null, null, null, $TDateStart[1], $TDateStart[0], $TDateStart[2]);
            $date_end = strtotime('+'.$iPeriodeClient.' month -1 day', $date_start);

            $TRes[] = array(
                'period' => $i,
                'payment' => $solde,
                'date_start' => date('Y-m-d', $date_start),
                'date_end' => date('Y-m-d', $date_end),
                'display' => ($displaySolde === 1),
                'retraitCopies' => $soldePerso
            );

            unset($date_start, $date_end, $TDateStart, $solde);
        }

        return $TRes;
    }

    /**
     * Get compliances
     *
     * Get a list of compliances
     *
     * @param int $entity
     * @param int $limit
     * @return  array
     *
     * @throws RestException
     * @url     GET /compliances
     */
    public function conformiteList($entity = 0, $limit = 100) {
        $dao = new DaoMulticompany($this->db);
        $res = $dao->fetch($entity);
        if($res <= 0) throw new RestException(400, 'Wrong value for entity filter');

        $TEntity = array($entity);
        $TRes = Conformite::getAll($TEntity, $limit);

        foreach($TRes as $c) {
            self::format($c);
            self::cleanData($c);
            parent::_cleanObjectDatas($c);
        }

        return $TRes;
    }

    /**
     * Get properties of a compliance object
     *
     * Return compliance informations
     *
     * @param int $id
     * @return Conformite
     *
     * @throws RestException
     * @url     GET /compliances/{id}
     */
    public function getConformite($id) {
        $res = $this->conformite->fetch($id);
        if(! $res) throw new RestException(404, 'Compliance not found');

        self::format($this->conformite);
        self::cleanData($this->conformite);
        parent::_cleanObjectDatas($this->conformite);

        return $this->conformite;
    }

    /**
     * @param int $id
     *
     * @throws RestException
     * @url     GET /compliances/{id}/
     */
    public function setConformiteStatus($id) {

    }

    protected function _cleanObjectDatas($object) {
        parent::_cleanObjectDatas($object);

        $object->affaire = $object->TLien[0]->affaire;
        $object->nature_financement = $object->affaire->nature_financement;
        $object->type_contrat = $object->affaire->contrat;
        $object->type_financement = $object->affaire->type_financement;
        $object->TAsset = $object->affaire->TAsset;
        $object->affaire->code_client = $object->affaire->societe->code_client;
        $object->client = $object->affaire->societe;

        $object->leaser = new Societe($this->db);
        $object->leaser->fetch($object->financementLeaser->fk_soc);

        if(! empty($object->client->date_creation)) $object->client->date_creation = date('Y-m-d', $object->client->date_creation);
        else $object->client->date_creation = null;
        if(! empty($object->client->date_modification)) $object->client->date_modification = date('Y-m-d', $object->client->date_modification);
        else $object->client->date_modification = null;

        if(! empty($object->leaser->date_creation)) $object->leaser->date_creation = date('Y-m-d', $object->leaser->date_creation);
        else $object->leaser->date_creation = null;
        if(! empty($object->leaser->date_modification)) $object->leaser->date_modification = date('Y-m-d', $object->leaser->date_modification);
        else $object->leaser->date_modification = null;


        $apiThirdparties = new Thirdparties;
        $apiThirdparties->_cleanObjectDatas($object->client);
        $apiThirdparties->_cleanObjectDatas($object->leaser);

        $object->TEquipement = array();
        foreach($object->TAsset as $assetLink) {
            self::cleanData($assetLink->asset);
            unset($assetLink->asset->assetType);

            $object->TEquipement[] = $assetLink->asset;
        }
        unset($object->TAsset, $object->TLien, $object->affaire->TAsset, $object->affaire->TLien, $object->affaire->societe);

        self::cleanData($object);
        self::cleanData($object->affaire);
        self::cleanData($object->financementLeaser);

        $object->financementLeaser->opt_periodicite = $object->financementLeaser->getiPeriode();

        if($object->nature_financement == 'INTERNE') {
            self::cleanData($object->financement);
            $object->financement->opt_periodicite = $object->financement->getiPeriode();
        }
        else unset($object->financement);   // Dans le cas d'un dossier Externe, le financement client n'est pas utile

        return $object;
    }

    private static function cleanData(&$object) {
        unset($object->TNoSaveVars, $object->withChild, $object->to_delete, $object->debug, $object->TChildObjetStd, $object->unsetChildDeleted, $object->errors);
        unset($object->date_0, $object->champs_indexe);
        unset($object->table, $object->TChamps, $object->TConstraint, $object->TList, $object->PDOdb);
    }

    private static function formatData(TFin_dossier &$object) {
        self::format($object);
        self::format($object->TLien[0]->affaire);
        foreach($object->TLien[0]->affaire->TAsset as $assetLink) self::format($assetLink->asset);
        self::format($object->financementLeaser);
        if($object->nature_financement == 'INTERNE') self::format($object->financement);
    }

    private static function format($object) {
        // To format these fields too
        $object->TChamps['date_cre'] = array('type' => 'date');
        $object->TChamps['date_maj'] = array('type' => 'date');

        $TBoolean = array(
            'incident_paiement',
            'okPourFacturation',
            'reloc',
            'relocOK',
            'intercalaireOK'
        );

        foreach($object->TChamps as $k => $v) {
            $type = array_shift($v);

            if($type == 'date') {
                if($object->$k <= 0) $object->$k = null;
                else $object->$k = date('Y-m-d', $object->$k);
            }
            else if(in_array($k, $TBoolean)) {
                $object->$k = ($object->$k == 'OUI');
            }
        }
    }

    private function getEntityFromCristal($entityCristal) {
        $sql = 'SELECT fk_object';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'entity_extrafields';
        $sql.= " WHERE entity_cristal LIKE '".$this->db->escape($entityCristal)."'";

        $resql = $this->db->query($sql);
        if(! $resql) {
            return false;
        }

        $TRes = array();
        while($obj = $this->db->fetch_object($resql)) $TRes[] = $obj->fk_object;

        return $TRes;
    }
}