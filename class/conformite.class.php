<?php

class Conformite extends TObjetStd
{
    public $tablename = 'fin_conformite';

    const STATUS_WAITING_FOR_COMPLIANCE = 0;
    const STATUS_COMPLIANT = 1;
    const STATUS_NOT_COMPLIANT = 2;
    const STATUS_REFUSAL = 3;
    const STATUS_FIRST_CHECK = 4;

    public static $TStatus = array(
        0 => 'ConformiteWaitingForCompliance',
        1 => 'ConformiteCompliant',
        2 => 'ConformiteNotCompliant',
        3 => 'ConformiteRefusal',
        4 => 'ConformiteFirstCheck',
    );

    /**
     * @var int
     * @deprecated Use $id instead
     */
    public $rowid;
    public $id;
    public $fk_simulation;
    public $fk_user;
    public $status;
    public $entity;

    public $PDOdb;

    function __construct() {
        parent::set_table(MAIN_DB_PREFIX.$this->tablename);

        // Foreign keys
        parent::add_champs('fk_simulation,fk_user', array('type' => 'int', 'index' => true));

        parent::add_champs('status,entity', array('type' => 'int', 'index' => true));

        parent::start();

        $this->PDOdb = new TPDOdb;
    }

    function create() {
        return $this->save($this->PDOdb);
    }

    function update() { return $this->create(); }

    function fetch($id) {
        $this->load($this->PDOdb, $id);
    }

    function fetchBy($field, $value) {
        return $this->loadBy($this->PDOdb, $value, $field);
    }
}
