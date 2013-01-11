<?php

/*
 * Etend les fonctions de la table de base Dolibarr type_societe_commerciaux
 */
  
class TCommerciauxCpro extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'societe_commerciaux');
		parent::add_champs('fk_soc,fk_user','type=entier;');
		parent::add_champs('type_activite_cpro','type=chaine;index;');
		parent::start();
		parent::_init_vars();
		
	}
}