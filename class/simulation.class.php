<?php

/*
 * Etend les fonctions de la table de base Dolibarr type_societe_commerciaux
 */
  
class TSimulation extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
		parent::add_champs('entity,fk_soc,fk_user_author,fk_leaser','type=entier;');
		parent::add_champs('duree,opt_administration,opt_creditbail','type=entier;');
		parent::add_champs('montant,echeance,vr,coeff,cout_financement','type=float;');
		parent::add_champs('date_simul','type=date;');
		parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme,fk_type_contrat,accord','type=chaine;');
		parent::start();
		parent::_init_vars();
	}
	
	function init() {
		$this->opt_periodicite = 'opt_trimestriel';
		$this->opt_mode_reglement = 'opt_prelevement';
		$this->opt_terme = 'opt_aechoir';
		$this->vr = 0;
		$this->coeff = 0;
	}
}

