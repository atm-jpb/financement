<?php
ini_set('display_errors', true);
require('config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/lib/financement.lib.php');

$dossier=new TFin_Dossier;
$PDOdb=new TPDOdb;

$visaauto = GETPOST('visaauto');
if(!empty($visaauto)) set_time_limit(0);

$id_dossier = GETPOST('id_dossier');

/********************************************************************************************************************
 * Liste des dossiers qui doivent être contrôlés car il y a risque de rentabilité négative
 * 1 - loyer client < loyer leaser (Case à cocher sur le dossier de financement)
 * 2 - facture client < loyer leaser (Case à cocher sur la facture client)
 * 3 - facture client < loyer client (Case à cocher sur la facture client)
 * 4 - facture client impayée (A CONFIRMER)
 * 5 - échéance non facturée (A CONFIRMER)
 ********************************************************************************************************************/

$TDossiersError = array('all'=>array(),'err1'=>array(),'err2'=>array(),'err3'=>array(),'err4'=>array(),'err5'=>array());

$sqlfields = 'a.reference as refaffaire, a.rowid as fk_affaire, a.fk_soc as fk_client,';
$sqlfields.= 'dfcli.reference as refdoscli, dfcli.duree, dfcli.periodicite, dfcli.montant, dfcli.echeance, dfcli.date_debut, dfcli.date_fin, dfcli.date_prochaine_echeance, ';
$sqlfields.= 'd.renta_previsionnelle, d.renta_attendue, d.renta_reelle, d.marge_previsionnelle, d.marge_attendue, d.marge_reelle, ';
$sqlfields.= 'dflea.reference as refdoslea, dflea.fk_soc as fk_leaser, scli.nom as nomcli, slea.nom as nomlea';
$sqljoin = " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type = 'CLIENT')";
$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";
$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."societe slea ON (slea.rowid = dflea.fk_soc)";
$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier) ";
$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (da.fk_fin_affaire = a.rowid) ";
$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."societe scli ON (scli.rowid = a.fk_soc)";
$sqlwhere = " AND d.nature_financement = 'INTERNE'";
$sqlwhere.= " AND d.montant_solde = 0";
$sqlwhere.= " AND d.date_solde = '0000-00-00 00:00:00' ";
$sqlwhere.= " AND d.entity IN (".getEntity('fin_dossier', TFinancementTools::user_courant_est_admin_financement()).")";
$sqlwhere.= " AND d.reference NOT LIKE '%old%' ";
if(!empty($id_dossier)) $sqlwhere.= " AND d.rowid = ".$id_dossier;
//$sqlwhere.= " LIMIT 1 ";


/***********************************************************************************************************************************************************
 * 1 - Récupération de tous les dossiers dont "Visa renta négative" est à non, ce qui signifie que la règle 1 est à contrôler
 ***********************************************************************************************************************************************************/
$sql = "SELECT d.rowid";
$sql.= ", $sqlfields";
$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
$sql.= $sqljoin;
$sql.= " WHERE d.visa_renta = 0";
$sql.= $sqlwhere;

//echo $sql . '<hr>';

$PDOdb->Execute($sql);
$TRes = $PDOdb->Get_All();

foreach($TRes as $res) {
	$rowid = $res->rowid;
	$renta_neg = false;
	
	// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
	if(!empty($visaauto)) {
		$dossier->load($PDOdb, $rowid,false,true);
		
		// Si règle 1 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
		if($dossier->financement->echeance < $dossier->financementLeaser->echeance) {
			$renta_neg = true;
		} else {
			echo 'Dossier '.$dossier->financement->reference.' respecte la règle 1, case "Visa renta négative" cochée automatiquement.<br>';
			$dossier->visa_renta = 1;
			$dossier->save($PDOdb);
		}
	}
	
	if($renta_neg || empty($visaauto)) {
		if(!in_array($rowid, $TDossiersError['all'])) {
			$TDossiersError['all'][] = $rowid;
			$TDossiersError['data'][$rowid] = $res;
		}
		if(!in_array($rowid, $TDossiersError['err1'])) $TDossiersError['err1'][] = $rowid;
	}
}

//pre($TDossiersError,true);
//exit;

/***********************************************************************************************************************************************************
 * 2 - Récupération de tous les dossiers dont "Visa renta facture < loyer leaser" est à non
 * ce qui signifie que la règle 2 est à contrôler
 ***********************************************************************************************************************************************************/
