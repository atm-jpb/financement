<?php
/*
 * Dossier
 */
class TFin_dossier extends TObjetStd {
	function __construct() { /* declaration */
		parent::set_table(MAIN_DB_PREFIX.'fin_dossier');
		parent::add_champs('solde,montant,montant_solde','type=float;');
		parent::add_champs('renta_previsionnelle,renta_attendue,renta_reelle,marge_previsionnelle,marge_attendue,marge_reelle','type=float;');
		parent::add_champs('reference,nature_financement,commentaire,reference_contrat_interne','type=chaine;');
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
	function loadReferenceContratDossier(&$db, $reference, $annexe=false) {
		
		$db->Execute("SELECT rowid FROM ".$this->get_table()." WHERE reference_contrat_interne='".$reference."'");
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
			$this->load_facture($db);
			$this->load_factureFournisseur($db);
		}
		
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
				//return false; // cette affaire a déjà le financement nécessaire
				// MKO : Désactivé car plusieurs dossiers sur une même affaire possible et la facture matériel comporte le prix total
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
		$this->calculRenta($db);
			
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
		
		// Calcul des sommes totales
		if(!empty($this->financement)) $this->financement->somme_echeance = $this->financement->duree * $this->financement->echeance;
		$this->financementLeaser->somme_echeance = $this->financementLeaser->duree * $this->financementLeaser->echeance;
	}
	function calculRenta(&$ATMdb) {
		$this->renta_previsionnelle = $this->getRentabilitePrevisionnelle();
		$this->renta_attendue = $this->getRentabiliteAttendue($ATMdb);
		$this->renta_reelle = $this->getRentabiliteReelle();
		
		$this->marge_previsionnelle = $this->getMargePrevisionnelle();
		$this->marge_attendue = $this->getMargeAttendue($ATMdb);
		$this->marge_reelle = $this->getMargeReelle();
	}
		
