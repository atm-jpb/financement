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
				td[field="Montant"] {
					white-space:nowrap;
				}
			</style>
		<?php
		
	}
	
}
