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
class FinancementSimulationsAutoAgreementRate_box extends ModeleBoxes
{
    public $boxcode = "FinancementSimulationsAutoAgreementRate_box";
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
    public function __construct() {
        global $langs;
        $langs->load('boxes');
        $langs->load('financement@financement');

        $this->boxlabel = $langs->transnoentitiesnoconv('BoxSimulationsAutoAgreementRateTitle');
    }

    /**
     * Load data into info_box_contents array to show array later.
     *
     * 	@param		int		$max		Maximum number of records to load
     * 	@return		void
     */
    public function loadBox($max = 5) {
        global $conf, $user, $langs, $db;

        $this->max = $max;

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/financement/config.php');
        dol_include_once('/financement/class/dossier.class.php');
        dol_include_once('/financement/class/affaire.class.php');
        dol_include_once('/financement/class/grille.class.php');

        $form = new Form($db);

        $text = $langs->trans('BoxSimulationsAutoAgreementRateTitle');
        $this->info_box_head = array(
            'text' => $text,
            'limit' => dol_strlen($text)
        );

        if(! $user->rights->financement->admin->write) { // Accès à la box uniquement pour les admins
            $this->info_box_contents[0][0] = array('td' => 'align="left"', 'text' => $langs->trans("ReadPermissionNotAllowed"));
            return;
        }

		$r = 0;

        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => '');
        $this->info_box_contents[$r][1] = array('td' => 'align="left"', 'text' => $langs->trans('BoxSimulationsAutoAgreementRateFirstColumn'));

        $TRes = array();

        $sql = 'SELECT accord, count(*) as nb';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation';
        $sql.= " WHERE accord = 'OK'";
        $sql.= " AND date_cre >= '".date('Y-m', strtotime('-3 month'))."-01'";    // 3 derniers mois
        $autoAgreement = ' AND unix_timestamp(date_accord) <= (unix_timestamp(date_cre) + 30*60)';  // Accords donnés en moins de 30 minutes
        $autoAgreementTwoHours = ' AND unix_timestamp(date_accord) <= (unix_timestamp(date_cre) + 2*60*60)';  // Accords donnés en moins de 2 heures
        $groupBy = ' GROUP BY accord';

        // Auto agreement
        $resql = $db->query($sql.$autoAgreement.$groupBy);
        if(! $resql) {
            return;
        }

        while($obj = $db->fetch_object($resql)) $TRes['auto'] = $obj->nb;
        $db->free($resql);

        // Less than 2 hours
        $resql = $db->query($sql.$autoAgreementTwoHours.$groupBy);
        if(! $resql) {
            return;
        }

        while($obj = $db->fetch_object($resql)) $TRes['twoHours'] = $obj->nb;
        $db->free($resql);

        // All
        $resql = $db->query($sql.$groupBy);
        if(! $resql) {
            return;
        }

        while($obj = $db->fetch_object($resql)) $TRes['all'] = $obj->nb;
        $db->free($resql);

        $autoAgreementRate = round($TRes['auto'] / $TRes['all'] * 100, 2);
        $icon = '&nbsp;';
        $goal = 80;
        if($autoAgreementRate >= $goal) $icon .= img_picto($langs->trans('BoxIconGoal', $goal.'%'), 'statut4'); // Vert
        else $icon .= img_picto($langs->trans('BoxIconGoal', $goal.'%'), 'statut8'); // Rouge

        $autoAgreementTwoHoursRate = round($TRes['twoHours'] / $TRes['all'] * 100, 2);
        $iconTwoHours = '&nbsp;';
        if($autoAgreementTwoHoursRate >= $goal) $iconTwoHours .= img_picto($langs->trans('BoxIconGoal', $goal.'%'), 'statut4'); // Vert
        else $iconTwoHours .= img_picto($langs->trans('BoxIconGoal', $goal.'%'), 'statut8'); // Rouge

        $r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxSimulationsAutoAgreementRate'));
        $this->info_box_contents[$r][1] = array(
            'td' => 'align="left"',
            'text' => $form->textwithpicto($autoAgreementRate.'%', $langs->trans('BoxSimulationsAutoAgreementRateDetails', $TRes['auto'], $TRes['all'])).$icon
        );

        $r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxSimulationsAutoAgreementTwoHoursRate'));
        $this->info_box_contents[$r][1] = array(
            'td' => 'align="left"',
            'text' => $form->textwithpicto($autoAgreementTwoHoursRate.'%', $langs->trans('BoxSimulationsAutoAgreementRateTwoHoursDetails', $TRes['twoHours'], $TRes['all'])).$iconTwoHours
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
    public function showBox($head = null, $contents = null, $nooutput = 0) {
        parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}