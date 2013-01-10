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
 *  \file       dev/skeletons/Grille.class.php
 *  \ingroup    mymodule othermodule1 othermodule2
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *				Initialy built by build_class_from_table on 2012-12-16 13:32
 */

// Put here all includes required by your class file
//require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");
//require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
//require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");


/**
 *	Put here description of your class
 */
class Grille // extends CommonObject
{
	var $db;							//!< To store db handler
	var $error;							//!< To return error code (or message)
	var $errors=array();				//!< To return several error codes (or messages)
	//var $element='Grille';			//!< Id that identify managed objects
	//var $table_element='Grille';	//!< Name of table without prefix where object is stored

    var $id;
    
	var $fk_soc;
	var $fk_type_contrat;
	var $montant;
	var $periode;
	var $coeff;
	var $fk_user;
	var $tms='';

    


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
        
		if (isset($this->fk_soc)) $this->fk_soc=trim($this->fk_soc);
		if (isset($this->fk_type_contrat)) $this->fk_type_contrat=trim($this->fk_type_contrat);
		if (isset($this->montant)) $this->montant=trim($this->montant);
		if (isset($this->periode)) $this->periode=trim($this->periode);
		if (isset($this->coeff)) $this->coeff=trim($this->coeff);
		if (isset($this->fk_user)) $this->fk_user=trim($this->fk_user);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."fin_grille_leaser(";
		
		$sql.= "fk_soc,";
		$sql.= "fk_type_contrat,";
		$sql.= "montant,";
		$sql.= "periode,";
		$sql.= "coeff,";
		$sql.= "fk_user";

		
        $sql.= ") VALUES (";
        
		$sql.= " ".(! isset($this->fk_soc)?'NULL':"'".$this->fk_soc."'").",";
		$sql.= " ".(! isset($this->fk_type_contrat)?'NULL':"'".$this->fk_type_contrat."'").",";
		$sql.= " ".(! isset($this->montant)?'NULL':"'".$this->montant."'").",";
		$sql.= " ".(! isset($this->periode)?'NULL':"'".$this->periode."'").",";
		$sql.= " ".(! isset($this->coeff)?'NULL':"'".$this->coeff."'").",";
		$sql.= " ".(! isset($this->fk_user)?'NULL':"'".$this->fk_user."'");

        
		$sql.= ")";

		$this->db->begin();

	   	dol_syslog(get_class($this)."::create sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
    	if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

		if (! $error)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."fin_grille_leaser");

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
		
		$sql.= " t.fk_soc,";
		$sql.= " t.fk_type_contrat,";
		$sql.= " t.montant,";
		$sql.= " t.periode,";
		$sql.= " t.coeff,";
		$sql.= " t.fk_user,";
		$sql.= " t.tms";

		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
        $sql.= " WHERE t.rowid = ".$id;