$sql = "SELECT DISTINCT d.rowid,f.facnumber";
$sql.= ", $sqlfields";
$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fext ON (fext.fk_object = f.rowid)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
$sql.= $sqljoin;
$sql.= " WHERE (fext.visa_renta_loyer_leaser = 0 OR fext.visa_renta_loyer_leaser IS NULL)";
$sql.= $sqlwhere;

//echo $sql . '<hr>';

$PDOdb->Execute($sql);
$TRes = $PDOdb->Get_All();

foreach($TRes as $res) {
	$rowid = $res->rowid;
	$renta_neg = false;
	
	// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
	if(!empty($visaauto)) {
		$dossier->load($PDOdb, $rowid);
		
		// On fait la somme des échéances des dossiers leaser associés à cette référence dossier (prise en compte des adjonctions)
		$sql = "SELECT SUM(dflea.echeance) as total_echeances
				FROM ".MAIN_DB_PREFIX."fin_dossier d
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type='CLIENT')
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type='LEASER')
				WHERE dfcli.reference LIKE '".$dossier->financement->reference."%'";
		$TRes = $PDOdb->ExecuteAsArray($sql);
		$total_echeances = $TRes[0]->total_echeances;
		
		// Attention on vérifie les factures et regroupements de factures
		$montant_facture = 0;
		foreach($dossier->TFacture as $p => $d) {
			// Récupération du montant facturé au client pour comparer aux loyers. Si plusieurs factures, on fait la somme
			if(is_array($d)) {
				foreach ($d as $i => $f) {
					$montant_facture += $f->total_ht;
				}
			} else {
				$montant_facture = $d->total_ht;
			}
			
			// Comparaison au loyer leaser
			// Si règle 2 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
			if($montant_facture < $total_echeances) {
				$renta_neg = true;
			} else {
				echo 'Dossier '.$dossier->financement->reference.', période '.($p+1).' respecte la règle 2, case "Visa renta facture < loyer leaser" cochée automatiquement.<br>';
				if(is_array($d)) {
					foreach ($d as $i => $f) {
						$f->array_options['options_visa_renta_loyer_leaser'] = 1;
						$f->insertExtraFields();
					}
				} else {
					$d->array_options['options_visa_renta_loyer_leaser'] = 1;
					$d->insertExtraFields();
				}
			}
		}
	}

	if($renta_neg || empty($visaauto)) {
		if(!in_array($rowid, $TDossiersError['all'])) {
			$TDossiersError['all'][] = $rowid;
			$TDossiersError['data'][$rowid] = $res;
		}
		if(!in_array($rowid, $TDossiersError['err2'])) $TDossiersError['err2'][] = $rowid;
	}
}

/***********************************************************************************************************************************************************
 * 3 - Récupération de tous les dossiers dont "Visa renta facture < loyer client" est à non
 * ce qui signifie que la règle 3 est à contrôler
 ***********************************************************************************************************************************************************/
$sql = "SELECT DISTINCT d.rowid";
$sql.= ", $sqlfields";
$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fext ON (fext.fk_object = f.rowid)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
$sql.= $sqljoin;
$sql.= " WHERE (fext.visa_renta_loyer_client = 0 OR fext.visa_renta_loyer_client IS NULL)";
$sql.= $sqlwhere;

//echo $sql . '<hr>';

$PDOdb->Execute($sql);
$TRes = $PDOdb->Get_All();

foreach($TRes as $res) {
	$rowid = $res->rowid;
	$renta_neg = false;
	
	// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
	if(!empty($visaauto)) {
		$dossier->load($PDOdb, $rowid);
		
		// On fait la somme des échéances des dossiers client associés à cette référence dossier (prise en compte des adjonctions)
		$sql = "SELECT SUM(dfcli.echeance) as total_echeances
				FROM ".MAIN_DB_PREFIX."fin_dossier_financement dfcli
				WHERE dfcli.reference LIKE '".$dossier->financement->reference."%'
				AND dfcli.type='CLIENT'";
		$TRes = $PDOdb->ExecuteAsArray($sql);
		$total_echeances = $TRes[0]->total_echeances;
		
		// Attention on vérifie les factures et regroupements de factures
		$montant_facture = 0;
		foreach($dossier->TFacture as $p => $d) {
			// Récupération du montant facturé au client pour comparer aux loyers. Si plusieurs factures, on fait la somme
			if(is_array($d)) {
				foreach ($d as $i => $f) {
					$montant_facture += $f->total_ht;
				}
			} else {
				$montant_facture = $d->total_ht;
			}
			
			// Comparaison au loyer client
			// Si règle 3 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
			if($montant_facture < $total_echeances) {
				$renta_neg = true;
			} else {
				echo 'Dossier '.$dossier->financement->reference.', période '.($p+1).' respecte la règle 3, case "Visa renta facture < loyer client" cochée automatiquement.<br>';
				if(is_array($d)) {
					foreach ($d as $i => $f) {
						$f->array_options['options_visa_renta_loyer_client'] = 1;
						$f->insertExtraFields();
					}
				} else {
					$d->array_options['options_visa_renta_loyer_client'] = 1;
					$d->insertExtraFields();
				}
			}
		}
	}

	if($renta_neg || empty($visaauto)) {
		if(!in_array($rowid, $TDossiersError['all'])) {
			$TDossiersError['all'][] = $rowid;
			$TDossiersError['data'][$rowid] = $res;
		}
		if(!in_array($rowid, $TDossiersError['err3'])) $TDossiersError['err3'][] = $rowid;
	}
}

