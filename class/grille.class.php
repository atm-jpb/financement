<?php

class TFin_grille_leaser extends TObjetStd {
	function __construct($type='LEASER') { /* declaration */
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'fin_grille_leaser');
		parent::add_champs('fk_type_contrat,type');
		parent::add_champs('fk_soc','type=entier;index;');//fk_soc_leaser
		parent::add_champs('periode','type=entier;');
		parent::add_champs('montant,coeff','type=float;');
		
		parent::_init_vars();
		parent::start();
		
		$this->TGrille=array();
		$this->TPalier=array();
		$this->TPeriode=array();
		
		$this->type=$type;
	}

    /**
     *  Chargement d'un tableau de grille pour un leaser donné, pour un type de contrat donné
     *
     *  @param	int		$idLeaser    		Id leaser
	 *  @param	int		$idTypeContrat  Id type contrat
     *  @return array   Tableau contenant les grilles de coeff, false si vide
     */
    function get_grille(&$ATMdb, $idLeaser, $idTypeContrat, $periodicite='TRIMESTRE' , $options=array())
    {
    	if(empty($idLeaser)) $idLeaser = FIN_LEASER_DEFAULT;

		$this->fk_soc = $idLeaser;

    	$sql = "SELECT rowid, periode, montant,coeff
        	 	FROM ".MAIN_DB_PREFIX."fin_grille_leaser
        	 	WHERE fk_soc = ".$idLeaser. " AND type='".$this->type."' AND fk_type_contrat = '".$idTypeContrat."'
        	 	ORDER BY periode, montant ASC";

		$ATMdb->Execute($sql);
		
		$this->TPeriode=array();
		
		$result = &$this->TGrille;
		$result = array();
		$lastMontant=0;
		
		$TResult=array();
		
		while($ATMdb->get_line()) {
			$TResult[] = array(
				'rowid' => $ATMdb->Get_field('rowid')
				,'periode' => $ATMdb->Get_field('periode')
				,'montant' => $ATMdb->Get_field('montant')
				,'coeff' => $ATMdb->Get_field('coeff')
			);
		}
		
		foreach($TResult as $ligne_grille) {
			
			$periode = (int)$ligne_grille['periode'];
			if($periodicite == 'MOIS') $periode *= 3;
			
			$montant = (int)$ligne_grille['montant'];
			$coeff = $this->_calculate_coeff($ATMdb, $ligne_grille['coeff'], $options);
			
			$result[$periode][$montant]=array(
				'rowid' => $ligne_grille['rowid']
				,'coeff' => $coeff
				,'echeance' => $montant / $periode * (1 + $coeff / 100)
				,'montant' => $montant
				,'periode' => $periode
			);
			
		}

		$this->normalizeGrille();
		
		return $result;
	}
	
	private function setPalier() {
		
		$this->TPalier=array();
		$Tmp=array();
		
		foreach($this->TGrille as $periode=>$TPalier) {
			foreach($TPalier as $palier=>$row) {
				$Tmp[$palier]=true;
				
			}
		}

		ksort($Tmp, SORT_NUMERIC);
		$lastMontant=0;
		foreach($Tmp as $montant=>$null) {
			$this->TPalier[]=array(
				'montant'=>$montant
				,'lastMontant'=>$lastMontant
			);
			$lastMontant=$montant;
		}

	}
	
	function normalizeGrille() {
			/* S'assure que toutes les colonnes sont correctement définie dans la grille (parfois selon la base il en manque) */
		
			$this->setPalier();
		
			foreach($this->TPalier as $palier) {
				
				foreach($this->TGrille as $periode=>&$row) {
					//print $palier['montant'];
					if(!isset($row[$palier['montant']])) {
							
						$row[$palier['montant']]=array(
							'rowid'=>0
							,'coeff'=>0
							,'echeance'=>0
							,'periode'=>$periode
							,'montant'=>$palier['montant']
						);
						
					}
				}
			}
		
	}
	function addPalier($palier) {
		$palier = (int)$palier;
		//print "addPalier:".$palier;
		
		if(empty($palier)) { return false; }
		
		
		$this->TPalier[]=array(
				'montant'=>$palier
				,'lastMontant'=>(empty($this->TPalier) ? 0 : $this->TPalier[ count($this->TPalier)-1 ]['montant'])
		);
		
		foreach($this->TGrille as $periode=>&$row) {
				
				$row[$palier] = array(
					'rowid'=>0
					,'coeff'=>0
					,'echeance'=>0
					,'periode'=>$periode
					,'montant'=>$palier
				);
			
		}
		
		$this->normalizeGrille();
		
		return true;
	}
	function setCoef(&$ATMdb, $idCoeff ,$idLeaser, $idTypeContrat, $periode, $montant, $coeff) {
		
		$periode=(int)$periode;
		$montant=(int)$montant;
		
		$grilleLigne = new TFin_grille_leaser;
		if($idCoeff>0) $grilleLigne->load($ATMdb, $idCoeff);
		if(empty($coeff)) {
			if($idCoeff>0) $grilleLigne->delete($ATMdb);
			
			/*print "$periode, $montant<br>"; 
			print_r($this->TGrille[$periode][$montant]);
			print "<hr>";
			*/
			unset($this->TGrille[$periode][$montant]);
			
			/*print "<hr>";
			print "$periode, $montant<br>"; 
			print_r($this->TGrille[$periode][$montant]);
			*/
			
			if(empty($this->TGrille[$periode])) unset($this->TGrille[$periode]);
			
			
		}
		else {
			$tabStrConversion = array(',' => '.', ' ' => ''); // Permet de transformer les valeurs en nombres
			
			$grilleLigne->coeff=(double)strtr($coeff, $tabStrConversion);
			$grilleLigne->montant=(double)strtr($montant, $tabStrConversion);
			$grilleLigne->periode=(int)$periode;
			$grilleLigne->fk_soc = $idLeaser;
			$grilleLigne->fk_type_contrat = $idTypeContrat;
			
			$grilleLigne->type = $this->type;
			
			$grilleLigne->save($ATMdb);
			
			if($idCoeff==0) {
				$this->TGrille[$periode][$montant]=array(
					'rowid'=>$grilleLigne->id
					,'coeff'=>$coeff
					,'echeance'=>0
					,'periode'=>$periode
					,'montant'=>$montant
				);
			}
		}
		
		
	}
	function addPeriode($periode) {
		$periode = (int)$periode;
		
		if($periode==0) { return false; }
		
		if(!isset($this->TGrille[$periode])) {
				
		/*	if(!empty($this->TGrille)) {
				foreach($this->TGrille as $lastPeriode=>$row) { null; }	
				$this->TGrille[$periode]=$row;
			}
			else {*/
				$this->TGrille[$periode]=array();
			//}	
			
			foreach($this->TGrille[$periode] as &$palier) $palier['rowid']=0;
		}
		
		$this->normalizeGrille();
		
		return true;
	}
	/**
	 * Récupération de la liste des durée possible pour un type de contrat et pour un leaser
	 */
	function get_duree(&$ATMdb,$idLeaser, $idTypeContrat=0, $periodicite='TRIMESTRE') {
		if(empty($idLeaser)) return -1;
		global $langs;

		$sql = "SELECT";
		$sql.= " t.periode";		
		$sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
		$sql.= " WHERE t.fk_soc = ".$idLeaser. " AND t.type='".$this->type."'";
		if(!empty($idTypeContrat)) $sql.= " AND t.fk_type_contrat = ".$idTypeContrat;
		$sql.= " ORDER BY t.periode ASC";

		$ATMdb->Execute($sql);

    	dol_syslog(get_class($this)."::get_coeff sql=".$sql, LOG_DEBUG);
		
        if ($ATMdb->Get_Recordcount()>0)
        {
        	
			$TDuree = array();
			while($ATMdb->Get_line()) {
				$duree = $ATMdb->Get_field('periode');
				if($periodicite == 'MOIS') $duree *= 3;
				$label = $duree;
				$label.= ($periodicite == 'TRIMESTRE') ? ' '.$langs->trans('Trimestres') : '';
				$label.= ($periodicite == 'MOIS') ? ' '.$langs->trans('Mois') : '';
				$TDuree[$duree] = $label;
				
				$i++;
			}
			
			return $TDuree;
		} else {
			
			return -1;
		}
	}

	function get_coeff(&$ATMdb, $idLeaser, $idTypeContrat, $periodicite='TRIMESTRE', $montant, $duree, $options=array()) {
		
    	if(empty($idLeaser) || empty($idTypeContrat)) return -1;
		
		if($periodicite == 'MOIS') $duree /= 3;

    	global $langs;
        $sql = "SELECT";
		$sql.= " t.montant, t.coeff";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
        $sql.= " WHERE t.fk_soc = ".$idLeaser;
		$sql.= " AND t.fk_type_contrat = '".$idTypeContrat."'
		 AND t.periode <= ".$duree. " AND t.type='".$this->type."' AND t.montant>=".$montant;
		$sql.= " ORDER BY t.periode DESC, t.montant ASC LIMIT 1";

		$ATMdb->Execute($sql);
		if($ATMdb->Get_recordCount()>0) {
		/*	while($db->Get_line()) {
				if($montant <= $db->Get_field('montant')) {*/
					$ATMdb->Get_line();
					$coeff = $this->_calculate_coeff($ATMdb, $ATMdb->Get_field('coeff'), $options);
					return $coeff;
				//}	
		//	}	
				
			
		}
		else {
			return -1;
		}
	}

	private function _calculate_coeff(&$ATMdb, $coeff, $options) {
		if(!empty($options)) {
			foreach($options as $name => $value) {
				$penalite = $this->_get_penalite($ATMdb, $name, $value);
				if($penalite < 0) continue;
				$coeff += $coeff * $penalite / 100;
			}
		}
		
		return round($coeff, 2);
	}
	
	private function _get_penalite(&$ATMdb, $name, $value) {
		$sql = "SELECT penalite FROM ".MAIN_DB_PREFIX."fin_grille_penalite";
		$sql.= " WHERE opt_name = '".$name."'";
		$sql.= " AND opt_value = '".$value."'";
		
		$ATMdb->Execute($sql);
		if($ATMdb->Get_line()) {
			return (double)$ATMdb->Get_field('penalite');
		}
		else {
			return -1;
		}
	}
	
	/**
	 * Calcul des élément du financement
	 * Montant : Capital emprunté
	 * Durée : Durée en trimestre
	 * échéance : Echéance trimestrielle
	 * VR : Valeur residuelle du financement
	 * coeff : taux d'emprunt annuel
	 * 
	 * @return $res :
	 * 			1	= calcul OK
	 * 			-1	= montant ou echeance vide (calcul impossible)
	 * 			-2	= montant hors grille
	 * 			-3	= echeance hors grille
	 * 			-4	= Pas de grille chargée
	 */
	function calcul_financement(&$montant, &$duree, &$echeance, $vr, &$coeff, $typeCalcul='cpro') {
		/*
		 * Formule de calcul échéance
		 * 
		 * Echéance : Capital x tauxTrimestriel / (1 - (1 + tauxTrimestriel)^-nombreTrimestre )
		 * 
		 */ 
		
		if(empty($this->TGrille)) { // Pas de grille chargée, pas de calcul
			$this->error = 'ErrorNoGrilleSelected';
			return false;
		}
		else if(empty($montant) && empty($echeance)) {
			$this->error = 'ErrorMontantOrEcheanceRequired';
			return false;
		}
		else if($vr > $montant) { // Erreur VR ne peut être supérieur au mopntant
			$this->error = 'ErrorInvalidVR';
			return false;
		}
		
		$coeff=0;
		foreach($this->TGrille[$duree] as $palier => $infos) {
			if(( !empty( $montant ) && $montant <= $palier)
			|| (!empty($echeance) && $echeance<=$infos['echeance']))
			{
					$coeff = $infos['coeff']; // coef annuel
			}
		}
		if($coeff==0){
			$this->error = 'ErrorAmountOutOfGrille';
			return false;
		}
		
		$coeffTrimestriel = $coeff / 4 /100; // en %

		if(!empty($montant)) { // Calcul à partir du montant
					
				if($typeCalcul=='cpro')$echeance = ($montant - $vr) / $duree * (1 + $coeff / 100);
				else $echeance = $montant * $coeffTrimestriel / (1- pow(1+$coeffTrimestriel, -$duree) );  
				
				//print "$echeance = $montant, &$duree, &$echeance, $vr, &$coeff::$coeffTrimestriel";
				
				$echeance = round($echeance, 2);
						
		} 
		else if(!empty($echeance)) { // Calcul à partir de l'échéance
		
				if($typeCalcul=='cpro')$montant = $echeance * (1 - $coeff / 100) * $duree + $vr;
				else $montant =  $echeance * (1- pow(1+$coeffTrimestriel, -$duree) ) / $coeffTrimestriel ;
				
				
				$montant = round($montant, 2);
				
			
		
			
		} 
		
		return true; 
	}

	
}