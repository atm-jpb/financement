<?php

class Conformite extends TObjetStd
{
    public $tablename = 'fin_conformite';
    /**
     * @var int
     * @deprecated Use $id instead
     */
    public $rowid;
    public $id;
    public $fk_simulation;
    public $fk_user;
    public $status;
    public $PDOdb;

    const STATUS_WAITING_FOR_COMPLIANCE = 0;
    const STATUS_COMPLIANT = 1;
    const STATUS_NOT_COMPLIANT = 2;
    const STATUS_REFUSAL = 3;
    const STATUS_FIRST_CHECK = 4;

    function __construct() {
        parent::set_table(MAIN_DB_PREFIX.$this->tablename);

        // Foreign keys
        parent::add_champs('fk_simulation,fk_user', array('type' => 'int', 'index' => true));

        parent::add_champs('status', array('type' => 'int'));

        parent::start();

        $this->PDOdb = new TPDOdb;
    }
}
