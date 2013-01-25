<?php
/*
 * Dossier
 */
class TFin_dossier extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier');
		parent::add_champs('solde,montant,montant_solde','type=float;');
		parent::add_champs('reference,nature_financement','type=chaine;');
		parent::add_champs('date_relocation,date_solde','type=date;');
			
		parent::start();
		parent::_init_vars();
		
		$this->somme_affaire = 0;
		
		$this->TLien=array();
		$this->financement=new TFin_financement;
		$this->financementLeaser=new TFin_financement;
		
		$this->nature_financement='EXTERNE';
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
		
		$this->load_financement($db);
		$this->calculSolde();
		
		return $res;
	}
	function load_financement(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_dossier_financement',array('fk_fin_dossier'=>$this->getId()));
		
		$somme_affaire = 0;
		foreach($Tab as $i=>$id) {
			$f=new TFin_financement;
			$f->load($db, $id);
			if($f->type=='LEASER') $this->financementLeaser = $f;
			elseif($this->nature_financement == 'INTERNE') $this->financement = $f;
		}
		
		$this->calculSolde();
	}
	
	function load_affaire(&$db) {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($db,MAIN_DB_PREFIX.'fin_dossier_affaire',array('fk_fin_dossier'=>$this->getId()));
		
		$somme_affaire = 0;
		foreach($Tab as $i=>$id) {
			$this->TLien[$i]=new TFin_dossier_affaire;
			$this->TLien[$i]->load($db, $id);
			$this->TLien[$i]->affaire->load($db, $this->TLien[$i]->fk_fin_affaire, false);
			
			$this->somme_affaire +=$this->TLien[$i]->affaire->montant;
			
			if($this->TLien[$i]->affaire->nature_financement=='INTERNE') {
				$this->nature_financement = 'INTERNE';
			}
		}
		
		if(count($Tab)==0)$this->nature_financement = 'INTERNE';
		
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
			 
			if($affaire->solde==0) {
				return false; // cette affaire a déjà le financement nécessaire
			} 
			 
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
		
			$this->calculSolde();
		
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
		global $user;
		
		if(!$user->rights->financement->affaire->write) return false;
		
		$this->calculSolde();
			
		parent::save($db);
		
		foreach($this->TLien as &$lien) {
			$lien->fk_fin_dossier = $this->getId();
			$lien->save($db);
		}
		
		
		$this->financementLeaser->fk_fin_dossier = $this->getId();
		$this->financementLeaser->type='LEASER';
		$this->financementLeaser->save($db);
		
		if($this->nature_financement == 'INTERNE') {

			$this->financement->fk_fin_dossier = $this->getId();
			$this->financement->type='CLIENT';
			$this->financement->save($db);	
			
		}
	
	}
	function calculSolde() {

		if($this->nature_financement == 'INTERNE') {
			 $f= &$this->financement;	
		}
		else {
			$f = &$this->financementLeaser;
		}
		
		$this->montant = $f->montant;
		$this->taux = $f->taux;
		$this->date_debut = $f->date_debut;
		$this->date_fin = $f->date_fin;
		$this->echeance1 = $f->echeance1;
		$this->echeance = $f->echeance;
		$this->incident_paiement = $f->incident_paiement;
		
		$this->somme_affaire=0;
		
		foreach($this->TLien as &$lien) { 
			
			$this->somme_affaire +=$lien->affaire->montant;
			
			if($lien->affaire->nature_financement=='INTERNE') {
				$this->nature_financement = 'INTERNE';
			}
		}
		
		$this->solde = $this->montant - $this->somme_affaire;// attention en cas d'affaire ajouté à la création du dossier ce chiffre sera faux, car non encore répercuté sur l'affaire
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
		parent::add_champs('montant_prestation,montant,echeance1,echeance,reste,taux, capital_restant,assurance,montant_solde','type=float;');
		parent::add_champs('reference,periodicite,reglement,incident_paiement,type','type=chaine;');
		parent::add_champs('date_debut,date_fin,date_prochaine_echeance,date_solde','type=date;index;');
		parent::add_champs('fk_soc','type=entier;index;');
		
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
		
		$this->numero_prochaine_echeance = 1;
		$this->date_prochaine_echeance = 0;
		
		$this->somme_facture = 0;
		$this->somme_echeance = 0;
		
	}
	function loadReference(&$db, $reference) {
		return $this->loadBy($db, $reference, 'reference');	
	}
	function createWithfindClientBySiren(&$db, $siren) {
		/*
		 * Trouve le client via le siren, vérifie s'il existe une affaire avec financement sans référence pour association automatique
		 */
		
		//TODO
		
		return false;
	}

	function calculDateFin() {
		
		if($this->periodicite=='TRIMESTRE')$iPeriode=3;
		else if($this->periodicite=='ANNEE')$iPeriode=12;
		else $iPeriode = 1;
		
		$this->date_fin = strtotime('+'.($iPeriode*$this->duree).' month', $this->date_debut);
		
	}
	function calculTaux() {
		if($this->periodicite=='TRIMESTRE')$iPeriode=3;
		else if($this->periodicite=='ANNEE')$iPeriode=12;
		else $iPeriode = 1;
		
		
		@$this->taux = round($iPeriode* (($this->echeance * $this->duree / ($this->montant - $this->reste)) - 1),2);
	}
	
	function load(&$ATMdb, $id, $annexe=false) {
		
		parent::load($ATMdb, $id);
		
		if($annexe) {
			$this->load_facture($ATMdb);
		}
		
	}
	
	function load_facture(&$ATMdb) {
		$this->somme_facture = 0;
	}
	
	function save(&$ATMdb) {
		global $db, $user;
		
		if(!$user->rights->financement->affaire->write) return false;
		
		if($this->date_prochaine_echeance<$this->date_debut) $this->date_prochaine_echeance = $this->date_debut;
		
		$this->calculDateFin();
		
		//$this->taux = 1 - (($this->montant * 100 / $this->echeance * $this->duree) - $this->reste);
		$this->calculTaux();
		
		$g=new Grille($db);

		$g->get_grille(1, $this->contrat);//TODO	
		
		$g->calcul_financement($this->montant, $this->duree, $this->echeance, $this->reste, $this->taux);
		
		parent::save($ATMdb);
		
	}
	
	function getPenalite() {
		return 1;
	}
	
	function echeancier() {
		 /*
		 * Affiche l'échéancier
		 * ----
		 * Périodes
		 * Dates des Loyers
		 * Période
		 * Valeurs de Rachat - Pénal 8.75%
		 * Capital Résid.Risque Résid. HT
		 * Amortissmt Capital HT
		 * Part Intérêts
		 * Assurance
		 * Loyers HT 
		 * Loyers TTC
		 */
		 $this->somme_echeance = 0;
		 
		 $capital_restant = $this->montant;
		 $TLigne=array();
		 for($i=1; $i<=$this->duree; $i++) {
		 	
			$time = strtotime('+'.($i*3).' month',  $this->date_debut);	
			$capital_restant-=$this->echeance;
			
			$TLigne[]=array(
				'date'=>date('d/m/Y', $time)
				,'valeur_rachat'=>$capital_restant*$this->getPenalite()
				,'capital'=>$capital_restant
				,'amortissement'=>$this->echeance
				,'interet'=>0
				,'assurance'=>$this->assurance
				,'loyerHT'=>$this->echeance
				,'loyer'=>$this->echeance * FIN_TVA_DEFAUT
			);
			
			$this->somme_echeance +=$this->echeance;
		 	
		 }
		 
		 
		 $TBS=new TTemplateTBS;
		 return $TBS->render('./tpl/echeancier.tpl.php'
			,array(
				'ligne'=>$TLigne
			)
			,array(
				'autre'=>array(
					'reste'=>$this->reste
					,'resteTTC'=>($this->reste*FIN_TVA_DEFAUT)
					,'capitalInit'=>$this->montant
				)
			)
		);
		 
	}

}