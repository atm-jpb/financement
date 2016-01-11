<?php
	chdir(__DIR__);
	if(is_file('../main.inc.php'))$dir = '../';
	else  if(is_file('../../../main.inc.php'))$dir = '../../../';
	else  if(is_file('../../../../main.inc.php'))$dir = '../../../../';
	else  if(is_file('../../../../../main.inc.php'))$dir = '../../../../../';
	else $dir = '../../';

	if(!defined('INC_FROM_DOLIBARR') && defined('INC_FROM_CRON_SCRIPT')) {
		include($dir."master.inc.php");
	}
	elseif(!defined('INC_FROM_DOLIBARR')) {
		include($dir."main.inc.php");
	} else {
		global $dolibarr_main_db_host, $dolibarr_main_db_name, $dolibarr_main_db_user, $dolibarr_main_db_pass;
	}
	if(!defined('DB_HOST') && !empty($dolibarr_main_db_host)) {
		define('DB_HOST',$dolibarr_main_db_host);
		define('DB_NAME',$dolibarr_main_db_name);
		define('DB_USER',$dolibarr_main_db_user);
		define('DB_PASS',$dolibarr_main_db_pass);
		define('DB_DRIVER',$dolibarr_main_db_type);
	}

	dol_include_once('/abricot/inc.core.php');
	
	dol_include_once('/core/lib/admin.lib.php');

	define('DOL_PACKAGE', true);
	define('USE_TBS', true);
	
	define('FIN_LEASER_DEFAULT', 1);
	define('FIN_TVA_DEFAUT', 1.2);
	define('DOL_ADMIN_USER', 'admin');
	define('FIN_IMPORT_FOLDER', dol_buildpath('/financement/import/'));
	define('FIN_IMPORT_FIELD_DELIMITER', ';');
	define('FIN_IMPORT_FIELD_ENCLOSURE', '"');
	
	define('FIN_PRODUCT_FRAIS_DOSSIER', '3');
	define('FIN_PRODUCT_LOC_ADOSSEE', '1');
	define('FIN_PRODUCT_LOC_MANDATEE', '2');
	
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
		,21382	=> 'CRD' // GE M
		,4440	=> 'CRD' // GRENKE
		,1210	=> 'CRD' // KBC
		,6065	=> 'CRD' // LIXXBAIL
		,19068	=> 'CRD' // LIXXBAIL A
		,19483	=> 'CRD' // LIXXBAIL M
		//,3306	=> 'CRD' // LOCAM
	);

	
	define('CRD_COEF_RENTA_ATTEINTE', 0.03);
	define('SEUIL_SOLDE_BANK_FINANCEMENT_LEASER_MONTH', 12);
	define('SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH', 14);
	define('FK_SOC_LOCAM', 3306);
	
	define('BASE_TEST', 0);
	
	define('BNP_TEST',0);
	define('BNP_PRESCRIPTEUR_ID','FRAFT03559');
	define('BNP_WSDL_URL','https://leaseoffersu.leasingsolutions.bnpparibas.fr:4444/ExtranetEuroWS/services/demandeDeFinancementService/demandeDeFinancementWSDLv1.wsdl');
	
	define('GE_TEST',0);
	define('GE_WSDL_URL','https://minervademouat.fr/imanageB2B/ws/dealws.wsdl');
	
	