/***********************************************************************************************************************************************************
 * 4 - Récupération de tous les dossiers dont au moins une facture client est impayée et en retard
 * ce qui signifie que la règle 4 est à contrôler
 ***********************************************************************************************************************************************************/
$sql = "SELECT DISTINCT d.rowid";
$sql.= ", $sqlfields";
$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fext ON (fext.fk_object = f.rowid)";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
$sql.= $sqljoin;
$sql.= " WHERE f.paye = 0";
$sql.= " AND f.date_lim_reglement <= '".date('Y-m-d')."'";
$sql.= $sqlwhere;

//echo $sql . '<hr>';

$PDOdb->Execute($sql);
$TRes = $PDOdb->Get_All();

foreach($TRes as $res) {
	$rowid = $res->rowid;
	$renta_neg = false;
	
	// TODO : voir si besoin d'un visa sur la règle concernant les factures impayées, sachant qu'elle passent en payées en automatique via import quotidien

	if($renta_neg || empty($visaauto)) {
		if(!in_array($rowid, $TDossiersError['all'])) {
			$TDossiersError['all'][] = $rowid;
			$TDossiersError['data'][$rowid] = $res;
		}
		if(!in_array($rowid, $TDossiersError['err4'])) $TDossiersError['err4'][] = $rowid;
	}
}

/***********************************************************************************************************************************************************
 * 5 - Récupération de tous les dossiers pour lesquels il manque une facture
 * ce qui signifie que la règle 5 est à contrôler
 ***********************************************************************************************************************************************************/
$sql = "SELECT d.rowid, dfcli.numero_prochaine_echeance";
	$sql.= ", ( SELECT COUNT(DISTINCT f.ref_client)";
	$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture a ON (f.rowid = a.fk_facture_source AND a.type = 2)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d2 ON (ee.fk_source = d2.rowid AND ee.sourcetype = 'dossier')";
	$sql.= " WHERE d.rowid = d2.rowid";
	$sql.= " AND f.type = 0";
	$sql.= " AND (f.total + IFNULL(a.total,0)) != 0";
	$sql.= ") as nb_echeances_facturees";
$sql.= ", $sqlfields";
$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
$sql.= $sqljoin;
$sql.= " WHERE 1";
$sql.= $sqlwhere;

//echo $sql . '<hr>';

$PDOdb->Execute($sql);
$TRes = $PDOdb->Get_All();

$TPeriodeType = array(
	'MOIS' => 1
	,'TRIMESTRE' => 3
	,'SEMESTRE' => 6
	,'ANNEE' => 12
);

foreach($TRes as $res) {
	$rowid = $res->rowid;
	$renta_neg = false;
	
	// Calcul du nombre de période écoulées entre le début du dossier et le 01/01/2014, date de début d'intégration des factures dans LeaseBoard
	$nb_periode_sans_fact = 0;
	$datedeb = strtotime($res->date_debut);
	$datelimit = strtotime('2014-01-01');
	$nbmonth = $TPeriodeType[$res->periodicite];
	
	while($datedeb < $datelimit) {
		$datedeb = strtotime('+ '.$nbmonth.' month', $datedeb);
		$nb_periode_sans_fact++;
	}
	
	if(($res->nb_echeances_facturees + $nb_periode_sans_fact) != ($res->numero_prochaine_echeance - 1)) {
		$renta_neg = true;
	}
	
	// TODO : voir si besoin d'un visa sur la règle concernant les factures manquantes, sachant qu'elles sont créées en automatique via import quotidien

	if($renta_neg) {
		if(!in_array($rowid, $TDossiersError['all'])) {
			$TDossiersError['all'][] = $rowid;
			$TDossiersError['data'][$rowid] = $res;
		}
		if(!in_array($rowid, $TDossiersError['err5'])) $TDossiersError['err5'][] = $rowid;
	}
}

