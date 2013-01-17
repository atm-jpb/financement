<?php

/*
 * Etend les fonctions de la table de base Dolibarr type_societe_commerciaux
 */
  
class TSimulation extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
		parent::add_champs('fk_soc,fk_user_author,fk_type_contrat,fk_leaser','type=entier;');
		parent::add_champs('duree,opt_administration,opt_creditbail','type=entier;');
		parent::add_champs('montant,echance,vr,coeff','type=float;');
		parent::add_champs('date_simul','type=date;');
		parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme','type=chaine;index;');
		parent::start();
		parent::_init_vars();
	}
}

