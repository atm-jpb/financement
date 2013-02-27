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
		
		$this->TFacture=array();
		$this->TFactureFournisseur=array();
		
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
			$this->contrat = $this->TLien[$i]->affaire->contrat;
			
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
			$this->financement->fk_soc = FIN_LEASER_DEFAULT;
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
	
		
	function load_facture(&$ATMdb) {
		$this->somme_facture = 0;
		$this->somme_facture_reglee=0;
		
		$ATMdb->Execute("SELECT fk_target
		FROM ll_element_element WHERE targettype='affaire' AND sourcetype='facture'");
		
		while($db->Get_line()) {
			
		}
		
	}
	function load_factureFournisseur(&$ATMdb) {
		$this->somme_facture_fournisseur = 0;
		
		
		
	}
	
	function getPenalite(&$ATMdb, $type, $nature_financement='INTERNE') {
		/*
		 * TODO
		 * à vérifier
		 */
		$g=new TFin_grille_leaser('PENALITE_'.$type);
		
		if($nature_financement == 'INTERNE') { $f= &$this->financement; }
		else {	$f = &$this->financementLeaser; }

		$g->get_grille($ATMdb,$f->fk_soc,$this->contrat);	
		
		return (double)$g->get_coeff($ATMdb, $f->fk_soc, $this->contrat, $f->periodicite, $f->montant, $f->duree);
	}
	function getMontantCommission() {
		$f= &$this->financement; 
		
		return ($f->taux_commission / 100) * $f->montant;
		
	}
	function getRentabilite(&$ATMdb) {
		
		$g=new TFin_grille_leaser('RENTABILITE');
		
		if($this->nature_financement == 'INTERNE') { $f= &$this->financement; }
		else {	$f = &$this->financementLeaser; }
		
		$g->get_grille($ATMdb,$f->fk_soc,$this->contrat);	
			
		return (double)$g->get_coeff($ATMdb, $f->fk_soc, $this->contrat, $f->periodicite, $f->montant, $f->duree);
		
	}
	function getRentabiliteAttendue(&$ATMdb) {
		if($this->nature_financement == 'INTERNE') { $f= &$this->financement; }
		else {	$f = &$this->financementLeaser; }
		
		return $f->montant * $this->getRentabilite($ATMdb)	;	
	}
	function getRentabiliteReelle() {
		if($this->nature_financement == 'INTERNE') { $f= &$this->financement; }
		else {	$f = &$this->financementLeaser; }
		
		return $this->somme_facture_reglee - $this->somme_facture_fournisseur;
	}
	
	function getRentabiliteReste(&$ATMdb) {
		
		$r = $this->getRentabiliteAttendue($ATMdb) - $this->getRentabiliteReelle();
		if($r<0)$r=0;
		return $r;
		
	}
	
	function getSolde($ATMdb, $type='SRBANK') {
	
		$CRD_Leaser = $this->financementLeaser->valeur_actuelle();
		$LRD_Leaser = $this->financementLeaser->echeance * $this->financementLeaser->duree_restante;
		
		$CRD = $this->financement->valeur_actuelle();
		$LRD = $this->financement->echeance * $this->financement->duree_restante;
		
		
		switch($type) {
			case 'SRBANK':
				return $CRD_Leaser * $this->getPenalite($ATMdb,'R', 'EXTERNE');

				break;
			case 'SNRBANK':
				return $LRD_Leaser * $this->getPenalite($ATMdb,'NR', 'EXTERNE');
				break;
				
			case 'SNRCPRO':
				if($this->nature_financement == 'INTERNE') {
					return (($CRD + $this->getRentabiliteReste($ATMdb)) * $this->getPenalite($ATMdb,'R','INTERNE')) + $this->getMontantCommission();
				}
				else {
					return $LRD_Leaser * $this->getPenalite($ATMdb,'NR', 'EXTERNE') * $this->getPenalite($ATMdb,'NR', 'INTERNE');
				}
				break;
					
			case 'SRCPRO':
				if($this->nature_financement == 'INTERNE') {
					return $LRD;
				}
				else {
					return $CRD_Leaser * $this->getPenalite($ATMdb,'R', 'EXTERNE') * $this->getPenalite($ATMdb,'R', 'INTERNE');
				}
				
				
				break;
		}
		
		
	}
	
	function echeancier(&$ATMdb,$nature_financement='INTERNE') {
		if($nature_financement == 'INTERNE') { $f= &$this->financement; }
		else {	$f = &$this->financementLeaser; }
		
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
		 $montant_finance = 0;
		 $capital_restant_init=$f->montant;
		 $capital_restant = $capital_restant_init;
		 $TLigne=array();
		 for($i=0; $i<$f->duree; $i++) {
		 	
			$time = strtotime('+'.($i*3).' month',  $f->date_debut);	
			
			
			$capital_amortit = $f->amortissement_echeance( $i ) ;
			$part_interet = $f->echeance -$capital_amortit; 			

			$capital_restant-=$capital_amortit;
			$montant_finance+=$capital_amortit;
			
			$TLigne[]=array(
				'date'=>date('d/m/Y', $time)
				/*,'valeur_rachat'=>$capital_restant*$this->getPenalite($ATMdb,'NR')*/
				,'capital'=>$capital_restant
				,'amortissement'=>$capital_amortit
				,'interet'=>$part_interet
				,'assurance'=>$f->assurance
				,'loyerHT'=>$f->echeance+$f->assurance
				,'loyer'=>($f->echeance+$f->assurance) * FIN_TVA_DEFAUT
			);
			
			$this->somme_echeance +=$f->echeance;
		 	
		 }
		 
		// print $f->montant.' = '.$capital_restant_init;
		 $TBS=new TTemplateTBS;
		 return $TBS->render('./tpl/echeancier.tpl.php'
			,array(
				'ligne'=>$TLigne
			)
			,array(
				'autre'=>array(
					'reste'=>$f->reste
					,'resteTTC'=>($f->reste*FIN_TVA_DEFAUT)
					,'capitalInit'=>$capital_restant_init
				)
			)
		);
		 
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
 * Lien dossier facture
 */
class TFin_dossier_facture extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier_facture');
		parent::add_champs('fk_fin_dossier,fk_facture','type=entier;');
		parent::add_champs('type','type=chaine;');
		parent::add_champs('montant','type=float;');
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
	global $langs;
	
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier_financement');
		parent::add_champs('duree,numero_prochaine_echeance,terme','type=entier;');
		parent::add_champs('montant_prestation,montant,echeance1,echeance,reste,taux, capital_restant,assurance,montant_solde,penalite_reprise,taux_commission,frais_dossier','type=float;');
		parent::add_champs('reference,periodicite,reglement,incident_paiement,type','type=chaine;');
		parent::add_champs('date_debut,date_fin,date_prochaine_echeance,date_solde','type=date;index;');
		parent::add_champs('fk_soc,fk_fin_dossier','type=entier;index;');
		parent::add_champs('okPourFacturation','type=chaine;index;');
				
		parent::start();
		parent::_init_vars();
		
		$this->TPeriodicite=array(
			'MOIS'=>'Mensuel'
			,'TRIMESTRE'=>'Trimestriel'
			,'ANNEE'=>'Annuel'
		);
		
		$this->TReglement=array();
		$this->load_reglement();  
		 
		 /*
		 array(
			'opt_prelevement'=>$langs->trans('opt_prelevement')
			,'opt_virement'=>$langs->trans('opt_virement')
			,'opt_cheque'=>$langs->trans('opt_cheque')
		);*/
		
		$this->taux_commission = 1;
		$this->duree_passe=0;
		$this->duree_restante=0;
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
		
		$this->terme = 1;
		$this->TTerme = array(
			0=>'Echu'
			,1=>'A Echoir'
		);
		
		$this->okPourFacturation='NON';
		$this->TOkPourFacturation =array(
			'NON'=>'Non'
			,'OUI'=>'Oui'
			,'AUTO'=>'Toujours'
		);
		
		$this->date_solde=0;
		
	}
	/*
	 * Définie la prochaine échéance
	 */
	function setNextEcheance() {
		
		$this->date_prochaine_echeance = time() + strotime( $this->getiPeriode().' month' );
		
		$this->numero_prochaine_echeance++;
	}

	function load_reglement() {
	global $db;
	
		if(!isset($db) ) return false;
	
		$this->TReglement=array();
		
		if(class_exists('Form')) {
			$form = new Form($db);	
			$form->load_cache_types_paiements();
			
			foreach($form->cache_types_paiements as $row) {
				if($row['code']!='') {
					$this->TReglement[$row['code']] = $row['label'];	
				}	
			}
		}
		
	}
	function loadReference(&$db, $reference, $type='LEASER') {
		$Tab = TRequeteCore::get_id_from_what_you_want($db,$this->get_table(),array('reference'=>$reference,'type'=>$type));
		if(count($Tab)>0) return $this->load($db, $Tab[0]);

		return false;
	
	}
	function createWithfindClientBySiren(&$db, $siren, $reference) {
		/*
		 * Trouve le client via le siren, vérifie s'il existe une affaire avec financement sans référence pour association automatique
		 */
		$db->Execute("SELECT s.id as 'fk_soc',a.rowid as 'fk_fin_affaire', f.rowid as 'fk_financement' 
			FROM ((".MAIN_DB_PREFIX."fin_affaire a LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc=s.rowid) 
				LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire l ON (l.fk_fin_affaire=a.rowid))
					LEFT JOIN OUTER ".$this->get_table()." f ON (f.fk_fin_dossier=a.fk_fin_dossier))
					
			WHERE s.siren='".$siren."' AND `fk_fin_affaire`>0 AND `fk_financement` IS NULL 
		");
		if($db->Get_line()) {
			$idAffaire =  $db->Get_field('fk_fin_affaire');
			
			$a=new TFin_affaire;
			$a->load($db, $idAffaire);
			
			$d=new TFin_dossier;
			$d->financementLeaser->reference = 
			$d->savell_element_element($db);
			
			$a->addDossier();
			
		}
		
		
		return false;
	}
	private function getiPeriode() {
		if($this->periodicite=='TRIMESTRE')$iPeriode=3;
		else if($this->periodicite=='ANNEE')$iPeriode=12;
		else $iPeriode = 1;
		
		return $iPeriode;
	} 
	function calculDateFin() {
		$this->date_fin = strtotime('+'.($this->getiPeriode()*($this->duree - 1)).' month', $this->date_debut);
		
	}
	function calculTaux() {
		$this->taux = round($this->taux($this->duree, $this->echeance, -$this->montant, $this->reste, $this->terme) * (12 / $this->getiPeriode()) * 100,4);
	}
	
	function load(&$ATMdb, $id, $annexe=false) {
		
		$res = parent::load($ATMdb, $id);
		$this->duree_passe = $this->numero_prochaine_echeance-1;
		$this->duree_restante = $this->duree - $this->duree_passe;
		if($annexe) {
			$this->load_facture($ATMdb);
			$this->load_factureFournisseur($ATMdb);
		}
		
		return $res;
	}
	function save(&$ATMdb) {
		global $db, $user;
		
		if(!$user->rights->financement->affaire->write) return false;
		
		if($this->date_prochaine_echeance<$this->date_debut) $this->date_prochaine_echeance = $this->date_debut;
		
		$this->calculDateFin();
		
		//$this->taux = 1 - (($this->montant * 100 / $this->echeance * $this->duree) - $this->reste);
		$this->calculTaux();
		
		/*$g=new TFin_grille_leaser();

		$g->get_grille($ATMdb, 1, $this->contrat);	
		
		$g->calcul_financement($this->montant, $this->duree, $this->echeance, $this->reste, $this->taux);
		*/
		parent::save($ATMdb);
		
	}
	private function getTypeContrat(&$ATMdb) {
		if(!isset($this->idTypeContrat)) {
			$ATMdb->Execute("SELECT contrat 
			FROM ".MAIN_DB_PREFIX."fin_affaire a LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire l ON (a.rowid=fk_fin_affaire AND l.fk_fin_dossier=".$this->fk_fin_dossier.")
			LIMIT 1");
			$ATMdb->Get_line();
			$this->idTypeContrat = $ATMdb->Get_field('contrat');	
		}
	} 

	
	/**
	 * FONCTION FINANCIERES PROVENANT D'EXCEL PERMETTANT DE CALCULER LE LOYER, LE MONTANT OU LE TAUX
	 * Source : http://www.tiloweb.com/php/php-formules-financieres-excel-en-php
	 */
	
	function amortissement_echeance($periode) {
		
		$duree = $this->duree;
		
		$r = $this->PRINCPER($this->taux / 100 / (12 / $this->getiPeriode()), $periode, $this->duree, $this->montant);

		$r = -$r;
		
	//	print "amortissement_echeance ".$this->montant."($periode) |$duree| $r <br>";
		
		return $r;
	}
	
	private function PRINCPER($taux, $p, $NPM, $VA)
	{
		$valeur_residuelle=0;$type=0;
		return $taux / (1 + $taux * $type) * $VA * (pow(1 + $taux,-$NPM+$p - 1)) / (pow(1 + $taux,-$NPM) - 1) - $valeur_residuelle * (pow(1 + $taux,$p - 1)) / (pow(1 + $taux,$NPM) - 1);
	} 
	
	/**
	 * VPM : Calcule le montant total de chaque remboursement périodique d'un investissement à remboursements et taux d'intérêt constants
	 * @param $taux Float : Le taux d'intérêt par période (à diviser par 4 si remboursement trimestriel, par 12 si mensuel, ...)
	 * @param $npm Float : Le nombre total de périodes de paiement de l'annuité (= Duree)
	 * @param $va Float : Valeur actuelle. La valeur, à la date d'aujourd'hui, d'une série de remboursement futurs (= Montant financé)
	 * @param $vc Float : Valeur future. La valeur capitalisée que vous souhaitez obtenir après le dernier paiement (= Valeur résiduelle)
	 * @param $type Int : Terme de l'échéance (0 = terme échu, 1 = terme à échoir)
	 * @return $vpm Float : Montant d'une échéance
	 */
	private function vpm($taux, $npm, $va, $vc = 0, $type = 0){
		if(!is_numeric($taux) || !is_numeric($npm) || !is_numeric($va) || !is_numeric($vc) || !is_numeric($type)) return false;
		if($type > 1|| $type < 0) return false;
		
		$tauxAct = pow(1 + $taux, -$npm);
		
		if((1 - $tauxAct) == 0) return 0;
		
		$vpm = ( ($va + $vc * $tauxAct) * $taux / (1 - $tauxAct) ) / (1 + $taux * $type);
		
		return -$vpm;
	}
	function valeur_actuelle() {
		return -$this->va($this->taux / 12 / 100, $this->duree, $this->echeance, $this->reste, $this->terme);
	}
	/**
	 * VA : Calcule la valeur actuelle d'un investissement
	 * @param $taux Float : Le taux d'intérêt par période (à diviser par 4 si remboursement trimestriel, par 12 si mensuel, ...)
	 * @param $npm Float : Le nombre total de périodes de paiement de l'annuité (= Duree)
	 * @param $vpm Float : Echéance constante payée pour chaque période
	 * @param $vc Float : Valeur future. La valeur capitalisée que vous souhaitez obtenir après le dernier paiement (= Valeur résiduelle)
	 * @param $type Int : Terme de l'échéance (0 = terme échu, 1 = terme à échoir)
	 * @return $va Float : Montant de l'investissement
	 */
	private function va($taux, $npm, $vpm, $vc = 0, $type = 0){
		if(!is_numeric($taux) || !is_numeric($npm) || !is_numeric($vpm) || !is_numeric($vc) || !is_numeric($type)) return false;
		if($type > 1|| $type < 0) return false;
		
		$tauxAct = pow(1 + $taux, -$npm);
		
		if((1 - $tauxAct) == 0) return 0;
		
		$va = $vpm * (1 + $taux * $type) * (1 - $tauxAct) / $taux - $vc * $tauxAct;
		
		return $va;
	}

	/**
	 * VA : Calcule la valeur actuelle d'un investissement
	 * @param $nper Float : Le nombre total de périodes de paiement de l'annuité (= Duree)
	 * @param $pmt Float : Echéance constante payée pour chaque période
	 * @param $pv Float : Valeur actuelle. La valeur, à la date d'aujourd'hui, d'une série de remboursement futurs (= Montant financé)
	 * @param $fv Float : Valeur future. La valeur capitalisée que vous souhaitez obtenir après le dernier paiement (= Valeur résiduelle)
	 * @param $type Int : Terme de l'échéance (0 = terme échu, 1 = terme à échoir)
	 * @param $guess Float : ???
	 * @return $rate Float : Taux d'intérêt
	 */
	private function taux($nper, $pmt, $pv, $fv = 0.0, $type = 0, $guess = 0.1) {
		$rate = $guess;
		if (abs($rate) < 20) {
			$y = $pv * (1 + $nper * $rate) + $pmt * (1 + $rate * $type) * $nper + $fv;
		} else {
			$f = exp($nper * log(1 + $rate));
			$y = $pv * $f + $pmt * (1 / $rate + $type) * ($f - 1) + $fv;
		}
		
		$y0 = $pv + $pmt * $nper + $fv;
		$y1 = $pv * $f + $pmt * (1 / $rate + $type) * ($f - 1) + $fv;
		
		$i = $x0 = 0.0;
		$x1 = $rate;
		while ((abs($y0 - $y1) > 0.0000001) && ($i < 20)) {
			$rate = ($y1 * $x0 - $y0 * $x1) / ($y1 - $y0);
			$x0 = $x1;
			$x1 = $rate;
			
			if(abs($rate) < 0.0000001) {
				$y = $pv * (1 + $nper * $rate) + $pmt * (1 + $rate * $type) * $nper + $fv;
			} else {
				$f = exp($nper * log(1 + $rate));
				$y = $pv * $f + $pmt * (1 / $rate + $type) * ($f - 1) + $fv;
			}
			
			$y0 = $y1;
			$y1 = $y;
			++$i;
		}
		
		return $rate;
	}
}
