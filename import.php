<?php
/* Copyright (C) 2012      Maxime Kohlhaas        <maxime@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */


/**
 *	\file       financement/import.php
 *	\ingroup    financement
 *	\brief      Import de données dans Dolibarr
 */

require('config.php');

dol_include_once('/financement/class/import.class.php');
dol_include_once('/financement/class/html.formfinancement.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");

if (!($user->rights->financement->import->read))
{
	accessforbidden();
}

$langs->load('financement@financement');

$error = false;
$mesg = '';

$id=GETPOST("id");
$import=GETPOST("import");
$cancel=GETPOST("cancel");
$mode=GETPOST("mode")?GETPOST("mode"):'list';

/*
 * Actions
 */

 if($mode == 'list') {
	$search_type_import=GETPOST('search_type_import','int');
	$search_filename=GETPOST('search_filename','alpha');
	$search_author=GETPOST('search_author','alpha');
	$year=GETPOST("year");
	$month=GETPOST("month");
	
	$sortfield = GETPOST("sortfield",'alpha');
	$sortorder = GETPOST("sortorder",'alpha');
	$page = GETPOST("page",'int');
	if ($page == -1) { $page = 0; }
	$offset = $conf->liste_limit * $page;
	$pageprev = $page - 1;
	$pagenext = $page + 1;
	
	if (! $sortfield) $sortfield='i.date';
	if (! $sortorder) $sortorder='DESC';
	$limit = $conf->liste_limit;
	
	$sql = "SELECT i.rowid, i.date, i.type_import, i.filename, i.fk_user_author, u.login, i.nb_lines, i.nb_errors, i.nb_create, i.nb_update";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_import i ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON i.fk_user_author = u.rowid";
	$sql.= " WHERE i.entity = ".$conf->entity;
	
	if ($search_type_import)
	{
		$sql.= " AND i.type_import = ".$db->escape(trim($search_type_import));
	}
	if ($search_filename)
	{
		$sql.= " AND i.filename LIKE '%".$db->escape(trim($search_filename))."%'";
	}
	if ($search_author)
	{
		$sql.= " AND (u.name LIKE '%".$db->escape(trim($search_author))."%' OR u.firstname LIKE '%".$db->escape(trim($search_author))."%')";
	}
	if ($month > 0)
	{
		if ($year > 0) $sql.= " AND date_format(i.date, '%Y-%m') = '".$year."-".$month."'";
		else $sql.= " AND date_format(i.date, '%m') = '".$month."'";
	}
	if ($year > 0)
	{
		$sql.= " AND date_format(i.date, '%Y') = '".$year."'";
	}
	
	$sql.= ' ORDER BY '.$sortfield.' '.$sortorder;
	$sql.= $db->plimit($limit + 1,$offset);
	$import_list=$db->query($sql);
}

if($mode == 'new') {
	if(!empty($import)) {
		$delimiter = ';'; $enclosure = '"';
		$listOfFileType = array('client');
		$importFolder = '../import/todo/';
		$importFolderOK = '../import/done/';
		$importFolderMapping = '../import/mappings/';

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
			$imp=new Import($db);
			$imp->entity = $conf->entity;
			$imp->fk_user_author = $user->id;
			
			$importScriptFile = 'import_'.$fileType.'.script.php';
			$mappingFile = $fileType.'.mapping';
			$imp->getMapping($importFolderMapping.$mappingFile); // Récupération du mapping
			
			$imp->filename = $fileName;
			$imp->type_import = $fileType;
			$imp->nb_lines = 0;
			$imp->nb_errors = 0;
			$imp->nb_create = 0;
			$imp->nb_update = 0;
			$imp->date = time();
			$imp->create($user); // Création de l'import

			$fileHandler = fopen($importFolder.$fileName, 'r');
			include $importScriptFile;

			$imp->update($user); // Mise à jour pour nombre de lignes et nombre d'erreurs

			rename($importFolder.$fileName, $importFolderOK.$fileName);
		}
	}
}

if($mode == 'list_error') {
	$import = new Import($db);
	$import->fetch($id);

	$sortfield = GETPOST("sortfield",'alpha');
	$sortorder = GETPOST("sortorder",'alpha');
	$page = GETPOST("page",'int');
	if ($page == -1) { $page = 0; }
	$offset = $conf->liste_limit * $page;
	$pageprev = $page - 1;
	$pagenext = $page + 1;
	
	if (! $sortfield) $sortfield='ie.num_line';
	if (! $sortorder) $sortorder='ASC';
	$limit = $conf->liste_limit;

	$sql = "SELECT ie.num_line, ie.error_msg, ie.content_line, ie.sql_errno, ie.sql_error";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_import_error ie ";
	$sql.= " WHERE ie.fk_import = ".$import->id;
	$sql.= ' ORDER BY '.$sortfield.' '.$sortorder;
	$sql.= $db->plimit($limit + 1,$offset);
	$import_error_list=$db->query($sql);
}

/*
 * View
 */


llxHeader();

//$form = new Form($db);
$formother = new FormOther($db);
//$formcompany = new FormCompany($db);
$formfin = new FormFinancement($db);

switch ($mode) {
	case 'new':
		$tpl = 'tpl/import_new.tpl.php';
		break;
	case 'list':
		$tpl = 'tpl/import_list.tpl.php';
		break;
	case 'list_error':
		$tpl = 'tpl/import_error_list.tpl.php';
		break;
	
	default:
		$tpl = 'tpl/import_list.tpl.php';
		break;
}

include $tpl;

dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));

llxFooter('');

$db->close();
?>
