<?php
/* Copyright (C) 2012-2013 Maxime Kohlhaas      <maxime@atm-consulting.fr>
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
 * 	\defgroup   financement     Module Financement
 *  \brief      Module financement pour C'PRO
 *  \file       /financement/core/modules/modFinancement.class.php
 *  \ingroup    Financement
 *  \brief      Description and activation file for module financement
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module financement
 */
class modFinancement extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 210000;
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'financement';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "ATM";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module financement";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = 'dolibarr';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='financeico@financement';

		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		// for default path (eg: /financement/core/xxxxx) (0=disable, 1=enable)
		// for specific path of parts (eg: /financement/core/modules/barcode)
		// for specific css file (eg: /financement/css/financement.css.php)
		//$this->module_parts = array(
		//                        	'triggers' => 0,                                 	// Set this to 1 if module has its own trigger directory (core/triggers)
		//							'login' => 0,                                    	// Set this to 1 if module has its own login method directory (core/login)
		//							'substitutions' => 0,                            	// Set this to 1 if module has its own substitution function file (core/substitutions)
		//							'menus' => 0,                                    	// Set this to 1 if module has its own menus handler directory (core/menus)
		//							'theme' => 0,                                    	// Set this to 1 if module has its own theme directory (core/theme)
		//                        	'tpl' => 0,                                      	// Set this to 1 if module overwrite template dir (core/tpl)
		//							'barcode' => 0,                                  	// Set this to 1 if module has its own barcode directory (core/modules/barcode)
		//							'models' => 0,                                   	// Set this to 1 if module has its own models directory (core/modules/xxx)
		//							'css' => array('/financement/css/financement.css.php'),	// Set this to relative path of css file if module has its own css file
	 	//							'js' => array('/financement/js/financement.js'),          // Set this to relative path of js file if module must load a js on all pages
		//							'hooks' => array('hookcontext1','hookcontext2')  	// Set here all hooks context managed by module
		//							'dir' => array('output' => 'othermodulename'),      // To force the default directories names
		//							'workflow' => array('WORKFLOW_MODULE1_YOURACTIONTYPE_MODULE2'=>array('enabled'=>'! empty($conf->module1->enabled) && ! empty($conf->module2->enabled)', 'picto'=>'yourpicto@financement')) // Set here all workflow context managed by module
		//                        );
		$this->module_parts = array(
			'hooks'=>array('thirdpartycard','salesrepresentativescard','invoicecard','invoicesuppliercard','searchform')
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/financement/temp");
		$this->dirs = array("/financement/temp");

		// Config pages. Put here list of php page, stored into financement/admin directory, to use to setup module.
		$this->config_page_url = array("config.php@financement");

		// Dependencies
		$this->depends = array();		// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->phpmin = array(5,0);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(3,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("financement@financement");

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(0=>array('MYMODULE_MYNEWCONST1','chaine','myvalue','This is a constant to add',1),
		//                             1=>array('MYMODULE_MYNEWCONST2','chaine','myvalue','This is another constant to add',0)
		// );
		$this->const = array();

		// Array to add new pages in new tabs
		// Example: $this->tabs = array('objecttype:+tabname1:Title1:mylangfile@financement:$user->rights->financement->read:/financement/mynewtab1.php?id=__ID__',  // To add a new tab identified by code tabname1
        //                              'objecttype:+tabname2:Title2:mylangfile@financement:$user->rights->othermodule->read:/financement/mynewtab2.php?id=__ID__',  // To add another new tab identified by code tabname2
        //                              'objecttype:-tabname');                                                     // To remove an existing tab identified by code tabname
		// where objecttype can be
		// 'thirdparty'       to add a tab in third party view
		// 'intervention'     to add a tab in intervention view
		// 'order_supplier'   to add a tab in supplier order view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'invoice'          to add a tab in customer invoice view
		// 'order'            to add a tab in customer order view
		// 'product'          to add a tab in product view
		// 'stock'            to add a tab in stock view
		// 'propal'           to add a tab in propal view
		// 'member'           to add a tab in fundation member view
		// 'contract'         to add a tab in contract view
		// 'user'             to add a tab in user view
		// 'group'            to add a tab in group view
		// 'contact'          to add a tab in contact view
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
        $this->tabs = array(
        	'thirdparty:+scores:ScoreList:financement@financement:$user->rights->financement->score->read:/financement/score.php?socid=__ID__'
        	,'thirdparty:+transfert:Dossiers Transférable:financement@financement:$user->rights->financement->affaire->write:/financement/dossier.php?fk_leaser=__ID__'
        	,'thirdparty:+affaire:Financement:financement@financement:$user->rights->financement->affaire->read:/financement/affaire.php?socid=__ID__'
        	,'thirdparty:+simulation:Simulations:financement@financement:$user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list:/financement/simulation.php?socid=__ID__'
        	,'thirdparty:+penaliteR:penaliteR:financement@financement:$user->rights->financement->admin->write:/financement/admin/penalite.php?type=R&socid=__ID__'
        	,'thirdparty:+penaliteNR:penaliteNR:financement@financement:$user->rights->financement->admin->write:/financement/admin/penalite.php?type=NR&socid=__ID__'
		);
 		

        // Dictionnaries
        if (! isset($conf->financement->enabled)) 
        {
        	$conf->financement=new stdClass();
        	$conf->financement->enabled=0;
	}
	$this->dictionaries=array(
		'langs'=>'financement@financement'
		,'tabname'=>array(
			MAIN_DB_PREFIX.'c_financement_type_contrat'
			,MAIN_DB_PREFIX.'c_financement_categorie_bien'
			,MAIN_DB_PREFIX.'c_financement_nature_bien'
		)
		,'tablib'=>array(
			'Type de contrat'
			,'Categorie du Bien'
			,'Nature du Bien'
		)
		,'tabsql'=>array(
			'SELECT f.rowid as rowid, f.code, f.label, f.entity, f.active FROM '.MAIN_DB_PREFIX.'c_financement_type_contrat as f WHERE entity = '.$conf->entity
			,'SELECT f.rowid as rowid, f.cat_id, f.label, f.entity, f.active FROM '.MAIN_DB_PREFIX.'c_financement_categorie_bien as f WHERE entity IN (0, '.$conf->entity.')'
			,'SELECT f.rowid as rowid, f.nat_id, f.label, f.entity, f.active FROM '.MAIN_DB_PREFIX.'c_financement_nature_bien as f WHERE entity IN (0, '.$conf->entity.')'
		)
		,'tabsqlsort'=>array(
			'label ASC'
			,'label ASC'
			,'label ASC'
		)
		,'tabfield'=>array(
			'code,label'
			,'cat_id,label,entity'
			,'nat_id,label,entity'
		)
		,'tabfieldvalue'=>array(
			'code,label,entity'
			,'cat_id,label,entity'
			,'nat_id,label,entity'
		)
		,'tabfieldinsert'=>array(
			'code,label,entity'
			,'cat_id,label,entity'
			,'nat_id,label,entity'
		)
		,'tabrowid'=>array(
			'rowid'
			,'rowid'
			,'rowid'
		)
		,'tabcond'=>array(
			$conf->financement->enabled
			,$conf->financement->enabled
			,$conf->financement->enabled
		)
	);
        /* Example:
        if (! isset($conf->financement->enabled)) $conf->financement->enabled=0;	// This is to avoid warnings
        $this->dictionnaries=array(
            'langs'=>'mylangfile@financement',
            'tabname'=>array(MAIN_DB_PREFIX."table1",MAIN_DB_PREFIX."table2",MAIN_DB_PREFIX."table3"),		// List of tables we want to see into dictonnary editor
            'tablib'=>array("Table1","Table2","Table3"),													// Label of tables
            'tabsql'=>array('SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table1 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table2 as f','SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.MAIN_DB_PREFIX.'table3 as f'),	// Request to select fields
            'tabsqlsort'=>array("label ASC","label ASC","label ASC"),																					// Sort order
            'tabfield'=>array("code,label","code,label","code,label"),																					// List of fields (result of select to show dictionnary)
            'tabfieldvalue'=>array("code,label","code,label","code,label"),																				// List of fields (list of fields to edit a record)
            'tabfieldinsert'=>array("code,label","code,label","code,label"),																			// List of fields (list of fields for insert)
            'tabrowid'=>array("rowid","rowid","rowid"),																									// Name of columns with primary key (try to always name it 'rowid')
            'tabcond'=>array($conf->financement->enabled,$conf->financement->enabled,$conf->financement->enabled)												// Condition to show each dictionnary
        );
        */

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
		$r=0;
		// Example:
		/*
		$this->boxes[$r][1] = "myboxa.php";
		$r++;
		$this->boxes[$r][1] = "myboxb.php";
		$r++;
		*/

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;
		$this->rights[$r][0] = 210001;
		$this->rights[$r][1] = 'Consulter les dossiers de financement de mes clients';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'mydossier';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 210002;
		$this->rights[$r][1] = 'Créer/modifier les dossiers de financement de mes clients';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'mydossier';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 210003;
		$this->rights[$r][1] = 'Supprimer les dossiers de financement de mes clients';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'mydossier';
		$this->rights[$r][5] = 'delete';
		$r++;
		
		$this->rights[$r][0] = 210011;
		$this->rights[$r][1] = 'Consulter tous les dossiers de financement';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'alldossier';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 210012;
		$this->rights[$r][1] = 'Créer/modifier tous les dossiers de financement';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'alldossier';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 210013;
		$this->rights[$r][1] = 'Supprimer tous les dossiers de financement';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'alldossier';
		$this->rights[$r][5] = 'delete';
		$r++;
		$this->rights[$r][0] = 210014;
		$this->rights[$r][1] = 'Consulter les dossiers intégrale';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'integrale';
		$this->rights[$r][5] = 'read';
		$r++;
		
		$this->rights[$r][0] = 210021;
		$this->rights[$r][1] = 'Accéder au calculateur';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'allsimul';
		$this->rights[$r][5] = 'calcul';
		$r++;
		$this->rights[$r][0] = 210022;
		$this->rights[$r][1] = 'Accéder au simulateur';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'allsimul';
		$this->rights[$r][5] = 'simul';
		$r++;
		
		$this->rights[$r][0] = 210031;
		$this->rights[$r][1] = 'Accéder à mes simulations';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'mysimul';
		$this->rights[$r][5] = 'simul_list';
		$r++;
		$this->rights[$r][0] = 210032;
		$this->rights[$r][1] = 'Accéder à toutes les simulations';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'allsimul';
		$this->rights[$r][5] = 'simul_list';
		$r++;
		$this->rights[$r][0] = 210033;
		$this->rights[$r][1] = 'Péconiser les simulations';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'allsimul';
		$this->rights[$r][5] = 'simul_preco';
		$r++;
		$this->rights[$r][0] = 210034;
		$this->rights[$r][1] = 'Accéder au suivi leaser simulation';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'allsimul';
		$this->rights[$r][5] = 'suivi_leaser';
		$r++;
		
		$this->rights[$r][0] = 210041;
		$this->rights[$r][1] = 'Consulter les scores client';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'score';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 210042;
		$this->rights[$r][1] = 'Créer/modifier les scores client';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'score';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = 210043;
		$this->rights[$r][1] = 'Supprimer les scores client';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'score';
		$this->rights[$r][5] = 'delete';
		$r++;
		
		$this->rights[$r][0] = 210051;
		$this->rights[$r][1] = 'Consulter les imports';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'import';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = 210052;
		$this->rights[$r][1] = 'Créer un nouvel import';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'import';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = 210053;
		$this->rights[$r][1] = 'Gérer les affaires et dossiers de financement';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'affaire';
		$this->rights[$r][5] = 'write';
		$r++;
		
		$this->rights[$r][0] = 210054;
		$this->rights[$r][1] = 'Voir les affaires et dossiers de financement';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'affaire';
		$this->rights[$r][5] = 'read';
		$r++;

		
		$this->rights[$r][0] = 210999;
		$this->rights[$r][1] = 'Administration du module';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'admin';
		$this->rights[$r][5] = 'write';
		$r++;
		
		$this->rights[$r][0] = 210500;
		$this->rights[$r][1] = 'Accès aux PDF simulation';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';
		//$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.$r;
		$this->rights[$r][1] = 'Webservice : autoriser les réponses aux demandes de financement';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'webservice';
		$this->rights[$r][5] = 'repondre_demande';
		$r++;

		// Main menu entries
		$this->menus = array();			// List of menus to add
		$r=0;

		// Add here entries to declare new menus
		//
		// Example to declare a new Top Menu entry and its Left menu entry:
		$this->menu[$r]=array(	'fk_menu'=>0,			                // Put 0 if this is a top menu
								'type'=>'top',			                // This is a Top menu entry
								'titre'=>$langs->trans('Financement'),
								'mainmenu'=>'financement',
								'leftmenu'=>'financement',
								'url'=>'/financement/simulation.php?action=new',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>100,
								'enabled'=>'$conf->financement->enabled && ($user->rights->financement->allsimul->calcul || $user->rights->financement->allsimul->simul ||
											$user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list)',	// Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled.
								'perms'=>'',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;

		// Example to declare a Left Menu entry into an existing Top menu entry:
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Simulations'),
								'mainmenu'=>'financement',
								'leftmenu'=>'simulation',
								'url'=>'/financement/simulation.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>110,
								'enabled'=>'$conf->financement->enabled && ($user->rights->financement->allsimul->calcul || $user->rights->financement->allsimul->simul ||
											$user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list)',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->allsimul->calcul || $user->rights->financement->allsimul->simul ||
											$user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=simulation',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Calculator'),
								'mainmenu'=>'financement',
								'leftmenu'=>'calculateur',
								'url'=>'/financement/simulation.php?action=new',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>112,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->allsimul->calcul',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->allsimul->calcul',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=simulation',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('SimulationList'),
								'mainmenu'=>'financement',
								'leftmenu'=>'simulation_list',
								'url'=>'/financement/simulation.php?action=list',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>114,
								'enabled'=>'$conf->financement->enabled && ($user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list)',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->allsimul->simul_list || $user->rights->	financement->mysimul->simul_list',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Imports'),
								'mainmenu'=>'financement',
								'leftmenu'=>'import',
								'url'=>'/financement/import.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>120,
								'enabled'=>'$conf->financement->enabled && ($user->rights->financement->import->read || $user->rights->financement->import->write)',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->import->read || $user->rights->financement->import->write',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=import',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('NewImport'),
								'mainmenu'=>'financement',
								'leftmenu'=>'import_new',
								'url'=>'/financement/import.php?action=new',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>122,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->import->write',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->import->write',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=import',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('ImportList'),
								'mainmenu'=>'financement',
								'leftmenu'=>'import_list',
								'url'=>'/financement/import.php?mode=list',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>124,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->import->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->import->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Admin'),
								'mainmenu'=>'financement',
								'leftmenu'=>'admin',
								'url'=>'/financement/admin/config.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>125,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->admin->write',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->admin->write',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		/*
		 * Gestion des affaires
		 */
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Affaires'),
								'mainmenu'=>'financement',
								'leftmenu'=>'affaire',
								'url'=>'/financement/affaire.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>410,
								'enabled'=>'$conf->financement->enabled && ($user->rights->financement->mydossier->read || $user->rights->financement->alldossier->read ||
											$user->rights->financement->integrale->read)',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->mydossier->read || $user->rights->financement->alldossier->read ||
											$user->rights->financement->integrale->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		/*$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Nouvelle affaire'),
								'mainmenu'=>'financement',
								'leftmenu'=>'affaire_new',
								'url'=>'/financement/affaire.php?action=new',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>412,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->allsimul->calcul',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->allsimul->calcul',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;*/
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Liste affaires'),
								'mainmenu'=>'financement',
								'leftmenu'=>'affaire_list',
								'url'=>'/financement/affaire.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>413,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->alldossier->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->alldossier->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Affaires en erreur'),
								'mainmenu'=>'financement',
								'leftmenu'=>'affaire_list',
								'url'=>'/financement/affaire.php?errone=1',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>413,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->alldossier->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->alldossier->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		/*$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Nouveau dossier'),
								'mainmenu'=>'financement',
								'leftmenu'=>'dossier',
								'url'=>'/financement/dossier.php?action=new',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>414,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->allsimul->calcul',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->allsimul->calcul',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;*/
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Liste des dossiers'),
								'mainmenu'=>'financement',
								'leftmenu'=>'dossier_list',
								'url'=>'/financement/dossier.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>415,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->alldossier->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->alldossier->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>$langs->trans('Dossiers renta négative'),
								'mainmenu'=>'financement',
								'leftmenu'=>'dossier_list',
								'url'=>'/financement/dossier.php?liste_renta_negative=1',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>418,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->alldossier->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->alldossier->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>'Dossiers incomplet',
								'mainmenu'=>'financement',
								'leftmenu'=>'dossier_list',
								'url'=>'/financement/dossier.php?liste_incomplet',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>416,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->alldossier->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->alldossier->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement,fk_leftmenu=affaire',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>'Dossiers intégrale',
								'mainmenu'=>'financement',
								'leftmenu'=>'dossier_list',
								'url'=>'/financement/dossier_integrale.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>417,
								'enabled'=>'$conf->financement->enabled && $user->rights->financement->integrale->read',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'$user->rights->financement->integrale->read',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;
		
		$this->menu[$r]=array(
			            'fk_menu'=>'fk_mainmenu=report',			// Put 0 if this is a top menu
			        	'type'=> 'left',			// This is a Top menu entry
			        	'titre'=>'Pilotage',
			        	'mainmenu'=> 'report',
			        	'leftmenu'=> 'pilotage',		// Use 1 if you also want to add left menu entries using this descriptor. Use 0 if left menu entries are defined in a file pre.inc.php (old school).
						'url'=> '/financement/pilotage.php',
						'langs'=> 'report@report',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
						'position'=> 181,
						'enabled'=> '$conf->report->enabled && $conf->financement->enabled',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
						'perms'=> '$user->rights->financement->alldossier->read',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
						'target'=> '',
						'user'=> 2	// 0=Menu for internal users, 1=external users, 2=both
        );
		
		$r++;
        $this->menu[$r]=array(
			            'fk_menu'=>'fk_mainmenu=report,fk_leftmenu=pilotage',			// Put 0 if this is a top menu
			        	'type'=> 'left',			// This is a Top menu entry
			        	'titre'=>'Pilotage financement',
			        	'mainmenu'=> '',
			        	'leftmenu'=> '',		// Use 1 if you also want to add left menu entries using this descriptor. Use 0 if left menu entries are defined in a file pre.inc.php (old school).
						'url'=> '/financement/pilotage.php',
						'langs'=> 'report@report',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
						'position'=> 182,
						'enabled'=> '$conf->report->enabled && $conf->financement->enabled',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
						'perms'=> '$user->rights->financement->alldossier->read',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
						'target'=> '',
						'user'=> 2	// 0=Menu for internal users, 1=external users, 2=both
        );
		
		$r++;
		
		$this->menu[$r]=array(
			            'fk_menu'=>'fk_mainmenu=report,fk_leftmenu=pilotage',			// Put 0 if this is a top menu
			        	'type'=> 'left',			// This is a Top menu entry
			        	'titre'=>'Echéances restantes',
			        	'mainmenu'=> '',
			        	'leftmenu'=> '',		// Use 1 if you also want to add left menu entries using this descriptor. Use 0 if left menu entries are defined in a file pre.inc.php (old school).
						'url'=> '/financement/report-echeance-restante.php',
						'langs'=> 'report@report',	// Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
						'position'=> 183,
						'enabled'=> '$conf->report->enabled && $conf->financement->enabled',			// Define condition to show or hide menu entry. Use '$conf->mymodule->enabled' if entry must be visible if module is enabled.
						'perms'=> '$user->rights->financement->alldossier->read',			// Use 'perms'=>'$user->rights->mymodule->level1->level2' if you want your menu with a permission rules
						'target'=> '',
						'user'=> 2	// 0=Menu for internal users, 1=external users, 2=both
        );
		
		$r++;
		
		
		/*$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=financement',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
								'type'=>'left',			                // This is a Left menu entry
								'titre'=>'Lists',
								'mainmenu'=>'financement',
								'leftmenu'=>'lists',
								'url'=>'/financement/lists.php',
								'langs'=>'financement@financement',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
								'position'=>100,
								'enabled'=>'$conf->financement->enabled',  // Define condition to show or hide menu entry. Use '$conf->financement->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
								'perms'=>'1',			                // Use 'perms'=>'$user->rights->financement->level1->level2' if you want your menu with a permission rules
								'target'=>'',
								'user'=>2);				                // 0=Menu for internal users, 1=external users, 2=both
		$r++;*/


		// Exports
		$r=1;

		// Example:
		// $this->export_code[$r]=$this->rights_class.'_'.$r;
		// $this->export_label[$r]='CustomersInvoicesAndInvoiceLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
        // $this->export_enabled[$r]='1';                               // Condition to show export in list (ie: '$user->id==3'). Set to 1 to always show when module is enabled.
		// $this->export_permission[$r]=array(array("facture","facture","export"));
		// $this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.cp'=>'Zip','s.ville'=>'Town','s.fk_pays'=>'Country','s.tel'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','s.code_compta'=>'CustomerAccountancyCode','s.code_compta_fournisseur'=>'SupplierAccountancyCode','f.rowid'=>"InvoiceId",'f.facnumber'=>"InvoiceRef",'f.datec'=>"InvoiceDateCreation",'f.datef'=>"DateInvoice",'f.total'=>"TotalHT",'f.total_ttc'=>"TotalTTC",'f.tva'=>"TotalVAT",'f.paye'=>"InvoicePaid",'f.fk_statut'=>'InvoiceStatus','f.note'=>"InvoiceNote",'fd.rowid'=>'LineId','fd.description'=>"LineDescription",'fd.price'=>"LineUnitPrice",'fd.tva_tx'=>"LineVATRate",'fd.qty'=>"LineQty",'fd.total_ht'=>"LineTotalHT",'fd.total_tva'=>"LineTotalTVA",'fd.total_ttc'=>"LineTotalTTC",'fd.date_start'=>"DateStart",'fd.date_end'=>"DateEnd",'fd.fk_product'=>'ProductId','p.ref'=>'ProductRef');
		// $this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.cp'=>'company','s.ville'=>'company','s.fk_pays'=>'company','s.tel'=>'company','s.siren'=>'company','s.siret'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.code_compta'=>'company','s.code_compta_fournisseur'=>'company','f.rowid'=>"invoice",'f.facnumber'=>"invoice",'f.datec'=>"invoice",'f.datef'=>"invoice",'f.total'=>"invoice",'f.total_ttc'=>"invoice",'f.tva'=>"invoice",'f.paye'=>"invoice",'f.fk_statut'=>'invoice','f.note'=>"invoice",'fd.rowid'=>'invoice_line','fd.description'=>"invoice_line",'fd.price'=>"invoice_line",'fd.total_ht'=>"invoice_line",'fd.total_tva'=>"invoice_line",'fd.total_ttc'=>"invoice_line",'fd.tva_tx'=>"invoice_line",'fd.qty'=>"invoice_line",'fd.date_start'=>"invoice_line",'fd.date_end'=>"invoice_line",'fd.fk_product'=>'product','p.ref'=>'product');
		// $this->export_sql_start[$r]='SELECT DISTINCT ';
		// $this->export_sql_end[$r]  =' FROM ('.MAIN_DB_PREFIX.'facture as f, '.MAIN_DB_PREFIX.'facturedet as fd, '.MAIN_DB_PREFIX.'societe as s)';
		// $this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product as p on (fd.fk_product = p.rowid)';
		// $this->export_sql_end[$r] .=' WHERE f.fk_soc = s.rowid AND f.rowid = fd.fk_facture';
		// $r++;
	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		global $db;
		
		$sql = array();

		$result=$this->load_tables();

		//$url = 'http://'.$_SERVER['SERVER_NAME'].'/'.DOL_URL_ROOT_ALT.'/financement/script/create-maj-base.php';
		//file_get_contents($url);
		define('INC_FROM_DOLIBARR',true);
		dol_include_once('/financement/config.php');
		dol_include_once('/financement/script/create-maj-base.php');
		
		dol_include_once('/core/class/extrafields.class.php');
		$extra = new ExtraFields($db);
		$extra->addExtraField('fk_leaser_webservice', 'Identifiant du leaser associé pour les réponses de demande de financement', 'int', '1', '', 'user', 0, 0, '', unserialize('a:1:{s:7:"options";a:1:{s:0:"";N;}}'), 1);
		
		return $this->_init($sql, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}


	/**
	 *		Create tables, keys and data required by module
	 * 		Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
	 * 		and create data commands must be stored in directory /financement/sql/
	 *		This function is called by this->init
	 *
	 * 		@return		int		<=0 if KO, >0 if OK
	 */
	function load_tables()
	{
		return $this->_load_tables('/financement/sql/');
	}
}

?>
