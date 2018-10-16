<?php

dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/user/class/user.class.php');


class TFin_DossierQualityRule extends TObjetStd
{
	public $name;
	public $sql_filter;


	public function __construct()
	{
		parent::__construct();

		$this->set_table(MAIN_DB_PREFIX . 'fin_dossier_quality_rule');

		$this->add_champs('name', array('type' => 'chaine', 'length' => 32));
		$this->add_champs('sql_filter', array('type' => 'chaine', 'length' => 255));
	}


	public function getNbDossiersSelectable(TPDOdb &$PDOdb)
	{
		$resql = $PDOdb->Execute('SELECT COUNT(DISTINCT d.rowid) AS count ' . $this->getCompleteSQLFilter());

		if($resql === false)
		{
			return false;
		}

		$result = $PDOdb->Get_line();
		return intval($result->count);
	}


	private function getCompleteSQLFilter()
	{
		return 'FROM ' . MAIN_DB_PREFIX . 'fin_dossier d
				LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier)
				LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
				LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_dossier_financement dfc ON (dfc.fk_fin_dossier = d.rowid AND dfc.type = "CLIENT")
				LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type = "LEASER")
				WHERE a.entity IN (' . getEntity('fin_dossier', true) . ')
				AND (' . $this->sql_filter . ')';
	}


	public function testIfValid(&$PDOdb)
	{
		return ($this->getNbDossiersSelectable($PDOdb) !== false);
	}
}


class TFin_DossierQualityTest extends TObjetStd
{
	public $fk_fin_quality_rule;
	public $quality_rule;
	public $fk_fin_dossier;
	public $dossier;
	public $result;
	public $fk_user_tester;
	public $user_tester;
	public $TResults = array(
		'TODO' => 'À faire'
		, 'OK' => 'OK'
		, 'KO' => 'KO'
	);


	public function __construct()
	{
		parent::__construct();

		$this->set_table(MAIN_DB_PREFIX . 'fin_dossier_quality_test');

		$this->add_champs('fk_fin_dossier_quality_rule', array('type' => 'int', 'index' => true));
		$this->add_champs('fk_fin_dossier', array('type' => 'int'));
		$this->add_champs('result', array('type' => 'string', 'index' => true, 'default' => 'TODO'));
		$this->add_champs('fk_user_tester', array('type' => 'int'));
	}


	public function load(&$PDOdb, $id, $loadChildren = true)
	{
		$loadReturn = parent::load($PDOdb, $id, $loadChildren);

		if($loadChildren)
		{
			$this->loadRule($PDOdb);
			$this->loadDossier($PDOdb);
			$this->loadTester();
		}

		return $loadReturn;
	}


	public function loadRule(&$PDOdb)
	{
		if($this->fk_fin_dossier_quality_rule <= 0)
		{
			return false;
		}

		$quality_rule = new TFin_QualityRule;
		$ruleLoaded = $quality_rule->load($PDOdb, $this->fk_fin_dossier_quality_rule);

		if($ruleLoaded)
		{
			$this->quality_rule = &$quality_rule;
		}

		return $ruleLoaded;
	}


	public function loadDossier(&$PDOdb)
	{
		if($this->fk_fin_dossier <= 0)
		{
			return false;
		}

		$dossier = new TFin_dossier();
		$dossierLoaded = $dossier->load($PDOdb, $this->fk_fin_dossier);

		if($dossierLoaded)
		{
			$this->dossier = &$dossier;
		}

		return $dossierLoaded;
	}


	public function loadTester()
	{
		global $db;

		if($this->fk_user_tester <= 0)
		{
			return false;
		}

		$user_tester = new User($db);
		$userFetchReturn = $user_tester->fetch($this->fk_user_tester);
		$userLoaded = $userFetchReturn > 0;

		if($userLoaded)
		{
			$this->user_tester = &$user_tester;
		}

		return $userLoaded;
	}
}

