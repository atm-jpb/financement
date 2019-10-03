<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/conformite.class.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');

$langs->load('dict');
$langs->load('other');
$langs->load('error');

$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOST('id', 'int');
$fk_simu = GETPOST('fk_simu', 'int');
$upload = GETPOST('upload', '', 2);

// Security check
$socid='';
if (! empty($user->societe_id)) {
    $action = '';
    $socid = $user->societe_id;
}
$result = restrictedArea($user, 'financement', $fk_simu, 'fin_simulation&societe', '', 'fk_soc', 'rowid');

$dao = new DaoMulticompany($db);
$dao->getEntities();
foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

$formfile = new FormFile($db);
$formMail = new FormMail($db);
$soc = new Societe($db);
$PDOdb = new TPDOdb;
$object = new Conformite;
if(! empty($id)) {
    $object->fetch($id);
    if(empty($fk_simu)) $fk_simu = $object->fk_simulation;
}

$simu = new TSimulation;
$simu->load($PDOdb, $fk_simu, false);
if ($simu->rowid > 0) {
    $soc->fetch($simu->fk_soc);
    $oldEntity = $conf->entity;
    switchEntity($simu->entity);
    $upload_dir = $conf->financement->dir_output.'/'.dol_sanitizeFileName($simu->reference).'/conformite';
    include_once DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_pre_headers.tpl.php';
    switchEntity($oldEntity);
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
        $object->fk_simulation = $fk_simu;
        $object->fk_user = $user->id;
        $object->status = Conformite::STATUS_WAITING_FOR_COMPLIANCE;
        $object->entity = $simu->entity;
        $res = $object->create();

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
        $object->status = $status;
        $object->update();
    }
}
elseif($action === 'createDossier' && $object->status === Conformite::STATUS_COMPLIANT) {
    // TODO: Continue !
    $d = new TFin_dossier;

    $d->financementLeaser->fk_soc = $simu->fk_leaser;
    $d->financementLeaser->reference = $simu->numero_accord;

    $d->save($PDOdb);

    if($d->rowid > 0) {
        // This will add link between dossier and simulation
        $simu->fk_fin_dossier = $d->rowid;
        $simu->save($PDOdb);
    }
}
elseif(! empty($upload) && ! empty($conf->global->MAIN_UPLOAD_DOC) && ! empty($object->id) && ! empty($_FILES['userfile'])) {
    // TODO: Traitement à refactorer/virer avec une version plus récente de Dolibarr
    if(! empty($upload_dir) && ! file_exists($upload_dir)) dol_mkdir($upload_dir);

    $TData = array_shift($_FILES);
    $nbFiles = count($TData['name']);

    for($i = 0 ; $i < $nbFiles ; $i++) {
        $destPath = $upload_dir.'/'.$TData['name'][$i];

        $res = dol_move_uploaded_file($TData['tmp_name'][$i], $destPath, 1, 0, $TData['error'][$i], 0, 'userfile');
        if(is_numeric($res) && $res > 0) {
            $formMail->add_attached_files($destPath, $TData['name'][$i], $TData['type'][$i]);

            setEventMessage($langs->trans('FileTransferComplete'));
        }
        else {
            if($res < 0) {    // Unknown error
                setEventMessage($langs->trans("ErrorFileNotUploaded"), 'errors');
            }
            else if(preg_match('/ErrorFileIsInfectedWithAVirus/', $res)) {  // Files infected by a virus
                setEventMessage($langs->trans("ErrorFileIsInfectedWithAVirus"), 'errors');
            }
            else {  // Known error
                setEventMessage($langs->trans($res), 'errors');
            }
        }
    }

    header('Location: '.$_SERVER['PHP_SELF'].'?fk_simu='.$simu->rowid.'&id='.$object->id);
    exit;
}


/*
 * View
 */

llxHeader('',$langs->trans('Simulation'),'');
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

$form = new Form($db);

if ($simu->id > 0) {
    $head = simulation_prepare_head($simu, $object);
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
    print $simu->reference.'&nbsp;'.get_picto($simu->accord);
    print '</td></tr>';

    // Entity
    print '<tr><td width="25%">'.$langs->trans('DemandReasonTypeSRC_PARTNER').'</td><td colspan="3">';
    print $TEntity[$simu->entity];
    print '</td></tr>';

    if(! empty($id)) {
        print '<tr>';
        print '<td>'.$langs->trans('ConformiteStatus').'</td>';
        print '<td>'.$langs->trans(Conformite::$TStatus[$object->status]).'</td>';
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

    $url = $_SERVER['PHP_SELF'].'?fk_simu='.$simu->rowid;
    if(! empty($id)) $url .= '&id='.$id;

    $perm = (empty($user->rights->financement->admin) || empty($conf->global->MAIN_UPLOAD_DOC));
    $param = '&fk_simu='.$simu->rowid;
    ?>
    <div class="titre"><?php echo $langs->trans('AttachANewFile'); ?></div>
    <form id="formuserfile" name="formuserfile" action="<?php echo $url; ?>" enctype="multipart/form-data" method="POST">
        <input type="hidden">
        <input type="file" class="flat" name="userfile[]" size="50" <?php echo ($perm ? 'disabled="disabled"' : ''); ?> multiple="multiple" />&nbsp;
        <input type="submit" class="button" name="upload"  value="<?php echo $langs->trans('Upload'); ?>" <?php echo ($perm ? 'disabled="disabled"' : ''); ?> />
    </form>
    <br />
    <?php

    // List of document
    $formfile->list_of_documents(
        $filearray,
        $object,
        'financement',
        $param,
        0,
        dol_sanitizeFileName($simu->reference).'/conformite/',
        $user->rights->financement->admin
    );
}
else {
    print $langs->trans("ErrorUnknown");
}

print '<div class="tabsAction">';

if(empty($id)) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&action=save">'.$langs->trans('Save').'</a>';
}
elseif($object->status === Conformite::STATUS_WAITING_FOR_COMPLIANCE) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=firstCheck">'.$langs->trans('ConformiteFirstCheck').'</a>';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=compliant">'.$langs->trans('ConformiteCompliant').'</a>';
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=notCompliant">'.$langs->trans('ConformiteNotCompliant').'</a>';
}
elseif(in_array($object->status, array(Conformite::STATUS_COMPLIANT, Conformite::STATUS_NOT_COMPLIANT, Conformite::STATUS_FIRST_CHECK))) {
    if($object->status === Conformite::STATUS_COMPLIANT) {
        print '<a class="butAction" href="#" title="Incoming...">'.$langs->trans('ConformiteCreateDossier').'</a>';
    }
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=wait">'.$langs->trans('ConformiteWaitingForCompliance').'</a>';
}

print '</div>';

llxFooter();
$db->close();
