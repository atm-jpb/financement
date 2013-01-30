<?php

/*
 * Etend les fonctions de la table de base Dolibarr type_societe_commerciaux
 */
  
class TCommercialCpro extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'societe_commerciaux');
		parent::add_champs('fk_soc,fk_user','type=entier;');
		parent::add_champs('type_activite_cpro','type=chaine;index;');
		parent::start();
		parent::_init_vars();
		
	}
	function loadUserClient(&$db, $fk_user, $fk_soc) {
		
		$db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE fk_user='".$fk_user."' AND fk_soc=".$fk_soc);
		if($db->Get_line()) {
			return $this->load($db, $db->Get_field('rowid'));
		}
		else {
			return false;
		}
		
	}
}