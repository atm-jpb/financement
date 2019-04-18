<?php
require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

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
$affaire = new TFin_affaire;
$PDOdb = new TPDOdb;
$form = new Form($db);

$search_ref = GETPOST('search_ref');
$search_entity = GETPOST('search_entity');
if(! empty($search_entity) && ! is_array($search_entity)) $search_entity = explode(',', $search_entity);
$search_thirdparty = GETPOST('search_thirdparty');
$search_typeContrat = GETPOST('search_typeContrat');
$year = GETPOST('search_dateSimulyear');
$month = GETPOST('search_dateSimulmonth');
$day = GETPOST('search_dateSimulday');
if(! empty($year) && ! empty($month) && ! empty($day)) {
    $search_dateSimul = dol_mktime(12, 0, 0, $month, $day, $year);
    $search_dateSimul = date('Y-m-d', $search_dateSimul);
}
$search_user = GETPOST('search_user');
$search_statut = GETPOST('search_statut');
$search_leaser = GETPOST('search_leaser');

$fk_soc = GETPOST('socid', 'int');
$action = GETPOST('action');
$searchnumetude = GETPOST('searchnumetude');
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
    unset($search_ref, $search_entity, $search_thirdparty, $search_typeContrat, $search_dateSimul, $search_user, $search_statut, $search_leaser);
}

$sql = "SELECT DISTINCT s.rowid, s.reference, e.label as entity_label, s.fk_soc, s.fk_user_author, s.fk_type_contrat, s.montant_total_finance, s.echeance,";
$sql .= " s.duree, s.opt_periodicite, s.date_simul, s.date_validite, u.rowid as fk_user, s.accord, s.type_financement, lea.rowid as fk_leaser, s.attente, s.fk_fin_dossier";
$sql .= " ,SUM(CASE WHEN ss.statut = 'OK' THEN 1 ELSE 0 END) as nb_ok";
$sql .= " ,SUM(CASE WHEN ss.statut = 'KO' THEN 1 ELSE 0 END) as nb_refus";
$sql .= " ,SUM(CASE WHEN ss.statut = 'WAIT' THEN 1 ELSE 0 END) as nb_wait";
$sql .= " ,SUM(CASE WHEN ss.statut = 'ERR' THEN 1 ELSE 0 END) as nb_err";
$sql .= ' FROM '.MAIN_DB_PREFIX.'fin_simulation s ';
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (s.fk_user_author = u.rowid)";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON (s.fk_soc = soc.rowid)";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as lea ON (s.fk_leaser = lea.rowid)";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.'entity as e ON (e.rowid = s.entity) ';
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.'fin_simulation_suivi as ss ON (s.rowid = ss.fk_simulation)';

if(! $user->rights->societe->client->voir) {
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON (sc.fk_soc = soc.rowid)";
}

$sql .= " WHERE ss.date_historization < '1970-00-00 00:00:00'";
if(! $user->rights->societe->client->voir) //restriction
{
    $sql .= " AND sc.fk_user = ".$user->id;
}
if($user->rights->societe->client->voir && ! $user->rights->financement->allsimul->simul_list) {
    $sql .= " AND s.fk_user_author = ".$user->id;
}
if(! empty($searchnumetude)) {
    $sql .= " AND ss.numero_accord_leaser='".$searchnumetude."'";
}

