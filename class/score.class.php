<?php

class TScore extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_score');
		parent::add_champs('fk_soc,fk_user_author,fk_import','type=entier;');
		
		parent::add_champs('effectif,score,rotation_bfr,delai_client,delai_fourn,vetustes_actifs_immo','type=entier;');
		
		parent::add_champs('encours_conseille,ca,resultat_exploitation,charges_financieres,resultat_net,capitaux_propres,marge_exploitation,marge_nette,ebe','type=float;');
		parent::add_champs('poids_ca_capitaux_propres,renta_capital,caf,autonomie_financiere,capacite_remboursement,solvabilite_gen','type=float;');
		parent::add_champs('liquidite_gen,liquidite_red,charges_fin_ca,endettement,surface_fin,bfr','type=float;');
		
		parent::add_champs('date_score,date_creation_entreprise,date_cloture_compte,date_derniere_publi','type=date;');
		
		parent::add_champs('siren,code_client_externe,raison_sociale_externe,adresse1_externe,adresse2_externe,cp_externe,ville_externe','type=chaine;');
		parent::add_champs('naf,libelle_activite,forme_juridique,civilite_externe,nom_externe,prenom_externe,fonction_externe','type=chaine;');
		parent::add_champs('raison_sociale_groupe,siren_groupe,naf_groupe,adresse_goupe,cp_groupe,ville_groupe,pays_groupe','type=chaine;');
		parent::add_champs('raison_sociale_maison_mere,siren_maison_mere,naf_maison_mere,adresse_maison_mere,cp_maison_mere,ville_maison_mere,pays_maison_mere','type=chaine;');
		parent::add_champs('derniere_publi,source','type=chaine;');
		
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

	function get_nom_externe() {
		$TInfos = array();
		if(!empty($this->civilite_externe)) $TInfos[] = $this->civilite_externe;
		if(!empty($this->prenom_externe)) $TInfos[] = $this->prenom_externe;
		if(!empty($this->nom_externe)) $TInfos[] = $this->nom_externe;
		if(!empty($this->fonction_externe)) $TInfos[] = '('.$this->fonction_externe.')'; 
		
		return implode(' ', $TInfos);
	}
}

