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
class FinancementInvoicedTurnoverStats_box extends ModeleBoxes
{
    public $boxcode = "FinancementInvoicedTurnoverStats_box";
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

        $this->boxlabel = $langs->transnoentitiesnoconv('BoxInvoicedTurnoverStatsTitle');
    }

    /**
     * Load data into info_box_contents array to show array later.
     *
     * 	@param		int		$max		Maximum number of records to load
     * 	@return		void
     */
    public function loadBox($max = 5) {
        global $user, $langs, $db;

        $this->max = $max;

        define('INC_FROM_DOLIBARR', true);
        dol_include_once('/financement/config.php');
        dol_include_once('/financement/class/dossier.class.php');
        dol_include_once('/financement/class/affaire.class.php');
        dol_include_once('/financement/class/grille.class.php');
        dol_include_once('/multicompany/class/dao_multicompany.class.php');

        $dao = new DaoMulticompany($db);
        $dao->getEntities();
        $TEntity = array();
        foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

        $text = $langs->trans('BoxInvoicedTurnoverStatsTitle');
        $this->info_box_head = array(
            'text' => $text,
            'limit' => dol_strlen($text)
        );

        if(! $user->rights->financement->admin->write) { // Accès à la box uniquement pour les admins
            $this->info_box_contents[0][0] = array('td' => 'align="left"', 'text' => $langs->trans("ReadPermissionNotAllowed"));
            return;
        }

        $TRes = array();
		$r = 0;

        // Header
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxInvoicedTurnoverStats'));
        $this->info_box_contents[$r][1] = array('td' => 'align="right"', 'text' => $langs->trans('BoxInvoicedTurnoverStatsFirstColumn'));
        $this->info_box_contents[$r][2] = array('td' => 'align="right"', 'text' => $langs->trans('BoxInvoicedTurnoverStatsThirdColumn'));
        $this->info_box_contents[$r][3] = array('td' => 'align="right"', 'text' => $langs->trans('BoxInvoicedTurnoverStatsSecondColumn'));
        $this->info_box_contents[$r][4] = array('td' => 'align="right"', 'text' => $langs->trans('BoxInvoicedTurnoverStatsThirdColumn'));
        foreach($TEntity as $e => $label) $TRes[$e] = array('twelve' => array('calc' => 0, 'nb' => 0), 'curr' => array('calc' => 0, 'nb' => 0));

        // Data lines
        $sql = 'SELECT entity, extract(year from dflea.date_envoi) as anneeCreation, extract(month from dflea.date_envoi) as moisCreation, count(*) as nb, sum(dflea.montant) as sum';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier d';
        $sql.= ' INNER JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";
        $sql.= ' WHERE d.entity <> 0';
        $sql.= ' AND dflea.date_envoi IS NOT NULL';
        $sql.= " AND dflea.date_envoi >= '".date('Y-m', strtotime('-1 year'))."-01'";
        $sql.= ' GROUP BY d.entity, anneeCreation, moisCreation';
        $sql.= ' ORDER BY d.entity, anneeCreation, moisCreation';

        $resql = $db->query($sql);
        if(! $resql) {
        	return;
        }

        while($obj = $db->fetch_object($resql)) {
            $TRes[$obj->entity]['twelve']['calc'] += $obj->sum;
            $TRes[$obj->entity]['twelve']['nb'] += $obj->nb;

            if($obj->anneeCreation == date('Y') && $obj->moisCreation == date('n')) {
                $TRes[$obj->entity]['curr']['calc'] += $obj->sum;
                $TRes[$obj->entity]['curr']['nb'] += $obj->nb;
            }
        }
        $db->free($resql);

        foreach($TEntity as $entity => $label) {
            if(array_sum($TRes[$entity]['twelve']) == 0) continue;    // Aucune données pour cette entité
            $TRes[$entity]['twelve']['calc'] /= 12; // On veut la moyenne par mois

            if($TRes[$entity]['curr']['calc'] >= $TRes[$entity]['twelve']['calc']) $icon = '&nbsp;'.img_picto('', 'statut4');
            else $icon = '&nbsp;'.img_picto('', 'statut8');

            $r++;
            $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $label);
            $this->info_box_contents[$r][1] = array('td' => 'align="right"', 'text' => price($TRes[$entity]['twelve']['calc'], 0, '', 1, -1, 0));
            $this->info_box_contents[$r][2] = array('td' => 'align="center"', 'text' => $TRes[$entity]['twelve']['nb']);
            $this->info_box_contents[$r][3] = array('td' => 'align="right"', 'text' => '<div class="inline-block">'.price($TRes[$entity]['curr']['calc'], 0, '', 1, -1, 0).'</div>'.$icon);
            $this->info_box_contents[$r][4] = array('td' => 'align="center"', 'text' => $TRes[$entity]['curr']['nb']);
        }

        // Totaux
        $r++;
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => $langs->trans('Total'));

        $icon = '';
        $max = 0;
        $TData = array(1 => 'twelve', 3 => 'curr');
        foreach($TData as $i => $data) {
            $sum = $nb = 0;
            foreach($TEntity as $entity => $label) {
                $sum += $TRes[$entity][$data]['calc'];
                $nb += $TRes[$entity][$data]['nb'];
            }

            // Icon
            if(empty($max)) $max = $sum;
            else if($max < $sum) $icon = '&nbsp;'.img_picto('', 'statut4');
            else $icon = '&nbsp;'.img_picto('', 'statut8');

            $this->info_box_contents[$r][$i] = array('td' => 'align="right"', 'text' => '<div class="inline-block">'.price($sum, 0, '', 1, -1, 0).'</div>'.$icon);
            $this->info_box_contents[$r][$i+1] = array('td' => 'align="center"', 'text' => $nb);
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
