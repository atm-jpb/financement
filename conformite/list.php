<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

dol_include_once('/financement/class/conformite.class.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');

$langs->load('other');
$langs->load('dict');
$langs->load('financement@financement');

$simulation = new TSimulation(true);
$conformite = new Conformite;
$affaire = new TFin_affaire;
$PDOdb = new TPDOdb;
$form = new Form($db);

$search_ref = GETPOST('search_ref');
$search_entity = GETPOST('search_entity');
if(! empty($search_entity) && ! is_array($search_entity)) $search_entity = explode(',', $search_entity);
$search_thirdparty = GETPOST('search_thirdparty');
$search_status = GETPOST('search_status');

$action = GETPOST('action');
$sortfield = GETPOST('sortfield');
$sortorder = GETPOST('sortorder');
$page = GETPOST('page', 'int');
$limit = GETPOST('limit', 'int');
if(empty($limit)) $limit = $conf->liste_limit;
if(empty($sortfield)) $sortfield = 's.rowid';
if(empty($sortorder)) $sortorder = 'DESC';
if(empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;

$dao = new DaoMulticompany($db);
$dao->getEntities();
$TEntity = array(0 => '');
foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

/*
 * Action
 */
// Remove filters
if(GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha')) {
    unset($search_ref, $search_entity, $search_thirdparty, $search_status);
}

$sql = 'SELECT s.rowid, s.reference, s.rowid, soc.rowid as fk_soc, c.status, s.entity, c.fk_user, c.rowid as fk_conformite, c.commentaire';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_conformite c';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (c.fk_simulation = s.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'societe soc ON (s.fk_soc = soc.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'user u ON (c.fk_user = u.rowid)';

$strEntityShared = getEntity('fin_simulation', true);
$TEntityShared = explode(',', $strEntityShared);
//var_dump($search_entity);
$sql.= ' WHERE 1';
if(! empty($search_ref)) $sql .= natural_search('s.reference', $search_ref);
if(! empty($search_thirdparty)) $sql .= natural_search('soc.nom', $search_thirdparty);
if(! empty($search_status) && $search_status != -1) $sql .= natural_search('c.status', $search_status);
if(! empty($search_entity)) {
    $TSearchEntity = array_intersect($TEntityShared, $search_entity);
    if(! empty($TSearchEntity)) $sql .= ' AND s.entity IN ('.implode(',', $TSearchEntity).')';
}
else {
    $sql .= ' AND s.entity IN ('.$strEntityShared.')';
}

$sql .= ' GROUP BY s.rowid, c.status, c.fk_user, c.rowid';

$sql .= $db->order($sortfield, $sortorder);

$nbtotalofrecords = 0;
if(empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $result = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($result);
}

$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if(! $resql) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($resql);

llxHeader('', $langs->trans('ConformiteLabel'));
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

$param = '';
if($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.$limit;
if(! empty($search_ref)) $param .= '&search_ref='.urlencode($search_ref);
if(! empty($search_entity)) $param .= '&search_entity='.urlencode(implode(',', $search_entity));
if(! empty($search_thirdparty)) $param .= '&search_thirdparty='.urlencode($search_thirdparty);
if(! empty($search_status)) $param .= '&search_status='.urlencode($search_status);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" id="formfilteraction" name="formfilteraction" value="list" />';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'" />';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'" />';
print '<input type="hidden" name="page" value="'.$page.'" />';

$title = $langs->trans('ConformiteLabel');
if(! empty($nbtotalofrecords)) $title .= ' ('.$nbtotalofrecords.')';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'simul32@financement');

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Filters
print '<tr class="liste_titre">';

// Entity
print '<td colspan="7" style="min-width: 150px;">';
print '<span>'.$langs->trans('DemandReasonTypeSRC_PARTNER').' : </span>';
print Form::multiselectarray('search_entity', $TEntity, $search_entity, 0, 0, 'style="min-width: 250px;"');
print '</td>';

print '</tr>';
print '<tr class="liste_titre">';

// Reference
print '<td align="center">';
print '<input type="text" name="search_ref" value="'.$search_ref.'" size="6" />';
print '</td>';

// Entity
print '<td></td>';

// Thirdparty
print '<td>';
print '<input type="text" name="search_thirdparty" value="'.$search_thirdparty.'" size="20" />';
print '</td>';

// Statut
print '<td>';
print Form::selectarray('search_status', Conformite::$TStatus, $search_status, 1, 0, 0, 'style="width: 200px;"', 1);
print '</td>';

// User
print '<td>&nbsp;</td>';

// Commentaire
print '<td>&nbsp;</td>';

print '<td>';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans('Search'), 'search', '', false, 1).'" value="'.$langs->trans('Search').'" />';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans('RemoveFilter'), 'searchclear', '', false, 1).'" value="'.$langs->trans('RemoveFilter').'" />';
print '</td>';

print '</tr>';

