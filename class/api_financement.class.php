<?php

use Luracast\Restler\RestException;

define('INC_FROM_CRON_SCRIPT', true);
require_once __DIR__.'/../config.php';
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

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
     * @param   int     $entity         Entity of contract to search
     * @param   int     $ongoing        1 to only get ongoing contract, 0 otherwise
     * @return  array
     *
     * @url     GET /contract
     * @throws  RestException
     */
    public function getContract($id = null, $customerCode = null, $idprof2 = null, $entity = 1, $ongoing = 1) {
        if(is_null($id) && is_null($customerCode) && is_null($idprof2)) throw new RestException(400, 'No filter found');

        $TDossier = array();
        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');
            $this->dossier->load_affaire($this->PDOdb);
            $this->dossier->TLien[0]->affaire->loadEquipement($this->PDOdb);

            unset($this->dossier->table, $this->dossier->TChamps, $this->dossier->TConstraint, $this->dossier->TList);
            unset($this->dossier->TLien[0]->table, $this->dossier->TLien[0]->TChamps, $this->dossier->TLien[0]->TConstraint, $this->dossier->TLien[0]->TList);
            unset($this->dossier->TLien[0]->dossier);
            unset($this->dossier->TLien[0]->affaire->table, $this->dossier->TLien[0]->affaire->TChamps, $this->dossier->TLien[0]->affaire->TConstraint, $this->dossier->TLien[0]->affaire->TList, $this->dossier->TLien[0]->affaire->societe->fields, $this->dossier->TLien[0]->affaire->societe->db);
            unset($this->dossier->TLien[0]->affaire->table, $this->dossier->TLien[0]->affaire->TChamps, $this->dossier->TLien[0]->affaire->TConstraint, $this->dossier->TLien[0]->affaire->TList);
            unset($this->dossier->financement->table, $this->dossier->financement->TChamps, $this->dossier->financement->TConstraint, $this->dossier->financement->TList);
            unset($this->dossier->financementLeaser->table, $this->dossier->financementLeaser->TChamps, $this->dossier->financementLeaser->TConstraint, $this->dossier->financementLeaser->TList);

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

        return $TDossier;
    }

    /**
     * Get payments for one contract
     *
     * Get a list of contracts
     *
     * @param   int     $id         Id of contract
     * @param   string  $reference  Customer code related to contract
     * @return  array
     *
     * @url     GET /payments
     * @throws  RestException
     */
    public function getPayments($id, $reference) {
        if(is_null($id) && is_null($reference)) throw new RestException(400, 'No filter found');

        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');

            // TODO: Continue
//            $this->dossier->getSolde()
        }
        else {

        }
    }
}