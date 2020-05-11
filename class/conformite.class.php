<?php

class Conformite extends TObjetStd
{
    public static $tablename = 'fin_conformite';

    const STATUS_DRAFT = 0;
    const STATUS_WAITING_FOR_COMPLIANCE_N1 = 1;
    const STATUS_COMPLIANT_N1 = 2;
    const STATUS_NOT_COMPLIANT_N1 = 3;
    const STATUS_WAITING_FOR_COMPLIANCE_N2 = 4;
    const STATUS_COMPLIANT_N2 = 5;
    const STATUS_NOT_COMPLIANT_N2 = 6;
    const STATUS_WITHOUT_FURTHER_ACTION = 7;

    public static $TStatus = array(
        0 => 'ConformiteDraft',
        1 => 'ConformiteWaitingForComplianceN1',
        2 => 'ConformiteCompliantN1',
        3 => 'ConformiteNotCompliantN1',
        4 => 'ConformiteWaitingForComplianceN2',
        5 => 'ConformiteCompliantN2',
        6 => 'ConformiteNotCompliantN2',
        7 => 'ConformiteWithoutFurtherAction'
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
    public $commentaire_adv;
    public $date_envoi;
    public $date_reception_papier;
    public $date_conformeN1;
    public $date_attenteN2;
    public $date_conformeN2;

    public $PDOdb;

    function __construct() {
        parent::set_table(MAIN_DB_PREFIX.self::$tablename);

        // Foreign keys
        parent::add_champs('fk_simulation,fk_user', array('type' => 'int', 'index' => true));

        parent::add_champs('status,entity', array('type' => 'int', 'index' => true));
        parent::add_champs('commentaire,commentaire_adv', array('type' => 'text'));
        parent::add_champs('date_envoi,date_reception_papier', array('type' => 'date', 'index' => true));
        parent::add_champs('date_conformeN1', array('type' => 'date', 'index' => true));
        parent::add_champs('date_attenteN2,date_conformeN2', array('type' => 'date', 'index' => true));

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

    /**
     * @return bool
     */
    function sendMail($fk_soc) {
        if(empty($fk_soc)) return false;

        global $conf, $db, $langs;

        $client = new Societe($db);
        $client->fetch($fk_soc);

        $refSimu = sprintf("%'.06d", $this->fk_simulation);
        $subject = $langs->transnoentities(self::$TStatus[$this->status]).' S'.$refSimu.' '.$client->name;

        $mesg = 'Bonjour,'."\n\n";
        switch($this->status) {
            case self::STATUS_COMPLIANT_N1:
                $mesg .= 'Ce dossier est conforme.'."\n";
                $mesg .= 'Vous pouvez dès aujourd\'hui l\'envoyer par courrier à l\'attention du service financement.';
                break;
            case self::STATUS_COMPLIANT_N2:
                $mesg .= 'Ce dossier est conforme.';
                break;
            case self::STATUS_NOT_COMPLIANT_N1:
            case self::STATUS_NOT_COMPLIANT_N2:
                $mesg .= 'Ce dossier n\'est pas accepté pour le ou les motifs suivants :'."\n\n";
                $mesg .= $this->commentaire;
                break;
        }

        $mesg .= "\n\n";
        $mesg .= 'Cordialement,'."\n";
        $mesg .= 'La cellule financement'."\n\n";

        $user = new User($db);
        $user->fetch($this->fk_user);
        if(! isValidEmail($user->email)) return false;

        $old_entity = $conf->entity;
        switchEntity($this->entity);

        $r = new TReponseMail($conf->global->MAIN_MAIL_EMAIL_FROM, $user->email, $subject, $mesg);
        $res = $r->send(false);

        switchEntity($old_entity);

        return $res !== false;
    }

    public static function add($a, $b) { return $a + $b; }
}
