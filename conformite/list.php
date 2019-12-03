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
$search_leaser = GETPOST('search_leaser');
$search_status = GETPOST('search_status');
$search_user = GETPOST('search_user');

$action = GETPOST('action');
$sortfield = GETPOST('sortfield');
$sortorder = GETPOST('sortorder');
$page = GETPOST('page', 'int');
$limit = GETPOST('limit', 'int');
var_dump($limit, $conf->liste_limit);
if(empty($limit)) $limit = $conf->liste_limit;
if(empty($sortfield)) $sortfield = 'c.date_cre';
if(empty($sortorder)) $sortorder = 'DESC';
if(empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;

$dao = new DaoMulticompany($db);
$dao->getEntities();
foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

/*
 * Action
 */
// Remove filters
if(GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha')) {
    unset($search_ref, $search_entity, $search_thirdparty, $search_leaser, $search_status, $search_user);
}

$sql = 'SELECT s.rowid, s.reference, soc.rowid as fk_soc, c.status, s.entity, c.fk_user, c.rowid as fk_conformite, c.commentaire, c.date_cre, lea.rowid as fk_leaser';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_conformite c';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (c.fk_simulation = s.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'societe soc ON (s.fk_soc = soc.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'societe lea ON (s.fk_leaser = lea.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'user u ON (c.fk_user = u.rowid)';

$strEntityShared = getEntity('fin_simulation', true);
$TEntityShared = explode(',', $strEntityShared);

$sql.= ' WHERE 1';
if(! empty($search_ref)) $sql .= natural_search('s.reference', $search_ref);
if(! empty($search_thirdparty)) $sql .= natural_search('soc.nom', $search_thirdparty);
if(! empty($search_leaser)) $sql .= natural_search('lea.nom', $search_leaser);
if(! empty($search_status) && $search_status != -1) $sql .= natural_search('c.status', $search_status);
if(! empty($search_user)) $sql .= natural_search('u.login', $search_user);
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
if(! empty($search_leaser)) $param .= '&search_leaser='.urlencode($search_leaser);
if(! empty($search_status)) $param .= '&search_status='.urlencode($search_status);
if(! empty($search_user)) $param .= '&search_user='.urlencode($search_user);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" id="formfilteraction" name="formfilteraction" value="list" />';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'" />';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'" />';
print '<input type="hidden" name="page" value="'.$page.'" />';

$title = $langs->trans('ConformiteLabel');
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'simul32@financement', 0, '', '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Filters
print '<tr class="liste_titre">';

// Entity
print '<td colspan="9" style="min-width: 150px;">';
print '<span>'.$langs->trans('DemandReasonTypeSRC_PARTNER').' : </span>';
print Form::multiselectarray('search_entity', $TEntity, $search_entity, 0, 0, 'style="min-width: 250px;"');
print '</td>';

print '</tr>';
print '<tr class="liste_titre">';

// Reference
print '<td>';
print '<input type="text" name="search_ref" value="'.$search_ref.'" size="8" />';
print '</td>';

// Entity
print '<td></td>';

// Thirdparty
print '<td>';
print '<input type="text" name="search_thirdparty" value="'.$search_thirdparty.'" size="20" />';
print '</td>';

// Leaser
print '<td>';
print '<input type="text" name="search_leaser" value="'.$search_leaser.'" size="20" />';
print '</td>';

// Statut
print '<td>';
print Form::selectarray('search_status', Conformite::$TStatus, $search_status, 1, 0, 0, 'style="width: 200px;"', 1);
print '</td>';

// Date création
print '<td>';
print '&nbsp;';
print '</td>';

// User
print '<td>';
print '<input type="text" name="search_user" value="'.$search_user.'" size="14" />';
print '</td>';

// Commentaire
print '<td>&nbsp;</td>';

print '<td>';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans('Search'), 'search', '', false, 1).'" value="'.$langs->trans('Search').'" />';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans('RemoveFilter'), 'searchclear', '', false, 1).'" value="'.$langs->trans('RemoveFilter').'" />';
print '</td>';

print '</tr>';

// Titles
print '<tr class="liste_titre">';
print_liste_field_titre('Ref.', $_SERVER['PHP_SELF'], 's.reference', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Ref simulation
print_liste_field_titre('Partenaire', $_SERVER['PHP_SELF'], 's.entity', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Entity
print_liste_field_titre('Client', $_SERVER['PHP_SELF'], 's.fk_soc', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Thirdparty
print_liste_field_titre('Leaser', $_SERVER['PHP_SELF'], 's.fk_leaser', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Leaser
print_liste_field_titre('Statut', $_SERVER['PHP_SELF'], 'c.status', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('DateCreation'), $_SERVER['PHP_SELF'], 'c.date_cre', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('User'), $_SERVER['PHP_SELF'], 'u.login', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('ConformiteCommentaire'), $_SERVER['PHP_SELF'], 'c.commentaire', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
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
    if(! empty($obj->fk_user)) $u->fetch($obj->fk_user);

    print '<tr class="'.$class.'">';

    // Reference
    print '<td align="left">';
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

    // Leaser
    print '<td>';
    print $form->textwithtooltip($leaser->getNomUrl(1, '', 18), $leaser->name);
    print '</td>';

    // Statut
    print '<td>';
    print $langs->trans(Conformite::$TStatus[$obj->status]);
    print '</td>';

    // Date création
    print '<td>';
    print date('d/m/Y', strtotime($obj->date_cre));
    print '</td>';

    // User
    print '<td>';
    if(! empty($u->id)) print $u->getLoginUrl(1);
    print '</td>';

    // Commentaire
    print '<td>';
    print $form->textwithtooltip(dol_trunc($obj->commentaire, 18), str_replace("\n", "<br/>", $obj->commentaire));
    print '</td>';

    print '<td>&nbsp;</td>';

    print '</tr>';
}
print '</table></div>';
print '</form>';

llxFooter();