if(! empty($fk_soc)) {
    $societe = new Societe($db);
    $societe->fetch($fk_soc);

    // Recherche par SIREN
    $search_by_siren = true;
    if(! empty($societe->array_options['options_no_regroup_fin_siren'])) {
        $search_by_siren = false;
    }

    $sql .= " AND (s.fk_soc = ".$societe->id;
    if(! empty($societe->idprof1) && $search_by_siren) {
        $sql .= " OR s.fk_soc IN
						(
							SELECT s.rowid 
							FROM ".MAIN_DB_PREFIX."societe as s
								LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON (se.fk_object = s.rowid)
							WHERE
							(
								s.siren = '".$societe->idprof1."'
								AND s.siren != ''
							) 
							OR
							(
								se.other_siren LIKE '%".$societe->idprof1."%'
								AND se.other_siren != ''
							)
						)";
    }
    $sql .= " )";

    // Affichage résumé client

    // Infos sur SIREN
    $info = '';
    if(! empty($societe->idprof1)) {
        if($societe->id_prof_check(1, $societe) > 0) $info = ' &nbsp; '.$societe->id_prof_url(1, $societe);
        else $info = ' <font class="error">('.$langs->trans("ErrorWrongValue").')</font>';
    }
    // TODO: REMOVE !!!!!!!
    print $TBS->render('./tpl/client_entete.tpl.php'
        , array()
        , array(
            'client' => array(
                'dolibarr_societe_head' => dol_get_fiche_head(societe_prepare_head($societe), 'simulation', $langs->trans("ThirdParty"), 0, 'company')
                , 'showrefnav' => $formDoli->showrefnav($societe, 'socid', '', ($user->societe_id ? 0 : 1), 'rowid', 'nom')
                , 'code_client' => $societe->code_client
                , 'idprof1' => $societe->idprof1.$info
                , 'adresse' => $societe->address
                , 'cpville' => $societe->zip.($societe->zip && $societe->town ? " / " : "").$societe->town
                , 'pays' => picto_from_langcode($societe->country_code).' '.$societe->country
            )
            , 'view' => array(
                'mode' => 'view'
            )
        )
    );

    $THide[] = 'Client';
}

$strEntityShared = getEntity('fin_simulation', true);
$TEntityShared = explode(',', $strEntityShared);

if(! empty($search_ref)) $sql .= natural_search('s.reference', $search_ref);
if(! empty($search_thirdparty)) $sql .= natural_search('soc.nom', $search_thirdparty);
if(! empty($search_typeContrat) && $search_typeContrat != -1) $sql .= natural_search('s.fk_type_contrat', $search_typeContrat);
if(! empty($search_dateSimul)) $sql .= natural_search('s.date_simul', $search_dateSimul);
if(! empty($search_user)) $sql .= natural_search('u.login', $search_user);
if(! empty($search_statut) && $search_statut != -1) $sql .= natural_search('s.accord', $search_statut);
if(! empty($search_leaser)) $sql .= natural_search('lea.nom', $search_leaser);
if(! empty($search_entity)) {
    $TSearchEntity = array_intersect($TEntityShared, $search_entity);
    $sql .= ' AND s.entity IN ('.implode(',', $TSearchEntity).')';
}
else {
    $sql .= ' AND s.entity IN ('.$strEntityShared.')';
}
//print $sql;exit;
$sql .= ' GROUP BY s.rowid';

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

llxHeader('', 'Simulations');
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

$param = '';
if($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.$limit;
if(! empty($search_ref)) $param .= '&search_ref='.urlencode($search_ref);
if(! empty($search_entity)) $param .= '&search_entity='.urlencode(implode(',', $search_entity));
if(! empty($search_thirdparty)) $param .= '&search_thirdparty='.urlencode($search_thirdparty);
if(! empty($search_typeContrat)) $param .= '&search_typeContrat='.urlencode($search_typeContrat);
if(! empty($search_dateSimul)) $param .= '&search_dateSimul='.urlencode($search_dateSimul);
if(! empty($search_user)) $param .= '&search_user='.urlencode($search_user);
if(! empty($search_statut)) $param .= '&search_statut='.urlencode($search_statut);
if(! empty($search_leaser)) $param .= '&search_leaser='.urlencode($search_leaser);

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" id="formfilteraction" name="formfilteraction" value="list" />';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'" />';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'" />';
print '<input type="hidden" name="page" value="'.$page.'" />';

$title = 'Simulations';
if(! empty($nbtotalofrecords)) $title .= ' ('.$nbtotalofrecords.')';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'simul32@financement');

//if(! empty($fk_soc)) {
//    $href = '?action=new&fk_soc='.$fk_soc;
//    foreach($_POST as $k => $v) $href .= '&'.$k.'='.$v;
//
//    ?>
<!--    <div class="tabsAction"><a href="--><?php //echo $href; ?><!--" class="butAction">Nouvelle simulation</a></div>-->
<!--    --><?php
//}

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Filters
print '<tr class="liste_titre">';

// Entity
print '<td colspan="14" style="min-width: 150px;">';
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
print '<td style="min-width: 150px;">';
print '</td>';

// Thirdparty
print '<td>';
print '<input type="text" name="search_thirdparty" value="'.$search_thirdparty.'" size="20" />';
print '</td>';

// Type de contrat
print '<td>';
print Form::selectarray('search_typeContrat', $affaire->TContrat, $search_typeContrat, 1, 0, 0, 'style="width: 100px;"');
print '</td>';

