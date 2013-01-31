<?php

class TFin_grille_leaser extends TObjetStd {
	function __construct() { /* declaration */
		global $langs;

		parent::set_table(MAIN_DB_PREFIX.'fin_grille_leaser');
		parent::add_champs('fk_type_contrat');
		parent::add_champs('fk_soc,fk_user','type=entier;index;');//fk_soc_leaser
		parent::add_champs('periode','type=entier;');
		parent::add_champs('montant,coeff','type=float;');
		
		parent::_init_vars();
		parent::start();
		
		$this->grille=array();
		$this->TPalier=array();
		$this->TPeriode=array();
	}
	/******************************************************************
	 * PERSO FUNCTIONS
	 ******************************************************************/
    /**
     *  Chargement d'un tableau de grille pour un leaser donné, pour un type de contrat donné
     *
     *  @param	int		$idLeaser    		Id leaser
	 *  @param	int		$idTypeContrat  Id type contrat
     *  @return array   Tableau contenant les grilles de coeff, false si vide
     */
    function get_grille(&$ATMdb, $idLeaser, $idTypeContrat, $periodicite='TRIMESTRE' , $options=array(),$type='LEASER')
    {
    	if(empty($idLeaser) || empty($idTypeContrat)) return false;

		$this->fk_soc = $idLeaser;

    	$sql = "SELECT t.rowid, t.periode, t.montant
        	 	FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t
        	 	WHERE t.fk_soc = ".$idLeaser. " AND t.type='$type' AND t.fk_type_contrat = '".$idTypeContrat."'
        	 	ORDER BY t.periode, t.montant ASC";

		$ATMdb->Execute($sql);
		
		$this->TPalier=array();
		$this->TPeriode=array();
		
		$result = &$this->grille;
		$result = array();
		$lastMontant=0;
		
		while($ATMdb->get_line()) {
			
			$periode = $ATMdb->get_field('periode');
			if($periodicite == 'MOIS') $periode *= 3;
			
			$montant = $ATMdb->get_field('montant');
			$coeff = $this->_calculate_coeff($ATMdb, $ATMdb->get_field('coeff'), $options);
			
			$result[$periode][$montant]['rowid'] = $ATMdb->Get_field('rowid');
			$result[$periode][$montant]['coeff'] = $coeff;
			$result[$periode][$montant]['echeance'] = $montant / $periode * (1 + $coeff / 100);

			$this->TPalier[$periode]=array(
				'montant'=>$montant
				,'lastMontant'=>$lastMontant
			);
			
			$lastMontant=$montant;	
		}
		
		
		return $result;
	}
	
	/**
	 * Récupération de la liste des durée possible pour un type de contrat et pour un leaser
	 */
	function get_duree(&$ATMdb,$idLeaser, $idTypeContrat=0, $periodicite='TRIMESTRE',$type='LEASER') {
		if(empty($idLeaser)) return -1;
		global $langs;

		$sql = "SELECT";
		$sql.= " t.periode";		
		$sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
		$sql.= " WHERE t.fk_soc = ".$idLeaser. " AND t.type='$type'";
		if(!empty($idTypeContrat)) $sql.= " AND t.fk_type_contrat = ".$idTypeContrat;
		$sql.= " ORDER BY t.periode ASC";

    	dol_syslog(get_class($this)."::get_coeff sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
        	$num = $this->db->num_rows($resql);
			$i = 0;
			$TDuree = array();
			while($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$duree = $obj->periode;
				if($periodicite == 'MOIS') $duree *= 3;
				$label = $duree;
				$label.= ($periodicite == 'TRIMESTRE') ? ' '.$langs->trans('Trimestres') : '';
				$label.= ($periodicite == 'MOIS') ? ' '.$langs->trans('Mois') : '';
				$TDuree[$duree] = $label;
				
				$i++;
			}
			
			$this->db->free($resql);

			return $TDuree;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	function get_coeff($idLeaser, $idTypeContrat, $periodicite='opt_trimestriel', $montant, $duree, $options=array(),$type='LEASER')
    {
    	if(empty($idLeaser) || empty($idTypeContrat)) return -1;
		
		if($periodicite == 'opt_mensuel') $duree /= 3;

    	global $langs;
        $sql = "SELECT";
		$sql.= " t.montant, t.coeff";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
        $sql.= " WHERE t.fk_soc = ".$idLeaser;
		$sql.= " AND t.fk_type_contrat = ".$idTypeContrat;
		$sql.= " AND t.periode = ".$duree. " AND t.type='$type'";
		$sql.= " ORDER BY t.montant ASC";

    	dol_syslog(get_class($this)."::get_coeff sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
        	$num = $this->db->num_rows($resql);
			$i = 0;
			$coeff = -1;
			while($i < $num) {
				$obj = $this->db->fetch_object($resql);
				if($montant <= $obj->montant) {
					$coeff = $this->_calculate_coeff($obj->coeff, $options);
					break;
				}
				
				$i++;
			}
			
			$this->db->free($resql);

			return $coeff;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	private function _calculate_coeff(&$ATMdb, $coeff, $options) {
		if(!empty($options)) {
			foreach($options as $name) {
				$penalite = $this->_get_penalite($name);
				if($penalite < 0) return 0;
				$coeff += $coeff * $penalite / 100;
			}
		}
		
		return round($coeff, 2);
	}
	
	private function _get_penalite(&$ATMdb, $name) {
		$sql = "SELECT opt_value FROM ".MAIN_DB_PREFIX."fin_grille_penalite";
		$sql.= " WHERE opt_name = '".$name."'";
		
		$ATMdb->Execute($sql);
		if($ATMdb->Get_line()) {
			return (double)$db->Get_field('opt_value');
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
		
		if(empty($this->grille)) { // Pas de grille chargée, pas de calcul
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
		foreach($this->grille[$duree] as $palier => $infos) {
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