	function load_facture(&$ATMdb) {
		global $db;
		$this->somme_facture = 0;
		$this->somme_facture_reglee=0;
		
		$sql = "SELECT fk_target";
		$sql.= " FROM ".MAIN_DB_PREFIX."element_element";
		$sql.= " WHERE sourcetype='dossier'";
		$sql.= " AND targettype='facture'";
		$sql.= " AND fk_source=".$this->getId();
		
		$ATMdb->Execute($sql);
		
		dol_include_once("/compta/facture/class/facture.class.php");
		
		while($ATMdb->Get_line()) {
			$fact = new Facture($db);
			$fact->fetch($ATMdb->Get_field('fk_target'));
			if($fact->socid == $this->financementLeaser->fk_soc) continue; // Facture matériel associée au leaser, ne pas prendre en compte comme une facture client au sens CPRO
			
			$facidavoir=$fact->getListIdAvoirFromInvoice();
			//$totalht = $fact->total_ht;
			foreach ($facidavoir as $idAvoir) {
				$avoir = new Facture($db);
				$avoir->fetch($idAvoir);
				$fact->total_ht += $avoir->total_ht;
			}
			
			if($fact->type == 0 && $fact->total_ht > 0) { // Récupération uniquement des factures standard et sans avoir qui l'annule complètement
				$this->somme_facture += $fact->total_ht;
				if($fact->statut == 2) $this->somme_facture_reglee += $fact->total_ht;
				$this->TFacture[] = $fact;
			}
		}
	}
	function load_factureFournisseur(&$ATMdb) {
		global $db;
		$this->somme_facture_fournisseur = 0;
		
		$sql = "SELECT fk_target";
		$sql.= " FROM ".MAIN_DB_PREFIX."element_element";
		$sql.= " WHERE sourcetype='dossier'";
		$sql.= " AND targettype='invoice_supplier'";
		$sql.= " AND fk_source=".$this->getId();
		
		$ATMdb->Execute($sql);
		
		dol_include_once("/fourn/class/fournisseur.facture.class.php");
		
		while($ATMdb->Get_line()) {
			$fact = new FactureFournisseur($db);
			$fact->fetch($ATMdb->Get_field('fk_target'));
			$this->somme_facture_fournisseur += $fact->total_ht;
			$this->TFactureFournisseur[] = $fact;
		}
	}
	function load_factureMateriel(&$ATMdb) {
		global $db;
		
		$sql = "SELECT fk_target";
		$sql.= " FROM ".MAIN_DB_PREFIX."element_element";
		$sql.= " WHERE sourcetype='dossier'";
		$sql.= " AND targettype='facture'";
		$sql.= " AND fk_source=".$this->getId();
		
		$ATMdb->Execute($sql);
		
		dol_include_once("/fourn/class/fournisseur.facture.class.php");
		
		while($ATMdb->Get_line()) {
			$fact = new FactureFournisseur($db);
			$fact->fetch($ATMdb->Get_field('fk_target'));
			if($fact->fk_soc == $this->financementLeaser->fk_soc) $this->facture_materiel = $fact;
		}
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
		$coeff = (double)$g->get_coeff($ATMdb, $f->fk_soc, $this->contrat, $f->periodicite, $f->montant, $f->duree);
		return $coeff > 0 ? $coeff : 0;
	}
	function getMontantCommission() {
		$f= &$this->financement; 
		
		return ($f->taux_commission / 100) * $f->montant;
		
	}
	// Récupère le coeff de renta attendue dans le tableau défini en admin
	function getRentabilite(&$ATMdb) {
		
		/*$g=new TFin_grille_leaser('RENTABILITE');
		
		if($this->nature_financement == 'INTERNE') { $f= &$this->financement; }
		else {	$f = &$this->financementLeaser; }
		
		$g->get_grille($ATMdb,$f->fk_soc,$this->contrat);	
		$coeff = (double)$g->get_coeff($ATMdb, $f->fk_soc, $this->contrat, $f->periodicite, $f->montant, $f->duree);
		return $coeff > 0 ? $coeff : 0;*/
		
		$g=new TFin_grille_leaser('RENTABILITE');
		$coeff = (double)$g->get_coeff($ATMdb, $this->financement->fk_soc, $this->contrat, 'TRIMESTRE', $this->financement->montant, 5);
		return $coeff > 0 ? $coeff : 0;
	}
	function getRentabilitePrevisionnelle() {
		return $this->financement->somme_echeance - $this->financementLeaser->somme_echeance
			 + $this->financement->reste - $this->financementLeaser->reste
			 + $this->financement->frais_dossier - $this->financementLeaser->frais_dossier
			 + $this->financement->loyer_intercalaire;
	}
	function getRentabiliteAttendue(&$ATMdb) {
		return $this->financement->montant * $this->getRentabilite($ATMdb) / 100;
	}
	function getRentabiliteReelle() {
		return $this->somme_facture_reglee - $this->somme_facture_fournisseur;
	}
	function getMargePrevisionnelle() {
		if(empty($this->financement->montant)) return 0;
		return $this->getRentabilitePrevisionnelle() / $this->financement->montant * 100;
	}
	function getMargeAttendue(&$ATMdb) {
		return $this->getRentabilite($ATMdb);
	}
	function getMargeReelle() {
		if(empty($this->financement->montant)) return 0;
		return $this->getRentabiliteReelle() / $this->financement->montant * 100;
	}
	
	function getRentabiliteReste(&$ATMdb) {
		
		$r = $this->getRentabiliteAttendue($ATMdb) - $this->getRentabiliteReelle();
		if($r<0)$r=0;
		return $r;
		
	}
	