// Montant
print '<td>&nbsp;</td>';

// Echeance
print '<td>&nbsp;</td>';

// Durée
print '<td>&nbsp;</td>';

// Date simulation
print '<td style="min-width: 105px;">';
print $form->select_date($search_dateSimul, 'search_dateSimul', 0, 0, 1);
print '</td>';

// Utilisateur
print '<td>';
print '<input type="text" name="search_user" value="'.$search_user.'" size="15" />';
print '</td>';

// Statut
print '<td>';
print Form::selectarray('search_statut', $simulation->TStatut, $search_statut, 1, 0, 0, 'style="width: 100px;"');
print '</td>';

// Leaser
print '<td>';
print '<input type="text" name="search_leaser" value="'.$search_leaser.'" size="20" />';
print '</td>';

// Délai
print '<td>&nbsp;</td>';

// Statut Leaser
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
print_liste_field_titre('Type contrat', $_SERVER['PHP_SELF'], 's.fk_type_contrat', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Type contrat
print_liste_field_titre('Montant', $_SERVER['PHP_SELF'], 's.montant', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Montant
print_liste_field_titre('Echeance', $_SERVER['PHP_SELF'], 's.echeance', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Echeance
print_liste_field_titre('Durée', $_SERVER['PHP_SELF'], 's.duree', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Durée
print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 's.date_simul', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Date simulation
print_liste_field_titre('Utilisateur', $_SERVER['PHP_SELF'], 's.fk_user_author', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Utilisateur
print_liste_field_titre('Statut', $_SERVER['PHP_SELF'], 's.accord', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre('Leaser', $_SERVER['PHP_SELF'], 's.fk_leaser', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Leaser
print_liste_field_titre('Délai', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Délai
print_liste_field_titre('Statut<br/>Leaser', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Statut leaser
print '<td>&nbsp;</td>';
print '</tr>';

// Print data
for($i = 0 ; $i < min($num, $limit) ; $i++) {
    $obj = $db->fetch_object($resql);

    $soc = new Societe($db);
    $soc->fetch($obj->fk_soc);

    if(! empty($obj->fk_leaser)) {
        $leaser = new Societe($db);
        $leaser->fetch($obj->fk_leaser);
    }

    $u = new User($db);
    $u->fetch($obj->fk_user);

    print '<tr class="oddeven">';

    // Reference
    print '<td align="center">';
    print '<a href="simulation.php?id='.$obj->rowid.'">'.$obj->reference.'</a>';
    print '</td>';

    // Entity
    print '<td>';
    print $obj->entity_label;
    print '</td>';

    // Thirdparty
    print '<td>';
    print $form->textwithtooltip($soc->getNomUrl(1, '', 18), $soc->name);
    print '</td>';

    // Type de contrat
    print '<td>';
    print $affaire->TContrat[$obj->fk_type_contrat];
    print '</td>';

    // Montant
    print '<td align="right">';
    print price($obj->montant_total_finance, 0, $langs, 1, -1, 2);
    print '</td>';

    // Echeance
    print '<td align="right">';
    print price($obj->echeance, 0, $langs, 1, -1, 2);
    print '</td>';

    // Durée
    print '<td align="center">';
    print $obj->duree.' '.substr($obj->opt_periodicite, 0, 1);
    print '</td>';

    // Date simulation
    print '<td align="center">';
    print date('d/m/y', strtotime($obj->date_simul));
    print '</td>';

    // Utilisateur
    print '<td>';
    print $u->getLoginUrl(1);
    print '</td>';

    // Statut
    print '<td>';
    print $simulation->TStatut[$obj->accord];
    print '</td>';

    // Leaser
    print '<td>';
    if(! empty($obj->fk_leaser)) print $form->textwithtooltip($leaser->getNomUrl(1, 0, 18), $leaser->name);
    else print '';
    print '</td>';

    // Délai
    print '<td>';
    print print_attente($obj->attente);
    print '</td>';

    // Statut Leaser
    print '<td><div align="center">';
    print getStatutSuivi($obj->rowid, $obj->accord, $obj->fk_fin_dossier, $obj->nb_ok, $obj->nb_refus, $obj->nb_wait, $obj->nb_err);
    print '</div></td>';

    print '<td>';
    print _simu_edit_link($obj->rowid, $obj->date_validite);
    print '</td>';  // Un truc qui sert à mettre des icônes, tu vois le genre ?

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
