<?php

class TFin_affaire extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table('llx_fin_affaire');
		parent::add_champs('reference,nature_financement,contrat,type_financement,type_materiel','type=chaine;');
		parent::add_champs('date_affaire','type=date;');
		parent::add_champs('fk_soc','type=entier;index;');
		parent::add_champs('montant,solde','type=float;');
		
		parent::_init_vars();
		parent::start();
		
		$this->TLien=array();
		
		$this->TContrat=array();
		$this->TTypeFinancement=array(//TODO
			'PURE'=>'Location Pure'
		);
		
		$this->TTypeMateriel=array(); 
		$this->TNatureFinancement=array(
			'INTERNE'=>'Interne'
			,'EXTERNE'=>'Externe'
		);
		
		$this->somme_dossiers=0;
	}
	
	function load(&$db, $id, $annexe=true) {
		
		$res = parent::load($db, $id);
		$this->loadTypeContrat($db);
		
		if($annexe) {
			$this->loadDossier($db);
			
		}
		
		return $res;
	}
	function loadTypeContrat(&$db) {
		global $langs;
		$langs->load('financement@financement');
		$db->Execute("SELECT code FROM llx_fin_const WHERE type='type_contrat'");
		while($db->Get_line()){
			$this->TContrat[$db->Get_field('code')] = $langs->trans( $db->Get_field('code') );
		}
	}
	function loadDossier(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,'llx_fin_dossier_affaire',array('fk_fin_affaire'=>$this->getId()));
		
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->dossier->load($db, $this->TLien[$i]->fk_fin_dossier, false);
			
			$this->somme_dossiers += $this->TLien[$i]->dossier->montant;
		}
		
		$this->solde = $this->montant - $this->somme_dossiers;
	}
	function delete(&$db) {
		parent::delete($db);
		$db->dbdelete('llx_fin_dossier_affaire', $this->getId(), 'fk_fin_affaire' );
	}
	function save(&$db) {
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->fk_fin_affaire = $this->getId();	
			$lien->save($db);
		}
	}
	function deleteDossier(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_dossier==$id) {
				$db->dbdelete('llx_fin_dossier_affaire', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				return true;
				
			}
		}		 
		
		return false;
	}
	function addDossier(&$db, $id=null, $reference=null) {
		$dossier =new TFin_dossier;
		
		if((!is_null($id) && $dossier->load($db, $id)) 
		|| (!is_null($reference)  && $dossier->loadReference($db, $reference))) {
			/*
			 * Le dossier existe liaison
			 */
			//print_r($this->TLien);
			foreach($this->TLien as $k=>$lien) {
				if($lien->fk_fin_dossier==$dossier->getId()) {return false;}
			}		 
			 
			$i = count($this->TLien); 
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->fk_fin_affaire = $this->rowid;
			$this->TLien[$i]->fk_fin_dossier = $dossier->rowid;  
			 
			$this->TLien[$i]->dossier= $dossier;
			
		//	print_r($this->TLien[$i]);
		
			return true;
		}
		else {
			//exit('Echec');
			return false;
		}
		
	}
	function deleteEquipement(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_dossier==$id) {
				$db->dbdelete('llx_fin_dossier_affaire', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				return true;
				
			}
		}		 
		
		return false;
	}
	function addEquipement(&$db, $id=null, $reference=null) {
		$dossier =new TFin_dossier;
		
		if((!is_null($id) && $dossier->load($db, $id)) 
		|| (!is_null($reference)  && $dossier->loadReference($db, $reference))) {
			/*
			 * Le dossier existe liaison
			 */
			//print_r($this->TLien);
			foreach($this->TLien as $k=>$lien) {
				if($lien->fk_fin_dossier==$dossier->getId()) {return false;}
			}		 
			 
			$i = count($this->TLien); 
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->fk_fin_affaire = $this->rowid;
			$this->TLien[$i]->fk_fin_dossier = $dossier->rowid;  
			 
			$this->TLien[$i]->dossier= $dossier;
			
		//	print_r($this->TLien[$i]);
		
			return true;
		}
		else {
			//exit('Echec');
			return false;
		}
		
	}
	
	function loadReference(&$db, $reference,$annexe=false) {
		
		$db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference='".$reference."'");
		if($db->Get_line()) {
			return $this->load($db, $db->Get_field('rowid'), $annexe);
		}
		else {
			return false;
		}
		
	}
	
}