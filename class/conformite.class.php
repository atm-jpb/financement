<?php
dol_include_once('/financement/lib/financement.lib.php');

class Conformite extends TObjetStd
{
    public static $tablename = 'fin_conformite';

    public const STATUS_DRAFT = 0;
    public const STATUS_WAITING_FOR_COMPLIANCE_N1 = 1;
    public const STATUS_COMPLIANT_N1 = 2;
    public const STATUS_NOT_COMPLIANT_N1 = 3;
    public const STATUS_WAITING_FOR_COMPLIANCE_N2 = 4;
    public const STATUS_COMPLIANT_N2 = 5;
    public const STATUS_NOT_COMPLIANT_N2 = 6;
    public const STATUS_WITHOUT_FURTHER_ACTION = 7;

    public static $TStatus = array(
        self::STATUS_DRAFT => 'ConformiteDraft',
        self::STATUS_WAITING_FOR_COMPLIANCE_N1 => 'ConformiteWaitingForComplianceN1',
        self::STATUS_COMPLIANT_N1 => 'ConformiteCompliantN1',
        self::STATUS_NOT_COMPLIANT_N1 => 'ConformiteNotCompliantN1',
        self::STATUS_WAITING_FOR_COMPLIANCE_N2 => 'ConformiteWaitingForComplianceN2',
        self::STATUS_COMPLIANT_N2 => 'ConformiteCompliantN2',
        self::STATUS_NOT_COMPLIANT_N2 => 'ConformiteNotCompliantN2',
        self::STATUS_WITHOUT_FURTHER_ACTION => 'ConformiteWithoutFurtherAction'
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
    public $dateCalculSurfact;
    public $montantSurfact;
    public $montantFinanceLeaser;
    public $tauxLeaser;

    public $PDOdb;

    public function __construct() {
        parent::set_table(MAIN_DB_PREFIX.self::$tablename);

        // Foreign keys
        parent::add_champs('fk_simulation,fk_user', array('type' => 'int', 'index' => true));

        parent::add_champs('status,entity', array('type' => 'int', 'index' => true));
        parent::add_champs('commentaire,commentaire_adv', array('type' => 'text'));
        parent::add_champs('date_envoi,date_reception_papier', array('type' => 'date', 'index' => true));
        parent::add_champs('date_conformeN1', array('type' => 'date', 'index' => true));
        parent::add_champs('date_attenteN2,date_conformeN2', array('type' => 'date', 'index' => true));
        parent::add_champs('dateCalculSurfact', array('type' => 'date', 'index' => true));
        parent::add_champs('montantSurfact,montantFinanceLeaser,tauxLeaser', array('type' => 'float'));

        parent::start();

        $this->PDOdb = new TPDOdb;
    }

    public function init($entity, $fk_simulation): void {
        $this->fk_simulation = $fk_simulation;
        $this->status = self::STATUS_DRAFT;
        $this->entity = $entity;
    }

    /**
     * @param string $statusLabel
     * @return ?int
     */
    public static function getStatusFromLabel($statusLabel): ?int {
        global $user;

        $status = null;
        switch($statusLabel) {
            case 'draft':
                if(! empty($user->rights->financement->conformite->create)) $status = self::STATUS_DRAFT;
                break;
            case 'notCompliantN1':
                if(! empty($user->rights->financement->conformite->accept)) $status = self::STATUS_NOT_COMPLIANT_N1;
                break;
            case 'notCompliantN2':
                if(! empty($user->rights->financement->conformite->accept)) $status = self::STATUS_NOT_COMPLIANT_N2;
                break;
            case 'compliantN1':
                if(! empty($user->rights->financement->conformite->accept)) $status = self::STATUS_COMPLIANT_N1;
                break;
            case 'compliantN2':
                if(! empty($user->rights->financement->conformite->accept)) $status = self::STATUS_COMPLIANT_N2;
                break;
            case 'waitN1':
                if(! empty($user->rights->financement->conformite->validate)) $status = self::STATUS_WAITING_FOR_COMPLIANCE_N1;
                break;
            case 'waitN2':
                if(! empty($user->rights->financement->conformite->validate)) $status = self::STATUS_WAITING_FOR_COMPLIANCE_N2;
                break;
            case 'withoutFurtherAction':
                if(! empty($user->rights->financement->conformite->accept)) $status = self::STATUS_WITHOUT_FURTHER_ACTION;
                break;
            default:
                break;
        }

        return $status;
    }

    /**
     * @param int $status
     * @return int
     */
    public function setStatus($status): int {
        if(empty($status) || ! in_array($status, array_keys(self::$TStatus))) return -1;

        global $user;

        switch($status) {
            case self::STATUS_WAITING_FOR_COMPLIANCE_N1:
                $this->date_envoi = time();
                if($this->status === self::STATUS_DRAFT) $this->fk_user = $user->id;    // On save le user qui fait la demande
                break;
            case self::STATUS_COMPLIANT_N1:
                $this->date_conformeN1 = time();
                break;
            case self::STATUS_WAITING_FOR_COMPLIANCE_N2:
                $this->date_attenteN2 = time();
                break;
            case self::STATUS_COMPLIANT_N2:
                $this->date_conformeN2 = time();
                break;
        }

        $this->status = $status;
        return $this->update();
    }

    public function calculSurfact(): float {
        global $conf;

        $PDOdb = new TPDOdb;
        $s = new TSimulation;
        $s->load($PDOdb, $this->fk_simulation, false);
        $s->load_suivi_simulation($PDOdb);

        $oldEntity = $conf->entity;
        switchEntity($s->entity);

        /** @var TSimulationSuivi $suivi */
        foreach($s->TSimulationSuivi as $suivi) if($suivi->fk_leaser == $s->fk_leaser) break;

        // Cas spécifique Lixxbail Mandaté ne fait pas de 22T
        if($s->fk_leaser == 19483 && $s->duree == 22) $s->duree = 21;

        $surfact = $tauxLeaser = 0;
        if(! isLeaserLocPure($s->fk_leaser) && ! isLeaserAdosse($s->fk_leaser) && $conf->global->FINANCEMENT_SURFACT_CALCULATION_METHOD === 'same') {
            if(empty($suivi->montantfinanceleaser)) $s->calculMontantFinanceLeaser($PDOdb, $suivi);
            if(! empty($suivi->montantfinanceleaser)) $surfact = $suivi->montantfinanceleaser - $s->montant;
        }
        elseif(! isLeaserLocPure($s->fk_leaser) && ! isLeaserAdosse($s->fk_leaser) && $conf->global->FINANCEMENT_SURFACT_CALCULATION_METHOD === 'diff' && ! empty($conf->global->FINANCEMENT_SURFACT_PERCENT)) {
            $surfact = $s->montant * $conf->global->FINANCEMENT_SURFACT_PERCENT/100;
        }

        if(! empty($surfact)) {
            $coeffLine = $suivi->getCoefLineLeaser($PDOdb, $s->montant, $s->fk_type_contrat, $s->duree, $s->opt_periodicite);
            $tauxLeaser = $coeffLine['coeff'];
        }
        $this->montantSurfact = $surfact;
        $this->montantFinanceLeaser = $s->montant + $surfact;
        $this->dateCalculSurfact = time();
        $this->tauxLeaser = $tauxLeaser;

        $res = $this->update();

        if($res >= 0) { // On met à jour le montant financé leaser du dossier qui est lié à la simulation
            $d = new TFin_dossier;
            $d->load($PDOdb, $s->fk_fin_dossier);

            /** @var TFin_financement $f */
            $f = &$d->financementLeaser;
            $f->montant = $this->montantFinanceLeaser;
            $f->duree = $s->duree;
            $f->reste = TFin_financement::getVR($s->fk_leaser);
            $f->date_debut = getNextQuarter();

            if($conf->global->FINANCEMENT_SURFACT_CALCULATION_METHOD === 'same') $f->echeance = $d->financement->echeance;
            else $f->echeance = $this->montantFinanceLeaser * $tauxLeaser/100;

            $resLeaser = $f->save($PDOdb);

            $d->financement->frais_dossier = $conf->global->FINANCEMENT_CONFORMITE_FRAIS_DOSSIER;
            $d->financement->assurance = $conf->global->FINANCEMENT_CONFORMITE_ASSURANCE;

            $resClient = $d->financement->save($PDOdb);

            switchEntity($oldEntity);

            return $resLeaser && $resClient;    // Si un des 2 updates fail, on revoit false
        }

        switchEntity($oldEntity);

        return false;
    }

    public function create() {
        return $this->save($this->PDOdb);
    }

    public function update() { return $this->create(); }

    public function fetch($id) {
        return $this->load($this->PDOdb, $id);
    }

    public function fetchBy($field, $value) {
        return $this->loadBy($this->PDOdb, $value, $field);
    }

    /**
     * @param int $fk_soc
     * @return bool
     */
    public function sendMail($fk_soc) {
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

    public static function load_board($fk_status) {
        global $db, $conf, $langs;

        $nbWait = $nbDelayed = 0;

        $sql = "SELECT rowid, date_format(date_cre, '%Y-%m-%d') as date_cre";
        $sql.= ' FROM '.MAIN_DB_PREFIX.self::$tablename;
        $sql.= ' WHERE status = '.$db->escape($fk_status);
        $sql.= ' AND entity IN ('.getEntity('fin_simulation').')';

        $resql = $db->query($sql);
        if(! $resql || ! array_key_exists($fk_status, Conformite::$TStatus)) {
            dol_print_error($db);
            return -1;
        }

        while($obj = $db->fetch_object($resql)) {
            $nbWait++;

            $dateCre = strtotime($obj->date_cre);
            if(time() >= ($dateCre + $conf->global->FINANCEMENT_DELAY_CONFORMITE * 86400)) $nbDelayed++;
        }
        $db->free($resql);

        $r = new WorkboardResponse;
        $r->warning_delay = $conf->global->FINANCEMENT_DELAY_CONFORMITE;
        $r->label = $langs->trans(self::$TStatus[$fk_status].'Short');
        $r->url = dol_buildpath('/financement/conformite/list.php', 1).'?search_status='.$fk_status;
        $r->img = img_picto('', 'object_simul@financement');

        $r->nbtodo = $nbWait;
        $r->nbtodolate = $nbDelayed;

        return $r;
    }

    public static function getAll($TEntity, $limit = null) {
        global $db, $conf;

        if(empty($TEntity)) $TEntity[] = $conf->entity;

        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_conformite';
        $sql.= ' WHERE entity IN ('.implode(',', $TEntity).')';
        if(! empty($limit)) $sql .= ' LIMIT '.$db->escape($limit);

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        $TRes = array();
        while($obj = $db->fetch_object($resql)) {
            $c = new Conformite;
            $c->fetch($obj->rowid);

            $TRes[] = $c;
        }

        return $TRes;
    }
}