//pre($TDossiersError,true);
//exit;

/************************************************************************
 * VIEW
 ************************************************************************/
llxHeader('','Dossiers renta négative');

foreach($TDossiersError['all'] as $id_dossier) {
	$data = $TDossiersError['data'][$id_dossier];
	
	$TLinesFile[] = array(
		'refdoscli' => $data->refdoscli
		,'refdoslea' => $data->refdoslea
		,'affaire'=> $data->refaffaire
		,'nomcli' => $data->nomcli
		,'nomlea' => $data->nomlea
		,'status_1' => (in_array($id_dossier, $TDossiersError['err1']) ? "Oui" : "Non")
		,'status_2' => (in_array($id_dossier, $TDossiersError['err2']) ? "Oui" : "Non")
		,'status_3' => (in_array($id_dossier, $TDossiersError['err3']) ? "Oui" : "Non")
		,'status_4' => (in_array($id_dossier, $TDossiersError['err4']) ? "Oui" : "Non")
		,'status_5' => (in_array($id_dossier, $TDossiersError['err5']) ? "Oui" : "Non")
		,'duree' => $data->duree
		,'periodicite' => $data->periodicite
		,'montant' => price($data->montant,0,'',1,-1,2)
		,'echeance' => price($data->echeance,0,'',1,2)
		,'date_prochaine' => date('d/m/y', strtotime($data->date_prochaine_echeance))
		,'date_debut' => date('d/m/y', strtotime($data->date_debut))
		,'date_fin' => date('d/m/y', strtotime($data->date_fin))
		,'renta_previsionnelle'=>number_format($data->renta_previsionnelle,2, ',', ' ').' / '.number_format($data->marge_previsionnelle,2).' %'
		,'renta_attendue'=>number_format($data->renta_attendue,2, ',', ' ').' / '.number_format($data->marge_attendue, 2).' %'
		,'renta_reelle'=>number_format($data->renta_reelle,2, ',', ' ').' / '.number_format($data->marge_reelle,2).' %'
	);
	
	$TLinesDisp[] = array(
		'iddos' => $id_dossier
		,'refdos' => '<a href="dossier.php?id='.$id_dossier.'">'.$data->refdoscli .'<br>'. $data->refdoslea.'</a>'
		,'fk_affaire' => $data->fk_affaire
		,'affaire'=> $data->refaffaire
		,'noms' => '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$data->fk_client.'">'.$data->nomcli.'</a><br><a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$data->fk_leaser.'">'.$data->nomlea.'</a>'
		,'status_1' => (in_array($id_dossier, $TDossiersError['err1']) ? "Oui" : "Non")
		,'status_2' => (in_array($id_dossier, $TDossiersError['err2']) ? "Oui" : "Non")
		,'status_3' => (in_array($id_dossier, $TDossiersError['err3']) ? "Oui" : "Non")
		,'status_4' => (in_array($id_dossier, $TDossiersError['err4']) ? "Oui" : "Non")
		,'status_5' => (in_array($id_dossier, $TDossiersError['err5']) ? "Oui" : "Non")
		,'montants' => price($data->montant,0,'',1,-1,2) . '<br>' . price($data->echeance,0,'',1,2) . '<br>' . $data->duree . ' ' . substr($data->periodicite, 0, 1) 
		,'dates' => date('d/m/y', strtotime($data->date_debut)) . '<br>' . date('d/m/y', strtotime($data->date_prochaine_echeance)) . '<br>' . date('d/m/y', strtotime($data->date_fin))
		,'renta_previsionnelle'=>number_format($data->renta_previsionnelle,2, ',', ' ').' <br> '.number_format($data->marge_previsionnelle,2).' %'
		,'renta_attendue'=>number_format($data->renta_attendue,2, ',', ' ').' <br> '.number_format($data->marge_attendue, 2).' %'
		,'renta_reelle'=>number_format($data->renta_reelle,2, ',', ' ').' <br> '.number_format($data->marge_reelle,2).' %'
	);
}

$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
echo $form->hidden('liste_renta_negative', '1');
$aff = new TFin_affaire;
$dos = new TFin_dossier;

