<?php

class TIntegrale extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_facture_integrale');
		parent::add_champs('facnumber','type=chaine;index;');

		parent::add_champs('label','type=chaine;');
		parent::add_champs('vol_noir_engage,vol_noir_realise,vol_noir_facture,vol_coul_engage,vol_coul_realise,vol_coul_facture','type=entier;');
		parent::add_champs('cout_unit_noir,cout_unit_coul,fas,fass,frais_dossier,frais_bris_machine,frais_facturation,total_ht_engage,total_ht_realise,total_ht_facture,ecart','type=float;');
		
		parent::start();
		parent::_init_vars();
		
		$this->total_ht_engage = 0;
		$this->total_ht_realise = 0;
		$this->total_frais = 0;
		$this->ecart = 0;
	}
	
	function load(&$db, $id, $annexe=false) {
		parent::load($db, $id);
		// Ce n'est plus utile de recalculer les totaux au load car maintenant toutes les données sont stockées
		// Le calcul des totaux et écart se fait juste avant le save
		//$this->calcule_totaux();
		
		if($annexe && !empty($this->facnumber)) {
			$this->load_annexe($db);
		}
	}
	
	
	function load_annexe(&$PDOdb) {
		global $db;
		
		dol_include_once('/compta/facture/class/facture.class.php');
		
		$this->facture = new Facture($db);
		$this->facture->fetch(0,$this->facnumber);
		
		$sql= "SELECT f.fk_soc, d.rowid";
		$sql.= " FROM llx_facture f";
		$sql.= " LEFT JOIN llx_element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
		$sql.= " LEFT JOIN llx_fin_dossier d ON (d.rowid = ee.fk_source AND ee.sourcetype = 'dossier')";
		$sql.= " WHERE f.facnumber = ".$this->facnumber;
		
		$PDOdb->Execute($sql);
		$PDOdb->Get_line();
		$idSoc = $PDOdb->Get_field('fk_soc');
		$idDoss = $PDOdb->Get_field('rowid');
		
		dol_include_once('/financement/class/dossier.class.php');
		dol_include_once('/financement/class/grille.class.php');
		
		if(!empty($idDoss)) {
			$this->dossier = new TFin_dossier();
			$this->dossier->load($PDOdb, $idDoss, false);
		}
		
		if(!empty($idSoc)) {
			$this->client = new Societe($db);
			$this->client->fetch($idSoc);
		}
	}
	
	function save(&$db) {
		$this->calcule_totaux();
		if(!empty($this->vol_noir_engage) || !empty($this->vol_noir_realise) || !empty($this->vol_coul_engage) || !empty($this->vol_coul_realise)) parent::save($db);
	}
	
	function calcule_totaux() {
		$this->total_frais = 0;
		$this->total_frais+= $this->fas;
		$this->total_frais+= $this->fass;
		$this->total_frais+= $this->frais_dossier;
		$this->total_frais+= $this->frais_bris_machine;
		$this->total_frais+= $this->frais_facturation;
		
		$this->total_ht_engage = $this->vol_noir_engage * $this->cout_unit_noir;
		$this->total_ht_engage+= (!empty($this->vol_coul_engage) ? $this->vol_coul_engage : $this->vol_coul_realise) * $this->cout_unit_coul;
		$this->total_ht_engage+= $this->total_frais;
		
		$this->total_ht_realise = $this->vol_noir_realise * $this->cout_unit_noir;
		$this->total_ht_realise+= $this->vol_coul_realise * $this->cout_unit_coul;
		$this->total_ht_realise+= $this->total_frais;
		
		$this->total_ht_facture = $this->vol_noir_facture * $this->cout_unit_noir;
		$this->total_ht_facture+= $this->vol_coul_facture * $this->cout_unit_coul;
		$this->total_ht_facture+= $this->total_frais;
		
		if($this->total_ht_engage > 0) {
			$this->ecart = ($this->total_ht_facture - $this->total_ht_engage) * 100 / $this->total_ht_engage;
			$this->ecart = round($this->ecart, 2);
		}
	}
}

