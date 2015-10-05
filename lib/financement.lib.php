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

		if(!TFinancementTools::user_courant_est_admin_financement() && $object->rowid > 0 && $object->entity != getEntity()) accessforbidden();
		
	}
	
}
