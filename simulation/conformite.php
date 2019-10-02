<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/conformite.class.php');

$langs->load('compta');
$langs->load('other');

$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOST('id', 'int');
$fk_simu = GETPOST('fk_simu', 'int');

// Security check
$socid='';
if (! empty($user->societe_id))
{
    $action = '';
    $socid = $user->societe_id;
}
$result = restrictedArea($user, 'financement', $fk_simu, 'fin_simulation&societe', '', 'fk_soc', 'rowid');

$formfile=new FormFile($db);
$soc = new Societe($db);
$PDOdb = new TPDOdb;
$conformite = new Conformite;
if(! empty($id)) $conformite->fetch($id);

$object = new TSimulation;
$object->load($PDOdb, $fk_simu, false);
if ($object->rowid > 0)
{
    $soc->fetch($object->fk_soc);
    $upload_dir = $conf->financement->dir_output.'/'.dol_sanitizeFileName($object->reference).'/conformite';
    include_once DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_pre_headers.tpl.php';
}
else {
    // Pas de conformite sans id
    header('Location: list.php');
    exit;
}

/*
 * Actions
 */
if($action === 'save') {
    if(empty($id)) {    // Dans le cas d'une création
        $conformite->fk_simulation = $fk_simu;
        $conformite->fk_user = $user->id;
        $conformite->status = Conformite::STATUS_WAITING_FOR_COMPLIANCE;
        $res = $conformite->create();

        if($res > 0) {
            setEventMessage($langs->trans('ConformiteCreated'));

            $url = $_SERVER['PHP_SELF'];
            $url.= '?fk_simu='.$fk_simu;
            $url.= '&id='.$res;
            header('Location: '.$url);
            exit;
        }
    }
}
elseif($action === 'setStatus' && ! empty($id)) {
    $statusLabel = GETPOST('status', 'alpha');
    switch($statusLabel) {
        case 'notCompliant':
            $status = Conformite::STATUS_NOT_COMPLIANT;
            break;
        case 'compliant':
            $status = Conformite::STATUS_COMPLIANT;
            break;
        case 'firstCheck':
            $status = Conformite::STATUS_FIRST_CHECK;
            break;
        case 'wait':
            $status = Conformite::STATUS_WAITING_FOR_COMPLIANCE;
            break;
        default:
            break;
    }

    if(! is_null($status)) {
        $conformite->status = $status;
        $conformite->update();
    }
}
elseif($action === 'createDossier' && $conformite->status === Conformite::STATUS_COMPLIANT) {
    $d = new TFin_dossier;

    $d->financementLeaser->fk_soc = $object->fk_leaser;
    $d->financementLeaser->reference = $object->numero_accord;

    $d->save($PDOdb);

    if($d->rowid > 0) {
        // This will add link between dossier and simulation
        $object->fk_fin_dossier = $d->rowid;
        $object->save($PDOdb);
    }
}


/*
 * View
 */

llxHeader('',$langs->trans('Simulation'),'');
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

$form = new Form($db);

if ($object->id > 0)
{
    $upload_dir = $conf->financement->dir_output.'/'.dol_sanitizeFileName($object->reference).'/conformite';

    $head = simulation_prepare_head($object, $conformite);
    dol_fiche_head($head, 'conformite', $langs->trans('Simulation'), 0, 'simulation');

    // Construit liste des fichiers
    $filearray = dol_dir_list($upload_dir, "files", 0, '', '(\.meta|_preview\.png)$', $sortfield, (strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
    $totalsize = 0;
    foreach($filearray as $key => $file) {
        $totalsize += $file['size'];
    }

    print '<table class="border"width="100%">';

    // Ref
    print '<tr><td width="25%">'.$langs->trans('Ref').'</td><td colspan="3">';
    print $object->reference.'&nbsp;'.get_picto($object->accord);
    print '</td></tr>';

    if(! empty($id)) {
        print '<tr>';
        print '<td>'.$langs->trans('ConformiteStatus').'</td>';
        print '<td>'.$langs->trans(Conformite::$TStatus[$conformite->status]).'</td>';
        print '</tr>';
    }

    // Customer
    print "<tr><td>".$langs->trans("Company")."</td>";
    print '<td colspan="3">'.$soc->getNomUrl(1).'</td></tr>';

    print '<tr><td>'.$langs->trans("NbOfAttachedFiles").'</td><td colspan="3">'.count($filearray).'</td></tr>';
    print '<tr><td>'.$langs->trans("TotalSizeOfAttachedFiles").'</td><td colspan="3">'.$totalsize.' '.$langs->trans("bytes").'</td></tr>';

    print '<tr>';
    print '<td>'.$langs->trans('RequiredFiles').'</td>';
    print '<td colspan="3">'.$langs->trans('ListOfRequiredFiles').'</td>';
    print '</tr>';

    print '</table>';

    print '</div>';

    $param = '?fk_simu='.$object->rowid;
    if(! empty($id)) $param .= '&id='.$id;

    // Show upload form (document and links)
    $formfile->form_attach_new_file(
        $_SERVER["PHP_SELF"].$param.(empty($withproject) ? '' : '&withproject=1'),
        '',
        0,
        0,
        $user->rights->financement->admin,
        50,
        $conformite,
        '',
        1,
        '',
        0
    );

// List of document
    $formfile->list_of_documents(
        $filearray,
        $object,
        'financement',
        $param,
        0,
        $upload_dir,
        $user->rights->financement->admin
    );
}
else
{
    print $langs->trans("ErrorUnknown");
}

print '<div class="tabsAction">';

if(empty($id)) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&action=save">'.$langs->trans('Save').'</a>';
}
elseif($conformite->status === Conformite::STATUS_WAITING_FOR_COMPLIANCE) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=firstCheck">'.$langs->trans('ConformiteFirstCheck').'</a>';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=compliant">'.$langs->trans('ConformiteCompliant').'</a>';
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=notCompliant">'.$langs->trans('ConformiteNotCompliant').'</a>';
}
elseif(in_array($conformite->status, array(Conformite::STATUS_COMPLIANT, Conformite::STATUS_NOT_COMPLIANT, Conformite::STATUS_FIRST_CHECK))) {
    if($conformite->status === Conformite::STATUS_COMPLIANT) {
        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=createDossier">'.$langs->trans('ConformiteCreateDossier').'</a>';
    }
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=wait">'.$langs->trans('ConformiteWaitingForCompliance').'</a>';
}

print '</div>';

llxFooter();
$db->close();
