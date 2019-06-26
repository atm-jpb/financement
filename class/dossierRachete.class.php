<?php

/*
 * Dossier
 */

class DossierRachete extends TObjetStd
{
    public static $tablename = 'fin_dossier_rachete';

    /**
     * @var TPDOdb
     */
    protected $PDOdb;

    /**
     * @var int
     * @deprecated Use $id instead
     */
    public $rowid;
    public $id;
    public $fk_dossier;
    public $fk_leaser;
    public $fk_simulation;
    public $ref_simulation;
    public $num_contrat;
    public $num_contrat_leaser;
    public $retrait_copie_sup;
    public $decompte_copies_sup;
    public $date_debut_periode_leaser;
    public $date_fin_periode_leaser;
    public $solde_banque_a_periode_identique;
    public $type_contrat;
    public $duree;
    public $echeance;
    public $loyer_actualise;
    public $date_debut;
    public $date_fin;
    public $date_prochaine_echeance;
    public $numero_prochaine_echeance;
    public $terme;
    public $reloc;
    public $maintenance;
    public $assurance;
    public $assurance_actualise;
    public $montant;
    public $date_debut_periode_client_m1;
    public $date_fin_periode_client_m1;
    public $solde_vendeur_m1;
    public $solde_banque_m1;
    public $date_debut_periode_client;
    public $date_fin_periode_client;
    public $solde_vendeur;
    public $solde_banque;
    public $date_debut_periode_client_p1;
    public $date_fin_periode_client_p1;
    public $solde_vendeur_p1;
    public $solde_banque_p1;
    public $choice;

    function __construct() { /* declaration */
        parent::set_table(MAIN_DB_PREFIX.self::$tablename);

        // Foreign keys
        parent::add_champs('fk_dossier,fk_leaser,fk_simulation', array('type' => 'int', 'index' => true));

        parent::add_champs('ref_simulation,num_contrat,num_contrat_leaser', array('type' => 'chaine'));
        parent::add_champs('retrait_copie_sup,decompte_copies_sup', array('type' => 'int'));
        parent::add_champs('date_debut_periode_leaser,date_fin_periode_leaser', array('type' => 'date'));
        parent::add_champs('solde_banque_a_periode_identique', array('type' => 'float'));
        parent::add_champs('type_contrat', array('type' => 'int'));
        parent::add_champs('duree', array('type' => 'chaine', 'length' => 10));
        parent::add_champs('echeance', array('type' => 'int'));
        parent::add_champs('loyer_actualise', array('type' => 'float'));
        parent::add_champs('date_debut,date_fin,date_prochaine_echeance', array('type' => 'date'));
        parent::add_champs('numero_prochaine_echeance', array('type' => 'chaine'));
        parent::add_champs('terme', array('type' => 'chaine', 'length' => 10));
        parent::add_champs('reloc', array('type' => 'chaine', 'length' => 5));
        parent::add_champs('maintenance,assurance,assurance_actualise,montant', array('type' => 'float'));

        // Prev
        parent::add_champs('date_debut_periode_client_m1,date_fin_periode_client_m1', array('type' => 'date'));
        parent::add_champs('solde_vendeur_m1,solde_banque_m1', array('type' => 'float'));

        // Curr
        parent::add_champs('date_debut_periode_client,date_fin_periode_client', array('type' => 'date'));
        parent::add_champs('solde_vendeur,solde_banque', array('type' => 'float'));

        // Next
        parent::add_champs('date_debut_periode_client_p1,date_fin_periode_client_p1', array('type' => 'date'));
        parent::add_champs('solde_vendeur_p1,solde_banque_p1', array('type' => 'float'));

        parent::add_champs('choice', array('type' => 'chaine', 'length' => 5));

        parent::start();
        parent::_init_vars();

        $this->PDOdb = new TPDOdb;
    }

    public function create() {
        return $this->save($this->PDOdb);
    }

    public function fetch($id) {
        $this->load($this->PDOdb, $id);
    }

    public function update() {
        return $this->save($this->PDOdb);
    }
}