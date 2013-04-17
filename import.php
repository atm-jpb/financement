<?php
	@set_time_limit(0);
	ini_set('display_errors', true);

	require('config.php');

	dol_include_once("/financement/class/import.class.php");
	dol_include_once("/financement/class/import_error.class.php");
	dol_include_once("/financement/class/commerciaux.class.php");
	dol_include_once("/financement/class/affaire.class.php");
	dol_include_once("/financement/class/dossier.class.php");
	dol_include_once("/financement/class/grille.class.php");
	dol_include_once("/financement/class/score.class.php");
	dol_include_once("/equipement/class/asset.class.php");
	dol_include_once("/societe/class/societe.class.php");
	dol_include_once("/compta/facture/class/facture.class.php");
	dol_include_once("/product/class/product.class.php");
	dol_include_once("/core/class/html.form.class.php");
	dol_include_once("/fourn/class/fournisseur.facture.class.php");
	
	require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");

	$langs->load('financement@financement');
	$import=new TImport();
	$ATMdb = new TPDOdb;
	$tbs = new TTemplateTBS;
	
	$mesg = '';
	$error=false;
	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'new':
				_fiche($ATMdb, $import, 'new');
				break;
			case 'add':
				$importFolder = FIN_IMPORT_FOLDER.'todo/';
				$importFolderOK = FIN_IMPORT_FOLDER.'done/';
				$importFolderMapping = FIN_IMPORT_FOLDER.'mappings/';
		
				if ($_FILES["fileToImport"]["error"] == UPLOAD_ERR_OK) {
					$tmp_name = $_FILES["fileToImport"]["tmp_name"];
					$fileName = $_FILES["fileToImport"]["name"];
					move_uploaded_file($tmp_name, $importFolder.$fileName);
				} else {
					$mesg = 'ErrorUploadingFile_'.$_FILES["fileToImport"]["error"];
					$error = true;
				}
		
				$fileType = GETPOST('type_import', 'alpha');
				if(empty($fileType)) {
					$mesg = 'ErrorTypeOfImportEmpty';
					$error = true;
				}
		
				if(!$error) {
					$imp=new TImport();
					$imp->entity = $conf->entity;
					$imp->fk_user_author = $user->id;
					
					$societe =new Societe($db);
					$societe->fetch($_REQUEST['socid']);
					
					$mappingFile = ($fileType == 'fichier_leaser' ? $fileType.'.'.$societe->code_client.'.mapping' : $fileType.'.mapping');
					$imp->getMapping($importFolderMapping.$mappingFile); // Récupération du mapping
					
					$imp->init($fileName, $fileType);
					$imp->save($ATMdb); // Création de l'import
		
					$f1 = fopen($importFolder.$fileName, 'r');
					
					if(isset($_REQUEST['ignore_first_line'])) {
						fgetcsv($f1 ,1024, $_REQUEST['delimiter'], empty($_REQUEST['enclosure']) ? FIN_IMPORT_FIELD_ENCLOSURE : $_REQUEST['enclosure']);
					}
					
					$TInfosGlobale = array();
					while($dataline = fgetcsv($f1, 1024, $_REQUEST['delimiter'], empty($_REQUEST['enclosure']) ? FIN_IMPORT_FIELD_ENCLOSURE : $_REQUEST['enclosure'])) {
						$dataline[9999] = $societe->id;
						$imp->importLine($ATMdb, $dataline, $fileType, $TInfosGlobale);
					}
					
					$imp->save($ATMdb); // Mise à jour pour nombre de lignes et nombre d'erreurs

					rename($importFolder.$fileName, $importFolderOK.$fileName);
					
					fclose($f1);
					
					_fiche($ATMdb, $imp, 'view');
				} else {
					_fiche($ATMdb, $import, 'new');
				}
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$import->load($ATMdb, $_REQUEST['id']);
		
		_fiche($ATMdb, $import, 'view');global $mesg, $error;
	}
	else {
		/*
		 * Liste
		 */
		 _liste($ATMdb, $import);
	}
	
	$ATMdb->close();
	
