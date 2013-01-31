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
 *  \file       dev/skeletons/finimporterror.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2013-01-31 11:20
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");


/**
 *	Put here description of your class
 */
class ImportError // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='finimporterror';			//!< Id that identify managed objects
	//var $table_element='finimporterror';	//!< Name of table without prefix where object is stored

    var $id;
    
	var $fk_import;
	var $num_line;
	var $content_line;
	var $error_msg;
	var $error_data;
	var $sql_executed;
	var $type_erreur;
	var $sql_errno;
	var $sql_error;

    


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
        
		if (isset($this->fk_import)) $this->fk_import=trim($this->fk_import);
		if (isset($this->num_line)) $this->num_line=trim($this->num_line);
		if (isset($this->content_line)) $this->content_line=trim($this->content_line);
		if (isset($this->error_msg)) $this->error_msg=trim($this->error_msg);
		if (isset($this->error_data)) $this->error_data=trim($this->error_data);
		if (isset($this->sql_executed)) $this->sql_executed=trim($this->sql_executed);
		if (isset($this->type_erreur)) $this->type_erreur=trim($this->type_erreur);
		if (isset($this->sql_errno)) $this->sql_errno=trim($this->sql_errno);
		if (isset($this->sql_error)) $this->sql_error=trim($this->sql_error);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."fin_import_error(";
		
		$sql.= "fk_import,";
		$sql.= "num_line,";
		$sql.= "content_line,";
		$sql.= "error_msg,";
		$sql.= "error_data,";
		$sql.= "sql_executed,";
		$sql.= "type_erreur,";
		$sql.= "sql_errno,";
		$sql.= "sql_error";

		
        $sql.= ") VALUES (";
        
		$sql.= " ".(! isset($this->fk_import)?'NULL':"'".$this->fk_import."'").",";
		$sql.= " ".(! isset($this->num_line)?'NULL':"'".$this->num_line."'").",";
		$sql.= " ".(! isset($this->content_line)?'NULL':"'".$this->db->escape($this->content_line)."'").",";
		$sql.= " ".(! isset($this->error_msg)?'NULL':"'".$this->db->escape($this->error_msg)."'").",";
		$sql.= " ".(! isset($this->error_data)?'NULL':"'".$this->db->escape($this->error_data)."'").",";
		$sql.= " ".(! isset($this->sql_executed)?'NULL':"'".$this->db->escape($this->sql_executed)."'").",";
		$sql.= " ".(! isset($this->type_erreur)?'NULL':"'".$this->db->escape($this->type_erreur)."'").",";
		$sql.= " ".(! isset($this->sql_errno)?'NULL':"'".$this->db->escape($this->sql_errno)."'").",";
		$sql.= " ".(! isset($this->sql_error)?'NULL':"'".$this->db->escape($this->sql_error)."'")."";

        
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."fin_import_error");

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
		
		$sql.= " t.fk_import,";
		$sql.= " t.num_line,";
		$sql.= " t.content_line,";
		$sql.= " t.error_msg,";
		$sql.= " t.error_data,";
		$sql.= " t.sql_executed,";
		$sql.= " t.type_erreur,";
		$sql.= " t.sql_errno,";
		$sql.= " t.sql_error";

		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_import_error as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;
                
				$this->fk_import = $obj->fk_import;
				$this->num_line = $obj->num_line;
				$this->content_line = $obj->content_line;
				$this->error_msg = $obj->error_msg;
				$this->error_data = $obj->error_data;
				$this->sql_executed = $obj->sql_executed;
				$this->type_erreur = $obj->type_erreur;
				$this->sql_errno = $obj->sql_errno;
				$this->sql_error = $obj->sql_error;

                
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
        
		if (isset($this->fk_import)) $this->fk_import=trim($this->fk_import);
		if (isset($this->num_line)) $this->num_line=trim($this->num_line);
		if (isset($this->content_line)) $this->content_line=trim($this->content_line);
		if (isset($this->error_msg)) $this->error_msg=trim($this->error_msg);
		if (isset($this->error_data)) $this->error_data=trim($this->error_data);
		if (isset($this->sql_executed)) $this->sql_executed=trim($this->sql_executed);
		if (isset($this->type_erreur)) $this->type_erreur=trim($this->type_erreur);
		if (isset($this->sql_errno)) $this->sql_errno=trim($this->sql_errno);
		if (isset($this->sql_error)) $this->sql_error=trim($this->sql_error);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."fin_import_error SET";
        
		$sql.= " fk_import=".(isset($this->fk_import)?$this->fk_import:"null").",";
		$sql.= " num_line=".(isset($this->num_line)?$this->num_line:"null").",";
		$sql.= " content_line=".(isset($this->content_line)?"'".$this->db->escape($this->content_line)."'":"null").",";
		$sql.= " error_msg=".(isset($this->error_msg)?"'".$this->db->escape($this->error_msg)."'":"null").",";
		$sql.= " error_data=".(isset($this->error_data)?"'".$this->db->escape($this->error_data)."'":"null").",";
		$sql.= " sql_executed=".(isset($this->sql_executed)?"'".$this->db->escape($this->sql_executed)."'":"null").",";
		$sql.= " type_erreur=".(isset($this->type_erreur)?"'".$this->db->escape($this->type_erreur)."'":"null").",";
		$sql.= " sql_errno=".(isset($this->sql_errno)?"'".$this->db->escape($this->sql_errno)."'":"null").",";
		$sql.= " sql_error=".(isset($this->sql_error)?"'".$this->db->escape($this->sql_error)."'":"null")."";

        
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
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."fin_import_error";
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

		$object=new Finimporterror($this->db);

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
		
		$this->fk_import='';
		$this->num_line='';
		$this->content_line='';
		$this->error_msg='';
		$this->error_data='';
		$this->sql_executed='';
		$this->type_erreur='';
		$this->sql_errno='';
		$this->sql_error='';

		
	}

}
?>
