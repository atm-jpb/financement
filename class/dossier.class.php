<?php
/*
 * Dossier
 */
class TFin_dossier extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier');
		parent::add_champs('solde','type=float;');
		parent::add_champs('reference','type=chaine;');
		parent::add_champs('date_relocation','type=date;');
			
		parent::start();
		parent::_init_vars();
		
		$this->somme_affaire = 0;
		
		$this->TLien=array();
		$this->financement=new TFin_financement;
		$this->financementLeaser=new TFin_financement;
		
		
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
		
		$this->load_financement($db);
		
		if($annexe) {
			$this->load_affaire($db);
		}
		
		return $res;
	}
	function load_financement(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_dossier_financement',array('fk_fin_dossier'=>$this->getId()));
		
		$somme_affaire = 0;
		foreach($Tab as $i=>$id) {
			$f=new TFin_financement;
			$f->load($db, $id);
			if($f->type=='LEASER') $this->financementLeaser = $f;
			else  $this->financement = $f;
		}
	}
	
	function load_affaire(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_dossier_affaire',array('fk_fin_dossier'=>$this->getId()));
		
		$somme_affaire = 0;
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->affaire->load($db, $this->TLien[$i]->fk_fin_affaire, false);
			
			$this->somme_affaire +=$this->TLien[$i]->affaire->montant;
		}
		
		$this->solde = $this->montant - $this->somme_affaire;
	}
	function deleteAffaire(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_affaire==$id) {
				$db->dbdelete('llx_fin_dossier_affaire', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				return true;
				
			}
		}		 
		
		return false;
	}
	function addAffaire(&$db, $id=null, $reference=null) {
		$affaire =new TFin_affaire;
		
		if((!is_null($id) && $affaire->load($db, $id)) 
		|| (!is_null($reference)  && $affaire->loadReference($db, $reference))) {
			/*
			 * Le dossier existe liaison
			 */
			//print_r($this->TLien);
			foreach($this->TLien as $k=>$lien) {
				if($lien->fk_fin_affaire==$affaire->getId()) {return false;}
			}		 
			 
			$i = count($this->TLien); 
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->fk_fin_dossier = $this->getId();
			$this->TLien[$i]->fk_fin_affaire = $affaire->getId();  
			 
			$this->TLien[$i]->affaire= $affaire;
			
		//	print_r($this->TLien[$i]);
		
			return true;
		}
		else {
			//exit('Echec');
			return false;
		}
		
	}
	function delete(&$db) {
		parent::delete($db);
		$db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $this->getId(), 'fk_fin_dossier');
		$db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_financement', $this->getId(), 'fk_fin_dossier');
	}
	function save(&$db) {
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->fk_fin_dossier = $this->getId();
			$lien->save($db);
		}
		
		if(isset($this->financementLeaser)) {
			$this->financementLeaser->fk_fin_dossier = $this->getId();
			$this->financementLeaser->type='LEASER';
			$this->financementLeaser->save($db);
		}
		$this->financement->fk_fin_dossier = $this->getId();
		$this->financement->type='CLIENT';
		$this->financement->save($db);	
	}
}

/*
 * Lien dossier affaire
 */
class TFin_dossier_affaire extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier_affaire');
		parent::add_champs('fk_fin_affaire,fk_fin_dossier','type=entier;');
		parent::start();
		parent::_init_vars();
		
		$this->dossier = new TFin_dossier;
		$this->affaire=new TFin_affaire;
	}
}	
/*
 * Financement Dossier 
 */ 
class TFin_financement extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier_financement');
		parent::add_champs('duree,numero_prochaine_echeance,fk_fin_dossier','type=entier;');
		parent::add_champs('montant_prestation,montant,echeance1,echeance,reste,taux, capital_restant','type=float;');
		parent::add_champs('reference,periodicite,reglement,incident_paiement,type','type=chaine;');
		parent::add_champs('date_debut,date_fin,date_prochaine_echeance','type=date;');
		parent::start();
		parent::_init_vars();
		
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
		
		$this->somme_affaire = 0;
		$this->periodicite = 'TRIMESTRE';
		$this->incident_paiement='NON';
	}
	
}