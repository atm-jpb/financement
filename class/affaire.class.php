<?php

class TFin_affaire extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'fin_affaire');
		parent::add_champs('reference,nature_financement,contrat,type_financement,type_materiel','type=chaine;');
		parent::add_champs('date_affaire','type=date;');
		parent::add_champs('fk_soc,entity','type=entier;index;');//fk_soc_leaser
		parent::add_champs('montant,solde','type=float;');
		
		parent::_init_vars();
		parent::start();
		
		$this->TLien=array();
		$this->TCommercial=array();
		$this->TAsset=array();
		
		$this->TContrat=array(
			'LOCSIMPLE'=>$langs->trans('LocSimple')
			,'FORFAITGLOBAL'=>$langs->trans('ForfaitGlobal')
			,'INTEGRAL'=>$langs->trans('Integral')
		);
		$this->TTypeFinancement=array(//TODO
			'PURE'=>'Location Pure'
			,'ADOSSEE'=>'Location adossée'
			,'MANDATEE'=>'Location mandatée'
			,'FINANCIERE'=>'Location financière'
		);
		
		$this->TTypeMateriel=array(); 
		$this->TNatureFinancement=array(
			'INTERNE'=>'Interne'
			,'EXTERNE'=>'Externe'
		);
		
		$this->TPeriodicite=array(
			'opt_trimestriel'=>$langs->trans('opt_trimestriel')
			,'opt_mensuel'=>$langs->trans('opt_mensuel')
		);
		
		
		$this->TTerme=array(
			'opt_a_echoir'=>$langs->trans('opt_a_echoir')
			,'opt_echu'=>$langs->trans('opt_echu')
		);
		
		$this->somme_dossiers=0;
	}
	
	function load(&$db, $id, $annexe=true) {
		
		$res = parent::load($db, $id);
		
		if($annexe) {
			$this->loadDossier($db);
			$this->loadCommerciaux($db);	
			$this->loadEquipement($db);	
		}
		
		$this->calculSolde();
		
		return $res;
	}
	function loadCommerciaux(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_affaire_commercial',array('fk_fin_affaire'=>$this->getId()));
		
		foreach($Tab as $i=>$id) {
			$this->TCommercial[$i]=new TFin_affaire_commercial;
			$this->TCommercial[$i]->load($db, $id);
			
		}
	}
	function loadEquipement(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'asset_link',array('fk_document'=>$this->getId(), 'type_document'=>'affaire'));
		
		foreach($Tab as $i=>$id) {
			$this->TAsset[$i]=new TAssetLink;
			$this->TAsset[$i]->load($db, $id, true);
			
		}
	}
	function calculSolde() {
		$this->somme_dossiers =0;
		foreach($this->TLien as &$lien) {
			
			$this->somme_dossiers += $lien->dossier->montant;
		}
		$this->solde = $this->montant - $this->somme_dossiers;
	}
	function loadDossier(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_dossier_affaire',array('fk_fin_affaire'=>$this->getId()));
		
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->dossier->load($db, $this->TLien[$i]->fk_fin_dossier, false);
			
			
		}
		
		$this->calculSolde();
	}
	function delete(&$db) {
		parent::delete($db);
		$db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $this->getId(), 'fk_fin_affaire' );
	}
	function save(&$db) {
		global $conf, $user;
		
		if(!$user->rights->financement->affaire->write) return false;
		
		$this->calculSolde();
		$this->entity = $conf->entity;
		
		
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->fk_fin_affaire = $this->getId();	
			$lien->save($db);
		}
		foreach($this->TAsset as &$lien) {
			$lien->fk_document = $this->getId();	
			$lien->save($db);
		}
		foreach($this->TCommercial as &$lien) {
			$lien->fk_fin_affaire = $this->getId();	
			$lien->save($db);
		}
	}
	function deleteDossier(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_dossier==$id) {
				$db->dbdelete(MAIN_DB_PREFIX.'fin_dossier_affaire', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				$this->calculSolde();
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
			$this->calculSolde();
			return true;
		}
		else {
			//exit('Echec');
			return false;
		}
		
	}
	function deleteEquipement(&$db, $id) {
		foreach($this->TAsset as $k=>&$lien) {
			if($lien->asset->getId()==$id && $lien->fk_document==$this->getId() && $lien->type_document=='affaire') {
				$lien->delete($db);
				unset($this->TAsset[$k]);
				return true;
				
			}
		}		 
		
		return false;
	}
	function addEquipement(&$db, $id) {
		foreach($this->TAsset as $k=>&$asset) {
			if($asset->getId()==$id) {return false;}
		}		 
		 
		$asset =new TAsset;
		$asset->load($db, $id); 
		$i = $asset->add_link($this->getId(), 'affaire'); 
		$asset->save($db);
		 
		$asset->TLink[$i]->asset = $asset ;
		 
		$this->TAsset[]=$asset->TLink[$i];
		
		
		return true;		
	}
	
	function deleteCommercial(&$db, $id) {
		foreach($this->TLien as $k=>&$lien) {
			if($lien->fk_fin_dossier==$id) {
				$db->dbdelete(MAIN_DB_PREFIX.'fin_affaire_commercial', $lien->getId(), 'rowid' );
				unset($this->TLien[$k]);
				return true;
			}
		}		 
		
		return false;
	}
	function addCommercial(&$db, $id) {
		foreach($this->TCommercial as $k=>$lien) {
			if($lien->fk_fin_affaire==$id) {return false;}
		}		 
		 
		$i = count($this->TCommercial); 
		$this->TCommercial[$i]=new TFin_affaire_commercial;
		$this->TCommercial[$i]->fk_fin_affaire = $this->getId();
		$this->TCommercial[$i]->fk_user = $id;  
			 
		
		return true;
		
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

class TFin_affaire_commercial extends TObjetStd {
	function __construct() { /* declaration */

		parent::set_table(MAIN_DB_PREFIX.'fin_affaire_commercial');
		parent::add_champs('fk_user,fk_fin_affaire','type=entier;index;');
		
		parent::_init_vars();
		parent::start();
		
	}
}
	