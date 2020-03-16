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
     * @param   int     $entity         Entity of contract
     * @param   int     $ongoing        1 to only get ongoing contract, 0 otherwise
     * @return  array
     *
     * @url     GET /contract
     * @throws  RestException
     */
    function getContract($id = null, $customerCode = null, $idprof2 = null, $entity = 1, $ongoing = 1) {
        if(is_null($id) && is_null($customerCode) && is_null($idprof2)) throw new RestException(400, 'No filter found');

        if(! is_null($id)) {
            $res = $this->dossier->load($this->PDOdb, $id, false);
            if($res === false) throw new RestException(404, 'Contract not found');

            // TODO: Continue
        }
        else if(! is_null($customerCode)) {
            $TDossier = TFin_dossier::getContractFromThirdpartyInfo($this->PDOdb, $customerCode, null, $entity, $ongoing);
        }
        else {  // Id prof. 2
            // TODO: à vérifier
            TFin_dossier::getContractFromThirdpartyInfo($this->PDOdb, null, $idprof2, $entity, $ongoing);
        }
        return $TDossier;
    }
}