<?php

class TIntegrale extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_facture_integrale');
		parent::add_champs('facnumber','type=chaine;index;');

		parent::add_champs('label','type=chaine;');
		parent::add_champs('vol_noir_engage,vol_noir_realise,vol_noir_facture,vol_coul_engage,vol_coul_realise,vol_coul_facture','type=entier;');
		parent::add_champs('cout_unit_noir,cout_unit_coul,fas,fass,frais_dossier,frais_bris_machine,frais_facturation,total_ht_engage,total_ht_realise,total_ht_facture,ecart','type=float;');
		// Nouveaux Champs contenant le détail des cout_unit_noir et cout_unit_coul
		parent::add_champs('cout_unit_noir_tech,cout_unit_noir_mach,cout_unit_noir_loyer','type=float;');
		parent::add_champs('cout_unit_coul_tech,cout_unit_coul_mach,cout_unit_coul_loyer','type=float;');
		
		parent::start();
		parent::_init_vars();
		
		$this->total_ht_engage = 0;
		$this->total_ht_realise = 0;
		$this->total_frais = 0;
		$this->ecart = 0;
	}
	
	function load(&$db, $id, $annexe=false) {
		parent::load($db, $id);
		// Ce n'est plus utile de recalculer les totaux au load car maintenant toutes les données sont stockées
		// Le calcul des totaux et écart se fait juste avant le save
		//$this->calcule_totaux();
		
		if($annexe && !empty($this->facnumber)) {
			$this->load_annexe($db);
		}
	}
	
	
	function load_annexe(&$PDOdb) {
		global $db;
		
		dol_include_once('/compta/facture/class/facture.class.php');
		
		$this->facture = new Facture($db);
		$this->facture->fetch(0,$this->facnumber);
		
		$sql= "SELECT f.fk_soc, d.rowid";
		$sql.= " FROM llx_facture f";
		$sql.= " LEFT JOIN llx_element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
		$sql.= " LEFT JOIN llx_fin_dossier d ON (d.rowid = ee.fk_source AND ee.sourcetype = 'dossier')";
		$sql.= " WHERE f.facnumber = ".$this->facnumber;
		
		$PDOdb->Execute($sql);
		$PDOdb->Get_line();
		$idSoc = $PDOdb->Get_field('fk_soc');
		$idDoss = $PDOdb->Get_field('rowid');
		
		dol_include_once('/financement/class/dossier.class.php');
		dol_include_once('/financement/class/grille.class.php');
		
		if(!empty($idDoss)) {
			$this->dossier = new TFin_dossier();
			$this->dossier->load($PDOdb, $idDoss, false);
		}
		
		if(!empty($idSoc)) {
			$this->client = new Societe($db);
			$this->client->fetch($idSoc);
		}
	}
	
	function save(&$db) {
		$this->calcule_totaux();
		parent::save($db);
	}
	
	function calcule_totaux() {
		$this->total_frais = 0;
		$this->total_frais+= $this->fas;
		$this->total_frais+= $this->fass;
		$this->total_frais+= $this->frais_dossier;
		$this->total_frais+= $this->frais_bris_machine;
		$this->total_frais+= $this->frais_facturation;
		
		if(($this->vol_noir_engage > 0 && $this->cout_unit_noir > 0) || ($this->vol_coul_engage > 0 && $this->cout_unit_coul > 0)){
			$this->total_ht_engage = $this->vol_noir_engage * $this->cout_unit_noir;
			$this->total_ht_engage+= (!empty($this->vol_coul_engage) ? $this->vol_coul_engage : $this->vol_coul_realise) * $this->cout_unit_coul;
			$this->total_ht_engage+= $this->total_frais;
		}
		
		if(($this->vol_noir_realise > 0 && $this->cout_unit_noir > 0) || ($this->vol_coul_realise > 0 && $this->cout_unit_coul > 0)){
			$this->total_ht_realise = $this->vol_noir_realise * $this->cout_unit_noir;
			$this->total_ht_realise+= $this->vol_coul_realise * $this->cout_unit_coul;
			$this->total_ht_realise+= $this->total_frais;
		}
		
		if(($this->vol_noir_facture > 0 && $this->cout_unit_noir > 0) || ($this->vol_coul_facture > 0 && $this->cout_unit_coul > 0)){
			$this->total_ht_facture = $this->vol_noir_facture * $this->cout_unit_noir;
			$this->total_ht_facture+= $this->vol_coul_facture * $this->cout_unit_coul;
			$this->total_ht_facture+= $this->total_frais;
		}
		
		if($this->total_ht_engage > 0) {
			$this->ecart = ($this->total_ht_facture - $this->total_ht_engage) * 100 / $this->total_ht_engage;
			$this->ecart = round($this->ecart, 2);
		}
	}
	
	/**
	 * $nouvel_engagement est une valeur qui vient du formulaire
	 * $cout_unitaire est à la base l'ancien cout unitaire, mais peut également être modifié et provenir du formulaire
	 */
	function get_data_calcul_avenant_integrale($nouvel_engagement, $cout_unitaire, $type='noir', $nouveau_cout_unitaire_manuel=false) {
		
		global $conf;

		$TData = array();
		//echo $cout_unitaire.'<br>';
		if(!empty($nouveau_cout_unitaire_manuel)) $nouveau_cout_unitaire = $cout_unitaire;
		else {
			// Calcul du nouveau coût unitaire en fonction des règles demandées
			$nouveau_cout_unitaire = ($nouvel_engagement * $cout_unitaire
									 + ($nouvel_engagement - $this->{'vol_'.$type.'_engage'})
									 + (abs($this->{'vol_'.$type.'_engage'} - $nouvel_engagement) * ($conf->global->FINANCEMENT_PENALITE_SUIVI_INTEGRALE/100)* $this->{'cout_unit_'.$type.'_tech'}))
									 / $nouvel_engagement;
		}
		
		// Calcul du détail du nouveau coût unitaire en fonction des règles demandées
		$TData['nouveau_cout_unitaire'] = $this->ceil($nouveau_cout_unitaire);
		
		$this->get_data_detail_calcul_avenant_integrale($nouvel_engagement, $nouveau_cout_unitaire, $TData, $type);
		
		return $TData;
		
	}

	function calcul_cout_unitaire($engagement, $type='noir') {
		global $conf;
		$cout_act = $this->{'vol_'.$type.'_engage'} * $this->{'cout_unit_'.$type};
		$diff = $engagement - $this->{'vol_'.$type.'_engage'};
		$diff2 = $diff + abs($diff) * ($conf->global->FINANCEMENT_PENALITE_SUIVI_INTEGRALE/100);
		
		$cout_unitaire = 0;
		if($engagement > 0) {
			$cout_unitaire = ($cout_act + $diff2 * $this->{'cout_unit_'.$type.'_tech'}) / $engagement;
		}
		/*$cout_unitaire = ($engagement * $this->{'cout_unit_'.$type}
								 + (($engagement - $this->{'vol_'.$type.'_engage'}) + (abs($this->{'vol_'.$type.'_engage'} - $engagement)) * ($conf->global->FINANCEMENT_PENALITE_SUIVI_INTEGRALE/100)
								 * $this->{'cout_unit_'.$type.'_tech'}))
								 / $engagement;*/
		
		return $this->ceil($cout_unitaire);
	}
	
	function calcul_cout_unitaire_by_fas($TDetailCouts, $engagement, $new_fas, $pourcentage) {
		// 18000 * (0.00352+0.03821) = 751.14
		// - (413 - 313.33) * 82 / 100 = 669.4106
		// / 18000 = 0.03719
		// + 0.00844 = 
		/*echo 'ENG : '.$engagement.'<br>';
		echo 'PERC: '.$pourcentage.'<br>';
		echo 'MACH: '.$TDetailCouts['nouveau_cout_unitaire_mach'].'<br>';
		echo 'LOY : '.$TDetailCouts['nouveau_cout_unitaire_loyer'].'<br>';
		echo 'TECH: '.$TDetailCouts['nouveau_cout_unitaire_tech'].'<br>';
		pre($TDetailCouts,true);*/
		
		$l1 = $engagement * ($TDetailCouts['nouveau_cout_unitaire_mach'] + $TDetailCouts['nouveau_cout_unitaire_loyer']);
		$l2 = abs($new_fas - $this->fas) * $pourcentage / 100;
		$l3 = ($engagement > 0) ? ($l1 - $l2) / $engagement : 0;
		$l4 = $l3 + $TDetailCouts['nouveau_cout_unitaire_tech'];
		
		/*echo 'L1 : '.$l1.'<br>';
		echo 'L2 : '.$l2.'<br>';
		echo 'L3 : '.$l3.'<br>';
		echo 'L4 : '.$l4.'<br>';*/
		
		return $this->ceil($l4);
	}
	
	private function calcul_tcf($engagement_noir, $cout_mach_noir, $cout_loyer_noir, $engagement_couleur, $cout_mach_couleur, $cout_loyer_couleur) {
		
		return ($engagement_noir * ($cout_loyer_noir)) + ($engagement_couleur * ($cout_loyer_couleur));
		
	}
	
	function calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $engagement_noir, $TDetailCoutCouleur, $engagement_couleur, $pourcentage, $type='noir') {
		if($type == 'noir') {
			$cout_tech = $TDetailCoutNoir['nouveau_cout_unitaire_tech'];
			$cout_mach = $TDetailCoutNoir['nouveau_cout_unitaire_mach'];
		} else {
			$cout_tech = $TDetailCoutCouleur['nouveau_cout_unitaire_tech'];
			$cout_mach = $TDetailCoutCouleur['nouveau_cout_unitaire_mach'];
		}
		
		$tcf = $this->calcul_tcf(
			$engagement_noir,
			$TDetailCoutNoir['nouveau_cout_unitaire_mach'],
			$TDetailCoutNoir['nouveau_cout_unitaire_loyer'],
			$engagement_couleur,
			$TDetailCoutCouleur['nouveau_cout_unitaire_mach'],
			$TDetailCoutCouleur['nouveau_cout_unitaire_loyer']
		);
		
		$res = 0;
		if(${'engagement_'.$type} > 0) {
			$res = ($tcf * ($pourcentage/100) / ${'engagement_'.$type});
		}
		$res += $cout_tech + $cout_mach;
		
		return $this->ceil($res);
	}
	
	function calcul_fas($TData, &$cu_manuel, $engagement, $type='noir') {
		
		$fas_max = $this->{'vol_'.$type.'_engage'} * $TData['nouveau_cout_unitaire_loyer'] / 2;
		if($fas_max < $this->fas) $fas_max = $this->fas;
		$fas_necessaire = ($TData['cout_unitaire'] - $cu_manuel) * $engagement;
		
		//echo $fas_max.' - '.$fas_necessaire;
		
		if($fas_necessaire <= 0) return 0;
		if($fas_necessaire > $fas_max) {
			$cu_manuel = $TData['cout_unitaire'] - $fas_max / $engagement;
			
			return $fas_max;
		}
		
		return $fas_necessaire;
		
	}
	
	function calcul_total_global($TDetailCoutNoir, $TDetailCoutCoul, $fas=0) {
		if(empty($fas)) $fas = $this->fas;
		
		$total = $TDetailCoutNoir['nouveau_cout_total']
					+ $TDetailCoutCoul['nouveau_cout_total']
					+ $fas
					+ $this->fass
					+ $this->frais_bris_machine
					+ $this->frais_facturation;
					
		return round($total, 2);
	}
	
	function calcul_fas_max($TDetailCoutNoir, $TDetailCoutCoul, $engagement_noir, $engagement_coul,$fas=0) {
		$total_global = $this->calcul_total_global($TDetailCoutNoir, $TDetailCoutCoul,$fas);
		
		// Pour la part loyer, on part des cout de base avant avenant
		$TDetailCoutNoir = $this->calcul_detail_cout(0,0,'noir');
		$TDetailCoutCoul = $this->calcul_detail_cout(0,0,'coul');
		$part_loyer = $TDetailCoutNoir['nouveau_cout_unitaire_loyer'] * $this->{'vol_noir_engage'} + $TDetailCoutCoul['nouveau_cout_unitaire_loyer'] * $this->{'vol_coul_engage'};
		
		return min($total_global/2, $part_loyer + $this->fas);		
	}

	function calcul_detail_cout($engagement=0, $cout_unitaire=0, $type='noir') {
		if(empty($engagement)) $engagement = $this->{'vol_'.$type.'_engage'};
		if(empty($cout_unitaire)) $cout_unitaire = $this->{'cout_unit_'.$type};
		
		$TData = array();
		
		$TData['cout_unitaire'] = $this->ceil($cout_unitaire);
		$TData['nouveau_cout_unitaire_tech'] = $this->ceil($this->{'cout_unit_'.$type.'_tech'});
		$TData['nouveau_cout_unitaire_mach'] = 0;
		if($engagement > 0) {
			$TData['nouveau_cout_unitaire_mach'] = $this->ceil($this->{'vol_'.$type.'_engage'} * $this->{'cout_unit_'.$type.'_mach'} / $engagement);
		}
		$TData['nouveau_cout_unitaire_loyer'] = $TData['cout_unitaire'] - $TData['nouveau_cout_unitaire_mach'] - $TData['nouveau_cout_unitaire_tech'];
		$TData['nouveau_cout_total'] = price(round($this->ceil($engagement * $TData['cout_unitaire']),2));

		return $TData;
	}
	
	private function ceil($valeur, $puissance=5) {

		return ceil($valeur * pow(10, $puissance)) / pow(10, $puissance);
		
	}
	
	/**
	 * Retourne la ligne propal ayant le fk_product passé en param
	 */
	static function get_line_from_propal(&$propal, $ref_product) {
		//pre($propal->lines ,true);
		foreach($propal->lines as $line) {
			if($line->ref == $ref_product) return $line;
		}
		
	}
	
	function calcul_percent_couleur($cout_unit_noir_loyer=0, $engagement_noir=0, $cout_unitaire_coul_loyer=0, $engagement_couleur=0) {
		//return "25.23";
		// (cout loyer couleur * engagement couleur) / ((Cout loyer noir * engagement noir) + (cout loyer couleur * engagement couleur))
		if(empty($cout_unit_noir_loyer)) $cout_unit_noir_loyer = $this->cout_unit_noir_loyer;
		if(empty($engagement_noir)) $engagement_noir = $this->vol_noir_engage;
		if(empty($cout_unitaire_coul_loyer)) $cout_unitaire_coul_loyer = $this->cout_unit_coul_loyer;
		if(empty($engagement_couleur)) $engagement_couleur = $this->vol_coul_engage;
		
		$loyer_noir = $cout_unit_noir_loyer * $engagement_noir;
		$loyer_couleur = $cout_unitaire_coul_loyer * $engagement_couleur;
		//echo 'TADA'.$loyer_couleur. '-' .$loyer_noir;
		$total_loyer = ($loyer_noir + $loyer_couleur);
		if($total_loyer > 0) {
			return round($loyer_couleur / $total_loyer * 100);
		}
		return 0;
	}
}