	function getSolde($ATMdb, $type='SRBANK', $iPeriode=0) {
		
		$duree_restante_leaser = ($iPeriode == 0) ? $this->financementLeaser->duree_restante : $this->financementLeaser->duree - $iPeriode;
	
		$CRD_Leaser = $this->financementLeaser->valeur_actuelle($duree_restante_leaser);
		$LRD_Leaser = $this->financementLeaser->echeance * $duree_restante_leaser;
		
		$duree_restante_client = ($iPeriode == 0) ? $this->financement->duree_restante : $this->financement->duree - $iPeriode;
		
		$CRD = $this->financement->valeur_actuelle($duree_restante_client);
		$LRD = $this->financement->echeance * $duree_restante_client;
		
		switch($type) {
			case 'SRBANK':
				if($this->financementLeaser->duree - $duree_restante_leaser <= 5) return $this->financementLeaser->montant;
				
				return $CRD_Leaser * (1 + $this->getPenalite($ATMdb,'R', 'EXTERNE') / 100);

				break;
			case 'SNRBANK':
				if($this->financementLeaser->duree - $duree_restante_leaser <= 5) return $this->financementLeaser->montant;
				
				return $LRD_Leaser * (1 + $this->getPenalite($ATMdb,'NR', 'EXTERNE') / 100);
				break;
				
			case 'SNRCPRO':
				if($this->financement->duree - $duree_restante_client <= 5) return $this->financement->montant;
				
				if($this->nature_financement == 'INTERNE') {
					return ($CRD * (1 + $this->getPenalite($ATMdb,'R','INTERNE') / 100)) + $this->getRentabiliteReste($ATMdb) + $this->getMontantCommission();
				}
				else {
					return $LRD_Leaser * (1 + $this->getPenalite($ATMdb,'NR', 'EXTERNE') / 100) * (1 + $this->getPenalite($ATMdb,'NR', 'INTERNE') / 100);
				}
				break;
					
			case 'SRCPRO':
				if($this->financement->duree - $duree_restante_client <= 5) return $this->financement->montant;

				if($this->nature_financement == 'INTERNE') {
					return $LRD;
				}
				else {
					return $CRD_Leaser * (1 + $this->getPenalite($ATMdb,'R', 'EXTERNE') / 100) * (1 + $this->getPenalite($ATMdb,'R', 'INTERNE') / 100);
				}
				
				break;
		}
	}
	
