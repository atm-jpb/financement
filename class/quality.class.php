<?php

dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/user/class/user.class.php');


class TFin_QualityRule extends TObjetStd
{
	public $name;
	public $element_type;
	public $sql_filter;
	public $frequency_days;
	public $nb_tests;


	public function __construct()
	{
		global $langs;

		parent::__construct();

		$this->set_table(MAIN_DB_PREFIX . 'fin_quality_rule');

		$this->add_champs('name', array('type' => 'chaine', 'length' => 32));
		$this->add_champs('element_type', array('type' => 'chaine', 'length' => 32));
		$this->add_champs('sql_filter', array('type' => 'chaine', 'length' => 255));
		$this->add_champs('frequency_days', array('type' => 'integer', 'default' => 14));
		$this->add_champs('nb_tests', array('type' => 'integer', 'default' => 1));

		$this->TElementTypes = array(
			'fin_dossier' => $langs->trans('ListOfDossierFinancement')
			// , 'invoice' => $langs->trans('Invoices') // TODO if needed
		);

		$this->element_type = 'fin_dossier';
	}


	public function getAllElementsSelectable(&$PDOdb)
	{
		switch($this->element_type)
		{
			case 'invoice':
				$sql = ''; // TODO if needed
				break;

			case 'fin_dossier':
				$sql = 'SELECT d.rowid ' . $this->getCompleteSQLFilter() . ' GROUP BY d.rowid';
				break;
		}

		$resql = $PDOdb->Execute($sql);

		if($resql === false)
		{
			return false;
		}

		$TIDDossiers = array();

		while($result = $PDOdb->Get_line())
		{
			$TIDDossiers[] = intval($result->rowid);
		}

		return $TIDDossiers;
	}


	public function getNbElementsSelectable(&$PDOdb)
	{
		switch($this->element_type)
		{
			case 'invoice':
				$sql = ''; // TODO if needed
				break;

			case 'fin_dossier':
				$sql = 'SELECT COUNT(DISTINCT d.rowid) AS count ' . $this->getCompleteSQLFilter();
				break;
		}

		$resql = $PDOdb->Execute($sql);

		if($resql === false)
		{
			return false;
		}

		$result = $PDOdb->Get_line();
		return intval($result->count);
	}


	public function userHasRightToRead()
	{
		global $user;

		$canRead = false;

		switch($this->element_type)
		{
			case 'invoice':
				$canRead = ! empty($user->rights->facture->lire);
				break;

			case 'fin_dossier':
				$canRead = ! empty($user->rights->financement->affaire->read);
				break;
		}

		return $canRead;
	}


	private function getCompleteSQLFilter()
	{
		switch($this->element_type)
		{
			case 'invoice':
				return ''; // TODO if needed

			case 'fin_dossier':
			default:
				return 'FROM ' . MAIN_DB_PREFIX . 'fin_dossier d
						LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier)
						LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
						LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_dossier_financement dfc ON (dfc.fk_fin_dossier = d.rowid AND dfc.type = "CLIENT")
						LEFT JOIN ' . MAIN_DB_PREFIX . 'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type = "LEASER")
						WHERE a.entity IN (' . getEntity('fin_dossier', true) . ')
						AND (' . $this->sql_filter . ')';
		}
	}


	public function testIfValid(&$PDOdb)
	{
		return ($this->getNbElementsSelectable($PDOdb) !== false);
	}


	public function loadAnElement(&$PDOdb, $fk_element)
	{
		$element = new stdClass;
		$elementLoaded = false;

		switch($this->element_type)
		{
			case 'invoice':
				// TODO if needed
				break;

			case 'fin_dossier':
				$element = new TFin_dossier;
				$elementLoaded = $element->load($PDOdb, $fk_element);

				break;
		}

		$element->_loaded = $elementLoaded;

		return $element;
	}
}


