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
class FinancementSimulationsOpeningStats_box extends ModeleBoxes
{
    public $boxcode = "FinancementSimulationsOpeningStats_box";
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

        $this->boxlabel = $langs->transnoentitiesnoconv('BoxSimulationsOpeningStatsTitle');
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
        dol_include_once('/multicompany/class/dao_multicompany.class.php');

        $form = new Form($db);
        $dao = new DaoMulticompany($db);
        $dao->getEntities();
        $TEntity = array();
        foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

        $text = $langs->trans('BoxSimulationsOpeningStatsTitle');
        $this->info_box_head = array(
            'text' => $text,
            'limit' => dol_strlen($text)
        );

        if(! $user->rights->financement->admin->write) { // Accès à la box uniquement pour les admins
            $this->info_box_contents[0][0] = array('td' => 'align="left"', 'text' => $langs->trans("ReadPermissionNotAllowed"));
            return;
        }

		$r = 0;

        // Header
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => '');
        $this->info_box_contents[$r+1][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxSimulationsOpeningStats'));

        // Détails mois
        $TRes = array();
        $date = strtotime('first day of -1 year');
        for($i = 1 ; $i <= 13 ; $i++) { // 13 Pour prendre aussi le mois en cours
            foreach($TEntity as $entity => $label) $TRes[$entity][date('Ym', $date)] = 0;

            $this->info_box_contents[$r][$i] = array('td' => 'align="left"', 'text' => date('Y', $date));
            $this->info_box_contents[$r+1][$i] = array('td' => 'align="left"', 'text' => $langs->trans('MonthShort'.date('m', $date)));

            $date = strtotime('+1 month', $date);
        }

        // Détails mois en cours
        $TWeek = array();
        $ds = strtotime(date('Y-m-01'));    // 1er jour du mois en cours
        $de = date('W', strtotime('+1 month -1 day', $ds));    // Dernier jour du mois en cours
        $ds = date('W', $ds);

        for($j = $ds ; $j <= $de ; $j++) {
            foreach($TEntity as $entity => $label) $TWeek[$entity][$j] = 0;

            $this->info_box_contents[$r][$i] = array('td' => 'align="left"', 'text' => date('Y', $date));
            $this->info_box_contents[$r+1][$i] = array('td' => 'align="left"', 'text' => 'S'.$j);
            $i++;
        }

        $r++;

        // Data lines
        $sql = 'SELECT entity, extract(year from date_cre) as anneeCreation, extract(month from date_cre) as moisCreation, extract(day from date_cre) as jourCreation, count(*) as nb';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_simulation';
        $sql.= " WHERE date_cre >= '".date('Y-m', strtotime('-1 year'))."-01'"; // On prend toutes les simuls des 12 derniers mois
        $sql.= ' AND entity <> 0';
        $sql.= ' GROUP BY entity, anneeCreation, moisCreation, jourCreation';
        $sql.= ' ORDER BY entity, anneeCreation, moisCreation, jourCreation';

        $resql = $db->query($sql);
        if(! $resql) {
        	return;
        }

        while($obj = $db->fetch_object($resql)) {
            $moisCreation = sprintf("%02d", $obj->moisCreation);
            $TRes[$obj->entity][$obj->anneeCreation.$moisCreation] += $obj->nb;

            if($obj->anneeCreation == date('Y') && $obj->moisCreation == date('n')) {
                $jourCreation = sprintf("%02d", $obj->jourCreation);
                $numWeek = date('W', strtotime(date('Y-m-'.$jourCreation)));

                $TWeek[$obj->entity][$numWeek] += $obj->nb;
            }
        }
        $db->free($resql);

        foreach($TEntity as $entity => $label) {
            if(array_sum($TRes[$entity]) == 0) continue;    // Aucune simulation pour cette entité

            $r++;
            $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $label);

            $i = 1;
            // Détails mois
            foreach($TRes[$entity] as $TEntityData) {
                $this->info_box_contents[$r][$i] = array('td' => 'align="center"', 'text' => '<span>'.$TEntityData.'</span>');
                $i++;
            }

            // Détails mois en cours
            foreach($TWeek[$entity] as $weekEntityData) {
                $this->info_box_contents[$r][$i] = array('td' => 'align="center"', 'text' => '<span>'.$weekEntityData.'</span>');
                $i++;
            }
        }

        // Totaux
        $r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('Total'));

        $date = strtotime('first day of -1 year');
        for($i = 1; $i <= 13; $i++) { // 13 Pour prendre aussi le mois en cours
            $sum = 0;
            foreach($TEntity as $entity => $label) $sum += $TRes[$entity][date('Ym', $date)];

            $this->info_box_contents[$r][$i] = array('td' => 'align="center"', 'text' => $sum);
            $date = strtotime('+1 month', $date);
        }

        // Totaux par semaine du mois en cours
        for($j = $ds ; $j <= $de ; $j++) {
            $sum = 0;
            foreach($TEntity as $entity => $label) $sum += $TWeek[$entity][$j];

            $this->info_box_contents[$r][$i] = array('td' => 'align="center"', 'text' => $sum);
            $i++;
        }
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
