<?php

class TIntegrale extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_facture_integrale');
		parent::add_champs('facnumber','type=chaine;index');

		parent::add_champs('label','type=chaine;');
		parent::add_champs('vol_noir_engage,vol_noir_realise,vol_coul_engage,vol_coul_realise','type=entier;');
		parent::add_champs('cout_unit_noir,cout_unit_coul,fas,fass,frais_dossier,frais_bris_machine,frais_facturation,total_ht_engage,total_ht_realise,ecart','type=float;');
		
		parent::start();
		parent::_init_vars();
		
		$this->total_ht_engage = 0;
		$this->total_ht_realise = 0;
		$this->total_frais = 0;
		$this->ecart = 0;
	}
	
	function save(&$db) {
		$this->calcule_totaux();
		parent::save($db);
	}
	
	function calcule_totaux() {
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
		
		if($this->total_ht_realise > 0) {
			$this->ecart = ($this->total_ht_realise - $this->total_ht_engage) * 100 / $this->total_ht_realise;
			$this->ecart = round($this->ecart, 2);
		}
	}
}

