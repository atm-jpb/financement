<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
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
 * 	\file		core/boxes/mybox.php
 * 	\ingroup	mymodule
 * 	\brief		This file is a sample box definition file
 * 				Put some comments here
 */
include_once DOL_DOCUMENT_ROOT . "/core/boxes/modules_boxes.php";

/**
 * Class to manage the box
 */
class financement_indicateurs_box extends ModeleBoxes
{

    public $boxcode = "financement_indicateurs_box";
    public $boximg = "financeico@financement";
    public $boxlabel;
    public $depends = array("financement");
    public $db;
    public $param;
    public $info_box_head = array();
    public $info_box_contents = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        global $langs;
        $langs->load('boxes');
        $langs->load('financement@financement');

        $this->boxlabel = $langs->transnoentitiesnoconv('BoxIndicatorsTitle');
    }

    /**
     * Load data into info_box_contents array to show array later.
     *
     * 	@param		int		$max		Maximum number of records to load
     * 	@return		void
     */
    public function loadBox($max = 5)
    {
        global $conf, $user, $langs, $db;

        $this->max = $max;

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/financement/config.php');
        dol_include_once('/financement/class/dossier.class.php');
        dol_include_once('/financement/class/affaire.class.php');
        dol_include_once('/financement/class/grille.class.php');

        $PDOdb = new TPDOdb;

        //include_once DOL_DOCUMENT_ROOT . "/mymodule/class/mymodule.class.php";

        $text = $langs->trans('BoxIndicatorsTitle');
        $this->info_box_head = array(
            'text' => $text,
            'limit' => dol_strlen($text)
        );

        if(!$user->rights->financement->admin->write) { // Accès à la box uniquement pour les admins
                $this->info_box_contents[0][0] = array('td' => 'align="left"',
                    'text' => $langs->trans("ReadPermissionNotAllowed"));
                return;
        }

		$r = 0;

        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsIndicator'));
        $this->info_box_contents[$r][1] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsNumber'));
        $this->info_box_contents[$r][2] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsNumberTodo'));
        $this->info_box_contents[$r][3] = array('td' => 'align="right"', 'text' => $langs->trans('BoxIndicatorsAmount'));

        // Dossiers internes en relocation
		$r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsInternalFilesRelocation'));

        $sql = 'SELECT COUNT(*) as number
				, SUM( IF( dfc.relocOK = "OUI", 0, 1) ) as number_todo
				, ROUND( 100 * SUM(dfc.encours_reloc) ) / 100 as encours_reloc
				FROM '.MAIN_DB_PREFIX.'fin_dossier d
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier)
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfc ON (dfc.fk_fin_dossier = d.rowid AND dfc.type = "CLIENT")
				WHERE a.entity IN ('.getEntity('fin_dossier', true).')
				AND dfc.reloc = "OUI"
				AND a.nature_financement = "INTERNE"';

        $resql = $PDOdb->Execute($sql);

        if(! $resql)
        {
        	return;
        }

        $obj = $PDOdb->Get_line();

        $this->info_box_contents[$r][1] = array(
        	'td' => 'align="left"'
        	, 'text' => '<span>' . $obj->number . '</span>' // H4cK @N0nYM0u$-style : si cette valeur est empty(), (ex. 0, '0', NULL), elle n'est pas affichée...
        	, 'url' => dol_buildpath('/financement/dossier.php', 1) . '?reloc=1&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][nature_financement]=INTERNE'
        );

        $this->info_box_contents[$r][2] = array(
        	'td' => 'align="left"'
        	, 'text' => '<span>' . $obj->number_todo . '</span>'
        	, 'url' => dol_buildpath('/financement/dossier.php', 1) . '?reloc=1&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][nature_financement]=INTERNE&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][relocClientOK]=NON'
        );
        
        $this->info_box_contents[$r][3] = array(
        	'td' => 'align="right"'
        	, 'text' => price($obj->encours_reloc)
        );


        // Dossiers externes en relocation
		$r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsExternalFilesRelocation'));

        $sql = 'SELECT COUNT(*) as number
				, SUM( IF( dfl.relocOK = "OUI", 0, 1) ) as number_todo
				, ROUND( 100 * SUM(dfl.encours_reloc) ) / 100 as encours_reloc
				FROM '.MAIN_DB_PREFIX.'fin_dossier d
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier)
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type = "LEASER")
				WHERE a.entity IN ('.getEntity('fin_dossier', true).')
				AND dfl.reloc = "OUI"
				AND a.nature_financement = "EXTERNE"';

        $resql = $PDOdb->Execute($sql);

        if(! $resql)
        {
        	return;
        }

        $obj = $PDOdb->Get_line();

        $this->info_box_contents[$r][1] = array(
        	'td' => 'align="left"'
        	, 'text' => '<span>' . $obj->number . '</span>'
        	, 'url' => dol_buildpath('/financement/dossier.php', 1) . '?reloc=1&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][nature_financement]=EXTERNE'
        );

        $this->info_box_contents[$r][2] = array(
        	'td' => 'align="left"'
        	, 'text' => '<span>' . $obj->number_todo . '</span>'
        	, 'url' => dol_buildpath('/financement/dossier.php', 1) . '?reloc=1&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][nature_financement]=EXTERNE&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][relocLeaserOK]=NON'
        );

        $this->info_box_contents[$r][3] = array(
        	'td' => 'align="right"'
        	, 'text' => price($obj->encours_reloc)
        );


        // Loyers intercalaires des dossiers externes
        // En standy by sur cette partie, à revoir + tard
		/*$r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsExternalFilesIntercalaire'));

        $sql = 'SELECT COUNT(*) as number
				, SUM( IF(dfl.intercalaireOK = "NON", 1, 0) ) as number_todo
				, ROUND( 100 * SUM( IF(dfl.intercalaireOK = "NON", dfl.loyer_intercalaire, 0) ) ) / 100 as loyers
				FROM '.MAIN_DB_PREFIX.'fin_dossier d
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier)
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire = a.rowid)
				LEFT OUTER JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement dfl ON (dfl.fk_fin_dossier = d.rowid AND dfl.type = "LEASER")
				WHERE a.entity IN ('.getEntity('fin_dossier', true).')
				AND dfl.loyer_intercalaire > 0
				AND a.nature_financement = "EXTERNE"';

        $resql = $PDOdb->Execute($sql);

        if(! $resql)
        {
        	return;
        }

        $obj = $PDOdb->Get_line();

        $this->info_box_contents[$r][1] = array(
        		'td' => 'align="left"'
        		, 'text' => '<span>' . $obj->number . '</span>'
        		, 'url' => dol_buildpath('/financement/dossier.php', 1) . '?TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][nature_financement]=EXTERNE'
        );

        $this->info_box_contents[$r][2] = array(
        		'td' => 'align="left"'
        		, 'text' => '<span>' . $obj->number_todo . '</span>'
        		, 'url' => dol_buildpath('/financement/dossier.php', 1) . '?TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][nature_financement]=EXTERNE&TListTBS[list_' . MAIN_DB_PREFIX . 'fin_dossier][search][intercalaireLeaserOK]=NON'
        );

        $this->info_box_contents[$r][3] = array(
        		'td' => 'align="right"'
        		, 'text' => price($obj->loyers)
        );*/
		
		// Contrôles qualité
		$r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxIndicatorsQualityControl'));

        $sql = 'SELECT COUNT(*) as number
				, SUM( IF(q.result = "TODO", 1, 0) ) as number_todo
				FROM '.MAIN_DB_PREFIX.'fin_quality_test q
				WHERE 1';

        $resql = $PDOdb->Execute($sql);

        if(! $resql)
        {
        	return;
        }

        $obj = $PDOdb->Get_line();

        $this->info_box_contents[$r][1] = array(
        		'td' => 'align="left"'
        		, 'text' => '<span>' . $obj->number . '</span>'
        		, 'url' => dol_buildpath('/financement/qualite/list.php', 1)
        );

        $this->info_box_contents[$r][2] = array(
        		'td' => 'align="left"'
        		, 'text' => '<span>' . $obj->number_todo . '</span>'
        		, 'url' => dol_buildpath('/financement/qualite/list.php?', 1) . 'TListTBS[list_llx_fin_quality_test][search][result]=TODO'
        );

        $this->info_box_contents[$r][3] = array(
        		'td' => 'align="right"'
        		, 'text' => ''
        );
    }

    /**
     * 	Method to show box
     *
     * 	@param	array	$head       Array with properties of box title
     * 	@param  array	$contents   Array with properties of box lines
     *  @param	int		$nooutput	No print, only return string
     * 	@return	void
     */
    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}