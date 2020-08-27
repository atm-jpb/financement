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
class FinancementConformiteProcessingTime_box extends ModeleBoxes
{
    public $boxcode = "FinancementConformiteProcessingTime_box";
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

        $this->boxlabel = $langs->transnoentitiesnoconv('BoxConformiteProcessingTimeTitle');
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

        $text = $langs->trans('BoxConformiteProcessingTimeTitle');
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
        $this->info_box_contents[$r][1] = array('td' => 'align="left"', 'text' => $langs->trans('BoxConformiteProcessingTimeFirstColumn'));
        $this->info_box_contents[$r][2] = array('td' => 'align="left"', 'text' => $langs->trans('BoxConformiteProcessingTimeSecondColumn'));

        $TLine = array('compliant', 'invoice');
        $TColumn = array('twelve', 'three');

        foreach($TLine as $line) {
            if($line == 'compliant') $goal = 2;
            else $goal = 10;
            $redIcon = '&nbsp;'.img_picto($langs->trans('BoxIconGoal', $goal.' jours'), 'statut8');
            $greenIcon = '&nbsp;'.img_picto($langs->trans('BoxIconGoal', $goal.' jours'), 'statut4');

            $r++;
            $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxConformiteProcessingTime'.ucfirst($line)));

            foreach($TColumn as $k => $column) {
                $TRes = self::process($line, $column);
                $nbDay = round($TRes['calc'] / 24, 2);

                if($nbDay >= $goal) $icon = $redIcon;
                else $icon = $greenIcon;

                $help = $langs->trans('BoxConformiteProcessingTimeDetails', $TRes['nb'], $TRes['calc']);
                $this->info_box_contents[$r][$k+1] = array(
                    'td' => 'align="left"',
                    'text' => $form->textwithpicto($nbDay.' jour(s)', $help).$icon
                );
            }
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

    private function process($type, $condition) {
        global $db;

        $TRes = array();

        $sql = 'SELECT count(*) as nb,';
        if($type == 'compliant') $sql.= ' sum(unix_timestamp(dflea.date_envoi) - unix_timestamp(c.date_conformeN2)) as sum';
        else $sql.= ' sum(unix_timestamp(dflea.date_envoi) - unix_timestamp(d.date_facture_materiel)) as sum';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_conformite c';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (s.rowid = c.fk_simulation)';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_dossier d ON (d.rowid = s.fk_fin_dossier)';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";

        if($type == 'compliant') $sql.= " WHERE (c.date_conformeN2 is not null or c.date_conformeN2 > '1970-01-01')";
        else $sql.= " WHERE (d.date_facture_materiel is not null or d.date_facture_materiel > '1970-01-01')";

        if($condition == 'twelve') {    // 12 derniers mois sans compter le mois en cours
            $sql.= " AND c.date_cre BETWEEN '".date('Y-m', strtotime('-1 year'))."-01' AND '".date('Y-m-d', strtotime('last day of -1 month'))."'";
        }
        else $sql.= " AND c.date_cre BETWEEN '".date('Y-m', strtotime('-3 month'))."-01' AND '".date('Y-m-d', strtotime('last day of -1 month'))."'";   // 3 derniers mois sans compter le mois en cours

        $resql = $db->query($sql);
        if(! $resql) {
            return;
        }

        if($obj = $db->fetch_object($resql)) $TRes = array('calc' => $obj->sum / $obj->nb / 3600, 'nb' => $obj->nb);   // Convertie en heures
        $db->free($resql);

        return $TRes;
    }
}