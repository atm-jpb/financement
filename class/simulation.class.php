<?php

class TSimulation extends TObjetStd {
	function __construct() {
		global $langs;
		
		parent::set_table(MAIN_DB_PREFIX.'fin_simulation');
		parent::add_champs('entity,fk_soc,fk_user_author,fk_leaser,accord_confirme','type=entier;');
		parent::add_champs('duree,opt_administration,opt_creditbail','type=entier;');
		parent::add_champs('montant,echeance,vr,coeff,cout_financement','type=float;');
		parent::add_champs('date_simul','type=date;');
		parent::add_champs('opt_periodicite,opt_mode_reglement,opt_terme,fk_type_contrat,accord,type_financement','type=chaine;');
		parent::start();
		parent::_init_vars();
		
		$this->init();
		
		$this->TStatut=array(
			'OK'=>$langs->trans('Accord')
			,'WAIT'=>$langs->trans('Etude')
			,'KO'=>$langs->trans('Refus')
			,'SS'=>$langs->trans('SansSuite')
		);
	}
	
	function init() {
		global $user;
		$this->opt_periodicite = 'opt_trimestriel';
		$this->opt_mode_reglement = 'opt_prelevement';
		$this->opt_terme = 'opt_aechoir';
		$this->vr = 0;
		$this->coeff = 0;
		$this->fk_user_author = $user->id;
		$this->user = $user;
	}
	
	function load(&$db, &$doliDB, $id, $annexe=true) {
		parent::load($db, $id);
		
		if($annexe) {
			$this->load_annexe($db, $doliDB);
		}
	}
	
	function load_annexe(&$db, &$doliDB) {
		if(!empty($this->fk_soc)) {
			$this->societe = new Societe($doliDB);
			$this->societe->fetch($this->fk_soc);
			
			// Récupération du score du client
			$this->societe->score = new TScore();
			$this->societe->score->load_by_soc($db, $this->fk_soc);
		}
		
		if(!empty($this->fk_user_author)) {
			$this->user = new User($doliDB);
			$this->user->fetch($this->fk_user_author);
		}
	}
	
	// TODO : Revoir validation financement avec les règles finales
	function demande_accord() {
		// Calcul du coût du financement
		$this->cout_financement = $this->echeance * $this->duree - $this->montant;
		$this->accord = '';

		// Accord interne de financement
		if(!(empty($this->fk_soc))) {
			$this->accord = 'WAIT';
			if($this->societe->score > 50 && $this->societe->encours_max > ($this->societe->encours_cpro + $this-montant) * 0.8) {
				$this->accord = 'OK';
			}
		}
	}
}

