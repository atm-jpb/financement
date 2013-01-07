<?php
/*
 * Dossier
 */
class TFin_dossier extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table('llx_fin_dossier');
		parent::add_champs('fk_soc,duree,numero_prochaine_echeance','type=entier;');
		parent::add_champs('montant_prestation,montant,solde,echeance1,echeance,reste','type=float;');
		parent::add_champs('reference,periodicite,reglement,incident_paiement','type=chaine;');
		parent::add_champs('date_debut,date_fin,date_prochaine_echeance,date_relocation','type=date;');
		parent::start();
		parent::_init_vars();
		
		$this->TLien=array();
		
		$this->TPeriodicite=array(
			'MOIS'=>'Mensuel'
			,'TRIMESTRE'=>'Trimestriel'
			,'ANNEE'=>'Annuel'
		);
		
		$this->TReglement=array(
			'CHEQUE'=>'Chèque'
			,'VIREMENT'=>'Virement'
		);
		
		$this->TIncidentPaiement=array(
			'OUI'=>'Oui'
			,'NON'=>'Non'
		);
		
	}
	
	function loadReference(&$db, $reference, $annexe=false) {
		
		$db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference='".$reference."'");
		if($db->Get_line()) {
			return $this->load($db, $db->Get_field('rowid'), $annexe);
		}
		else {
			return false;
		}
		
	}
	function load(&$db, $id, $annexe=true) {
		
		$res = parent::load($db, $id);
		
		if($annexe) {
			$this->load_affaire($db);
		}
		
		return $res;
	}
	
	function load_affaire(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,$this->getId(),'llx_fin_dossier_affaire');
		
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->affaire->load($db, $id);
		}
		
	}
	function delete(&$db) {
		parent::delete($db);
		$db->dbdelete('llx_fin_dossier_affaire', $this->getId(), 'fk_fin_dossier');
	}
	function save(&$db) {
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->save($db);
		}
	}
}

/*
 * Lien dossier affaire
 */
class TFin_dossier_affaire extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table('llx_fin_dossier_affaire');
		parent::add_champs('fk_fin_affaire,fk_fin_dossier','type=entier;');
		parent::start();
		parent::_init_vars();
		
		$this->dossier = new TFin_dossier;
		$this->affaire=new TFin_affaire;
	}
}	