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
$langs->load('mails');
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
if(! empty($search_status) && ! is_array($search_status)) $search_status = explode(',', $search_status);
$search_user = GETPOST('search_user');

$action = GETPOST('action');
$toSelect = GETPOST('toselect', 'array');
$arrayOfSelected = is_array($toSelect) ? $toSelect : array();
$massaction = GETPOST('massaction', 'alpha');
$sortfield = GETPOST('sortfield');
$sortorder = GETPOST('sortorder');
$page = GETPOST('page', 'int');
$limit = GETPOST('limit', 'int');
if(empty($limit)) $limit = $conf->liste_limit;
if(empty($sortfield)) $sortfield = 'c.date_cre';
if(empty($sortorder)) $sortorder = 'DESC';
if(empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;

$strEntityShared = getEntity('fin_simulation', true);
$TEntityShared = explode(',', $strEntityShared);

$dao = new DaoMulticompany($db);
$dao->getEntities();
foreach($dao->entities as $mc_entity) if(in_array($mc_entity->id, $TEntityShared)) $TEntity[$mc_entity->id] = $mc_entity->label;

/*
 * Action
 */

// Remove filters
if(GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha')) {
    unset($search_ref, $search_entity, $search_thirdparty, $search_leaser, $search_status, $search_user);
}

$sql = 'SELECT s.rowid, s.reference, soc.rowid as fk_soc, c.status, s.entity, c.fk_user, c.rowid as fk_conformite, c.commentaire, lea.rowid as fk_leaser, c.date_envoi, s.fk_fin_dossier as fk_dossier, c.date_reception_papier, c.date_attenteN2';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_conformite c';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON (c.fk_simulation = s.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'societe soc ON (s.fk_soc = soc.rowid)';
$sql.= ' INNER JOIN '.MAIN_DB_PREFIX.'societe lea ON (s.fk_leaser = lea.rowid)';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'user u ON (c.fk_user = u.rowid)';

$sql.= ' WHERE 1';
if(! empty($search_ref)) $sql .= natural_search('s.reference', $search_ref);
if(! empty($search_thirdparty)) $sql .= natural_search('soc.nom', $search_thirdparty);
if(! empty($search_leaser)) $sql .= natural_search('lea.nom', $search_leaser);
if(! empty($search_status)) {
    $sql .= ' AND c.status IN ('.implode(',', $search_status).')';
}
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

if(! empty($arrayOfSelected)) {
    if($massaction == 'updateDateReception') {
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                function updateDateReception() {
                    var strDate = $('#dateReception').val();
                    var selectedConformite = "<?php echo implode(',', $arrayOfSelected); ?>";

                    $.ajax({
                        url: "<?php echo dol_buildpath('/financement/script/interface.php', 1); ?>",
                        data: {
                            json: 1,
                            action: 'updateDateReception',
                            strDate: strDate,
                            allSelectedConformite: selectedConformite
                        },
                        dataType: 'json',
                        type: 'POST',
                        async: false
                    });
                }

                $("div#updateDateReceptionDossier").dialog({
                    modal: true,
                    minWidth: 400,
                    minHeight: 100,
                    buttons: [{
                            text: "Ok",
                            click: function() {
                                updateDateReception();
                                $(this).dialog('close');
                                location.href = location.pathname;
                            }
                        },
                        { text: "<?php echo $langs->trans('Cancel'); ?>", click: function() { $(this).dialog('close'); }}
                    ]
                });
            });
        </script>
        <?php
    }
}