	function echeancier(&$ATMdb,$type_echeancier='CLIENT') {
		if($type_echeancier == 'CLIENT') $f = &$this->financement;
		else $f = &$this->financementLeaser;
		
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
		$total_capital_amortit = 0;
		$total_part_interet = 0;
		$total_assurance = 0;
		$total_loyer = 0;
		$total_facture = 0;
		$capital_restant_init = $f->montant;
		$capital_restant = $capital_restant_init;
		$TLigne=array();
		
		if($f->loyer_intercalaire > 0) {
			$nextPeriod = strtotime('+'.($f->getiPeriode()).' month',  $f->date_debut);
			$p = $f->getiPeriode();
			$firstDayOfNextPeriod = strtotime( strftime( '%Y' , $nextPeriod) . '-' . ( ceil( strftime( '%m' , $nextPeriod)/$p )*$p-($p-1) ).'-1');
			$calage = $firstDayOfNextPeriod - $f->date_debut;
		}
		
		for($i=0; $i<$f->duree; $i++) {
			
			$time = strtotime('+'.($i*$f->getiPeriode()).' month',  $f->date_debut + $calage);
			
			$capital_amortit = $f->amortissement_echeance( $i + 1 );
			$part_interet = $f->echeance -$capital_amortit;

			$capital_restant-=$capital_amortit;
			$total_loyer+=$f->echeance;
			$total_assurance+=$f->assurance;
			$total_capital_amortit+=$capital_amortit;
			$total_part_interet+=$part_interet;
			
			// Construction donnée pour échéancier
			$data=array(
				'date'=>date('d/m/Y', $time)
				/*,'valeur_rachat'=>$capital_restant*$this->getPenalite($ATMdb,'NR')*/
				,'capital'=>$capital_restant
				,'amortissement'=>$capital_amortit
				,'interet'=>$part_interet
				,'assurance'=>$f->assurance
				,'loyerHT'=>$f->echeance+$f->assurance
				,'loyer'=>($f->echeance+$f->assurance) * FIN_TVA_DEFAUT
			);
			
			// Ajout factures liées au dossier
			$iFacture = $i;
			if($f->loyer_intercalaire > 0) { // Décalage si loyer intercalaire car 1ère facture = loyer intercalaire, et non 1ère échéance
				$iFacture++;
			}
			$fact = false;
			if($type_echeancier == 'CLIENT' && !empty($this->TFacture[$iFacture])) $fact = $this->TFacture[$iFacture];
			if($type_echeancier == 'LEASER' && !empty($this->TFactureFournisseur[$iFacture])) $fact = $this->TFactureFournisseur[$iFacture];
			if(is_object($fact)) {
				$data['facture_total_ht'] = $fact->total_ht;
				$data['facture_link'] = ($type_echeancier == 'CLIENT') ? DOL_URL_ROOT.'/compta/facture.php?facid=' : DOL_URL_ROOT.'/fourn/facture/fiche.php?facid=';
				$data['facture_link'] .= $fact->id;
				$data['facture_bg'] = ($fact->statut == 1) ? '#FF0000' : '#00FF00';
			} else {
				$data['facture_total_ht'] = '';
				$data['facture_link'] = '';
				$data['facture_bg'] = '';
			}
			$total_facture += $fact->total_ht;
			
			// Ajout des soldes par période
			global $db;
			$form = new Form($db);
			$htmlSoldes = '<table>';
			if($type_echeancier == 'CLIENT') {
				$htmlSoldes.= '<tr><td colspan="2" align="center">Après l\'échéance n°'.($i+1).'</td></tr>';
				$htmlSoldes.= '<tr><td>Solde renouvellant : </td><td align="right"><strong>'.number_format($this->getSolde($ATMdb, 'SRCPRO', $i+1),2,',',' ').' &euro;</strong></td></tr>';
				$htmlSoldes.= '<tr><td>Solde non renouvellant : </td><td align="right"><strong>'.number_format($this->getSolde($ATMdb, 'SNRCPRO', $i+1),2,',',' ').' &euro;</strong></td></tr>';
			} else {
				$htmlSoldes.= '<tr><td colspan="2" align="center">Après l\'échéance n°'.($i+1).'</td></tr>';
				$htmlSoldes.= '<tr><td>Solde renouvellant : </td><td align="right"><strong>'.number_format($this->getSolde($ATMdb, 'SRBANK', $i+1),2,',',' ').' &euro;</strong></td></tr>';
				$htmlSoldes.= '<tr><td>Solde non renouvellant : </td><td align="right"><strong>'.number_format($this->getSolde($ATMdb, 'SNRBANK', $i+1),2,',',' ').' &euro;</strong></td></tr>';
			}
			$htmlSoldes.= '</table>';
			$data['soldes'] = htmlentities($htmlSoldes);
			
			$TLigne[] = $data;
		}
		$f->somme_echeance = $total_loyer;
		$total_loyer += $f->reste;
		
		// print $f->montant.' = '.$capital_restant_init;
		$TBS=new TTemplateTBS;
		
		$autre = array(
			'reste'=>$f->reste
			,'resteTTC'=>($f->reste*FIN_TVA_DEFAUT)
			,'capitalInit'=>$capital_restant_init
			,'total_capital_amortit'=>$total_capital_amortit
			,'total_part_interet'=>$total_part_interet
			,'total_loyer'=>$total_loyer
			,'total_assurance'=>$total_assurance
			,'total_facture'=>$total_facture
			,'loyer_intercalaire'=>$f->loyer_intercalaire
			,'nature_financement'=>$this->nature_financement
			,'date_debut'=>date('d/m/Y', $f->date_debut)
		);
		
		if($f->loyer_intercalaire > 0) {
			$fact = false;
			if($type_echeancier == 'CLIENT' && !empty($this->TFacture[0])) $fact = $this->TFacture[0];
			if($type_echeancier == 'LEASER' && !empty($this->TFactureFournisseur[0])) $fact = $this->TFactureFournisseur[0];
			if(is_object($fact)) {
				$autre['loyer_intercalaire_facture_total_ht'] = $fact->total_ht;
				$autre['loyer_intercalaire_facture_link'] = ($type_echeancier == 'CLIENT') ? DOL_URL_ROOT.'/compta/facture.php?facid=' : DOL_URL_ROOT.'/fourn/facture/fiche.php?facid=';
				$autre['loyer_intercalaire_facture_link'] .= $fact->id;
				$autre['loyer_intercalaire_facture_bg'] = ($fact->statut == 1) ? '#FF0000' : '#00FF00';
				$autre['total_facture'] += $fact->total_ht;
				$autre['total_loyer'] += $f->loyer_intercalaire;
			} else {
				$autre['loyer_intercalaire_facture_total_ht'] = '';
				$autre['loyer_intercalaire_facture_link'] = '';
				$autre['loyer_intercalaire_facture_bg'] = '';
			}
		} else {
			$autre['loyer_intercalaire_facture_total_ht'] = 0;
			$autre['loyer_intercalaire_facture_link'] = '';
			$autre['loyer_intercalaire_facture_bg'] = '';
		}
		
		return $TBS->render('./tpl/echeancier.tpl.php'
			,array(
				'ligne'=>$TLigne
			)
			,array(
				'autre'=>$autre
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
		parent::add_champs('fk_fin_affaire,fk_fin_dossier','type=entier;index');
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
		parent::add_champs('montant_prestation,montant,echeance1,echeance,loyer_intercalaire,reste,taux,capital_restant,assurance,montant_solde,penalite_reprise,taux_commission,frais_dossier','type=float;');
		parent::add_champs('reference,periodicite,reglement,incident_paiement,type','type=chaine;');
		parent::add_champs('date_debut,date_fin,date_prochaine_echeance,date_solde','type=date;index;');
		parent::add_champs('fk_soc,fk_fin_dossier','type=entier;index;');
		parent::add_champs('okPourFacturation','type=chaine;index;');
				
		parent::start();
		parent::_init_vars();
		
		$this->TPeriodicite=array(
			'MOIS'=>'Mensuel'
			,'TRIMESTRE'=>'Trimestriel'
			,'SEMESTRE'=>'Semestriel'
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
		$this->reglement = 'PRE';
		
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
			,'MANUEL'=>'Manuel'
		);
		
		$this->date_solde=0;
		
	}
	/*
	 * Définie la date de prochaine échéance et le numéro d'échéance en fonction de nb
	 * Augmente de nb periode la date de prochaine échéance et de nb le numéro de prochaine échéance
	 */
	function setEcheance($nb=1) {
		$this->numero_prochaine_echeance += $nb;
		$this->duree_passe = $this->numero_prochaine_echeance-1;
		$this->duree_restante = $this->duree - $this->duree_passe;
		
		$this->date_prochaine_echeance = strtotime(($this->duree_passe * $this->getiPeriode()).' month', $this->date_debut);
		
		
		/*$this->date_prochaine_echeance = strtotime(($nb * $this->getiPeriode()).' month', $this->date_prochaine_echeance);
		$this->numero_prochaine_echeance += $nb;
		
		$this->duree_passe = $this->numero_prochaine_echeance-1;
		$this->duree_restante = $this->duree - $this->duree_passe;*/
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
	function loadOrCreateSirenMontant(&$db, $siren, $montant) {
		$sql = "SELECT a.rowid, a.nature_financement, a.montant, df.rowid as idDossierLeaser, df.reference as refDossierLeaser ";
		$sql.= "FROM ".MAIN_DB_PREFIX."fin_affaire a ";
		$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc = s.rowid) ";
		$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (da.fk_fin_affaire = a.rowid) ";
		$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (da.fk_fin_dossier = d.rowid) ";
		$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid) ";
		if(strlen($siren) == 14) $sql.= "WHERE (s.siret = '".$siren."' OR s.siren = '".substr($siren, 0, 9)."')";
		else $sql.= "WHERE s.siren = '".$siren."' ";
		$sql.= "AND df.type = 'LEASER' ";
		//$sql.= "AND df.date_solde = '0000-00-00 00:00:00'";
		$sql.= "AND df.reference = '' ";
		$sql.= "AND a.montant >= ".($montant - 0.01);
		$sql.= "AND a.montant <= ".($montant + 0.01);
		
		$db->Execute($sql); // Recherche d'un dossier leaser en cours sans référence et dont le montant de l'affaire correspond
		if($db->Get_Recordcount() == 0) { // Aucun dossier trouvé, on essaye de le créer
			$sql = "SELECT a.rowid, a.montant ";
			$sql.= "FROM ".MAIN_DB_PREFIX."fin_affaire a ";
			$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc = s.rowid) ";
			if(strlen($siren) == 14) $sql.= "WHERE (s.siret = '".$siren."' OR s.siren = '".substr($siren, 0, 9)."')";
			else $sql.= "WHERE s.siren = '".$siren."' ";
			$sql.= "AND a.solde >= ".($montant - 0.01);
			$sql.= "AND a.solde <= ".($montant + 0.01);
			
			$db->Execute($sql); // Recherche d'une affaire sans dossier pour création du dossier
			if($db->Get_Recordcount() == 1) { // Une seule affaire trouvée OK, on créé
				$idAffaire = $db->Get_field('rowid');

				$d=new TFin_dossier;
				$d->financementLeaser = &$this;
				$d->save($db);
				
				$a=new TFin_affaire();
				$a->load($db, $idAffaire);
				$a->addDossier($db, $d->getId());
				return true;
			} else if($db->Get_Recordcount() == 0) { // Création d'une affaire pour création dossier fin externe
				$TIdClient = TRequeteCore::get_id_from_what_you_want($db, MAIN_DB_PREFIX."societe", array('siren'=>substr($siren, 0, 9)));
				if(!empty($TIdClient[0])) {
					$d=new TFin_dossier;
					$d->financementLeaser = &$this;
					$d->save($db);
					
					$idClient = $TIdClient[0];
					$a=new TFin_affaire();
					$a->reference = 'EXT-'.date('ymd').'-'.$idClient;
					$a->montant = $montant;
					$a->fk_soc = $idClient;
					$a->nature_financement = 'EXTERNE';
					$a->addDossier($db, $d->getId());
					$a->save($db);
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
			
		} else if($db->Get_Recordcount() == 1) { // Un seul dossier trouvé, load
			$db->Get_line();
			$idDossierFin = $db->Get_field('idDossierLeaser');
			$this->load($db, $idDossierFin);
			return true;
		} else { // Plusieurs dossiers trouvé correspondant, utilisation du premier trouvé
			$db->Get_line();
			$idDossierFin = $db->Get_field('idDossierLeaser');
			$this->load($db, $idDossierFin);
			return true;
		}

		return false;
	
	}
	function getiPeriode() {
		if($this->periodicite=='TRIMESTRE')$iPeriode=3;
		else if($this->periodicite=='ANNEE')$iPeriode=12;
		else $iPeriode = 1;
		
		return $iPeriode;
	} 
	function calculDateFin() {
		$this->date_fin = strtotime('+'.($this->getiPeriode()*($this->duree)).' month -1 day', $this->date_debut);
		$this->date_prochaine_echeance = strtotime('+'.($this->getiPeriode()*($this->duree_passe)).' month', $this->date_debut);
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
		$r = $this->PRINCPER(($this->taux / (12 / $this->getiPeriode())) / 100, $periode, $this->duree, $this->montant - $this->reste, $this->reste, $this->terme);

		$r = -$r;
		
	//	print "amortissement_echeance ".$this->montant."($periode) |$duree| $r <br>";
		
		return $r;
	}
	
	private function PRINCPER($taux, $p, $NPM, $VA, $valeur_residuelle, $type)
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
	function valeur_actuelle($duree=0) {
		if($duree==0) $duree = $this->duree_restante;
		return $this->va($this->taux / (12 / $this->getiPeriode()) / 100, $duree, $this->echeance, $this->reste, $this->terme);
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