    	dol_syslog(get_class($this)."::fetch sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            if ($this->db->num_rows($resql))
            {
                $obj = $this->db->fetch_object($resql);

                $this->id    = $obj->rowid;
                
				$this->fk_soc = $obj->fk_soc;
				$this->fk_type_contrat = $obj->fk_type_contrat;
				$this->montant = $obj->montant;
				$this->periode = $obj->periode;
				$this->coeff = $obj->coeff;
				$this->fk_user = $obj->fk_user;
				$this->tms = $this->db->jdate($obj->tms);

                
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
        
		if (isset($this->fk_soc)) $this->fk_soc=trim($this->fk_soc);
		if (isset($this->fk_type_contrat)) $this->fk_type_contrat=trim($this->fk_type_contrat);
		if (isset($this->montant)) $this->montant=trim($this->montant);
		if (isset($this->periode)) $this->periode=trim($this->periode);
		if (isset($this->coeff)) $this->coeff=trim($this->coeff);
		if (isset($this->fk_user)) $this->fk_user=trim($this->fk_user);

        

		// Check parameters
		// Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."fin_grille_leaser SET";
        
		$sql.= " fk_soc=".(isset($this->fk_soc)?$this->fk_soc:"null").",";
		$sql.= " fk_type_contrat=".(isset($this->fk_type_contrat)?$this->fk_type_contrat:"null").",";
		$sql.= " montant=".(isset($this->montant)?$this->montant:"null").",";
		$sql.= " periode=".(isset($this->periode)?$this->periode:"null").",";
		$sql.= " coeff=".(isset($this->coeff)?$this->coeff:"null").",";
		$sql.= " fk_user=".(isset($this->fk_user)?$this->fk_user:"null");

        
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
    		$sql = "DELETE FROM ".MAIN_DB_PREFIX."fin_grille_leaser";
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

		$object=new Grille($this->db);

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
		
		$this->fk_soc='';
		$this->fk_type_contrat='';
		$this->montant='';
		$this->periode='';
		$this->coeff='';
		$this->fk_user='';
		$this->tms='';

		
	}

	/******************************************************************
	 * PERSO FUNCTIONS
	 ******************************************************************/
    /**
     *  Chargement d'un tableau de grille pour un leaser donné, pour un type de contrat donné
     *
     *  @param	int		$idLeaser    		Id leaser
	 *  @param	int		$idTypeContrat  Id type contrat
     *  @return array   Tableau contenant les grilles de coeff, false si vide
     */
    function get_grille($idLeaser, $idTypeContrat, $periodicite='opt_trimestriel', $options=array())
    {
    	if(empty($idLeaser) || empty($idTypeContrat)) return false;

    	global $langs;
        $sql = "SELECT";
		$sql.= " t.rowid";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
        $sql.= " WHERE t.fk_soc = ".$idLeaser;
		$sql.= " AND t.fk_type_contrat = ".$idTypeContrat;
		$sql.= " ORDER BY t.periode, t.montant ASC";

    	dol_syslog(get_class($this)."::get_grille sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
        	$num = $this->db->num_rows($resql);
			$i = 0;
			$result = array();
			while($i < $num) {
				$obj = $this->db->fetch_object($resql);
				$this->fetch($obj->rowid);
				
				$periode = $this->periode;
				if($periodicite == 'opt_mensuel') $periode *= 3;
				$montant = $this->montant;
				$coeff = $this->_calculate_coeff($this->coeff, $options);
				
				$result[$periode][$montant]['rowid'] = $this->id;
				$result[$periode][$montant]['coeff'] = $coeff;
				$result[$periode][$montant]['echeance'] = $montant / $periode * (1 + $coeff / 100);
				
				$i++;
			}
			
			$this->db->free($resql);
			$this->grille = $result;

			return (empty($this->grille) ? false : $this->grille);
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	function get_coeff($idLeaser, $idTypeContrat, $periodicite='opt_trimestriel', $montant, $duree, $options=array())
    {
    	if(empty($idLeaser) || empty($idTypeContrat)) return -1;
		
		if($periodicite == 'opt_mensuel') $duree /= 3;

    	global $langs;
        $sql = "SELECT";
		$sql.= " t.montant, t.coeff";
		
        $sql.= " FROM ".MAIN_DB_PREFIX."fin_grille_leaser as t";
        $sql.= " WHERE t.fk_soc = ".$idLeaser;
		$sql.= " AND t.fk_type_contrat = ".$idTypeContrat;
		$sql.= " AND t.periode = ".$duree;
		$sql.= " ORDER BY t.montant ASC";

    	dol_syslog(get_class($this)."::get_coeff sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
        	$num = $this->db->num_rows($resql);
			$i = 0;
			$coeff = -1;
			while($i < $num) {
				$obj = $this->db->fetch_object($resql);
				if($montant <= $obj->montant) {
					$coeff = $this->_calculate_coeff($obj->coeff, $options);
					break;
				}
				
				$i++;
			}
			
			$this->db->free($resql);

			return $coeff;
		} else {
			$this->error="Error ".$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch ".$this->error, LOG_ERR);
			return -1;
		}
	}

	private function _calculate_coeff($coeff, $options) {
		if(!empty($options)) {
			foreach($options as $name) {
				$penalite = $this->_get_penalite($name);
				$coeff += $coeff * $penalite / 100;
			}
		}
		
		return round($coeff, 2);
	}
	
	private function _get_penalite($name) {
		$sql = "SELECT opt_value FROM ".MAIN_DB_PREFIX."fin_grille_penalite";
		$sql.= " WHERE opt_name = '".$name."'";
		
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$obj = $this->db->fetch_object($resql);
			return floatval($obj->opt_value);
		}
		else
		{
			dol_print_error($this->db);
			return -1;
		}
	}
	
	/**
	 * Calcul des élément du financement
	 * Montant : Capital emprunté
	 * Durée : Durée en trimestre
	 * échéance : Echéance trimestrielle
	 * VR : Valeur residuelle du financement
	 * coeff : taux d'emprunt annuel
	 * 
	 * @return $res :
	 * 			1	= calcul OK
	 * 			-1	= montant ou echeance vide (calcul impossible)
	 * 			-2	= montant hors grille
	 * 			-3	= echeance hors grille
	 * 			-4	= Pas de grille chargée
	 */
	function calcul_financement(&$montant, &$duree, &$echeance, $vr, &$coeff) {
		/*
		 * Formule de calcul échéance
		 * 
		 * Echéance : Capital x tauxTrimestriel / (1 - (1 + tauxTrimestriel)^-nombreTrimestre )
		 * 
		 */ 
		
		if(empty($this->grille)) { // Pas de grille chargée, pas de calcul
			$this->error = 'ErrorNoGrilleSelected';
		} 
		else if(!empty($montant)) { // Calcul à partir du montant
			
			foreach($this->grille[$duree] as $palier => $infos) {
				if($montant <= $palier) {
					$coeff = $infos['coeff']; // coef annuel
					$coeffTrimestriel = $coeff / 4 /100; // en %
					/*$echeance = ($montant - $vr) / $duree * (1 + $coeff / 100);*/
					
					$echeance = $montant * $coeffTrimestriel / (1- pow(1+$coeffTrimestriel, -$duree) );  
					
					//print "$echeance = $montant, &$duree, &$echeance, $vr, &$coeff::$coeffTrimestriel";
					
					$echeance = round($echeance, 2);
					return true;
					break;
				}
			}
			
			$this->error = 'ErrorAmountOutOfGrille';

		} 
		else if(!empty($echeance)) { // Calcul à partir de l'échéance
		
			foreach($this->grille[$duree] as $palier => $infos) {
				if($echeance <= $infos['echeance']) {
					$coeff = $infos['coeff'];
					$coeffTrimestriel = $coeff / 4 /100; // en %
					/*$montant = $echeance * (1 - $coeff / 100) * $duree + $vr;*/
					$montant =  $echeance * (1- pow(1+$coeffTrimestriel, -$duree) ) / $coeffTrimestriel ;
					
					
					$montant = round($montant, 2);
					return true;
					break;
				}
			}
			
			$this->error = 'ErrorEcheanceOutOfGrille';
			
		} else { // Montant et échéance vide
			$this->error = 'ErrorMontantOrEcheanceRequired';
		}
		
		return false; 
	}

	function showEcheancier($montant, &$duree, &$echeance, $vr, &$coeff, $affichage = 'TRIMESTRE') {
		/*
		 * Affiche l'échéancier
		 */
	}

}
?>