$param = '';
if($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.$limit;
if(! empty($search_ref)) $param .= '&search_ref='.urlencode($search_ref);
if(! empty($search_entity)) $param .= '&search_entity='.urlencode(implode(',', $search_entity));
if(! empty($search_thirdparty)) $param .= '&search_thirdparty='.urlencode($search_thirdparty);
if(! empty($search_leaser)) $param .= '&search_leaser='.urlencode($search_leaser);
if(! empty($search_status)) $param .= '&search_status='.urlencode(implode(',', $search_status));
if(! empty($search_user)) $param .= '&search_user='.urlencode($search_user);

$arrayofmassactions = array(
    'updateDateReception' => $langs->trans('ConformiteUpdateDateReception')
);

// This should be replaced by $form->SelectMassAction(...) in later versions
$massactionbutton = '<div class="centpercent center">';
$massactionbutton .= '<select class="flat massaction massactionselect" name="massaction">';
$massactionbutton .= '<option value="0">-- '.$langs->trans('SelectAction').' --</option>';
foreach($arrayofmassactions as $code => $label) {
    $massactionbutton .= '<option value="'.$code.'">'.$label.'</option>';
}
$massactionbutton .= '</select>';
$massactionbutton .= '<input type="submit" name="confirmmassactioninvisible" style="display: none;" tabindex="-1" />';
$massactionbutton .= '<input type="submit" class="button massaction massactionconfirmed" name="confirmmassaction" disabled="disabled" value="'.dol_escape_htmltag($langs->trans("Confirm")).'" />';
$massactionbutton .= '</div>';
$massactionbutton .= '<script type="text/javascript">
function initCheckForSelect(mode)	/* mode is 0 during init of page or click all, 1 when we click on 1 checkbox */
                {
                    atleastoneselected=0;
                    jQuery(".checkforselect").each(function( index ) {
                          /* console.log( index + ": " + $( this ).text() ); */
                          if ($(this).is(\':checked\')) atleastoneselected++;
                      });
                    console.log("initCheckForSelect mode="+mode+" atleastoneselected="+atleastoneselected);

                    if(atleastoneselected === $(".checkforselect").length) $("#checkallactions").prop("checked", "checked").prop("indeterminate", false);
                    else if(atleastoneselected !== 0) $("#checkallactions").prop("indeterminate", true).prop("checked", false);
                    else $("#checkallactions").prop("indeterminate", false).prop("checked", false);

                      if (atleastoneselected)
                      {
                          jQuery(".massaction").show();
                        '.($selected ? 'if (atleastoneselected) { jQuery(".massactionselect").val("'.$selected.'"); jQuery(".massactionconfirmed").prop(\'disabled\', false); }' : '').'
                        '.($selected ? 'if (! atleastoneselected) { jQuery(".massactionselect").val("0"); jQuery(".massactionconfirmed").prop(\'disabled\', true); } ' : '').'
                      }
                      else
                      {
                          jQuery(".massaction").hide();
                    }
                }

            jQuery(document).ready(function () {
                initCheckForSelect(0);
                jQuery(".checkforselect").click(function() {
                    initCheckForSelect(1);
                  });
                  jQuery(".massactionselect").change(function() {
                    var massaction = $( this ).val();
                    var urlform = $( this ).closest("form").attr("action").replace("#show_files","");
                    if (massaction == "builddoc")
                    {
                        urlform = urlform + "#show_files";
                    }
                    $( this ).closest("form").attr("action", urlform);
                    console.log("we select a mass action "+massaction+" - "+urlform);
                    /* Warning: if you set submit button to disabled, post using Enter will no more work if there is no other button */
                    if ($(this).val() != \'0\')
                      {
                          jQuery(".massactionconfirmed").prop(\'disabled\', false);
                      }
                      else
                      {
                          jQuery(".massactionconfirmed").prop(\'disabled\', true);
                      }
                });
            });
</script>';

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" id="formfilteraction" name="formfilteraction" value="list" />';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'" />';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'" />';
print '<input type="hidden" name="page" value="'.$page.'" />';

$title = $langs->trans('ConformiteLabel');
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'simul32@financement', 0, '', '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

// Filters
print '<tr class="liste_titre">';

// Entity
print '<td colspan="11" style="min-width: 150px;">';
print '<span>'.$langs->trans('DemandReasonTypeSRC_PARTNER').' : </span>';
print Form::multiselectarray('search_entity', $TEntity, $search_entity, 0, 0, '', 0, 1500);
print '</td>';

print '</tr>';
print '<tr class="liste_titre">';

// Status
print '<td colspan="11" style="min-width: 150px;">';
print '<span>'.$langs->trans('Status').' : </span>';
print Form::multiselectarray('search_status', Conformite::$TStatus, $search_status, 0, 0, '', 1, 1235);
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
print '<td>&nbsp;</td>';

// Date création
print '<td>';
print '&nbsp;';
print '</td>';

// Date attente N2
print '<td>&nbsp;</td>';

// User
print '<td>';
print '<input type="text" name="search_user" value="'.$search_user.'" size="14" />';
print '</td>';

// Commentaire
print '<td>&nbsp;</td>';

// Date reception dossier papier
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
print_liste_field_titre($langs->trans('Status'), $_SERVER['PHP_SELF'], 'c.status', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('ConformiteDateWaitingForComplianceN1'), $_SERVER['PHP_SELF'], 'c.date_envoi', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('ConformiteDateWaitingForComplianceN2'), $_SERVER['PHP_SELF'], 'c.date_attenteN2', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('User'), $_SERVER['PHP_SELF'], 'u.login', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('ConformiteCommentaire'), $_SERVER['PHP_SELF'], 'c.commentaire', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Statut simul
print_liste_field_titre($langs->trans('ConformiteDateReception'), $_SERVER['PHP_SELF'], 'c.date_reception_papier', '', $param, 'style="text-align: left;"', $sortfield, $sortorder);   // Date reception papier
print '<td align="center">';
print '<input type="checkbox" id="checkallactions" name="checkallactions" class="checkallactions" />';
print '<script type="text/javascript">
            $(document).ready(function() {
                $("#checkallactions").click(function() {
                    if($(this).is(\':checked\')){
                        console.log("We check all");
                        $(".checkforselect").prop(\'checked\', true);
                    }
                    else
                    {
                        console.log("We uncheck all");
                        $(".checkforselect").prop(\'checked\', false);
                    }
                    if (typeof initCheckForSelect == \'function\') { initCheckForSelect(0); } else { console.log("No function initCheckForSelect found. Call won\'t be done."); }         });
                });
            </script>';
print '</td>';
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

    // Date envoi
    $date_envoi = strtotime($obj->date_envoi);
    print '<td>';
    if(! empty($date_envoi) && $date_envoi > 0) print date('d/m/Y', $date_envoi);
    else print '&nbsp;';
    print '</td>';

    // Date attente N2
    $dateAttenteN2 = strtotime($obj->date_attenteN2);
    print '<td>';
    if(! empty($dateAttenteN2) && $dateAttenteN2 > 0) print date('d/m/Y', $dateAttenteN2);
    else print '&nbsp;';
    print '</td>';

    // User
    print '<td>';
    if(! empty($u->id)) print $u->getLoginUrl(1);
    print '</td>';

    // Commentaire
    print '<td>';
    print $form->textwithtooltip(dol_trunc($obj->commentaire, 18), str_replace("\n", "<br/>", $obj->commentaire));
    print '</td>';

    // Date reception papier
    $dateReceptionPapier = strtotime($obj->date_reception_papier);
    print '<td>';
    if(! empty($dateReceptionPapier) && $dateReceptionPapier > 0) print date('d/m/Y', $dateReceptionPapier);
    else print '&nbsp;';
    print '</td>';

    print '<td style="text-align: center;">';
    if(! empty($obj->fk_dossier)) {
        $selected = in_array($obj->fk_conformite, $arrayOfSelected);
        print '<input id="cb'.$obj->fk_conformite.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->fk_conformite.'" '.($selected ? 'checked="checked"' : '').'/>';
    }
    else print '&nbsp;';
    print '</td>';

    print '</tr>';
}
print '</table></div>';
print '</form>';

print '<div id="updateDateReceptionDossier" title="'.$langs->trans('ConformiteUpdateDateReception').'" style="display: none;">';
print '<span>'.$langs->trans('ConformiteDateReception').' :</span>&nbsp;';
print '<input type="date" id="dateReception" name="dateReception" required="required"  value="'.date('Y-m-d').'" />';
print '</div>';

llxFooter();