function _liste(&$ATMdb, &$import) {
	global $langs, $db, $conf;	
	
	llxHeader('','Imports');
	
	$r = new TListviewTBS('import_list');
	$sql = "SELECT i.rowid as 'ID', i.date as 'Date import', i.type_import as 'Type import', i.filename as 'Nom du fichier', i.fk_user_author,";
	$sql.= " u.login as 'Utilisateur', i.nb_lines as 'Nb lignes', i.nb_errors as 'Nb erreurs', i.nb_create as 'Nb création', i.nb_update as 'Nb modif'";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_import i ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON i.fk_user_author = u.rowid";
	$sql.= " WHERE i.entity = ".$conf->entity;
	
	$THide = array('fk_user_author');
	
	$TOrder = array('Date import' => 'DESC', 'ID' => 'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
	
	echo $r->render($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'30'
		)
		,'orderBy'=>$TOrder
		,'link'=>array(
			'ID'=>'<a href="?id=@ID@">@val@</a>'
			,'Utilisateur'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user_author@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
			,'Nb erreurs'=>'<a href="?id=@ID@">@val@</a>'
		)
		,'hide'=>$THide
		,'type'=>array('Date import'=>'datetime')
		,'liste'=>array(
			'titre'=>'Liste des imports'
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun import à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			
		)
	));
	
	?><div class="tabsAction"><a href="?action=new" class="butAction">Nouvel import</a></div><?

	llxFooter();
}	
	
function _fiche(&$ATMdb, &$import, $mode) {
	global $db, $langs;
	
	llxHeader('','Imports');
	
	$html=new Form($db);

	$form=new TFormCore($_SERVER['PHP_SELF'],'formNewImport','POST', true);
	$form->Set_typeaff($mode);

	echo $form->hidden('action', 'add');

	$user = new User($db);
	$user->fetch($import->fk_user_author);
	
	$TBS=new TTemplateTBS();
	
	print $TBS->render('./tpl/import.tpl.php'
		,array()
		,array(
			'import'=>array(
				'titre_new'=>load_fiche_titre($langs->trans("NewImport"),'','import32.png@financement')
				,'titre_view'=>img_picto('','object_import.png@financement', '', 0).' '.$langs->trans("Import")
			
				,'id'=>$import->getId()
				,'type_import'=>$form->combo('', 'type_import', ($mode == 'new') ? $import->TType_import : array_merge($import->TType_import, $import->TType_import_interne), $import->type_import) 
				,'date'=>date('d/m/Y à H:i:s', $import->date ? $import->date : time())
				,'filename'=>'<a href="./import/done/'.$import->filename.'" target="_blank">'.$import->filename.'</a>'
				,'ignore_first_line'=>$form->checkbox1('', 'ignore_first_line', 0)
				,'delimiter'=>$form->texte('', 'delimiter', FIN_IMPORT_FIELD_DELIMITER, 5)
				,'enclosure'=>$form->texte('', 'enclosure', FIN_IMPORT_FIELD_ENCLOSURE, 5)
				,'fileToImport'=>$form->fichier('', 'fileToImport', '', 10)
				
				,'user'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$import->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$user->nom.'</a>'
				,'nb_lines'=>$import->nb_lines
				,'nb_errors'=>$import->nb_errors
				,'nb_create'=>$import->nb_create
				,'nb_update'=>$import->nb_update
				
				,'leaser'=>$html->select_company('','socid','fournisseur=1',0, 0,1)
			)
			,'view'=>array(
				'mode'=>$mode
			)
			,'liste_errors'=>_liste_errors($ATMdb, $import)
		)
	);
	
	echo $form->end_form();
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}

function _liste_errors(&$ATMdb, $import) {
	global $langs;
	$langs->load("financement@financement");
	$r = new TListviewTBS('import_error_list');
	$sql = "SELECT ie.num_line, ie.type_erreur, ie.error_msg, ie.error_data, ie.content_line, ie.sql_errno, ie.sql_error";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_import_error ie ";
	$sql.= " WHERE ie.fk_import = ".$import->getId();
	
	$THide = array('sql_errno', 'sql_error');
	
	return $r->render($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'30'
		)
		,'orderBy'=>array(
			'num_line' => 'ASC'
		)
		,'link'=>array()
		,'translate'=>array()
		,'eval'=>array('error_msg'=>'_langs_trans("@val@")')
		,'hide'=>$THide
		,'type'=>array()
		,'liste'=>array(
			'titre'=>'Liste des erreur d\'imports de l\'import n° '.$import->getId()
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun import à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'num_line'=>'Ligne'
			,'type_erreur'=>'Type'
			,'error_msg'=>'Message'
			,'content_line'=> 'Données de la ligne'
			,'sql_errno'=> 'Erreur SQL'
			,'sql_error'=>'Trace SQL'
			,'error_data'=>'Donnée utilisée'
		)
		/*,'search'=>array(
			'num_line'=>true
			,'type_erreur'=>array('ERROR','WARNING')
			,'error_msg'=>true
		)*/
	));
}

function _langs_trans($str) {
	global $langs;
	$langs->load("financement@financement");
	return $langs->trans($str);
}
