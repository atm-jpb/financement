<?php
/*
 * Script executant toutes les copies nécessaire lors d'une création d'entité
 */

require_once __DIR__.'/../config.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/multicompany/class/dao_multicompany.class.php');

set_time_limit(0);

$action = GETPOST('action', 'alpha');
$limit = GETPOST('limit', 'int');
if($limit < 0) $limit = 0;
$forceRollback = GETPOST('forceRollback');
$entitySource = GETPOST('from', 'int');
$entityDest = GETPOST('to', 'int');

//$form = new Form($db);
$dao = new DaoMulticompany($db);
$dao->getEntities();
$TEntity = array(0 => '');
foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;
$TPossibleAction = array(
    'solveEmptyConfSoldeRowid' => 'Corriger les rowid vides llx_c_financement_conf_solde',
    'copyConfSolde' => 'Copier les confs de solde d\'une entité',
    'copyGrilleLeaser' => 'Copier les grilles leaser d\'une entité',
    'copyGrilleSuivi' => 'Copier les grilles de suivi d\'une entité',
    'copyGrillePenalite' => 'Copier les grilles de pénalité d\'une entité',
    'copyTypeContrat' => 'Copier les types de contrat d\'une entité'
);

llxHeader();

if($action === 'solveEmptyConfSoldeRowid') {
    solveEmptyConfSoldeRowid($limit);
}
else if($action === 'copyConfSolde') {
    copyConfSolde($entitySource, $entityDest, $forceRollback);
}
else if($action === 'copyGrilleLeaser') {
    copyGrilleLeaser($entitySource, $entityDest, $forceRollback);
}
else if($action === 'copyGrilleSuivi') {
    copyGrilleSuivi($entitySource, $entityDest, $forceRollback);
}
else if($action === 'copyGrillePenalite') {
    copyGrillePenalite($entitySource, $entityDest, $forceRollback);
}
else if($action === 'copyTypeContrat') {
    copyTypeContrat($entitySource, $entityDest, $forceRollback);
}

?>

<h3>Scripts pour nouvelles entités</h3>
<form action="<?php print $_SERVER['PHP_SELF'] ?>" method="post">
    <table>
        <tr class="action-list">
            <td style="width: 130px;">Action :</td>
            <td>
                <?php print Form::selectarray('action', $TPossibleAction, $action, 1, 0, 0, 'style="width: 400px;"'); ?>
            </td>
        </tr>
        <tr>
            <td>Entité source :</td>
            <td>
                <?php print Form::selectarray('from', $TEntity, $entitySource, 0, 0, 0, 'style="width: 400px;"'); ?>
            </td>
        </tr>
        <tr>
            <td>Entité destination :</td>
            <td>
                <?php print Form::selectarray('to', $TEntity, $entityDest, 0, 0, 0, 'style="width: 400px;"'); ?>
            </td>
        </tr>
        <tr>
            <td>Limit :</td>
            <td><input type="number" name="limit" value="0" min="0" /></td>
        </tr>
        <tr>
            <td>Force rollback ?</td>
            <td><input type="checkbox" name="forceRollback" <?php ! empty($forceRollback) ? print 'checked="checked"': print '' ?>/></td>
        </tr>
    </table>
    <br/><br/>
    <input class="butAction" type="submit" name="submit" value="Go" />
    <input class="butActionDelete" type="reset" name="reset" value="Cancel" />
</form>
<br/>
<div id="retours" class="tabBar">

</div>
<?php

llxFooter();

function printSummary($executionTime, $total = null, $nbCommit = null, $nbRollback = null) {
    $out ='<table>';

    if(! is_null($total)) {
        $out.='<thead>';
        $out.='<tr>';
        $out.='<td>Nb Line</td>';
        $out.='<td>'.$total.'</td>';
        $out.='</tr>';
        $out.='</thead>';
    }

    $out.='<tbody>';
    if(! is_null($nbCommit)) {
        $out.='<tr>';
        $out.='<td>Nb commit</td>';
        $out.='<td>'.$nbCommit.'</td>';
        $out.='</tr>';
    }

    if(! is_null($nbRollback)) {
        $out.='<tr>';
        $out.='<td>Nb Rollback</td>';
        $out.='<td>'.$nbRollback.'</td>';
        $out.='</tr>';
    }

    $out.='<tr>';
    $out.='<td>Execution time</td>';
    $out.='<td>'.$executionTime.' sec</td>';
    $out.='</tr>';
    $out.='</tbody>';

    $out.='</table>';

    print '<script type="text/javascript">';
    print "$(document).ready(function() { $('div#retours').html('".$out."'); });";
    print '</script>';
}

