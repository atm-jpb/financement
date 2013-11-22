<?php

	define('ROOT','/var/www/ATM/dolibarr/htdocs/');
	define('COREROOT','/var/www/ATM/atm-core/');
	define('COREHTTP','http://127.0.0.1/ATM/atm-core/');
	define('HTTP','http://127.0.0.1/ATM/dolibarr/');

	if(!defined('INC_FROM_DOLIBARR') && defined('INC_FROM_CRON_SCRIPT')) {
		include(ROOT."master.inc.php");
	}
	elseif(!defined('INC_FROM_DOLIBARR')) {
		include(ROOT."main.inc.php");
	}

	define('DB_HOST',$dolibarr_main_db_host);
	define('DB_NAME',$dolibarr_main_db_name);
	define('DB_USER',$dolibarr_main_db_user);
	define('DB_PASS',$dolibarr_main_db_pass);
	define('DB_DRIVER','mysqli');

	define('DOL_PACKAGE', true);
	define('USE_TBS', true);
	
	require(COREROOT.'inc.core.php');
	
	define('FIN_LEASER_DEFAULT', 1);
	define('FIN_TVA_DEFAUT', 1.196);
	define('DOL_ADMIN_USER', 'admin');
	define('FIN_IMPORT_FOLDER', ROOT.'custom/financement/import/');
	define('FIN_IMPORT_FIELD_DELIMITER', ';');
	define('FIN_IMPORT_FIELD_ENCLOSURE', '"');
	
	define('FIN_PRODUCT_FRAIS_DOSSIER', '0');
	define('FIN_PRODUCT_LOC_ADOSSEE', '0');
	define('FIN_PRODUCT_LOC_MANDATEE', '0');
	
	define('FIN_THEREFORE_AFFAIRE_URL','http://srvtherefore/TWA/TheGetDoc.aspx?CtgryNo=4&N_Affaire=');
	define('FIN_THEREFORE_DOSSIER_URL','http://srvtherefore/TWA/TheGetDoc.aspx?CtgryNo=4&N_Affaire=');
	define('FIN_WONDERBASE_USER_RIGHT_URL', 'http://192.168.0.112/dolibarr/getDroitsAcces.php');
	
	$TLeaserTypeSolde = array(
		18305	=> 'CRD' // ACECOM
		,3382	=> 'CRD' // BNP
		,19553	=> 'CRD' // BNP A
		,20113	=> 'CRD' // BNP M
		,3214	=> 'CRD' // CM-CIC A
		,7411	=> 'CRD' // GE
		,4440	=> 'CRD' // GRENKE
		,1210	=> 'CRD' // KBC
		,6065	=> 'CRD' // LIXXBAIL
		,19068	=> 'CRD' // LIXXBAIL A
		,19483	=> 'CRD' // LIXXBAIL M
		,18495	=> 'CRD' // LOC PURE
		,3306	=> 'CRD' // LOCAM
	);

	
	define('CRD_COEF_RENTA_ATTEINTE', 0.01);
	define('SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH', 12);
	define('SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH', 12);
	