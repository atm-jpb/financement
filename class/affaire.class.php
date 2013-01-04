<?php

class TFin_affaire extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table('llx_fin_affaire');
		parent::add_champs('reference,nature_financement,contrat,type_financement,type_materiel','type=chaine;');
		parent::add_champs('date_affaire','type=date;');
		parent::add_champs('fk_soc','type=entier;index;');
		parent::start();
		parent::_init_vars();
		
		$this->TLien=array();
		
		$this->TContrat=array();
		$this->TTypeFinancement=array(//TODO
			'PURE'=>'Location Pure'
		);
		
		$this->TTypeMateriel=array(); //TODO
		$this->TNatureFinancement=array(
			'INTERNE'=>'Interne'
			,'EXTERNE'=>'Externe'
		);
	}
	
	function load(&$db, $id, $annexe=true) {
		
		parent::load($db, $id);
		$this->load_type_contrat($db);
		
		if($annexe) {
			$this->load_dossier($db);
			
		}
		
	}
	function load_type_contrat(&$db) {
		//TODO
		
	}
	function load_dossier(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,'llx_fin_dossier_affaire',array('fk_affaire'=>$this->id));
		
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->dossier->load($db, $id);
		}
		
	}
	
	function save(&$db) {
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->save($db);
		}
	}
	
	function addDossier(&$db, $id=null, $reference=null) {
		$dossier =new TFin_dossier;
		
		if((!is_null($id) && $dossier->load($db, $id)) 
		|| (!is_null($reference)  && $dossier->loadReference($db, $reference))) {
			/*
			 * Le dossier existe liaison
			 */
			foreach($this->TLien as &$lien) {
				if($lien->fk_dossier==$dossier->id) return false;
			}		 
			 
			$i = count($this->TLien); 
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->fk_affaire = $this->id;
			$this->fk_dossier = $dossier->id;  
			 
		}
		
	}
	
	function loadReference(&$db, $reference) {
		
		$db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference='".$reference."'");
		if($db->Get_line()) {
			return $this->load($db, $db->Get_field('rowid'));
		}
		else {
			return false;
		}
		
	}
	
}