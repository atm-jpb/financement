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
	
	var $TType_import_interne = array(
		'client' => 'Fichier client','commercial' => 'Fichier commercial'
		,'affaire' => 'Fichier affaire','materiel' => 'Fichier matériel'
		,'facture_materiel' => 'Fichier facture matériel','facture_location' => 'Fichier facture location'
		,'facture_lettree' => 'Fichier facture lettrée'
	);
	var $TType_import = array('fichier_leaser' => 'Fichier leaser', 'score' => 'Fichier score');




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
		// Fonction inutile car fichier déposés directement par CPRO dans le répertoire qui est partagé en samba
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
	
	function addError($errMsg, $errData, $dataLine, $sqlExecuted='', $type='ERROR', $is_sql=false) {
		global $user;
		$thisErr = new ImportError($this->db);
		$thisErr->fk_import = $this->id;
		$thisErr->num_line = $this->nb_lines;
		$thisErr->content_line = serialize($dataLine);
		$thisErr->error_msg = $errMsg;
		$thisErr->error_data = $errData;
		$thisErr->sql_executed = $sqlExecuted;
		$thisErr->type_erreur = $type;
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
			case 'materiel':
				$this->importLineMateriel($dataline);
				break;
			case 'facture_materiel':
				$this->importLineFactureMateriel($dataline);
				break;
			case 'facture_location':
				$this->importLineFactureLocation($dataline);
				break;
			case 'facture_lettree':
				$this->importLineFactureLettree($dataline);
				break;
			case 'commercial':
				$this->importLineCommercial($dataline);
				break;
			case 'affaire':
				$this->importLineAffaire($dataline);
				break;
			case 'fichier_leaser':
				/*print_r($dataline); 
				print '<br>';*/
				$this->importFichierLeaser($dataline);
				break;
			case 'score':
				$this->importLineScore($dataline);
				break;
			
			default:
				
				break;
		}
	}
	function importFichierLeaser($dataline) {
		$ATMdb=new Tdb;
		//	$ATMdb->db->debug=true;
		$data= $this->contructDataTab($dataline);
	//	print_r($data);
	
		if($data['echeance']==0) {
			
			return false;
		}
	
		$f=new TFin_financement;
		if($f->loadReference($ATMdb, $data['reference_financement'])) {
			/*
			 * Youpi, on a retrouvé le financement et donc le client
			 */
			 
		}
		elseif($f->createWithfindClientBySiren($ATMdb, $data['siren'], $data['reference_financement'])) {
			/*
			 * On trouve le financement recherch d'une affaire sans financement dans un client sur siren
			 */
		}
		else {	
			return false;		
		}
		
		$f->echeance = $this->validateValue('echeance', $data['echeance']);
		$f->montant = $this->validateValue('montant', $data['montant']);
		$f->numero_prochaine_echeance = $this->validateValue('montant', $data['nb_echeance']);
		
		if($f->duree<$f->numero_prochaine_echeance)$f->duree = $f->numero_prochaine_echeance;
		
		$f->periodicite = $data['periodicite'];
		$f->date_debut = $this->validateValue('date_debut', $data['date_debut']);
		$f->date_fin = $this->validateValue('date_fin',$data['date_fin']);
		
		$f->save($ATMdb);

		$this->createFactureFournisseur();

		$ATMdb->close();
		
		return true;
	}

	private function createFactureFournisseur() {
		/*
		 * Finalement cette fonction est remplacer par un cron mensuel de création de facture
		 */
	}

	function importLineTiers($dataline) {
		global $user;
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si tiers existant dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Construction de l'objet final
		$societe = new Societe($this->db);
		if($rowid > 0) {
			$societe->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$societe->{$key} = $this->validateValue($key, $value);
		}
		
		$societe->idprof1 = substr($societe->idprof2,0,9);

		// Mise à jour ou créatioon
		if($rowid > 0) {
			$res = $societe->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $societe->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		return true;
	}

	function importLineFactureMateriel($dataline) {
		global $user;
		$sqlSearchFacture = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE %s = '%s'";
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleFactureFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingFacture', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			} else {
				$this->addError('ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $data[$this->mapping['search_key_client']], $dataline, $sql, 'ERROR', true);
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
		
		
		/*
		 * Création du lien  affaire/facture + lien entre matériel et affaire
		 */
		$ATMdb=new Tdb;
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'])) {
			$affaire->montant = $this->validateValue('total_ht',$data['total_ht']);	
			$affaire->save($ATMdb);	
				
			$facture_mat->linked_objects['affaire'] = $affaire->getId();	
			
			$TSerial = explode(' - ',$data['matricule']);
		
			foreach($TSerial as $serial) {
				
				$asset=new TAsset;
				if($asset->loadReference($ATMdb, $data['matricule'])) {
					$asset->fk_soc = $affaire->fk_soc;
					
					$asset->add_link($affaire->getId(),'affaire');	
					
					$asset->save($ATMdb);	
				}
				else {
					$this->addError('ErrorMaterielNotExist', $data['matricule'], $dataline);
					return false;
				}
			}
		}
		$ATMdb->close();
		
		// Mise à jour ou créatioon
		if($rowid > 0) {
			$res = $facture_mat->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}			
		} else {
			$res = $facture_mat->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		// Actions spécifiques
		$facture_mat->addline($facture_mat->id, 'Matricule '.$data['matricule'], 0, 1, 19.6, 0, 0, 0, 0, '', '', 0, 0, '', 'HT', $data['total_ht']);
		$facture_mat->validate($user, $data[$this->mapping['search_key']]); // Force la validation avec numéro de facture
		
		
		
		return true;
	}

	function importLineFactureLocation($dataline) {
		global $user;
		$sqlSearchFacture = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE %s = '%s'";
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleFactureFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingFacture', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Recherche tiers associé à la facture existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key_client'], $data[$this->mapping['search_key_client']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			} else {
				$this->addError('ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $data[$this->mapping['search_key_client']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		$data['socid'] = $fk_soc;
		
		// Construction de l'objet final
		$facture_loc = new Facture($this->db);
		if($rowid > 0) {
			$facture_loc->fetch($rowid);
		}

		foreach ($data as $key => $value) {
			$facture_loc->{$key} = $this->validateValue($key, $value);
		}
		
		/*
		 * Création du lien  affaire/facture + lien entre matériel et affaire
		 */
		/*$ATMdb=new Tdb;
		$affaire = new TFin_affaire;
		if($affaire->loadReference($ATMdb, $data['code_affaire'])) {
			$affaire->montant = $this->validateValue('total_ttc',$data['total_ttc']);
			$affaire->save($ATMdb);	
				
			$facture_mat->linked_objects['affaire'] = $affaire->getId();	
			
			$TSerial = explode(' - ',$data['matricule']);
		
			foreach($TSerial as $serial) {
				
				$asset=new TAsset;
				if($asset->loadReference($ATMdb, $data['matricule'])) {
					$asset->fk_soc = $affaire->fk_soc;
					
					$asset->add_link($affaire->getId(),'affaire');	
					
					$asset->save($ATMdb);	
				}
				else {
				//	print "ErrorMaterielNotExist";
					$this->addError('ErrorMaterielNotExist', $dataline, true);
					//return false;
				}
				
			}
			
			
			
		}
		$ATMdb->close();*/
		
		// Mise à jour ou créatioon
		if($rowid > 0) {
			$res = $facture_loc->update($rowid, $user);
			// Erreur : la mise à jour n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_update++;
			}
		} else {
			$res = $facture_loc->create($user);
			// Erreur : la création n'a pas marché
			if($res < 0) {
				$this->addError('ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
				return false;
			} else {
				$this->nb_create++;
			}
		}
		
		echo '<pre>';
		print_r($data);
		echo '</pre>';
		// Actions spécifiques
		echo 'add: '.$facture_loc->addline($facture_loc->id, $data['libelle_ligne'], $data['pu'], $data['quantite'], 19.6);
		if($facture_loc->statut == 0) $facture_loc->validate($user, $data[$this->mapping['search_key']]); // Force la validation avec numéro de facture
		$this->db->commit();
		
		return true;
	}

	function importLineFactureLettree($dataline) {
		global $user;
		$sqlSearchFacture = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		if (!preg_match('/^[A-Z]+$/', $data['code_lettrage'])) {
			// Code lettrage en minuscule = pré-lettrage = ne pas prendre en compte (ajout d'un addWarning ou addInfo ?)
			return false;
		}
		
		// Recherche si facture existante dans la base
		$rowid = 0;
		$sql = sprintf($sqlSearchFacture, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$rowid = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleFactureFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			} else {
				$this->addError('ErrorFactureNotFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingFacture', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Construction de l'objet final
		$facture_loc = new Facture($this->db);
		$facture_loc->fetch($rowid);
		$res = $facture_loc->set_paid($user, '', $data['code_lettrage']);
		if($res < 0) {
			$this->addError('ErrorWhileUpdatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
			return false;
		} else {
			$this->nb_update++;
		}

		return true;
	}

	function importLineAffaire($dataline) {
		global $user, $db;
		/*
		 *	référence	date_affaire, code_client login_user
		 *  "002-53740";"24/09/2012";"012469";"dpn"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;
		
		$ATMdb=new Tdb;	
		
		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		$commercial = new User($db);
		if(!$commercial->fetch('',$data[$this->mapping['search_key_user']])) {
			// Pas d'erreur si l'utilisateur n'est pas trouvé, lien avec l'utilisateur admin
			$fk_user = $user->id;
			//$this->addError('ErrorUserNotFound', $data[$this->mapping['search_key_user']], $dataline);
			//return false;
		}
		else {
			$fk_user = $commercial->id;
		}
		
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array($this->mapping['search_key_client']=>$data[$this->mapping['search_key_client']]));
		if(count($TRes)==0) {
			$this->addError('ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		}
		else if(count($TRes) > 1) { // Plusieurs trouvés, erreur
			$this->addError('ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		} else {
			$fk_soc = $TRes[0];
		}
		
		$a=new TFin_affaire;
		$a->loadReference($ATMdb, $data[$this->mapping['search_key']]);
		
		if($a->fk_soc > 0 && $a->fk_soc != $fk_soc) { // client ne correspond pas
			$this->addError('ErrorClientDifferent', $data[$this->mapping['search_key']], $dataline);
			return false;
		}
		
		foreach ($data as $key => $value) {
			$a->{$key} = $this->validateValue($key, $value);
		}
		
		$a->fk_soc = $fk_soc;		
		$a->addCommercial($ATMdb, $fk_user);
		
		if($a->id > 0) {
			$this->nb_update++;
		} else {
			$this->nb_create++;
		}
		
		$a->save($ATMdb);
		
		$ATMdb->close();
		
		return true;
	}

	function importLineMateriel($dataline) {
	/*
	 * J'insére les produits sans les lier à l'affaire. C'est l'import facture matériel qui le fera
	 */
		global $user;
		/*
		 *	serial_number,libelle_produit, date_achat, marque, type_copie, cout_copie
		 *  "C2I256312";"ES 256 COPIEUR STUDIO ES 256";"06/12/2012";"TOSHIBA";"MCENB";"0,004"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;
		
		$ATMdb=new Tdb;
		
		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
	
		$produit =new Product($this->db);
		$res=$produit->fetch('', $data['ref_produit']);
		$fk_produit = $produit->id;
		
		$produit->ref = $data['ref_produit'];
		$produit->libelle = $data['libelle_produit'];
		$produit->type=0;
		
		$produit->price_base_type    = 'TTC';
        $produit->price_ttc = 0;
		$produit->price_min_ttc = 0;

        $produit->tva_tx             = 19.6;
        $produit->tva_npr            = 0;

        // local taxes.
        $produit->localtax1_tx 			= get_localtax($produit->tva_tx,1);
        $produit->localtax2_tx 			= get_localtax($produit->tva_tx,2);
		
	    $produit->status             	= 1;
        $produit->status_buy           	= 1;
        $produit->description        	= $data['marque'];
        $produit->note               	= "Produit créé par import automatique";
        $produit->customcode            = '';
        $produit->country_id            = 1;
        $produit->duration_value     	= 0;
        $produit->duration_unit      	= 0;
        $produit->seuil_stock_alerte 	= 0;
        $produit->weight             	= 0;
        $produit->weight_units       	= 0;
        $produit->length             	= 0;
        $produit->length_units       	= 0;
        $produit->surface            	= 0;
        $produit->surface_units      	= 0;
        $produit->volume             	= 0;
        $produit->volume_units       	= 0;
        $produit->finished           	= 1;
        $produit->hidden =0;       
		
		
		if(!$res) {
			$fk_produit = $produit->create($user);
			//print "Création du produit (".$produit->error.")";	
		}	
		else {
			//print "Mise à jour produit ($fk_produit)";
			$produit->update($fk_produit, $user);
		}
	
	
		$TSerial = explode(' - ',$data['serial_number']);
		
		foreach($TSerial as $serial) {
			$asset=new TAsset;
			$asset->loadReference($ATMdb,$serial);
			
			$asset->fk_product = $fk_produit;
			
			$asset->serial_number = $serial;
			
			$asset->set_date('date_achat',$data['date_achat']);
			if($data['type_copie']=='MCENB')$asset->copy_black = $this->validateValue('cout_copie', $data['cout_copie']); 
			else $asset->copy_color = $this->validateValue('cout_copie', $data['cout_copie']); 
			
			if($asset->id > 0) {
				$this->nb_update++;
			} else {
				$this->nb_create++;
			}
			
			$asset->save($ATMdb);
			
		}	
		
		$ATMdb->close();
			
		return true;
		
	}

	function importLineCommercial($dataline) { 
		global $user, $conf, $db;
		/*
		 *  code client; type_activité;login user
		 *  "000012";"Copieur";"ABX"
		 */
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		$ATMdb=new Tdb;
		$c=new TCommercialCpro;

		$commercial = new User($db);
		if(!$commercial->fetch('',$data[$this->mapping['search_key']])) {
			// Pas d'erreur si l'utilisateur n'est pas trouvé, pas de lien créé
			//$this->addError('ErrorUserNotFound', $data[$this->mapping['search_key']], $dataline);
			return false;
		}
		else {
			$fk_user = $commercial->id;
		}
		
		$TRes = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'societe',array('code_client'=>$data[$this->mapping['search_key_client']], 'entity' => $conf->entity));

		if(count($TRes)==0) {
			$this->addError('ErrorClientNotFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		}
		else if(count($TRes) > 1) { // Plusieurs trouvés, erreur
			$this->addError('ErrorMultipleClientFound', $data[$this->mapping['search_key_client']], $dataline);
			return false;
		} else {
			$fk_soc = $TRes[0];
		}
		
		$c->loadUserClient($ATMdb, $fk_user, $fk_soc); // charge l'objet si existant
		
		$c->fk_soc = $fk_soc;
		$c->fk_user = $fk_user;
		
		$c->type_activite_cpro = $data['type_activite_cpro'];
		
		if($c->id > 0) {
			$this->nb_update++;
		} else {
			$this->nb_create++;
		}
		
		$c->save($ATMdb);

		$ATMdb->close();
		
		return true;
	}

	function importLineScore($dataline) {
		global $user;
		$sqlSearchClient = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE %s = '%s'";
		
		// Compteur du nombre de lignes
		$this->nb_lines++;

		if(!$this->checkData($dataline)) return false;
		$data = $this->contructDataTab($dataline);
		
		// Recherche si tiers existant dans la base
		$fk_soc = 0;
		$sql = sprintf($sqlSearchClient, $this->mapping['search_key'], $data[$this->mapping['search_key']]);
		$resql = $this->db->query($sql);
		if($resql) {
			$num = $this->db->num_rows($resql);
			if($num == 1) { // Enregistrement trouvé, mise à jour
				$obj = $this->db->fetch_object($resql);
				$fk_soc = $obj->rowid;
			} else if($num > 1) { // Plusieurs trouvés, erreur
				$this->addError('ErrorMultipleClientFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			} else {
				$this->addError('ErrorClientNotFound', $data[$this->mapping['search_key']], $dataline, $sql);
				return false;
			}
		} else {
			$this->addError('ErrorWhileSearchingClient', $data[$this->mapping['search_key']], $dataline, $sql, 'ERROR', true);
			return false;
		}
		
		// Construction de l'objet final
		$ATMdb = new Tdb();
		$score = new TScore();

		foreach ($data as $key => $value) {
			$score->{$key} = $this->validateValue($key, $value);
		}
		
		$score->fk_soc = $fk_soc;
		$score->fk_import = $this->id;
		$score->fk_user_author = $user->id;

		$res = $score->save($ATMdb);
		// Erreur : la création n'a pas marché
		if($res < 0) {
			$this->addError('ErrorWhileCreatingLine', $data[$this->mapping['search_key']], $dataline, '', 'ERROR', true);
			return false;
		} else {
			$this->nb_create++;
		}
		
		// Mise à jour de la fiche tiers
		$societe = new Societe($this->db);
		$societe->fetch($fk_soc);
		$societe->capital = $this->validateValue('capital', $data['capital']);
		$societe->fk_forme_juridique = $this->validateValue('forme_juridique', $data['forme_juridique']);
		$societe->update($societe->id, $user);
		
		return true;
	}

	function checkData($dataline) {
		// Vérification cohérence des données
		
		/*if(count($this->mapping['mapping']) != count($dataline)) {
			$this->addError('ErrorNbColsNotMatchingMapping', $dataline);
			return false;
		}
		*/
		return true;
	}
	
	function contructDataTab($dataline) {
		// Construction du tableau de données
		$data = array();
		array_walk($dataline, 'trim');
		
		foreach($this->mapping['mapping'] as $k=>$field) {
			$data[$field] = $dataline[$k-1];
		}
		
		if(isset($this->mapping['more'])) $data = array_merge($data, $this->mapping['more']); // Ajout des valeurs autres
		
		return $data;
	}
	
	function validateValue($key, $value) {
		// Nettoyage de la valeur
		$value = trim($value);
		
		// Si un tableau de transco existe, on l'utilise
		if(!empty($this->mapping['transco'][$key])) {
			if(!empty($this->mapping[$key][$value])) {
				$value = $this->mapping[$key][$value];
			} else {
				$value = $this->mapping[$key]['default'];
			}
		}
		
		// Si un format spécial existe, on l'applique
		if(!empty($this->mapping['format'][$key])) {
			switch($this->mapping['format'][$key]) {
				case 'date':
					list($day, $month, $year) = explode("/", $value);
					$value = mktime(0, 0, 0, $month, $day, $year);
					break;
				case 'date_english':
					$sep = (strpos($value,'-')===false) ? '/': '-';
					list($year, $month, $day) = explode('/', $value);
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
