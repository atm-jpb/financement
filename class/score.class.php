<?php

class TScore extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_score');
		parent::add_champs('fk_soc,fk_user_author,fk_import','type=entier;');
		parent::add_champs('score','type=entier;');
		parent::add_champs('encours_conseille,ca,resultat_net,marge_exploitation,marge_nette,ebe,caf,autonomie_fin,capacite_remb,solvabilite_gen,
								liquidite_gen,liquidite_red,charges_fin_ca,endettement,surface_fin,bfr,rotation_bfr,delai_client,delai_fourn','type=float;');
		parent::add_champs('date_score,date_cloture,date_derniere_publi','type=date;');
		parent::add_champs('dirigeant_mandataire,derniere_publi,source','type=chaine;');
		parent::start();
		parent::_init_vars();
	}
	
	function load_by_soc(&$db, $fk_soc) {
		$sql = "SELECT ".OBJETSTD_MASTERKEY;
		$sql.= " FROM ".$this->get_table();
		$sql.= " WHERE fk_soc = ".$fk_soc;
		$sql.= " ORDER BY date_score DESC";
		$sql.= " LIMIT 1";

		$db->Execute($sql);
		
		if($db->Get_line()) {
			return $this->load($db, $db->Get_field('rowid'));
		}
		else {
			return false;
		}	
	}
}