/**
 * Fonction permettant de corriger les lignes qui ont un rowid à 0
 *
 * @param $limit int
 */
function solveEmptyConfSoldeRowid($limit) {
    global $db;

    $a = microtime(true);

    $sql = 'SELECT rowid, fk_nature, fk_type_contrat, periode, date_application, base_solde, percent, percent_nr, entity, active';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'c_financement_conf_solde';
    $sql .= ' WHERE rowid = 0';
    if(! empty($limit)) $sql .= ' LIMIT '.$limit;

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    $nbLine = $db->num_rows($resql);
    $nbCommit = $nbRollback = 0;

    while($obj = $db->fetch_object($resql)) {
        $subquery = 'SELECT MAX(rowid)+1 as nextId FROM '.MAIN_DB_PREFIX.'c_financement_conf_solde';
        $resqlNb = $db->query($subquery);
        if(! $resqlNb) {
            dol_print_error($db);
            exit;
        }

        $nextId = null;
        if($objNb = $db->fetch_object($resqlNb)) $nextId = $objNb->nextId;
        $db->free($resqlNb);

        $db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'c_financement_conf_solde';
        $sql .= ' SET rowid = '.$nextId;
        if(! is_null($obj->fk_nature)) $sql .= " WHERE fk_nature = '".$db->escape($obj->fk_nature)."'";
        else $sql .= ' WHERE fk_nature IS NULL';
        $sql .= " AND fk_type_contrat = '".$db->escape($obj->fk_type_contrat)."'";
        $sql .= ' AND periode = '.$obj->periode;
        if(! is_null($obj->date_application)) $sql .= " AND date_application = '".$db->escape($obj->date_application)."'";
        else $sql .= ' AND date_application IS NULL';
        $sql .= " AND base_solde = '".$db->escape($obj->base_solde)."'";
        $sql .= ' AND percent = '.$obj->percent;
        $sql .= ' AND percent_nr = '.$obj->percent_nr;
        $sql .= ' AND entity = '.$obj->entity;
        $sql .= ' AND active = '.$obj->active;

        $resqlUpdate = $db->query($sql);
        if(! $resqlUpdate || ! empty($forceRollback)) {
            $db->rollback();
            $nbRollback++;
        }
        else {
            $db->commit();
            $nbCommit++;
        }

        $db->free($resqlUpdate);
    }
    $b = microtime(true);
    $db->free($resql);
    printSummary($b - $a, $nbLine, $nbCommit, $nbRollback);
}

/**
 * Fonction permettant de copier les conf de solde d'une entité vers une autre
 *
 * @param $entitySource     int
 * @param $entityDest       int
 * @param $forceRollback    int
 */
function copyConfSolde($entitySource, $entityDest, $forceRollback) {
    global $db;
    if(empty($entitySource) || empty($entityDest)) return;

    $a = microtime(true);
    $db->begin();

    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_financement_conf_solde(fk_nature, fk_type_contrat, periode, date_application, base_solde, percent, percent_nr, entity, active)';
    $sql .= ' SELECT fk_nature, fk_type_contrat, periode, date_application, base_solde, percent, percent_nr, '.$entityDest.', active';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'c_financement_conf_solde';
    $sql .= ' WHERE entity = '.$entitySource;

    $resql = $db->query($sql);
    if(! $resql || ! empty($forceRollback)) {
        if(! $resql) dol_print_error($db);
        $db->rollback();
    }
    else $db->commit();
    $db->free($resql);
    $b = microtime(true);
    printSummary($b - $a);
}

/**
 * Fonction permettant de copier les grilles leaser d'une entité vers une autre
 *
 * @param $entitySource     int
 * @param $entityDest       int
 * @param $forceRollback    int
 */
