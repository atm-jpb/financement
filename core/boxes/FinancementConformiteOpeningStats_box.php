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
class FinancementConformiteOpeningStats_box extends ModeleBoxes
{
    public $boxcode = "FinancementConformiteOpeningStats";
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

        $this->boxlabel = $langs->transnoentitiesnoconv('BoxConformiteOpeningStatsTitle');
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
        dol_include_once('/financement/class/conformite.class.php');
        dol_include_once('/multicompany/class/dao_multicompany.class.php');

        $valueN1 = Conformite::STATUS_WAITING_FOR_COMPLIANCE_N1;
        $valueN2 = Conformite::STATUS_WAITING_FOR_COMPLIANCE_N2;
        $whichOne = GETPOST('compliant');   // Entier correspondant au statut de la conformité
        if(empty($whichOne)) $whichOne = $valueN1;

        $form = new Form($db);
        $dao = new DaoMulticompany($db);
        $dao->getEntities();
        $TEntity = array();
        foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

        $text = $langs->trans('BoxConformiteOpeningStatsTitleWithStatus').' "'.$langs->trans(Conformite::$TStatus[$whichOne]).'"';
        $this->info_box_head = array(
            'text' => $text,
            'limit' => dol_strlen($text),
			'sublink'=>'',
			'subtext'=>$langs->trans("Filter"),
			'subpicto'=>'filter.png',
			'subclass'=>'linkobject boxfilter',
			'target'=>'none'
        );

        if(! $user->rights->financement->admin->write) { // Accès à la box uniquement pour les admins
            $this->info_box_contents[0][0] = array('td' => 'align="left"', 'text' => $langs->trans("ReadPermissionNotAllowed"));
            return;
        }

		$r = 0;

        // Ajout de la div permettant de sélectionner les filtres
        $stringtoshow = '';
        $stringtoshow .= '<script type="text/javascript" language="javascript">
					jQuery(document).ready(function() {
					    jQuery("#idfilter'.$this->boxcode.'").parents("tr").children("[data-remove=true]").remove() // Petit hack nécessaire pour enlever les td dont on a pas besoin
						jQuery("#idsubimg'.$this->boxcode.'").click(function() {
							jQuery("#idfilter'.$this->boxcode.'").toggle();
						});
					});
					</script>';
        $stringtoshow .= '<div class="center hideobject" id="idfilter'.$this->boxcode.'">';    // hideobject is to start hidden
        $stringtoshow .= '<form class="flat formboxfilter" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
        $stringtoshow .= '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        $stringtoshow .= '<input type="hidden" name="action" value="refresh_'.$this->boxcode.'">';
        $stringtoshow .= '<input type="hidden" name="DOL_AUTOSET_COOKIE" value="DOLUSERCOOKIE_box_'.$this->boxcode.':year,shownb,showtot">';

        $stringtoshow .= '<label for="compliantN1">';
        $stringtoshow .= '<input type="radio" id="compliantN1" name="compliant" value="'.$valueN1.'"'.($whichOne == $valueN1 ? ' checked="checked"' : '').' /> '.$langs->trans("ConformiteWaitingForComplianceN1Short");
        $stringtoshow .= '</label> &nbsp; <label for="compliantN2">';
        $stringtoshow .= '<input type="radio" id="compliantN2" name="compliant" value="'.$valueN2.'"'.($whichOne == $valueN2 ? ' checked="checked"' : '').' /> '.$langs->trans("ConformiteWaitingForComplianceN2Short");
        $stringtoshow .= '</label>';
        $stringtoshow .= '<input class="reposition inline-block valigntextbottom" type="image" alt="'.$langs->trans("Refresh").'" src="'.img_picto($langs->trans("Refresh"), 'refresh.png', '', '', 1).'">';
        $stringtoshow .= '</form>';
        $stringtoshow .= '</div>';

        $this->info_box_contents[$r][0] = array('tr' => 'class="oddeven nohover"', 'td' => 'colspan="19" class="nohover center"', 'textnoformat' => $stringtoshow);
        for($n = 1 ; $n < 19 ; $n++) $this->info_box_contents[$r][$n] = array('td' => 'data-remove="true"');    // On a besoin de ça pour que ce soit le standard qui gère le colspan
        $r++;

        // Header
        $this->info_box_contents[$r][0] = array('td' => 'align="left"', 'text' => '');
        $this->info_box_contents[$r+1][0] = array('td' => 'align="left"', 'text' => $langs->trans('BoxConformiteOpeningStats'));

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
        $sql = 'SELECT entity, count(*) as nb,';
        if($whichOne == $valueN1) $sql.= ' extract(year from date_envoi) as annee, extract(month from date_envoi) as mois, extract(day from date_envoi) as jour';
        else if($whichOne == $valueN2) $sql.= ' extract(year from date_attenteN2) as annee, extract(month from date_attenteN2) as mois, extract(day from date_attenteN2) as jour';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_conformite';
        $sql.= " WHERE date_cre >= '".date('Y-m', strtotime('-1 year'))."-01'"; // On prend toutes les conformités des 12 derniers mois
        if($whichOne == $valueN1) $sql.= " AND date_envoi > '1970-01-01'";
        else if($whichOne == $valueN2) $sql.= " AND date_attenteN2 > '1970-01-01'";
        $sql.= ' AND entity IN ('.getEntity('fin_simulation').')';
        $sql.= ' GROUP BY entity, annee, mois, jour';
        $sql.= ' ORDER BY entity, annee, mois, jour';

        $resql = $db->query($sql);
        if(! $resql) {
        	return;
        }

        $nbRow = $db->num_rows($resql);

        while($obj = $db->fetch_object($resql)) {
            $mois = sprintf("%02d", $obj->mois);
            $TRes[$obj->entity][$obj->annee.$mois] += $obj->nb;

            if($obj->annee == date('Y') && $obj->mois == date('n')) {
                $jour = sprintf("%02d", $obj->jour);
                $numWeek = date('W', strtotime(date('Y-m-'.$jour)));

                $TWeek[$obj->entity][$numWeek] += $obj->nb;
            }
        }
        $db->free($resql);

        foreach($TEntity as $entity => $label) {
            if(array_sum($TRes[$entity]) == 0) continue;    // Aucune conformité pour cette entité

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

        // Cas spécifique s'il n'y a pas de données à cause du getEntity
        if($nbRow == 0) {
            foreach($this->info_box_contents as $k => $v) {
                if($k == 0) continue;
                unset($this->info_box_contents[$k]);
            }

            $this->info_box_contents[1][0] = array('td' => 'align="left"', 'text' => $langs->trans("NoDataForThisEntity"));
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
