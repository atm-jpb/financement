<?php

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/propal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/conformite.class.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');

$langs->load('dict');
$langs->load('other');
$langs->load('error');

$action = GETPOST('action', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOST('id', 'int');
$fk_simu = GETPOST('fk_simu', 'int');
$upload = GETPOST('sendit', '', 2);

// Security check
$socid='';
if (! empty($user->societe_id)) {
    $action = '';
    $socid = $user->societe_id;
}
$result = restrictedArea($user, 'financement', $fk_simu, 'fin_simulation&societe', 'conformite', 'fk_soc', 'rowid');

$dao = new DaoMulticompany($db);
$dao->getEntities();
foreach($dao->entities as $mc_entity) $TEntity[$mc_entity->id] = $mc_entity->label;

$form = new Form($db);
$formfile = new FormFile($db);
$formMail = new FormMail($db);
$u = new User($db);
$soc = new Societe($db);
$leaser = new Societe($db);
$PDOdb = new TPDOdb;
$object = new Conformite;
if(! empty($id)) {
    $object->fetch($id);
    if(empty($fk_simu)) $fk_simu = $object->fk_simulation;

    if(! empty($object->fk_user)) $u->fetch($object->fk_user);
}

$simu = new TSimulation;
$simu->load($PDOdb, $fk_simu, false);
if ($simu->rowid > 0) {
    $soc->fetch($simu->fk_soc);
    $leaser->fetch($simu->fk_leaser);

    $oldEntity = $conf->entity;
    switchEntity($simu->entity);

    $upload_dir = $conf->financement->dir_output.'/'.dol_sanitizeFileName($simu->reference).'/conformite';

    switchEntity($oldEntity);
}
else {
    // Pas de conformite sans fk_simu
    header('Location: list.php');
    exit;
}

if(empty($id) && ! empty($user->rights->financement->conformite->create)) {    // Dans le cas d'une création
    $object->fk_simulation = $fk_simu;
    $object->status = Conformite::STATUS_DRAFT;
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
    else setEventMessage($langs->trans('ConformiteCreationError'), 'errors');
}

/*
 * Actions
 */
if($action === 'save' && (! empty($user->rights->financement->conformite->create) || ! empty($user->rights->financement->conformite->validate))) {
    $commentaire = GETPOST('commentaire', 'alpha');
    $commentaire_adv = GETPOST('commentaire_adv', 'alpha');

    if(! empty($user->rights->financement->conformite->validate)) $object->commentaire = $commentaire;
    if(! empty($user->rights->financement->conformite->create)) $object->commentaire_adv = $commentaire_adv;
    $res = $object->update();

    if($res > 0) {
        setEventMessage($langs->trans('ConformiteUpdated'));
    }

    $url = $_SERVER['PHP_SELF'];
    $url.= '?fk_simu='.$fk_simu;
    $url.= '&id='.$id;
    header('Location: '.$url);
    exit;
}
elseif($action === 'confirm_setStatus' && ! empty($id) && $confirm === 'yes') {
    $statusLabel = GETPOST('status', 'alpha');
    switch($statusLabel) {
        case 'draft':
            if(! empty($user->rights->financement->conformite->create)) $status = Conformite::STATUS_DRAFT;
            break;
        case 'notCompliantN1':
            if(! empty($user->rights->financement->conformite->accept)) $status = Conformite::STATUS_NOT_COMPLIANT_N1;
            break;
        case 'notCompliantN2':
            if(! empty($user->rights->financement->conformite->accept)) $status = Conformite::STATUS_NOT_COMPLIANT_N2;
            break;
        case 'compliantN1':
            if(! empty($user->rights->financement->conformite->accept)) $status = Conformite::STATUS_COMPLIANT_N1;
            break;
        case 'compliantN2':
            if(! empty($user->rights->financement->conformite->accept)) $status = Conformite::STATUS_COMPLIANT_N2;
            break;
        case 'waitN1':
            if(! empty($user->rights->financement->conformite->validate)) $status = Conformite::STATUS_WAITING_FOR_COMPLIANCE_N1;
            $fk_user = $user->id;   // On save le user qui fait la demande
            break;
        case 'waitN2':
            if(! empty($user->rights->financement->conformite->validate)) $status = Conformite::STATUS_WAITING_FOR_COMPLIANCE_N2;
            break;
        case 'withoutFurtherAction':
            if(! empty($user->rights->financement->conformite->accept)) $status = Conformite::STATUS_WITHOUT_FURTHER_ACTION;
            break;
        default:
            break;
    }

    if(! is_null($status)) {
        if(! is_null($fk_user) && $object->status === Conformite::STATUS_DRAFT) $object->fk_user = $fk_user;
        $object->status = $status;
        $res = $object->update();

        if($res > 0 && in_array($object->status, array(Conformite::STATUS_COMPLIANT_N1, Conformite::STATUS_COMPLIANT_N2, Conformite::STATUS_NOT_COMPLIANT_N1, conformite::STATUS_NOT_COMPLIANT_N2))) {
            $resMail = $object->sendMail($simu->fk_soc);
            if($resMail) {
                $u = new User($db);
                $u->fetch($object->fk_user);

                setEventMessage('Email envoyé à : '.$u->email);
            }
        }
    }

    $url = $_SERVER['PHP_SELF'];
    $url.= '?fk_simu='.$fk_simu;
    $url.= '&id='.$id;
    header('Location: '.$url);
    exit;
}
elseif($action === 'confirm_createDossier' && !empty($user->rights->financement->alldossier->write) && $confirm === 'yes') {
    // Création de l'affaire
    $a = new TFin_affaire;
    $a->reference = '00000-00000';
    $a->entity = $simu->entity;
    $a->montant = $simu->montant_accord;
    $a->nature_financement = 'INTERNE';
    $a->type_financement = $simu->type_financement;
    $a->contrat = $simu->fk_type_contrat;
    $a->date_affaire = time();  // Date du jour
    $a->fk_soc = $simu->fk_soc;

    $a->save($PDOdb);

    // Pour éviter les doublons de référence
    $a->reference = '(PROV'.$a->rowid.')';
    $a->save($PDOdb);

    // Création du dossier
    $d = new TFin_dossier;

    $d->entity = $simu->entity;
    $d->nature_financement = 'INTERNE'; // Tous les dossiers créés ici sont INTERNES

    $d->financementLeaser->fk_soc = $simu->fk_leaser;
    $d->financementLeaser->reference = $simu->numero_accord;

    $d->financement->montant = $simu->montant_total_finance;
    $d->financement->echeance = $simu->echeance;
    $d->financement->terme = $simu->opt_terme;
    $d->financement->duree = $simu->duree;
    $d->financement->reglement = $simu->opt_mode_reglement;
    $d->financement->reste = TFin_financement::getVR($simu->fk_leaser);
    $d->financement->periodicite = $simu->opt_periodicite;

    $d->save($PDOdb);

    if($d->rowid > 0) {
        // This will add link between dossier and simulation
        $simu->fk_fin_dossier = $d->rowid;
        $simu->save($PDOdb);

        $d->addAffaire($PDOdb, $a->rowid);
        $d->save($PDOdb);

        setEventMessage($langs->trans('ConformiteDossierCreated', $d->rowid));
    }
    else setEventMessage($langs->trans('ConformiteDossierCreationError'), 'errors');

    $url = $_SERVER['PHP_SELF'];
    $url.= '?fk_simu='.$fk_simu;
    $url.= '&id='.$id;
    header('Location: '.$url);
    exit;
}
elseif(! empty($upload) && ! empty($conf->global->MAIN_UPLOAD_DOC) && ! empty($object->id) && ! empty($_FILES['userfile'])) {
    dol_add_file_process($upload_dir, 0, 1, 'userfile');

    header('Location: '.$_SERVER['PHP_SELF'].'?fk_simu='.$simu->rowid.'&id='.$object->id);
    exit;
}
elseif($action === 'confirm_deleteFile' && $confirm === 'yes' && ! empty($user->rights->financement->conformite->create)) {
    // TODO: Traitement à refactorer/virer avec une version plus récente de Dolibarr
    $urlfile = GETPOST('urlfile', 'alpha');

    if (GETPOST('section')) $file = $upload_dir.'/'.$urlfile;
    else {
        $urlfile=basename($urlfile);
        $file = $upload_dir . "/" . $urlfile;
    }
    $linkid = GETPOST('linkid', 'int');

    if ($urlfile) {
        $dir = dirname($file).'/'; // Chemin du dossier contenant l'image d'origine
        $dirthumb = $dir.'/thumbs/'; // Chemin du dossier contenant la vignette

        $ret = dol_delete_file($file, 0, 0, 0, $object);

        // Si elle existe, on efface la vignette
        if (preg_match('/(\.jpg|\.jpeg|\.bmp|\.gif|\.png|\.tiff)$/i',$file,$regs)) {
            $photo_vignette=basename(preg_replace('/'.$regs[0].'/i','',$file).'_small'.$regs[0]);
            if (file_exists(dol_osencode($dirthumb.$photo_vignette))) {
                dol_delete_file($dirthumb.$photo_vignette);
            }

            $photo_vignette=basename(preg_replace('/'.$regs[0].'/i','',$file).'_mini'.$regs[0]);
            if (file_exists(dol_osencode($dirthumb.$photo_vignette))) {
                dol_delete_file($dirthumb.$photo_vignette);
            }
        }

        if ($ret) {
            setEventMessage($langs->trans("FileWasRemoved", $urlfile));
        } else {
            setEventMessage($langs->trans("ErrorFailToDeleteFile", $urlfile), 'errors');
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

if($action === 'deleteFile' && ! empty($user->rights->financement->conformite->create)) {
    $urlfile = GETPOST('urlfile');

    $url = $_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$object->id;
    if(! empty($urlfile)) $url .= '&urlfile='.urlencode($urlfile);

    print $form->formconfirm($url, $langs->trans('DeleteFile'), $langs->trans('ConfirmDeleteFile'), 'confirm_deleteFile', '', '', 1);
}
elseif($action === 'createDossier' && $object->status === Conformite::STATUS_COMPLIANT_N1 && ! empty($user->rights->financement->alldossier->write)) {
    $url = $_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$object->id;

    print $form->formconfirm($url, $langs->trans('ConformiteCreateDossier'), $langs->trans('ConformiteConfirmCreateDossier'), 'confirm_createDossier', '', '', 1);
}
elseif($action === 'setStatus') {
    $statusLabel = GETPOST('status', 'alpha');
    if(in_array($statusLabel, array('waitN1', 'waitN2')) && ! empty($user->rights->financement->conformite->validate) ||
        in_array($statusLabel, array('compliantN1', 'compliantN2', 'notCompliantN1', 'notCompliantN2', 'withoutFurtherAction')) && ! empty($user->rights->financement->conformite->accept))
    {
        $url = $_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$object->id;
        if(! empty($statusLabel)) $url .= '&status='.$statusLabel;

        print $form->formconfirm($url, $langs->trans('ConformiteSetStatus'), $langs->trans('ConformiteConfirmSetStatus'), 'confirm_setStatus', '', '', 1);
    }
}

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
    print '<tr><td width="20%">'.$langs->trans('Ref').'</td><td>';
    print $simu->reference.'&nbsp;';
    if($simu->accord === 'OK') print get_picto('super_'.$simu->accord);
    else print get_picto($simu->accord);
    print '</td></tr>';

    // Entity
    print '<tr><td width="20%">'.$langs->trans('DemandReasonTypeSRC_PARTNER').'</td><td>';
    print $TEntity[$simu->entity];
    print '</td></tr>';

    // Status
    if(! empty($id)) {
        print '<tr>';
        print '<td>'.$langs->trans('ConformiteStatus').'</td>';
        print '<td>'.$langs->trans(Conformite::$TStatus[$object->status]).'</td>';
        print '</tr>';
    }

    // Customer
    print "<tr><td>".$langs->trans("Company")."</td>";
    print '<td>'.$soc->getNomUrl(1).'</td></tr>';

    // Leaser
    print '<tr><td>'.$langs->trans('Leaser').'</td>';
    print '<td>'.$leaser->getNomUrl(1).'</td></tr>';

    // Num accord leaser
    $shouldILink = ! empty($simu->fk_fin_dossier);
    print '<tr><td>'.$langs->trans('NumAccord').'</td>';
    print '<td>';
    if($shouldILink) print '<a href="'.dol_buildpath('/financement/dossier.php', 1).'?id='.$simu->fk_fin_dossier.'">';
    print $simu->numero_accord;
    if($shouldILink) print '</a>';
    print '</td></tr>';

    // User
    print '<tr><td>'.$langs->trans('User').'</td>';
    print '<td>';
    if(! empty($u->id)) print $u->getLoginUrl(1);
    print '</td></tr>';

    // Required files
    print '<tr>';
    print '<td>'.$langs->trans('RequiredFiles').'</td>';
    print '<td>'.$langs->trans('ListOfRequiredFiles').'</td>';
    print '</tr>';

    // Commentaire ADV
    print '<tr>';
    print '<td>'.$langs->trans('ConformiteCommentaireADV');
    if(! empty($user->rights->financement->conformite->create)) print '&nbsp;<a href="'.$_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$id.'&action=editCommentaireADV">'.img_edit().'</a></td>';
    if(empty($action) || empty($user->rights->financement->conformite->create)) print '<td>'.str_replace("\n", "<br/>\n", $object->commentaire_adv).'</td>';
    elseif($action === 'editCommentaireADV') {
        print '<td>';
        print '<form action="'.$_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$id.'" method="POST">';
        print '<input type="hidden" name="action" value="save" />';
        print '<div style="display: flex; align-items: center;">';
        print '<textarea name="commentaire_adv" rows="5" cols="60">'.$object->commentaire_adv.'</textarea>';
        print '&nbsp;<input class="butAction" type="submit" value="'.$langs->trans('Save').'" />';
        print '</div></form>';
        print '</td>';
    }
    print '</tr>';

    // Commentaire
    print '<tr>';
    print '<td>'.$langs->trans('ConformiteCommentaire');
    if(! empty($user->rights->financement->conformite->validate)) print '&nbsp;<a href="'.$_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$id.'&action=editCommentaire">'.img_edit().'</a></td>';
    if(empty($action) || empty($user->rights->financement->conformite->validate)) print '<td>'.str_replace("\n", "<br/>\n", $object->commentaire).'</td>';
    elseif($action === 'editCommentaire') {
        print '<td>';
        print '<form action="'.$_SERVER['PHP_SELF'].'?fk_simu='.$fk_simu.'&id='.$id.'" method="POST">';
        print '<input type="hidden" name="action" value="save" />';
        print '<div style="display: flex; align-items: center;">';
        print '<textarea name="commentaire" rows="5" cols="60">'.$object->commentaire.'</textarea>';
        print '&nbsp;<input class="butAction" type="submit" value="'.$langs->trans('Save').'" />';
        print '</div></form>';
        print '</td>';
    }
    print '</tr>';

    print '</table>';

    print '</div>';

    $url = $_SERVER['PHP_SELF'].'?fk_simu='.$simu->rowid;
    if(! empty($id)) $url .= '&id='.$id;

    $perm = (empty($user->rights->financement->conformite->create) && empty($conf->global->MAIN_UPLOAD_DOC));
    $formfile->form_attach_new_file($url, '', 0, 0, $perm, 50, '', '', 1, '', 0);

    $filepath = dol_sanitizeFileName($simu->reference).'/conformite/';
    $formfile->list_of_documents($filearray, $object, 'financement', '', 0, $filepath);
}
else {
    print $langs->trans("ErrorUnknown");
}

print '<div class="tabsAction">';

if(in_array($object->status, array(Conformite::STATUS_DRAFT, Conformite::STATUS_NOT_COMPLIANT_N1)) && ! empty($user->rights->financement->conformite->validate)) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=confirm_setStatus&status=waitN1&confirm=yes">'.$langs->trans('ConformiteWaitingForComplianceN1Button').'</a>';
    if($object->status === Conformite::STATUS_NOT_COMPLIANT_N1) {
        print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=withoutFurtherAction">'.$langs->trans('ConformiteWithoutFurtherAction').'</a>';
    }
}
elseif($object->status === Conformite::STATUS_WAITING_FOR_COMPLIANCE_N1 && ! empty($user->rights->financement->conformite->accept)) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=compliantN1">'.$langs->trans('ConformiteCompliantN1Button').'</a>';
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=notCompliantN1">'.$langs->trans('ConformiteNotCompliantN1').'</a>';
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=withoutFurtherAction">'.$langs->trans('ConformiteWithoutFurtherAction').'</a>';
}
elseif(in_array($object->status, array(Conformite::STATUS_COMPLIANT_N1, Conformite::STATUS_NOT_COMPLIANT_N2)) && ! empty($user->rights->financement->conformite->validate)) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=confirm_setStatus&status=waitN2&confirm=yes">'.$langs->trans('ConformiteWaitingForComplianceN2Button').'</a>';
    if($object->status === Conformite::STATUS_COMPLIANT_N1 && empty($simu->fk_fin_dossier) && ! empty($user->rights->financement->alldossier->write)) {
        print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=createDossier">'.$langs->trans('ConformiteCreateDossier').'</a>';
    }
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=withoutFurtherAction">'.$langs->trans('ConformiteWithoutFurtherAction').'</a>';
}
elseif($object->status === Conformite::STATUS_WAITING_FOR_COMPLIANCE_N2 && ! empty($user->rights->financement->conformite->accept)) {
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=compliantN2">'.$langs->trans('ConformiteCompliantN2').'</a>';
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=notCompliantN2">'.$langs->trans('ConformiteNotCompliantN2').'</a>';
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=withoutFurtherAction">'.$langs->trans('ConformiteWithoutFurtherAction').'</a>';
}
elseif($object->status === Conformite::STATUS_COMPLIANT_N2 && ! empty($user->rights->financement->conformite->accept)) {
    print '<a class="butActionDelete" href="'.$_SERVER['PHP_SELF'].'?id='.$id.'&fk_simu='.$fk_simu.'&action=setStatus&status=withoutFurtherAction">'.$langs->trans('ConformiteWithoutFurtherAction').'</a>';
}

print '</div>';

llxFooter();
$db->close();