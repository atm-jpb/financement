<?php

class TFinancementTools {
	
	static function user_courant_est_admin_financement() {
		
		global $db, $user;
		
		dol_include_once('/user/class/usergroup.class.php');
		
		// On vérifie si l'utilisateur fait partie du groupe admin financement
		$g = new UserGroup($db);
		$g->fetch('', 'GSL_DOLIBARR_FINANCEMENT_ADMIN');

		// On ne peut pas utiliser la fonction listgroupforuser parce qu'elle cherche les groupes dans lesquels se trouve l'utilisateur, mais uniquement les groupes qui sont dans l'entité courante
		$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE fk_user = '.$user->id.' AND fk_usergroup = '.$g->id);
		$res = $db->fetch_object($resql);
		
		if($res->rowid > 0) return true;
		else return false;
		
	}
	
	function check_user_rights(&$object) {
		
		global $user, $conf;
		
		dol_include_once('/core/lib/security.lib.php');

		if(!TFinancementTools::user_courant_est_admin_financement() && GETPOST('action') != 'new' && $object->entity != getEntity()) accessforbidden();
		
	}
	
	static function build_array_entities() {
		
		global $db;
		
		$obj_entity = new DaoMulticompany($db);
		$obj_entity->getEntities();
		
		$TEntityName = self::get_entity_translation();
		
		$TEntities = array();
		foreach($obj_entity->entities as $ent) {
			if(!empty($TEntityName[$ent->label]))
				$TEntities[$ent->id] = $TEntityName[$ent->label];
			else {
				$TEntities[$ent->id] = $ent->label;
			}
		}
		
		return $TEntities;
	}
	
	static function get_entity_translation($entity_id=false) {
		global $db, $conf;
		
		$TEntityAlternativeName = $conf->global->FINANCEMENT_TAB_ENTITY_ALTERNATIVE_NAME;
		// Constante de la forme : 1,Impression,C'PRO;2,Informatique,C'PRO info;3,Télécom,C'PRO Télécom
		if(empty($TEntityAlternativeName)) return $entity_id;
		$TEntityAlternativeName = explode(';', $TEntityAlternativeName);
		
		$TEntityName = array();
		
		foreach ($TEntityAlternativeName as $TData) {
			$tab_temp = explode(',', $TData);
			//var_dump($tab_temp);exit;
			if(empty($entity_id)) $TEntityName[$tab_temp[1]] = $tab_temp[2];
			else $TEntityName[$tab_temp[0]] = $tab_temp[2];
		}

		if(!empty($entity_id)) return $TEntityName[$entity_id];
		
		return $TEntityName;
		
	}
	
	static function add_css() {
		
		?>
			<style type="text/css">
				td[field="Montant"] {white-space:nowrap;}
				td[field="reference"] {text-align:center;}
				td[field="date_simul"] {text-align:center;}
				td[field="login"] {text-align:center;}
				td[field="accord"] {text-align:center;}
				td[field="type_financement"] {text-align:center;}
				
				td[id="num_contrat"] {text-align:center;}
				td[id="entity_dossier"] {text-align:center;}
				td[id="leaser"] {text-align:center;}
				td[id="type_contrat"] {text-align:center;}
				td[id="Montant"] {text-align:center;}
				td[id="duree"] {text-align:center;}
				td[id="echeance"] {text-align:center;}
				td[id="debut_fin"] {text-align:center;}
				td[id="prochaine_echeance"] {text-align:center;}
				td[id="assurance"] {text-align:center;}
				td[id="maintenance"] {text-align:center;}
				td[id="solde_r"] {text-align:center;}
				td[id="solde_nr"] {text-align:center;}
				td[id="solde_r1"] {text-align:center;}
				td[id="solde_nr1"] {text-align:center;}
				td[id="solde_perso"] {text-align:center;}
			</style>
		<?php
		
	}
	
}