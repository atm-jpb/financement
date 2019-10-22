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
    public $commentaire;

    public $PDOdb;

    function __construct() {
        parent::set_table(MAIN_DB_PREFIX.$this->tablename);

        // Foreign keys
        parent::add_champs('fk_simulation,fk_user', array('type' => 'int', 'index' => true));

        parent::add_champs('status,entity', array('type' => 'int', 'index' => true));
        parent::add_champs('commentaire', array('type' => 'text'));

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

    function sendMail() {
        global $conf, $db;

        $mesg = '';
        $refSimu = sprintf("%'.06d", $this->fk_simulation);

        if($this->status === self::STATUS_COMPLIANT) {
            $mesg .= 'Bonjour'."\n\n";
            $mesg .= 'La conformité de la simulation S'.$refSimu.' est conforme';
        }
        else {  // Not compliant
            $mesg .= 'Bonjour'."\n\n";
            $mesg .= 'La conformité de la simulation S'.$refSimu.' n\'est pas conforme';
        }

        $mesg .= ','."\n\n";
        $mesg .= 'Cordialement,'."\n\n";
        $mesg .= 'La cellule financement'."\n\n";

        $subject = 'Conformite S'.$refSimu;

        $user = new User($db);
        $user->fetch($this->fk_user);

        $old_entity = $conf->entity;
        switchEntity($this->entity);

        $r = new TReponseMail($conf->global->MAIN_MAIL_EMAIL_FROM, $user->email, $subject, $mesg);
        $res = $r->send(false);

        switchEntity($old_entity);


        if($res !== false) setEventMessage('Email envoyé à : '.$user->email);
    }
}