$TErrorStatus=array(
	'error_1' => "Echéance Client <br>< Echéance Leaser",
	'error_2' => "Facture Client <br>< Loyer client",
	'error_3' => "Facture Client <br>< Facture leaser",
	'error_4' => "Facture Client <br>impayée",
	'error_5' => "Echéance client <br>non facturée"
);
$TTitles = array(
	'refdos'=>'Contrat'
	,'refdoscli'=>'Contrat'
	,'refdoslea'=>'Contrat Leaser'
	,'affaire'=>'Affaire'
	,'nomcli'=>'Client'
	,'nomlea'=>'Leaser'
	,'noms' => 'Tiers'
	,'status_1'=>$TErrorStatus['error_1']
	,'status_2'=>$TErrorStatus['error_2']
	,'status_3'=>$TErrorStatus['error_3']
	,'status_4'=>$TErrorStatus['error_4']
	,'status_5'=>$TErrorStatus['error_5']
	,'duree'=>'Durée'
	,'periodicite' => 'Périodicité'
	,'montant'=>'Montant'
	,'echeance'=>'Échéance'
	,'montants'=>'Montants'
	,'date_prochaine'=>'Prochaine'
	,'date_debut'=>'Début'
	,'date_fin'=>'Fin'
	,'dates'=>'Dates'
	,'renta_previsionnelle'=>'Renta <br />Prévisionnelle'
	,'renta_attendue'=>'Renta <br />Attendue'
	,'renta_reelle'=>'Renta <br />Réelle'
);
$limit = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : $conf->global->MAIN_SIZE_LISTE_LIMIT;

$r = new TListviewTBS('list_'.$dossier->get_table());
print $r->renderArray($PDOdb, $TLinesDisp, array(
	'limit'=>array(
		'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
		,'nbLine'=>$limit
	)
	,'link'=>array(
		'refdoscli'=>'<a href="?id=@iddos@">@val@</a>'
		,'refdoslea'=>'<a href="?id=@iddos@">@val@</a>'
		,'nomcli'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_client@">@val@</a>'
		,'nomlea'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_leaser@">@val@</a>'
		,'affaire'=>'<a href="'.dol_buildpath('/financement/affaire.php?id=@fk_affaire@',1).'">@val@</a>'
	)
	,'translate'=>array(
		'nature_financement'=>$aff->TNatureFinancement
		,'visa_renta'=>$dos->Tvisa
	)
	,'hide'=>array('iddos', 'fk_client','fk_leaser','fk_affaire')
	,'type'=>array()//'date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant'=>'money', 'Echéance'=>'money')
	,'liste'=>array(
		'titre'=>"Liste des dossiers à rentabilité négative"
		,'image'=>img_picto('','title.png', '', 0)
		,'picto_precedent'=>img_picto('','previous.png', '', 0)
		,'picto_suivant'=>img_picto('','next.png', '', 0)
		,'order_down'=>img_picto('','1downarrow.png', '', 0)
		,'order_up'=>img_picto('','1uparrow.png', '', 0)
		,'noheader'=>FALSE
		,'messageNothing'=>"Il n'y a aucun dossier"
		,'picto_search'=>img_picto('','search.png', '', 0)
		)
	,'title'=>$TTitles
	,'orderBy'=> array('ID'=>'DESC','fc.reference'=>'ASC'
	)
	
));
$form->end();

?>
<div class="tabsAction">
	<a href="?action=exportListeDossier" class="butAction">Exporter</a>
</div>
<?php

$action = GETPOST('action');
if($action === 'exportListeDossier'){
	_getExport($TLinesFile,$TTitles);
}

llxFooter();

function _getExport(&$TLines, $TTitles){
	$filename = 'export_liste_dossier_renta_neg.csv';
	$filepath = DOL_DATA_ROOT.'/financement/'.$filename;
	$file = fopen($filepath,'w');
	
	//Ajout première ligne libelle
	$l1 = $TLines[0];
	$TFirstline = array();
	foreach ($TTitles as $key => $value) {
		if(array_key_exists($key, $l1)) {
			$TFirstline[] = $value;
		}
	}
	
	fputcsv($file, $TFirstline,';','"');
	
	foreach($TLines as $line){
		fputcsv($file, $line,';','"');
	}
	
	fclose($file);
	
	?>
	<script language="javascript">
		document.location.href="<?php echo dol_buildpath("/document.php?modulepart=financement&entity=1&file=".$filename,2); ?>";
	</script>
	<?php
}	