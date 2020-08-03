<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_MyModuletrigger.class.php
 * 	\ingroup	mymodule
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceFinancementtrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'financement@financement';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion() {
        global $langs;
        $langs->load("admin");

        if($this->version == 'development') return $langs->trans("Development");
        else if($this->version == 'experimental') return $langs->trans("Experimental");
        else if($this->version == 'dolibarr') return DOL_VERSION;
        else if($this->version) return $this->version;

        return $langs->trans("Unknown");
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
     *
     * @param string $action code
     * @param Object $object
     * @param User $user user
     * @param Translate $langs langs
     * @param conf $conf conf
     * @return int <0 if KO, 0 if no triggered ran, >0 if OK
     */
    function runTrigger($action, $object, $user, $langs, $conf) {
        //For 8.0 remove warning
        $result=$this->run_trigger($action, $object, $user, $langs, $conf);
        return $result;
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		mixed		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
		global $db, $user;
		
        if ($action == 'PROPAL_CLOSE_SIGNED') {
        	
			// On regarde s'il existe une facture associée (normalement oui, et une seule)
			$object->fetchObjectLinked('', 'propal', '', 'facture');
			if(!empty($object->linkedObjects['facture'])) {
				
				$f = &$object->linkedObjects['facture'][0];
				$f->fetchObjectLinked('', 'propal', '', 'facture');
				
				// On clôture en non signées toutes les autres propositions (avenants)
				if(!empty($f->linkedObjects['propal'])) {
					$TAvenantsFermes = array();
					foreach($f->linkedObjects['propal'] as $p) {
						if($p->id != $object->id) {
							$p->cloture($user, 3, 'Fermé car avenant '.$object->ref.' signé');
							$TAvenantsFermes[] = $p->ref;
						}
					}
					setEventMessage('Avenant(s) '.implode(', ', $TAvenantsFermes).' clôturé(s) non signé(s) automatiquement');
				}
				
			}
        }
		else if ($action == 'COMPANY_CREATE' || $action == 'COMPANY_MODIFY') {
			if(empty($object->zip) || !is_numeric($object->zip)) {
				setEventMessage('Code postal invalide');
				return -1;
			}
            dol_include_once('/financement/lib/financement.lib.php');

            // Pour éviter d'avoir des doublons de code entre les 2 champs
            updateSocieteOtherCustomerCode($object, $object->array_options['other_customer_code'], false);
		}
		else if($action == 'COMPANY_DELETE') {
		    if(! class_exists('TSimulation')) dol_include_once('/financement/class/simulation.class.php');
            if(! class_exists('TFin_affaire')) dol_include_once('/financement/class/affaire.class.php');

		    if(TSimulation::isExistingObject(null, $object->id) || TFin_affaire::isExistingObject(null, $object->id)) {
                setEventMessage('CantDeleteThirdparty', 'errors');
                return -1;
            }
        }

        return 0;
    }
}