<?php
/* Copyright (C) 2007-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
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
 *  \file       dev/skeletons/Import.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2012-12-25 19:07
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");


/**
 *	Put here description of your class
 */
class Import // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='Import';			//!< Id that identify managed objects
	//var $table_element='Import';	//!< Name of table without prefix where object is stored

    var $id;
    
	var $entity;
	var $fk_user_author;
	var $type_import;
	var $date='';
	var $filename;
	var $nb_lines;
	var $nb_errors;
	var $nb_create;
	var $nb_update;

    


    /**
     *  Constructor
     *
     *  @param	DoliDb		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;
        return 1;
    }


    /**
     *  Create object into database
     *
     *  @param	User	$user        User that create
     *  @param  int		$notrigger   0=launch triggers after, 1=disable triggers
     *  @return int      		   	 <0 if KO, Id of created object if OK
     */
    function create($user, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->entity)) $this->entity=trim($this->entity);
		if (isset($this->fk_user_author)) $this->fk_user_author=trim($this->fk_user_author);
		if (isset($this->type_import)) $this->type_import=trim($this->type_import);
		if (isset($this->filename)) $this->filename=trim($this->filename);
		if (isset($this->nb_lines)) $this->nb_lines=trim($this->nb_lines);
		if (isset($this->nb_errors)) $this->nb_errors=trim($this->nb_errors);
		if (isset($this->nb_create)) $this->nb_create=trim($this->nb_create);
		if (isset($this->nb_update)) $this->nb_update=trim($this->nb_update);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."fin_import(";
		
		$sql.= "entity,";
		$sql.= "fk_user_author,";
		$sql.= "type_import,";
		$sql.= "date,";
		$sql.= "filename,";
		$sql.= "nb_lines,";
		$sql.= "nb_errors,";
		$sql.= "nb_create,";
		$sql.= "nb_update";

		
        $sql.= ") VALUES (";
        
		$sql.= " ".(! isset($this->entity)?'NULL':"'".$this->entity."'").",";
		$sql.= " ".(! isset($this->fk_user_author)?'NULL':"'".$this->fk_user_author."'").",";
		$sql.= " ".(! isset($this->type_import)?'NULL':"'".$this->db->escape($this->type_import)."'").",";
		$sql.= " ".(! isset($this->date) || dol_strlen($this->date)==0?'NULL':$this->db->idate($this->date)).",";
		$sql.= " ".(! isset($this->filename)?'NULL':"'".$this->db->escape($this->filename)."'").",";
		$sql.= " ".(! isset($this->nb_lines)?'NULL':"'".$this->nb_lines."'").",";
		$sql.= " ".(! isset($this->nb_errors)?'NULL':"'".$this->nb_errors."'").",";
		$sql.= " ".(! isset($this->nb_create)?'NULL':"'".$this->nb_create."'").",";
		$sql.= " ".(! isset($this->nb_update)?'NULL':"'".$this->nb_update."'")."";

        
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."fin_import");

			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_CREATE',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
			}
        }

        // Commit or rollback
        if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
            return $this->id;
		}
    }


    /**
     *  Load object in memory from database
     *
     *  @param	int		$id    Id object
     *  @return int          	<0 if KO, >0 if OK
     */
    function fetch($id)
    {
    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid,";
		
		$sql.= " t.entity,";
		$sql.= " t.fk_user_author,";
		$sql.= " t.type_import,";
		$sql.= " t.date,";
		$sql.= " t.filename,";
		$sql.= " t.nb_lines,";
		$sql.= " t.nb_errors,";
		$sql.= " t.nb_create,";
		$sql.= " t.nb_update";

		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_import as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;
                
				$this->entity = $obj->entity;
				$this->fk_user_author = $obj->fk_user_author;
				$this->type_import = $obj->type_import;
				$this->date = $this->db->jdate($obj->date);
				$this->filename = $obj->filename;
				$this->nb_lines = $obj->nb_lines;
				$this->nb_errors = $obj->nb_errors;
				$this->nb_create = $obj->nb_create;
				$this->nb_update = $obj->nb_update;

                
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
      	    $this->error="Error ".$this->db->lasterror();
            dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *  Update object into database
     *
     *  @param	User	$user        User that modify
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
     *  @return int     		   	 <0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
    	global $conf, $langs;
		$error=0;

		// Clean parameters
        
		if (isset($this->entity)) $this->entity=trim($this->entity);
		if (isset($this->fk_user_author)) $this->fk_user_author=trim($this->fk_user_author);
		if (isset($this->type_import)) $this->type_import=trim($this->type_import);
		if (isset($this->filename)) $this->filename=trim($this->filename);
		if (isset($this->nb_lines)) $this->nb_lines=trim($this->nb_lines);
		if (isset($this->nb_errors)) $this->nb_errors=trim($this->nb_errors);
		if (isset($this->nb_create)) $this->nb_create=trim($this->nb_create);
		if (isset($this->nb_update)) $this->nb_update=trim($this->nb_update);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."fin_import SET";
        
		$sql.= " entity=".(isset($this->entity)?$this->entity:"null").",";
		$sql.= " fk_user_author=".(isset($this->fk_user_author)?$this->fk_user_author:"null").",";
		$sql.= " type_import=".(isset($this->type_import)?"'".$this->db->escape($this->type_import)."'":"null").",";
		$sql.= " date=".(dol_strlen($this->date)!=0 ? "'".$this->db->idate($this->date)."'" : 'null').",";
		$sql.= " filename=".(isset($this->filename)?"'".$this->db->escape($this->filename)."'":"null").",";
		$sql.= " nb_lines=".(isset($this->nb_lines)?$this->nb_lines:"null").",";
		$sql.= " nb_errors=".(isset($this->nb_errors)?$this->nb_errors:"null").",";
		$sql.= " nb_create=".(isset($this->nb_create)?$this->nb_create:"null").",";
		$sql.= " nb_update=".(isset($this->nb_update)?$this->nb_update:"null")."";

        
        $sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
		{
			if (! $notrigger)
			{
	            // Uncomment this and change MYOBJECT to your own tag if you
	            // want this action call a trigger.

	            //// Call triggers
	            //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
	            //$interface=new Interfaces($this->db);
	            //$result=$interface->run_triggers('MYOBJECT_MODIFY',$this,$user,$langs,$conf);
	            //if ($result < 0) { $error++; $this->errors=$interface->errors; }
	            //// End call triggers
	    	}
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
    }


 	/**
	 *  Delete object in database
	 *
     *	@param  User	$user        User that delete
     *  @param  int		$notrigger	 0=launch triggers after, 1=disable triggers
	 *  @return	int					 <0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$this->db->begin();

		if (! $error)
		{
			if (! $notrigger)
			{
				// Uncomment this and change MYOBJECT to your own tag if you
		        // want this action call a trigger.

		        //// Call triggers
		        //include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
		        //$interface=new Interfaces($this->db);
		        //$result=$interface->run_triggers('MYOBJECT_DELETE',$this,$user,$langs,$conf);
		        //if ($result < 0) { $error++; $this->errors=$interface->errors; }
		        //// End call triggers
			}
		}

		if (! $error)
		{
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."fin_import";
    		$sql.= " WHERE rowid=".$this->id;

    		dol_syslog(get_class($this)."::delete sql=".$sql);
    		$resql = $this->db->query($sql);
        	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }
		}

        // Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
	            dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
	            $this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}



	/**
	 *	Load an object from its id and create a new one in database
	 *
	 *	@param	int		$fromid     Id of object to clone
	 * 	@return	int					New id of clone
	 */
	function createFromClone($fromid)
	{
		global $user,$langs;

		$error=0;

		$object=new Import($this->db);

		$this->db->begin();

		// Load source object
		$object->fetch($fromid);
		$object->id=0;
		$object->statut=0;

		// Clear fields
		// ...

		// Create clone
		$result=$object->create($user);

		// Other options
		if ($result < 0)
		{
			$this->error=$object->error;
			$error++;
		}

		if (! $error)
		{


		}

		// End
		if (! $error)
		{
			$this->db->commit();
			return $object->id;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}


	/**
	 *	Initialise object with example values
	 *	Id must be 0 if object instance is a specimen
	 *
	 *	@return	void
	 */
	function initAsSpecimen()
	{
		$this->id=0;
		
		$this->entity='';
		$this->fk_user_author='';
		$this->type_import='';
		$this->date='';
		$this->filename='';
		$this->nb_lines='';
		$this->nb_errors='';
		$this->nb_create='';
		$this->nb_update='';

		
	}

	/************************************************************************************************************************************
	 * PERSO FUNCTIONS
	 ************************************************************************************************************************************/
	/**
	 * Récupération des fichiers à importer
	 * Stockage dans le dossier import
	 */
	function getFiles($targetFolder)
	{
		// TODO
	}
	
	function getListOfFiles($folder, $filePrefix)
	{
		$result = array();
		
		$dirHandle = opendir($folder);
		while ($fname = readdir($dirHandle)) {
			if(substr($fname, 0, strlen($filePrefix)) == $filePrefix) $result[] = $fname;
		}
		
		sort($result);
		
		return $result;
	}
	
	function init($fileName, $fileType) {
		$this->filename = $fileName;
		$this->type_import = $fileType;
		$this->nb_lines = 0;
		$this->nb_errors = 0;
		$this->nb_create = 0;
		$this->nb_update = 0;
		$this->date = time();
	}
	
	function getMapping($mappingFile) {
		$this->mapping = parse_ini_file($mappingFile, true);
	}
	
	function addError($errMsg, $dataLine, $is_sql=false) {
		global $user;
		$thisErr = new ImportError($this->db);
		$thisErr->fk_import = $this->id;
		$thisErr->num_line = $this->nb_lines;
		$thisErr->content_line = serialize($dataLine);
		$thisErr->error_msg = $errMsg;
		if($is_sql) {
			$thisErr->sql_errno = $this->db->lasterrno;
			$thisErr->sql_error = lastqueryerror."\n".$this->db->lasterror."\n".$this->db->lastquery;
		}
		$thisErr->create($user);

		$this->nb_errors++;
	}
	
	function importLine($dataline, $type) {
		switch ($type) {
			case 'client':
				$this->importLineTiers($dataline);
				break;
			case 'facture_materiel':
				$this->importLineFactureMateriel($dataline);
				break;
			case 'commercial':
				$this->importLineCommercial($dataline);
				break;
			
			default:
				
				break;
		}
	}
	
	function importLineTiers($dataline) {
		global $user;
		dol_include_once("/societe/class/societe.class.php");
		$sqlSearchClient = "SELECT rowid FROM llx_societe WHERE code_client = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si tiers existant dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchClient, $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $dataline);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $dataline);
			return false;
		}
		
		// Construction de l'objet final
		$societe = new Societe($this->db);
		if($rowid > 0) {
			$societe->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$societe->{$key} = $this->validateValue($key, $val);
		}

		// Mise à jour ou créatioon
		if($rowid > 0) {
			$res = $societe->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileUpdatingLine', $dataline, true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $societe->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileCreatingLine', $dataline, true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		return true;
	}

	function importLineFactureMateriel($dataline) {
		global $user;
		dol_include_once("/compta/facture/class/facture.class.php");
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		$facnumber = $data[$this->mapping['search_key']];
		$socid = $data[$this->mapping['search_key_client']];
		
		$sqlSearchFacture = "SELECT rowid FROM llx_facture WHERE facnumber = '$facnumber'";
		$sqlSearchClient = "SELECT rowid FROM llx_societe WHERE code_client = '$socid'";
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$resql = $this->db->query($sqlSearchFacture);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleFactureFound', $dataline);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingFacture', $dataline);
			return false;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$resql = $this->db->query($sqlSearchClient);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $dataline);
				return false;
			} else {
				$this->addError('ErrorNoClientFound', $dataline);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $dataline, true);
			return false;
		}
		
		$data['socid'] = $fk_soc;
		
		// Construction de l'objet final
		$facture_mat = new Facture($this->db);
		if($rowid > 0) {
			$facture_mat->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$facture_mat->{$key} = $this->validateValue($key, $value);
		}
		
		// Mise à jour ou créatioon
		if($rowid > 0) {
			$res = $facture_mat->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileUpdatingLine', $dataline, true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $facture_mat->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileCreatingLine', $dataline, true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		// Actions spécifiques
		$facture_mat->addline($facture_mat->id, 'Matricule '.$data['matricule'], 0, 1, 19.6, 0, 0, 0, 0, '', '', 0, 0, '', 'TTC', $data['total_ttc']);
		$facture_mat->validate($user, $facnumber); // Force la validation avec numéro de facture
		
		return true;
	}

	function importLineAffaire($dataline) { // TODO : Faux, pas affaire
		global $user;
		dol_include_once("/compta/facture/class/facture.class.php");
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		$facnumber = $data[$this->mapping['search_key']];
		$socid = $data[$this->mapping['search_key_client']];
		
		$sqlSearchFacture = "SELECT rowid FROM llx_facture WHERE facnumber = '$facnumber'";
		$sqlSearchClient = "SELECT rowid FROM llx_societe WHERE code_client = '$socid'";
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$resql = $this->db->query($sqlSearchFacture);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleFactureFound', $dataline);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingFacture', $dataline);
			return false;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$resql = $this->db->query($sqlSearchClient);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $dataline);
				return false;
			} else {
				$this->addError('ErrorNoClientFound', $dataline);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $dataline, true);
			return false;
		}
		
		$data['socid'] = $fk_soc;
		
		// Construction de l'objet final
		$facture_mat = new Facture($this->db);
		if($rowid > 0) {
			$facture_mat->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$facture_mat->{$key} = $this->validateValue($key, $value);
		}
		
		// Mise à jour ou créatioon
		if($rowid > 0) {
			$res = $facture_mat->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileUpdatingLine', $dataline, true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $facture_mat->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileCreatingLine', $dataline, true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		// Actions spécifiques
		$facture_mat->addline($facture_mat->id, 'Matricule '.$data['matricule'], 0, 1, 19.6, 0, 0, 0, 0, '', '', 0, 0, '', 'TTC', $data['total_ttc']);
		$facture_mat->validate($user, $facnumber); // Force la validation avec numéro de facture
		
		return true;
	}

	function importLineCommercial($dataline) { // TODO : à tester
		global $user;
		/*
		 *  code client; type_activité;login user
		 *  "000012";"Copieur";"ABX"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$row = $this->contructDataTab($dataline);
		
		$ATMdb=new Tdb;	
		$c=new TCommercialCpro;
		$c->addFieldsInDb($ATMdb);
		
		if(!$user->fetch('',$row['login'])) {
			$this->addError('ErrorUserNotExist', $dataline);
			return false;
		}
		else {
			$fk_user = $user->id;
		}
		
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array('code_client'=>$row['code_client']));
		if(count($TRes)==0) {
			$this->addError('ErrorNoClientFound', $dataline);
			return false;
		}
		else if(count($TRes) > 1) { // Plusieurs trouvés, erreur
			$this->addError('ErrorMultipleClientFound', $dataline);
			return false;
		} else {
			$fk_soc = $TRes[0];
		}
		
		$c->loadUserClient($ATMdb, $fk_user, $fk_soc); // charge l'objet si existant
		
		$c->fk_soc = $fk_soc;
		$c->fk_user = $fk_user;
		
		$c->type_activite_cpro = $row['type_activite_cpro'];
		//print_r($c); exit;
		$c->save($ATMdb);
		
	
		
		return true;
	}

	function checkData($dataline) {
		// Vérification cohérence des données
		if(count($this->mapping['mapping']) != count($dataline)) {
			$this->addError('ErrorNbColsNotMatchingMapping', $dataline);
			return false;
		}
		
		return true;
	}
	
	function contructDataTab($dataline) {
		// Construction du tableau de données
		$data = array();
		array_walk($dataline, 'trim');
		$data = array_combine($this->mapping['mapping'], $dataline); // Combinaison des valeurs de la ligne et du mapping
		if(isset($this->mapping['more'])) $data = array_merge($data, $this->mapping['more']); // Ajout des valeurs autres
		
		return $data;
	}
	
	function validateValue($key, $value) {
		// Nettoyage de la valeur
		$value = trim($value);
		// Si un tableau de transco existe, on l'utilise
		if(!empty($this->mapping['transco'][$key]) && !empty($this->mapping[$key][$value])) $value = $this->mapping[$key][$value];
		// Si un format spécial existe, on l'applique
		if(!empty($this->mapping['format'][$key])) {
			switch($this->mapping['format'][$key]) {
				case 'date':
					list($day, $month, $year) = explode("/", $value);
					$value = mktime(0, 0, 0, $month, $day, $year);
					break;
				case 'float':
					$value = strtr($value, array(',' => '.', ' ' => ''));
					break;
				default:
					break;
			}
		}
		return $value;
	}
}
?>