function copyGrilleLeaser($entitySource, $entityDest, $forceRollback) {
    global $db;
    if(empty($entitySource) || empty($entityDest)) return;

    $a = microtime(true);
    $db->begin();

    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_grille_leaser(fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type, date_cre, date_maj, entity, coeff_interne)';
    $sql .= ' SELECT fk_soc, fk_type_contrat, montant, periode, coeff, fk_user, type, NOW(), NOW(), '.$entityDest.', coeff_interne';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_grille_leaser';
    $sql .= ' WHERE entity = '.$entitySource;
    $sql .= " AND fk_type_contrat IN ('LOCSIMPLE', 'FORFAITGLOBAL', 'INTEGRAL')";

    $resql = $db->query($sql);
    if(! $resql || ! empty($forceRollback)) {
        if(! $resql) dol_print_error($db);
        $db->rollback();
    }
    else $db->commit();
    $db->free($resql);
    $b = microtime(true);
    printSummary($b - $a);
}

/**
 * Fonction permettant de copier les grilles de suivi d'une entité vers une autre
 *
 * @param $entitySource     int
 * @param $entityDest       int
 * @param $forceRollback    int
 */
function copyGrilleSuivi($entitySource, $entityDest, $forceRollback) {
    global $db;
    if(empty($entitySource) || empty($entityDest)) return;

    $a = microtime(true);
    $db->begin();

    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_grille_suivi(date_cre, date_maj, fk_type_contrat, fk_leaser_solde, fk_leaser_entreprise, fk_leaser_administration, fk_leaser_association, montantbase, montantfin, entity)';
    $sql .= ' SELECT NOW(), NOW(), fk_type_contrat, fk_leaser_solde, fk_leaser_entreprise, fk_leaser_administration, fk_leaser_association, montantbase, montantfin, '.$entityDest;
    $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_grille_suivi';
    $sql .= " WHERE fk_type_contrat LIKE 'DEFAUT%'";
    $sql .= ' AND entity = '.$entitySource;

    $resql = $db->query($sql);
    if(! $resql || ! empty($forceRollback)) {
        if(! $resql) dol_print_error($db);
        $db->rollback();
    }
    else $db->commit();
    $db->free($resql);
    $b = microtime(true);
    printSummary($b - $a);
}

/**
 * Fonction permettant de copier les grilles de pénalité d'une entité vers une autre
 *
 * @param $entitySource     int
 * @param $entityDest       int
 * @param $forceRollback    int
 */
function copyGrillePenalite($entitySource, $entityDest, $forceRollback) {
    global $db;
    if(empty($entitySource) || empty($entityDest)) return;

    $a = microtime(true);
    $db->begin();

    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'fin_grille_penalite (opt_name, opt_value, penalite, entity)';
    $sql .= ' SELECT opt_name, opt_value, penalite, '.$entityDest;
    $sql .= ' FROM '.MAIN_DB_PREFIX.'fin_grille_penalite';
    $sql .= ' WHERE entity = '.$entitySource;

    $resql = $db->query($sql);
    if(! $resql || ! empty($forceRollback)) {
        if(! $resql) dol_print_error($db);
        $db->rollback();
    }
    else $db->commit();
    $db->free($resql);
    $b = microtime(true);
    printSummary($b - $a);
}

/**
 * Fonction permettant de copier les types de contrat d'une entité vers une autre
 *
 * @param $entitySource     int
 * @param $entityDest       int
 * @param $forceRollback    int
 */
function copyTypeContrat($entitySource, $entityDest, $forceRollback) {
    global $db;
    if(empty($entitySource) || empty($entityDest)) return;

    $a = microtime(true);
    $db->begin();

    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'c_financement_type_contrat(code, label, entity, active)';
    $sql .= ' SELECT code, label, '.$entityDest.', active';
    $sql .= ' FROM '.MAIN_DB_PREFIX.'c_financement_type_contrat';
    $sql .= ' WHERE entity = '.$entitySource;

    $resql = $db->query($sql);
    if(! $resql || ! empty($forceRollback)) {
        if(! $resql) dol_print_error($db);
        $db->rollback();
    }
    else $db->commit();
    $db->free($resql);
    $b = microtime(true);
    printSummary($b - $a);
}