// Titles
print '<tr class="liste_titre">';
print_liste_field_titre('Ref.', $_SERVER['PHP_SELF'], 's.reference', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Ref simulation
print_liste_field_titre('Partenaire', $_SERVER['PHP_SELF'], 's.entity', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Entity
print_liste_field_titre('Client', $_SERVER['PHP_SELF'], 's.fk_soc', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Thirdparty
print_liste_field_titre('Statut', $_SERVER['PHP_SELF'], 'c.status', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('User'), $_SERVER['PHP_SELF'], 'u.login', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('ConformiteCommentaire'), $_SERVER['PHP_SELF'], 'c.commentaire', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Statut simul
print '<td>&nbsp;</td>';
print '</tr>';

// Print data
for($i = 0 ; $i < min($num, $limit) ; $i++) {
    // FIXME: à remplacer par la class "oddeven" dans les versions plus récentes de Dolibarr
    if($i % 2 === 0) $class = 'impair';
    else $class = 'pair';

    $obj = $db->fetch_object($resql);

    $soc = new Societe($db);
    $soc->fetch($obj->fk_soc);

    if(! empty($obj->fk_leaser)) {
        $leaser = new Societe($db);
        $leaser->fetch($obj->fk_leaser);
    }

    $u = new User($db);
    $u->fetch($obj->fk_user);

    print '<tr class="'.$class.'">';

    // Reference
    print '<td align="center">';
    print '<a href="card.php?id='.$obj->fk_conformite.'">'.$obj->reference.'</a>';
    print '</td>';

    // Entity
    print '<td>';
    print $TEntity[$obj->entity];
    print '</td>';

    // Thirdparty
    print '<td>';
    print $form->textwithtooltip($soc->getNomUrl(1, '', 18), $soc->name);
    print '</td>';

    // Statut
    print '<td>';
    print $langs->trans(Conformite::$TStatus[$obj->status]);
    print '</td>';

    // User
    print '<td>';
    print $u->getLoginUrl(1);
    print '</td>';

    // Commentaire
    print '<td>';
    print $form->textwithtooltip(dol_trunc($obj->commentaire, 18), $obj->commentaire);
    print '</td>';

    print '<td>&nbsp;</td>';

    print '</tr>';
}
print '</table></div>';
print '</form>';

llxFooter();

function print_attente($compteur) {
    global $conf;

    $hour = intval($compteur / 3600);
    $min = ($compteur % 3600) / 60;
    if(! empty($conf->global->FINANCEMENT_FIRST_WAIT_ALARM) && $min >= (int) $conf->global->FINANCEMENT_FIRST_WAIT_ALARM) $style = 'color: orange;';
    if(! empty($conf->global->FINANCEMENT_SECOND_WAIT_ALARM) && $min >= (int) $conf->global->FINANCEMENT_SECOND_WAIT_ALARM) $style = 'color: red;';

    $ret = sprintf("%'.02dh%'.02d", $hour, $min);   // Format to 00h00

    if(! empty($style)) $ret = '<span style="'.$style.'">'.$ret.'</span>';

    return $ret;
}

function getStatutSuivi($idSimulation, $statut, $fk_fin_dossier, $nb_ok, $nb_refus, $nb_wait, $nb_err) {
    global $langs, $db;
    if(! function_exists('get_picto')) dol_include_once('/financement/lib/financement.lib.php');

    $suivi_leaser = '';
    $PDOdb = new TPDOdb;
    $s = new TSimulation;
    $s->load($PDOdb, $idSimulation, false);

    $iconSize = 'font-size: 21px;';
    if($s->fk_action_manuelle > 0) {
        $title = '';
        $color = 'deeppink';
        if($s->fk_action_manuelle == 2) $color = 'green';
        $sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_action_manuelle WHERE rowid = '.$s->fk_action_manuelle;
        $resql = $db->query($sql);

        if($obj = $db->fetch_object($resql)) {
            $title = $langs->trans($obj->label);
        }

        $suivi_leaser .= get_picto('manual', $title, $color);

        $db->free($resql);
    }
    else {
        $suivi_leaser .= '<a href="'.dol_buildpath('/financement/simulation/simulation.php?id='.$idSimulation, 1).'#suivi_leaser">';

        if(! empty($fk_fin_dossier)) { // La simulation a été financée, lien direct vers le dossier
            $suivi_leaser = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$fk_fin_dossier, 1).'">';
            $suivi_leaser .= get_picto('money');
            $suivi_leaser .= '</a>';
        }
        else if($statut == 'OK') $suivi_leaser .= get_picto('super_ok');
        else if($statut == 'WAIT_SELLER') $suivi_leaser .= get_picto('wait_seller');
        else if($statut == 'WAIT_LEASER') $suivi_leaser .= get_picto('wait_leaser');
        else if($statut == 'WAIT_AP') $suivi_leaser .= get_picto('wait_ap');
        else if($nb_ok > 0) $suivi_leaser .= get_picto('ok');
        else if($nb_refus > 0) $suivi_leaser .= get_picto('refus');
        else if($nb_wait > 0) $suivi_leaser .= get_picto('wait');
        else if($nb_err > 0) $suivi_leaser .= get_picto('err');
        else $suivi_leaser .= '';//'<img title="'.$langs->trans('Etude').'" src="'.dol_buildpath('/financement/img/WAIT.png',1).'" />';
        $suivi_leaser .= '</a>';
    }

    $suivi_leaser .= ' <span style="color: #00AA00;">'.$nb_ok.'</span>';
    $suivi_leaser .= ' <span style="color: #FF0000;">'.$nb_refus.'</span>';
    $suivi_leaser .= ' <span>'.($nb_ok + $nb_refus + $nb_wait + $nb_err).'</span>';

    return $suivi_leaser;
}

function _simu_edit_link($simulId, $date) {
    if(! function_exists('get_picto')) dol_include_once('/financement/lib/financement.lib.php');

    if(strtotime($date) > dol_now()) {
        $return = '<a href="'.dol_buildpath('/financement/simulation/simulation.php', 1).'?id='.$simulId.'&action=edit">'.get_picto('edit').'</a>';
    }
    else {
        $return = '';
    }
    return $return;
}
