<?php

class Conformite extends TObjetStd
{
    public $tablename = 'fin_conformite';

    const STATUS_DRAFT = 0;
    const STATUS_WAITING_FOR_COMPLIANCE_N1 = 1;
    const STATUS_COMPLIANT_N1 = 2;
    const STATUS_NOT_COMPLIANT_N1 = 3;
    const STATUS_WAITING_FOR_COMPLIANCE_N2 = 4;
    const STATUS_COMPLIANT_N2 = 5;
    const STATUS_NOT_COMPLIANT_N2 = 6;

    public static $TStatus = array(
        0 => 'ConformiteDraft',
        1 => 'ConformiteWaitingForComplianceN1',
        2 => 'ConformiteCompliantN1',
        3 => 'ConformiteNotCompliantN1',
        4 => 'ConformiteWaitingForComplianceN2',
        5 => 'ConformiteCompliantN2',
        6 => 'ConformiteNotCompliantN2'
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

        if($this->status === self::STATUS_COMPLIANT_N2) {
            $mesg .= 'Bonjour'."\n\n";
            $mesg .= 'La conformité de la simulation S'.$refSimu.' est conforme';
        }
        elseif(in_array($this->status, array(self::STATUS_NOT_COMPLIANT_N1, self::STATUS_NOT_COMPLIANT_N2))) {  // Not compliant
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
