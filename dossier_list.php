<?php
set_time_limit(0);
require('config.php');

if(GETPOST('DEBUG')) {
    ini_set("display_errors", 1);
    error_reporting(E_ALL);
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.php');
dol_include_once('/financement/class/dossier_transfert_xml.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/assetatm/class/asset.class.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');

$langs->load('accountancy');
$langs->load('dict');
$langs->load('financement@financement');

if(! $user->rights->financement->affaire->read) {
    accessforbidden();
}

$search_ref_client = GETPOST('search_ref_client');
$search_ref_leaser = GETPOST('search_ref_leaser');
$search_entity = GETPOST('search_entity');
if(! empty($search_entity) && ! is_array($search_entity)) $search_entity = explode(',', $search_entity);
$search_nature = GETPOST('search_nature');
$search_siren = GETPOST('search_siren');
$search_siret = GETPOST('search_siret');
$search_thirdparty = GETPOST('search_thirdparty');
$search_leaser = GETPOST('search_leaser');
$search_transfert = GETPOST('search_transfert');
if(isset($search_transfert) && $search_transfert !== '' && ! is_array($search_transfert)) $search_transfert = explode(',', $search_transfert);
$search_dateEnvoi = dol_mktime(0, 0, 0, GETPOST('search_dateEnvoimonth'), GETPOST('search_dateEnvoiday'), GETPOST('search_dateEnvoiyear'));
$search_dateStart = dol_mktime(0, 0, 0, GETPOST('search_dateStartmonth'), GETPOST('search_dateStartday'), GETPOST('search_dateStartyear'));
$reloc = GETPOST('reloc', 'int');
$reloc_customer_ok = GETPOST('reloc_customer_ok');
$reloc_leaser_ok = GETPOST('reloc_leaser_ok');
$loyer_leaser_ok = GETPOST('loyer_leaser_ok');
$search_fac_materiel = GETPOST('search_fac_materiel');
$sall = GETPOST('sall');
$search_dossier = GETPOST('searchdossier');
if(! empty($sall) && empty($search_dossier)) $search_dossier = $sall;
$search_demat = GETPOST('search_demat', 'int');

$toselect = GETPOST('toselect', 'array');
$arrayofselected = is_array($toselect) ? $toselect : array();
$massaction = GETPOST('massaction','alpha');
$fk_leaser = GETPOST('fk_leaser', 'int');
$sortfield = GETPOST('sortfield');
$sortorder = GETPOST('sortorder');
$page = GETPOST('page', 'int');
$limit = GETPOST('limit', 'int');
if(empty($limit)) $limit = $conf->liste_limit;
if(empty($sortfield)) $sortfield = 'd.rowid';
if(empty($sortorder)) $sortorder = 'DESC';
if(empty($page) || $page == -1) $page = 0;
$offset = $limit * $page;
$isListeIncomplet = array_key_exists('liste_incomplet', $_GET);

$dossier = new TFin_Dossier;
$affaire = new TFin_affaire;
$PDOdb = new TPDOdb;
$tbs = new TTemplateTBS;
$form = new Form($db);

$strEntityShared = getEntity('fin_dossier', true);
$TEntityShared = explode(',', $strEntityShared);

$TEntity = TFinancementTools::build_array_entities($TEntityShared);

if(GETPOST('envoiXML')) {
    setEventMessage('La génération et l\'envoi du fichier XML s\'est effectué avec succès');
}

// TODO: Remettre la liste des dossiers incomplets
//if(isset($isListeIncomplet)) _liste_dossiers_incomplets($PDOdb, $dossier);

/*
 * Action
 */

// On fait rien si on ne sélectionne pas de dossiers...
if(! empty($arrayofselected) && ! empty($fk_leaser)) {
    $param = GETPOST('param');
    $strToSelect = '';
    foreach($arrayofselected as $select) $strToSelect .= '&toselect[]='.$select;
    $param .= $strToSelect;

    if($massaction == 'generateXML') {
        $dt = TFinDossierTransfertXML::create($fk_leaser);
        $filePath = $dt->transfertXML($PDOdb, $arrayofselected);

        header("Location: ".dol_buildpath("/document.php?modulepart=financement&entity=".$conf->entity."&file=".$filePath, 2));
        exit;
    }
    else if($massaction == 'generateXMLandupload') {
        $dt = TFinDossierTransfertXML::create($fk_leaser, true);
        $filePath = $dt->transfertXML($PDOdb, $arrayofselected);

        header('Location: '.$_SERVER['PHP_SELF'].'?fk_leaser='.$fk_leaser.$param.'&envoiXML=ok');
        exit;
    }
    elseif(in_array($massaction, array('setReady', 'setSent', 'setYes', 'setnottransfer'))) {
        $statusToSet = substr($massaction, 3);  // 'READY', 'SENT', 'YES' or 'nottransfer'
        if($statusToSet == 'Ready') $const = TFin_financement::STATUS_TRANSFER_READY;
        else if($statusToSet == 'Sent') $const = TFin_financement::STATUS_TRANSFER_SENT;
        else if($statusToSet == 'Yes') $const = TFin_financement::STATUS_TRANSFER_YES;
        else if($statusToSet == 'nottransfer') $const = TFin_financement::STATUS_TRANSFER_NO;

        foreach($arrayofselected as $fk_affaire) {
            $a = new TFin_affaire;
            $a->load($PDOdb, $fk_affaire, false);
            $a->loadDossier($PDOdb);

            if(! empty($a->TLien[0]->dossier->rowid)) {
                $d = $a->TLien[0]->dossier;
                $d->financementLeaser->transfert = $const;

                // On renseigne une date d'envoi quand on passe des dossiers en 'Envoyé'
                if($massaction == 'setSent') $d->financementLeaser->date_envoi = dol_now();

                $d->save($PDOdb);
            }
        }

        header('Location: '.$_SERVER['PHP_SELF'].'?fk_leaser='.$fk_leaser.$param);
        exit;
    }
}

// Remove filters
if(GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha')) {
    unset($search_ref_client, $search_ref_leaser, $search_entity, $search_nature, $search_thirdparty, $search_leaser, $reloc_customer_ok, $reloc_leaser_ok, $loyer_leaser_ok, $search_transfert, $search_dateEnvoi);
    unset($search_dateStart, $search_fac_materiel, $search_siren, $search_siret, $sall, $search_demat, $reloc);
}

$sql = "SELECT d.rowid as fk_fin_dossier, e.label as entity_label, fc.reference as refDosCli, fl.fk_soc as fk_leaser, fl.reference as refDosLea, a.rowid as fk_fin_affaire, a.reference as ref_affaire, ";
$sql .= "a.nature_financement, a.fk_soc, c.nom as nomCli, l.nom as nomLea, c.siren as sirenCli, c.siret as siretCli, fl.date_debut as date_debut_leaser, fl.reste as vr, fl.terme, fl.transfert, ";
$sql .= "(CASE WHEN a.nature_financement = 'INTERNE' THEN fc.duree ELSE fl.duree END) as 'duree', ";
$sql .= "(CASE WHEN a.nature_financement = 'INTERNE' THEN fc.montant ELSE fl.montant END) as 'Montant', ";
$sql .= "(CASE WHEN a.nature_financement = 'INTERNE' THEN fc.echeance ELSE fl.echeance END) as 'echeance', ";
$sql .= "COALESCE(fc.relocOK, 'OUI') as relocClientOK, ";
$sql .= "COALESCE(fl.relocOK, 'OUI') as relocLeaserOK, ";
$sql .= "COALESCE(fl.intercalaireOK, 'OUI') as intercalaireLeaserOK, ";
$sql .= "fl.duree as 'dureeLeaser', ";
$sql .= "fl.montant as 'montantLeaser', ";
$sql .= "fl.echeance as 'echeanceLeaser', ";
$sql .= "(CASE WHEN fl.date_solde < '1970-01-01' OR fl.date_solde IS NULL THEN 'En cours' ELSE 'Soldé' END) as 'statut', ";
$sql .= "(CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_prochaine_echeance ELSE fl.date_prochaine_echeance END) as 'prochaine', ";
$sql .= "(CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_debut ELSE fl.date_debut END) as 'date_start', ";
$sql .= "(CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_fin ELSE fl.date_fin END) as 'date_end', ";
$sql .= "GROUP_CONCAT(f.rowid, '-', f.ref) as TInvoiceData, fl.date_envoi, d.commentaire_conformite, d.demat";
$sql .= ' FROM '.MAIN_DB_PREFIX.'fin_dossier d';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (d.rowid=da.fk_fin_dossier)';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (da.fk_fin_affaire=a.rowid)';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement fc ON (d.rowid=fc.fk_fin_dossier AND fc.type='CLIENT')";
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."fin_dossier_financement fl ON (d.rowid=fl.fk_fin_dossier AND fl.type='LEASER')";
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe c ON (a.fk_soc=c.rowid)';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe l ON (fl.fk_soc=l.rowid)';
$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'entity e ON (e.rowid = d.entity)';
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."element_element ee ON (ee.fk_source = a.rowid AND ee.sourcetype = 'affaire' AND ee.targettype = 'facture')";
$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'facture f ON (f.rowid = ee.fk_target)';
$sql .= ' WHERE 1=1';

if(isset($fk_leaser) && ! empty($fk_leaser)) {
    $sql .= " AND a.entity = ".$conf->entity;

    // Filtrage sur leaser et uniquement dossier avec "Bon pour transfert" = 1 (Oui)
    $sql .= " AND l.rowid = ".$fk_leaser." AND a.type_financement = 'MANDATEE'";
}

if(! empty($search_dossier)) {
    $sql .= " AND (fc.reference LIKE '%".$db->escape($search_dossier)."%' OR fl.reference LIKE '%".$db->escape($search_dossier)."%')";
}

if($reloc == 1) {
    $sql .= " AND (fc.reloc = 'OUI' OR fl.reloc = 'OUI')";
}

if(! empty($search_ref_client)) $sql .= natural_search('fc.reference', $search_ref_client);
if(! empty($search_ref_leaser)) $sql .= natural_search('fl.reference', $search_ref_leaser);
if(! empty($search_nature) && $search_nature != -1) $sql .= natural_search('a.nature_financement', $search_nature);
if(! empty($search_siren)) $sql .= natural_search('c.siren', $search_siren);
if(! empty($search_siret)) $sql .= natural_search('c.siret', $search_siret);
if(! empty($search_thirdparty)) $sql .= natural_search('c.nom', $search_thirdparty);
if(! empty($search_leaser)) $sql .= natural_search('l.nom', $search_leaser);
if(! empty($search_dateEnvoi)) $sql .= " AND DATE_FORMAT(fl.date_envoi, '%Y-%m-%d') = '".date('Y-m-d', $search_dateEnvoi)."'";
if(! empty($search_dateStart)) $sql .= " AND DATE_FORMAT(fl.date_debut, '%Y-%m-%d') = '".date('Y-m-d', $search_dateStart)."'";
if(! empty($search_entity)) {
    $TSearchEntity = array_intersect($TEntityShared, $search_entity);
    $sql .= ' AND d.entity IN ('.implode(',', $TSearchEntity).')';
}
else {
    $sql .= ' AND d.entity IN ('.$strEntityShared.')';
}
if(! empty($reloc_customer_ok) && $reloc_customer_ok != -1) $sql .= " AND fc.relocOK = '".$db->escape($reloc_customer_ok)."'";
if(! empty($reloc_leaser_ok) && $reloc_leaser_ok != -1) $sql .= " AND fl.relocOK = '".$db->escape($reloc_leaser_ok)."'";
if(! empty($loyer_leaser_ok) && $loyer_leaser_ok != -1) $sql .= " AND fl.intercalaireOK = '".$db->escape($loyer_leaser_ok)."'";
if(! empty($search_transfert)) $sql .= ' AND fl.transfert IN ('.implode(',', $search_transfert).')';
if(! empty($search_fac_materiel)) $sql .= natural_search('f.ref', $search_fac_materiel);
if($search_demat != '' && $search_demat != -1) $sql .= ' AND d.demat = '.$db->escape($search_demat);

$sql .= ' GROUP BY d.rowid, fc.reference, fl.fk_soc, fl.reference, a.rowid, fc.relocOK, fl.relocOK, fl.intercalaireOK, fc.duree, fl.duree, fc.montant, fl.montant, fc.echeance, fl.echeance';
$sql .= ', fc.date_prochaine_echeance, fl.date_prochaine_echeance, fc.date_debut, fl.date_debut, fc.date_fin, fl.date_fin, fl.date_debut, fl.reste, fl.terme, fl.transfert, fl.date_envoi, fl.date_solde';
$sql .= ', d.commentaire_conformite';

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

// S'il n'y a qu'un seul dossier après la recherche, on va directement sur la fiche
if(empty($fk_leaser) && $num === 1) {
    $obj = $db->fetch_object($resql);

    $url = dol_buildpath('/financement/dossier.php', 1);
    $url .= '?id='.$obj->fk_fin_dossier;
    header('Location: '.$url);
    exit;
}

llxHeader('', 'Dossiers');
print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

// Affichage de l'en-tête société si on a un fk_leaser
if(isset($fk_leaser) && ! empty($fk_leaser)) {
    $societe = new Societe($db);
    $societe->fetch($fk_leaser);
    $head = societe_prepare_head($societe);

    print dol_get_fiche_head($head, 'transfert', $langs->trans("ThirdParty"), -1, 'company');
    dol_banner_tab($societe, 'socid', '', 0);
}

$param = '';
if($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.$limit;
if(! empty($search_ref_client)) $param .= '&search_ref_client='.urlencode($search_ref_client);
if(empty($fk_leaser) && ! empty($search_entity)) $param .= '&search_entity='.urlencode(implode(',', $search_entity));
if(! empty($search_ref_leaser)) $param .= '&search_ref_leaser='.urlencode($search_ref_leaser);
if(! empty($search_nature)) $param .= '&search_nature='.urlencode($search_nature);
if(! empty($search_siren)) $param .= '&search_siren='.urlencode($search_siren);
if(! empty($search_siret)) $param .= '&search_siret='.urlencode($search_siret);
if(! empty($search_thirdparty)) $param .= '&search_thirdparty='.urlencode($search_thirdparty);
if(! empty($search_leaser)) $param .= '&search_leaser='.urlencode($search_leaser);
if(! empty($search_transfert)) $param .= '&search_transfert='.urlencode(implode(',', $search_transfert));
if(! empty($reloc) && $reloc != -1) $param .= '&reloc='.urlencode($reloc);
if(! empty($reloc_customer_ok)) $param .= '&reloc_customer_ok='.urlencode($reloc_customer_ok);
if(! empty($reloc_leaser_ok)) $param .= '&reloc_leaser_ok='.urlencode($reloc_leaser_ok);
if(! empty($loyer_leaser_ok)) $param .= '&loyer_leaser_ok='.urlencode($loyer_leaser_ok);
if(! empty($search_dateEnvoi)) {
    $param .= '&search_dateEnvoiday='.urlencode(GETPOST('search_dateEnvoiday'));
    $param .= '&search_dateEnvoimonth='.urlencode(GETPOST('search_dateEnvoimonth'));
    $param .= '&search_dateEnvoiyear='.urlencode(GETPOST('search_dateEnvoiyear'));
}
if(! empty($search_dateStart)) {
    $param .= '&search_dateStartday='.urlencode(GETPOST('search_dateStartday'));
    $param .= '&search_dateStartmonth='.urlencode(GETPOST('search_dateStartmonth'));
    $param .= '&search_dateStartyear='.urlencode(GETPOST('search_dateStartyear'));
}
if(! empty($fk_leaser)) $param .= '&fk_leaser='.urlencode($fk_leaser);
if(! empty($search_fac_materiel)) $param .= '&search_fac_materiel='.urlencode($search_fac_materiel);
if($search_demat != '' && $search_demat != -1) $param .= '&search_demat='.urlencode($search_demat);

// List of mass actions available
$arrayofmassactions =  array(
    'generateXML'=>$langs->trans("DownloadXML"),
    'generateXMLandupload'=>$langs->trans("SendXML"),
    'setnottransfer'=>$langs->trans("SetNoTransferable"),
    'setYes'=>$langs->trans("setYes"),
    'setReady'=>$langs->trans("setReady"),
    'setSent'=>$langs->trans("setSent")
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

// ----------------------------------------------------------------------

// Entête
if(! empty($fk_leaser)) {
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border tableforfield" width="100%">';

    // Customer code
    if($societe->client) {
        print '<tr><td class="titlefield">';
        print $langs->trans('CustomerCode').'</td><td>';
        print $societe->code_client;
        if($societe->check_codeclient() <> 0) print ' <font class="error">('.$langs->trans("WrongCustomerCode").')</font>';
        print '</td>';
        print '</tr>';
    }

    // Supplier code
    if(! empty($conf->fournisseur->enabled) && $societe->fournisseur && ! empty($user->rights->fournisseur->lire)) {
        print '<tr><td class="titlefield">';
        print $langs->trans('SupplierCode').'</td><td>';
        print $societe->code_fournisseur;
        if($societe->check_codefournisseur() <> 0) print ' <font class="error">('.$langs->trans("WrongSupplierCode").')</font>';
        print '</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="formfilter">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'" />';
print '<input type="hidden" id="formfilteraction" name="formfilteraction" value="list" />';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'" />';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'" />';
print '<input type="hidden" name="page" value="'.$page.'" />';
if(! empty($fk_leaser)) print '<input type="hidden" name="fk_leaser" value="'.$fk_leaser.'" />';
print '<input type="hidden" name="param" value="'.$param.'" />';

$title = 'Dossiers';
print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_generic.png', 0, '', '', $limit);

print '<div class="div-table-responsive">';

// Filters
if(empty($fk_leaser)) {
    print '<table class="tagtable liste">';
    print '<tr class="liste_titre">';

    // Reloc ?
    print '<td style="width: 200px;">';
    print $langs->trans('Reloc').'&nbsp;';
    print Form::selectarray('reloc', array('Non', 'Oui'), $reloc, 1, 0, 0, 'style="width: 75px;"');
    print '</td>';

    // Reloc client ok ?
    print '<td style="width: 280px;">';
    print $langs->trans('RelocCustomerOK').'&nbsp;';
    print Form::selectarray('reloc_customer_ok', $dossier->financement->TRelocOK, $reloc_customer_ok, 1, 0, 0, 'style="width: 75px;"');
    print '</td>';

    // Reloc leaser ok ?
    print '<td style="width: 280px;">';
    print $langs->trans('RelocLeaserOK').'&nbsp;';
    print Form::selectarray('reloc_leaser_ok', $dossier->financementLeaser->TRelocOK, $reloc_leaser_ok, 1, 0, 0, 'style="width: 75px;"');
    print '</td>';

    // Loyer intercalaire leaser ok ?
    print '<td>';
    print $langs->trans('LoyerLeaserOK').'&nbsp;';
    print Form::selectarray('loyer_leaser_ok', $dossier->financementLeaser->TIntercalaireOK, $loyer_leaser_ok, 1, 0, 0, 'style="width: 75px;"');
    print '</td>';

    print '</tr>';

    print '<tr class="liste_titre">';

    // Entity
    print '<td colspan="4" style="min-width: 150px;">';
    print '<span>'.$langs->trans('DemandReasonTypeSRC_PARTNER').' : </span>';
    print Form::multiselectarray('search_entity', $TEntity, $search_entity, 0, 0, '', 0, 1500);
    print '</td>';

    print '</tr>';
    print '</table>';
}
print '<table class="tagtable liste">';
if(! empty($fk_leaser)) {
    // Show entity
    print '<tr class="liste_titre">';
    print '<td colspan="17">';
    print $langs->trans('DemandReasonTypeSRC_PARTNER').' : '.$TEntity[$conf->entity];
    print '</td>';
    print '</tr>';
}
print '<tr class="liste_titre">';

if(empty($fk_leaser)) {
    // Ref financement client
    print '<td>';
    print '<input type="text" name="search_ref_client" value="'.$search_ref_client.'" size="10" />';
    print '</td>';

    // Entity
    print '<td>&nbsp;</td>';
}

// Ref financement leaser
print '<td>';
print '<input type="text" name="search_ref_leaser" value="'.$search_ref_leaser.'" size="10" />';
print '</td>';

if(empty($fk_leaser)) {
    // Ref affaire
    print '<td>&nbsp;</td>';

    // Nature
    print '<td>';
    print Form::selectarray('search_nature', $affaire->TNatureFinancement, $search_nature, 1, 0, 0, 'style="width: 85px;"');
    print '</td>';
}
else {
    $TDemat = array('Non', 'Oui');

    print '<td>';
    print Form::selectarray('search_demat', $TDemat, $search_demat, 1);
    print '</td>';

    // Siren Client
    print '<td style="width: 90px;">';
    print '<input type="text" name="search_siren" value="'.$search_siren.'" size="8" />';
    print '</td>';

    // Siret Client
    print '<td style="width: 90px;">';
    print '<input type="text" name="search_siret" value="'.$search_siret.'" size="8" />';
    print '</td>';
}

// Thirdparty
print '<td>';
print '<input type="text" name="search_thirdparty" value="'.$search_thirdparty.'" size="20" />';
print '</td>';

if(empty($fk_leaser)) {
    // Leaser
    print '<td>';
    print '<input type="text" name="search_leaser" value="'.$search_leaser.'" size="20" />';
    print '</td>';
}

print '<td colspan="5">&nbsp;</td>';
if(empty($fk_leaser)) print '<td>&nbsp;</td>';
else {
    // Date début
    print '<td>';
    print $form->select_date($search_dateStart, 'search_dateStart', 0, 0, 1, '', 1, 0, 1);
    print '</td>';
}
print '<td colspan="2">&nbsp;</td>';
if(empty($fk_leaser)) print '<td>&nbsp;</td>';
else {
    // Bon pour transfert ?
    print '<td>';
    print Form::multiselectarray('search_transfert', $dossier->financementLeaser->TTransfert, $search_transfert, 0, 0, '', 0, 90);
    print '</td>';

    // Date envoi
    print '<td>';
    print $form->select_date($search_dateEnvoi, 'search_dateEnvoi', 0, 0, 1, '', 1, 0, 1);
    print '</td>';
}

// Facture matériel && boutons filtres
print '<td>';
print '<input type="text" name="search_fac_materiel" value="'.$search_fac_materiel.'" size="10" />';
print '</td>';
print '<td colspan="2">';
print '<input type="image" class="liste_titre" name="button_search" src="'.img_picto($langs->trans('Search'), 'search', '', false, 1).'" value="'.$langs->trans('Search').'" />';
print '<input type="image" class="liste_titre" name="button_removefilter" src="'.img_picto($langs->trans('RemoveFilter'), 'searchclear', '', false, 1).'" value="'.$langs->trans('RemoveFilter').'" />';
print '</td>';

print '</tr>';
// Titles
print '<tr class="liste_titre">';
if(empty($fk_leaser)) {
    print_liste_field_titre('Contrat', $_SERVER['PHP_SELF'], 'fc.reference', '', $param, 'style="text-align: center; min-width: 110px;"', $sortfield, $sortorder);   // Ref financement client
    print_liste_field_titre('Partenaire', $_SERVER['PHP_SELF'], 'd.entity', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Entity
}
print_liste_field_titre('Contrat<br/>Leaser', $_SERVER['PHP_SELF'], 'fl.reference', '', $param, 'style="text-align: center; width: 90px;"', $sortfield, $sortorder);   // Ref financement leaser
if(empty($fk_leaser)) {
    print_liste_field_titre('Affaire', $_SERVER['PHP_SELF'], 'a.reference', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Ref affaire
    print_liste_field_titre('Nature', $_SERVER['PHP_SELF'], 'a.nature_financement', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Nature financement
}
else {
    print_liste_field_titre('Démat', $_SERVER['PHP_SELF'], 'd.demat', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Contrat démat
    print_liste_field_titre('Siren<br/>Client', $_SERVER['PHP_SELF'], 'c.siren', '', $param, 'style="text-align: center; width: 100px;"', $sortfield, $sortorder);   // Siren Client
    print_liste_field_titre('Siret<br/>Client', $_SERVER['PHP_SELF'], 'c.siret', '', $param, 'style="text-align: center; width: 100px;"', $sortfield, $sortorder);   // Siret Client
}
print_liste_field_titre('Client', $_SERVER['PHP_SELF'], 'a.fk_soc', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Thirdparty
if(empty($fk_leaser)) {
    print_liste_field_titre('Leaser', $_SERVER['PHP_SELF'], 'fl.fk_soc', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Leaser
    print_liste_field_titre('Durée', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Durée
    print_liste_field_titre('Montant', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Montant
    print_liste_field_titre('Echéance', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Echéance
}
else {
    print_liste_field_titre('Matériel', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Matériel
    print_liste_field_titre('N° série', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // N° de série
}
print_liste_field_titre('Durée<br/>Leaser', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Durée Leaser
print_liste_field_titre('Montant<br/>Leaser', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Montant Leaser
print_liste_field_titre('Echéance<br/>Leaser', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Echéance Leaser
if(empty($fk_leaser)) {
    print_liste_field_titre('Prochaine', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Prochaine echéance
    print_liste_field_titre('Début', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Date start
    print_liste_field_titre('Fin', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center;"');   // Date end
}
else {
    print_liste_field_titre('Début', $_SERVER['PHP_SELF'], 'fl.date_debut', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Date start leaser
    print_liste_field_titre('Terme', $_SERVER['PHP_SELF'], 'fl.terme', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Terme
    print_liste_field_titre('VR', $_SERVER['PHP_SELF'], 'fl.reste', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // VR
    print_liste_field_titre('Transfert', $_SERVER['PHP_SELF'], 'fl.transfert', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Transfert
    print_liste_field_titre('Date Envoi', $_SERVER['PHP_SELF'], 'fl.date_envoi', '', $param, 'style="text-align: center;"', $sortfield, $sortorder);   // Date envoi
}
print_liste_field_titre('Facture<br/>matériel', $_SERVER['PHP_SELF'], '', '', $param, 'style="text-align: center; min-width: 100px;"');
if(empty($fk_leaser)) print_liste_field_titre('Statut', $_SERVER['PHP_SELF'], 'statut', '', $param, 'style="text-align: center;"');
print '<td>';
if(! empty($fk_leaser)) {
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
}
else print '&nbsp;';
print '</td>';
print '</tr>';

// Print data
for($i = 0 ; $i < min($num, $limit) ; $i++) {
    $obj = $db->fetch_object($resql);

    if($i % 2 == 0) $class = 'pair';
    else $class = 'impair';

    $socStatic = new Societe($db);
    $socStatic->id = $obj->fk_soc;
    $socStatic->name = $obj->nomCli;
    $socStatic->siren = $obj->sirenCli;
    $socStatic->siret = $obj->siretCli;

    if(! empty($obj->fk_leaser)) {
        $leaserStatic = new Societe($db);
        $leaserStatic->id = $obj->fk_leaser;
        $leaserStatic->name = $obj->nomLea;
    }

    if(! empty($fk_leaser)) {
        $affaire->load($PDOdb, $obj->fk_fin_affaire, false);
        $affaire->loadEquipement($PDOdb);

        unset($asset, $p); // This will prevent using same values on multiple lines
        if(! empty($affaire->TAsset[0])) {
            $asset = $affaire->TAsset[0]->asset;
            $p = new Product($db);
            if(! empty($asset->fk_product)) $p->fetch($asset->fk_product);
        }
    }

    print '<tr class="'.$class.'">';

    if(empty($fk_leaser)) {
        // Ref financement client
        print '<td align="center">';
        print '<a href="dossier.php?id='.$obj->fk_fin_dossier.'">'.(! empty($obj->refDosCli) ? $obj->refDosCli : '(vide)').'</a>';
        print '</td>';

        // Entity
        print '<td>'.$obj->entity_label.'</td>';
    }

    // Ref financement leaser
    print '<td align="center" style="white-space: nowrap">';
    print '<a href="dossier.php?id='.$obj->fk_fin_dossier.'">'.(! empty($obj->refDosLea) ? $obj->refDosLea : '(vide)');
    if(! empty($fk_leaser) && ! empty($obj->commentaire_conformite)) {
        print '&nbsp;';
        print img_picto_common($obj->commentaire_conformite, 'mime/other.png');
    }
    print '</a>';
    print ' ('.howManyAffaire($obj->fk_fin_dossier, $obj->fk_leaser).')';
    print '</td>';

    if(empty($fk_leaser)) {
        // Ref affaire
        print '<td align="center">';
        print '<a href="'.dol_buildpath('financement/affaire.php', 1).'?id='.$obj->fk_fin_affaire.'">'.$obj->ref_affaire.'</a>';
        print '</td>';

        // Nature
        print '<td align="center">'.$affaire->TNatureFinancement[$obj->nature_financement].'</td>';
    }
    else {
        // Contrat démat
        $checked = '';
        if(! empty($obj->demat)) $checked = 'checked="checked"';

        print '<td align="center">';
        print '<input type="checkbox" name="demat" disabled="disabled" '.$checked.'/>';
        print '</td>';

        // Siren Client
        print '<td align="center">';
        print $socStatic->siren;
        print '</td>';

        // Siret Client
        print '<td align="center">';
        print $socStatic->siret;
        print '</td>';
    }

    // Client
    print '<td>';
    if(! empty($socStatic->id)) {
        print $form->textwithtooltip($socStatic->getNomUrl(1, '', 18), $socStatic->name);
    }
    else print '&nbsp;';
    print '</td>';

    if(empty($fk_leaser)) {
        // Leaser
        print '<td>';
        if(! empty($leaserStatic->id)) {
            print $form->textwithtooltip($leaserStatic->getNomUrl(1, '', 18), $leaserStatic->name);
        }
        else print '&nbsp;';
        print '</td>';
    }

    if(empty($fk_leaser)) {
        // Durée
        print '<td align="right">'.$obj->duree.'</td>';

        // Montant
        print '<td align="right" style="white-space: nowrap;">'.price($obj->montant).'</td>';

        // Echéance
        print '<td align="right" style="white-space: nowrap;">'.price($obj->echeance).'</td>';
    }
    else {
        // Matériel
        print '<td align="center" style="width: 80px;">';
        if(! empty($p->id)) print $form->textwithtooltip(substr($p->label, 0, 7).'...', $p->label);
        else print '&nbsp';
        print '</td>';

        // N° de série
        print '<td align="center">'.$asset->serial_number.'</td>';
    }

    // Durée Leaser
    print '<td align="right">'.$obj->dureeLeaser.'</td>';

    // Montant Leaser
    print '<td align="right" style="white-space: nowrap;">'.price($obj->montantLeaser).'</td>';

    // Echéance Leaser
    print '<td align="right" style="white-space: nowrap;">'.price($obj->echeanceLeaser).'</td>';

    if(empty($fk_leaser)) {
        // Prochaine écheance
        print '<td>'.date('d/m/Y', strtotime($obj->prochaine)).'</td>';

        // Date début
        print '<td>'.date('d/m/Y', strtotime($obj->date_start)).'</td>';

        // Date fin
        print '<td>'.date('d/m/Y', strtotime($obj->date_end)).'</td>';
    }
    else {
        // Date start leaser
        print '<td align="center">'.date('d/m/Y', strtotime($obj->date_debut_leaser)).'</td>';

        // Terme
        print '<td>'.$dossier->financementLeaser->TTerme[$obj->terme].'</td>';

        // VR
        print '<td align="right" style="white-space: nowrap;">'.price($obj->vr).'</td>';

        // Bon pour transfert ?
        print '<td align="center">'.$dossier->financementLeaser->TTransfert[$obj->transfert].'</td>';

        // Date d'envoi
        print '<td align="center">';
        $dateEnvoi = strtotime($obj->date_envoi);
        if($dateEnvoi === false || $dateEnvoi < 0) print '&nbsp;';
        else print date('d/m/Y', $dateEnvoi);
        print '</td>';
    }

    // Facture matériel
    if(! empty($obj->TInvoiceData)) {
        $TInvoiceData = explode(',', $obj->TInvoiceData);

        print '<td align="center">';
        foreach($TInvoiceData as $invoiceData) {
            $TData = explode('-', $invoiceData);
            $facStatic = new Facture($db);
            $facStatic->id = $TData[0];
            $facStatic->ref = $TData[1];
            print '<div>'.$facStatic->getNomUrl(1).'</div>';
        }
        print '</td>';
    }
    else print '<td>&nbsp;</td>';

    if(empty($fk_leaser)) {
        $style = ($obj->statut == 'En cours') ? 'background-color: green;' : 'background-color: red;';
        print '<td align="center" style="'.$style.'">';
        print $obj->statut;
        print '</td>';
    }
    else print '&nbsp;';

    print '<td style="text-align: center;">';
    if(! empty($fk_leaser)) {
        $selected = 0;
        if(in_array($obj->fk_fin_affaire, $arrayofselected)) $selected=1;
        print '<input id="cb'.$obj->fk_fin_affaire.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->fk_fin_affaire.'" '.($selected ? 'checked="checked"' : '').'/>';
    }
    else print '&nbsp';
    print '</td>';

    print '</tr>';
}

print '</table>';
print '</div>';

if(isset($fk_leaser) && ! empty($fk_leaser)) {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=exportXML'.$param.'">'.$langs->trans('Export').'</a>';
    print '</div>';
}

// Cas action export CSV de la liste des futurs affaire transféré en XML
$action = GETPOST('action');
if($action === 'exportXML') {
    _getExportXML($sql);
}

llxFooter();

function _getExportXML($sql) {
    global $conf, $db;

    $PDOdb = new TPDOdb;
    $fin = new TFin_financement;

    $PDOdb->Execute($sql);
    $TTRes = $PDOdb->Get_All(PDO::FETCH_ASSOC);

    $filename = 'export_XML.csv';

    if($conf->entity > 1)
        $url = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/XML/Lixxbail/';
    else
        $url = DOL_DATA_ROOT.'/financement/XML/Lixxbail/';

    dol_mkdir($url);

    $filepath = $url.$filename;
    $file = fopen($filepath, 'w');

    //Ajout première ligne libelle
    $TLabel = array(
        'Partenaire',
        'Contrat Leaser',
        'Client',
        'Siren Client',
        'Debut',
        'VR',
        'Terme',
        'Duree Leaser',
        'Montant Leaser',
        'Echeance Leaser',
        'Materiel',
        'Num. serie',
        'Facture Materiel',
        'Date Envoi'
    );
    fputcsv($file, $TLabel, ';', '"');

    foreach($TTRes as $TRes) {
        $affaire = new TFin_affaire;
        $affaire->load($PDOdb, $TRes['fk_fin_affaire'], false);
        $affaire->loadEquipement($PDOdb);
        $TRes['materiel'] = '';
        $TRes['serial_number'] = '';

        if(! empty($affaire->TAsset[0])) {
            $asset = $affaire->TAsset[0]->asset;
            $TRes['serial_number'] = $asset->serial_number;

            if(! empty($asset->fk_product)) {
                $p = new Product($db);
                $p->fetch($asset->fk_product);
                $TRes['materiel'] = $p->label;
            }
        }

        //On renseigne la facture mat car on l'a avec un eval() dans la liste
        $TRes['fact_materiel'] = _get_facture_mat($TRes['fk_fin_affaire'], false);
        $TRes['terme'] = $fin->TTerme[$TRes['terme']];  // Il faut traduire le terme

        $date_envoi = $TRes['date_envoi'];

        //Suppression des colonnes inutiles
        unset($TRes['fk_fin_dossier'], $TRes['fk_fin_affaire'], $TRes['fk_soc'], $TRes['refDosCli'], $TRes['fk_leaser'], $TRes['nature_financement'], $TRes['statut']);
        unset($TRes['prochaine'], $TRes['date_start'], $TRes['date_end'], $TRes['TInvoiceData'], $TRes['ref_affaire'], $TRes['nomLea'], $TRes['transfert']);
        unset($TRes['duree'], $TRes['Montant'], $TRes['echeance'], $TRes['relocClientOK'], $TRes['relocLeaserOK'],$TRes['intercalaireLeaserOK'], $TRes['date_envoi']);
        unset($TRes['commentaire_conformite'], $TRes['demat'], $TRes['siretCli']);

        $TRes['date_envoi'] = $date_envoi;  // Tout ça pour mettre cette colonne à la fin

        fputcsv($file, $TRes, ';', '"');
    }

    fclose($file);

    ?>
    <script language="javascript">
        document.location.href = "<?php echo dol_buildpath("/document.php?modulepart=financement&entity=".$conf->entity."&file=XML/Lixxbail/".$filename, 2); ?>";
    </script>
    <?php

    $PDOdb->close();
}

function _get_facture_mat($fk_source, $withlink = true) {
    $PDOdb = new TPDOdb;

    $sql = "SELECT f.rowid, f.ref
			FROM ".MAIN_DB_PREFIX."element_element as ee
				LEFT JOIN ".MAIN_DB_PREFIX."facture as f ON (ee.fk_target = f.rowid)
			WHERE ee.fk_target=f.rowid AND ee.sourcetype = 'affaire' AND ee.targettype = 'facture' AND ee.fk_source = ".$fk_source;

    $PDOdb->Execute($sql);

    $link = '';
    while($PDOdb->Get_line()) {
        if($withlink) {
            $link .= '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?facid='.$PDOdb->Get_field('rowid').'">'.img_object('', 'bill').' '.$PDOdb->Get_field('ref').'</a><br>';
        }
        else {
            $link .= $PDOdb->Get_field('ref')." ";
        }
    }

    $PDOdb->close();

    return $link;
}

function howManyAffaire($fk_dossier, $fk_leaser = null) {
    global $db;

    $sql = 'SELECT da.fk_fin_dossier, count(da.fk_fin_affaire) as nbAff';
    $sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_affaire da';
    $sql.= ' WHERE da.fk_fin_dossier = '.$fk_dossier;
    $sql.= ' GROUP BY da.fk_fin_dossier';

    $resql = $db->query($sql);
    if(! $resql) {
        dol_print_error($db);
        exit;
    }

    $res = 0;
    if($obj = $db->fetch_object($resql)) {
        $res = $obj->nbAff;
    }

    return $res;
}