class TFin_QualityTest extends TObjetStd
{
	public $fk_fin_quality_rule;
	public $quality_rule;
	public $fk_element;
	public $element;
	public $result;
	public $fk_user_tester;
	public $user_tester;
	public $TResults = array(
		'TODO' => 'À faire'
		, 'OK' => 'Validé'
		, 'KO' => 'Refusé'
	);


	public function __construct()
	{
		parent::__construct();

		$this->set_table(MAIN_DB_PREFIX . 'fin_quality_test');

		$this->add_champs('fk_fin_quality_rule', array('type' => 'int', 'index' => true));
		$this->add_champs('fk_element', array('type' => 'int'));
		$this->add_champs('date_test', array('type' => 'date'));
		$this->add_champs('result', array('type' => 'string', 'index' => true, 'default' => 'TODO'));
		$this->add_champs('comment', array('type' => 'text'));
		$this->add_champs('fk_user_tester', array('type' => 'int'));

		$this->result = 'TODO';
		$this->comment = '';
	}


	public function load(&$PDOdb, $id, $loadChildren = true)
	{
		$loadReturn = parent::load($PDOdb, $id, $loadChildren);

		if($loadChildren)
		{
			$this->loadRule($PDOdb);
			$this->loadElement($PDOdb);
			$this->loadTester();
		}

		return $loadReturn;
	}


	public function loadRule(&$PDOdb)
	{
		if($this->fk_fin_quality_rule <= 0)
		{
			return false;
		}

		$quality_rule = new TFin_QualityRule;
		$ruleLoaded = $quality_rule->load($PDOdb, $this->fk_fin_quality_rule);

		if($ruleLoaded)
		{
			$this->quality_rule = &$quality_rule;
		}

		return $ruleLoaded;
	}


	public function loadElement(&$PDOdb)
	{
		if($this->fk_element <= 0)
		{
			return false;
		}

		$element = $this->quality_rule->loadAnElement($PDOdb, $this->fk_element);

		if($element->_loaded)
		{
			$this->element = &$element;
		}

		return $element->_loaded;
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


	public function save(&$PDOdb)
	{
		if(empty($this->rowid))
		{
			if(! empty($this->fk_fin_quality_rule) && empty($this->element))
			{
				if(empty($this->quality_rule))
				{
					$this->loadRule($PDOdb);
				}

				$TIDElements = $this->quality_rule->getAllElementsSelectable($PDOdb);

				if(! empty($TIDElements))
				{
					$randomIndex = mt_rand(0, count($TIDElements) - 1);
					$this->fk_element = $TIDElements[$randomIndex];
				}
			}
		}

		return parent::save($PDOdb);
	}


	public function setResult(&$PDOdb, $result, $comment)
	{
		global $user;

		if(! $this->quality_rule->userHasRightToRead() || $this->result != 'TODO' || ! in_array($result, array_keys($this->TResults)))
		{
			return;
		}

		$this->result = $result;
		$this->comment = $comment;
		$this->date_test = dol_now();
		$this->fk_user_tester = $user->id;

		$saveID = $this->save($PDOdb);

		if($saveID !== false)
		{
			$this->loadTester();
		}
	}


	public function getLibResult($full = false)
	{
		$label = $this->TResults[$this->result];

		$picto = 'statut0';

		switch($this->result)
		{
			case 'OK':
				$picto = 'statut4';

				if($full)
				{
					$label.= ' par ' . $this->user_tester->getNomUrl(1) . ' le ' . dol_print_date($this->date_test, '%d/%m/%Y à %H:%M:%S');
				}

				break;

			case 'KO':
				$picto = 'statut8';

				if($full)
				{
					$label.= ' par ' . $this->user_tester->getNomUrl(1) . ' le ' . dol_print_date($this->date_test, '%d/%m/%Y à %H:%M:%S');
				}

				break;
		}

		return img_picto($label, $picto) . ' ' . $label;
	}
}

