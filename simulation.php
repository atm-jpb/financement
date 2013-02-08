<?php
require('config.php');
require('./class/simulation.class.php');
require('./class/grille.class.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/score.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

$langs->load('financement@financement');
$simulation=new TSimulation;
$ATMdb = new Tdb;
$tbs = new TTemplateTBS;

$mesg = '';
$error=false;

$action = GETPOST('action');
if(!empty($_REQUEST['calculate'])) $action = 'calcul';
if(!empty($_REQUEST['cancel'])) { // Annulation
	if(!empty($_REQUEST['id'])) { header('Location: '.$_SERVER['PHP_SELF'].'?id='.$_REQUEST['id']); exit; } // Retour sur simulation si mode modif
	if(!empty($_REQUEST['fk_soc'])) { header('Location: ?socid='.$_REQUEST['fk_soc']); exit; } // Retour sur client sinon
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}

if(!empty($action)) {
	switch($action) {
		case 'list':
			_liste($ATMdb, $simulation);
			break;
		case 'add':
		case 'new':
			
			$simulation->set_values($_REQUEST);
			_fiche($ATMdb, $simulation,'edit');
			
			break;
		case 'calcul':
			if(!empty($_REQUEST['id'])) $simulation->load($ATMdb, $db, $_REQUEST['id']);
			$simulation->set_values($_REQUEST);
			_calcul($simulation);
			_fiche($ATMdb, $simulation,'edit');
		
			break;	
		case 'edit'	:
		
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			
			_fiche($ATMdb, $simulation,'edit');
			break;
			
		case 'save':
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			$simulation->set_values($_REQUEST);
			
			// Si une donnée de préconisation a été remplie, on fige la simulation pour le commercial
			if($simulation->fk_leaser > 0 || $simulation->type_financement != '') $simulation->accord_confirme = 1;
			
			//$ATMdb->db->debug=true;
			//print_r($_REQUEST);
			
			$simulation->save($ATMdb);
			
			_fiche($ATMdb, $simulation,'view');
			
			break;
		
			
		case 'delete':
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			//$ATMdb->db->debug=true;
			$simulation->delete($ATMdb);
			
			?>
			<script language="javascript">
				document.location.href="?delete_ok=1";					
			</script>
			<?
			
			
			break;
	}
	
}
elseif(isset($_REQUEST['id'])) {
	$simulation->load($ATMdb, $db, $_REQUEST['id']);
	
	_fiche($ATMdb, $simulation, 'view');global $mesg, $error;
}
else {
	 _liste($ATMdb, $simulation);
}



llxFooter();
	
function _liste(&$ATMdb, &$simulation) {
	global $langs, $db, $conf, $user;
	
	$affaire = new TFin_affaire();
	
	llxHeader('','Simulations');
	getStandartJS();
	
	$r = new TSSRenderControler($simulation);
	
	$THide = array('fk_soc', 'fk_user_author');
	
	$sql = "SELECT s.rowid as 'ID', soc.nom as 'Client', s.fk_soc, s.fk_user_author, s.fk_type_contrat as 'Type de contrat', s.montant as 'Montant', s.echeance as 'Echéance',";
	$sql.= " CONCAT(s.duree, ' ', CASE WHEN s.opt_periodicite = 'opt_mensuel' THEN 'mois' ELSE 'trimestres' END) as 'Durée',";
	$sql.= " s.date_simul as 'Date simulation', u.login as 'Utilisateur', s.accord as 'Statut'";
	$sql.= " FROM @table@ s ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON s.fk_user_author = u.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON s.fk_soc = soc.rowid";
	if (!$user->rights->societe->client->voir && !$_REQUEST['socid']) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc";
	$sql.= " WHERE s.entity = ".$conf->entity;
	
	if (!$user->rights->societe->client->voir && !$_REQUEST['socid']) //restriction
	{
		$sql.= " AND sc.fk_user = " .$user->id;
	}

	if(isset($_REQUEST['socid'])) {
		$sql.= ' AND s.fk_soc='.$_REQUEST['socid'];
		$societe = new Societe($db);
		$societe->fetch($_REQUEST['socid']);
		
		// Affichage résumé client
		$formDoli = new Form($db);
		
		$TBS=new TTemplateTBS();
	
		print $TBS->render('./tpl/client_entete.tpl.php'
			,array(
				
			)
			,array(
				'client'=>array(
					'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'scores', $langs->trans("ThirdParty"),0,'company')
					,'showrefnav'=>$formDoli->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom')
					,'idprof1'=>$societe->idprof1
					,'adresse'=>$societe->address
					,'cpville'=>$societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town
					,'pays'=>picto_from_langcode($societe->country_code).' '.$societe->country
				)
				,'view'=>array(
					'mode'=>'view'
				)
			)
		);
		
		$THide[] = 'Client';
	}
	
	$TOrder = array('Date simulation'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
	
	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'ID'=>'<a href="?id=@ID@">@val@</a>'
			,'Utilisateur'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user_author@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
			,'Client'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_picto('','object_company.png', '', 0).' @val@</a>'
		)
		,'translate'=>array(
			'Type de contrat'=>$affaire->TContrat
			,'Statut'=>$simulation->TStatut
		)
		,'hide'=>$THide
		,'type'=>array('Date simulation'=>'date')
		,'liste'=>array(
			'titre'=>'Liste des simulations'
			,'image'=>img_picto('','simul32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=>"Il n'y a aucune affaire à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			
		)
		,'orderBy'=>$TOrder
	));
	
	if(isset($_REQUEST['socid'])) {
		?><div class="tabsAction"><a href="?action=new&fk_soc=<?=$_REQUEST['socid'] ?>" class="butAction">Nouvelle simulation</a></div><?
	}
	
	llxFooter();
}
	
function _fiche(&$ATMdb, &$simulation, $mode) {
	global $db, $langs, $user, $conf;
	
	$simulation->load_annexe($ATMdb, $db);
	
	$extrajs = array('/financement/js/financement.js');
	llxHeader('',$langs->trans("Simulation"),'','','','',$extrajs);
	
	$affaire = new TFin_affaire;
	$financement = new TFin_financement;
	$grille = new TFin_grille_leaser();
	$html=new Form($db);
	$form=new TFormCore($_SERVER['PHP_SELF'],'formSimulation','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $simulation->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $simulation->fk_soc);
	echo $form->hidden('fk_user_author', $user->id);
	echo $form->hidden('entity', $conf->entity);
	echo $form->hidden('idLeaser', 1);
	echo $form->hidden('cout_financement', $simulation->cout_financement);
	echo $form->hidden('accord', $simulation->accord);

	$TBS=new TTemplateTBS();
	$ATMdb=new Tdb;
	
	print $TBS->render('./tpl/simulation.tpl.php'
		,array(
			
		)
		,array(
			'simulation'=>array(
				'titre_simul'=>load_fiche_titre($langs->trans("Simulator"),'','simul32.png@financement')
				,'titre_calcul'=>load_fiche_titre($langs->trans("Calculator"),'','simul32.png@financement')
				
				,'id'=>$simulation->rowid
				,'fk_soc'=>$simulation->fk_soc
				,'fk_type_contrat'=>$form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat)
				,'opt_administration'=>$form->checkbox1('', 'opt_administration', 1, $simulation->opt_administration) 
				,'opt_periodicite'=>$form->combo('', 'opt_periodicite', $financement->TPeriodicite, $simulation->opt_periodicite) 
				,'opt_creditbail'=>$form->checkbox1('', 'opt_creditbail', 1, $simulation->opt_creditbail)
				,'opt_mode_reglement'=>$form->combo('', 'opt_mode_reglement', $financement->TReglement, $simulation->opt_mode_reglement)
				,'opt_terme'=>$form->combo('', 'opt_terme', $financement->TTerme, $simulation->opt_terme)
				,'montant'=>$form->texte('', 'montant', $simulation->montant, 10)
				,'duree'=>$form->combo('', 'duree', $grille->get_duree($ATMdb,FIN_LEASER_DEFAULT), $simulation->duree)
				,'echeance'=>$form->texte('', 'echeance', $simulation->echeance, 10)
				,'vr'=>$form->texte('', 'vr', $simulation->vr, 10)
				,'coeff'=>$form->texteRO('', 'coeff', $simulation->coeff, 5)
				,'cout_financement'=>$simulation->cout_financement
				,'accord'=>$user->rights->financement->allsimul->simul_preco ? $form->combo('', 'accord', $simulation->TStatut, $simulation->accord) : $simulation->TStatut[$simulation->accord]
				,'accord_confirme'=>$simulation->accord_confirme
				
				,'user'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$simulation->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$simulation->user->login.'</a>'
				,'date'=>$simulation->date_simul
				,'bt_calcul'=>$form->btsubmit('Calculer', 'calculate')
				,'bt_cancel'=>$form->btsubmit('Annuler', 'cancel')
				,'bt_save'=>$form->btsubmit('Valider simulation', 'validate_simul')
				
				,'display_preco'=>$user->rights->financement->allsimul->simul_preco ? 1 : 0
				,'type_financement'=>$form->combo('', 'type_financement', array_merge(array(''=> ''), $affaire->TTypeFinancement), $simulation->type_financement)
				,'leaser'=>$html->select_company('','socid','fournisseur=1',0, 0,1)
			)
			,'client'=>array(
				'societe'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$simulation->fk_soc.'">'.img_picto('','object_company.png', '', 0).' '.$simulation->societe->nom.'</a>'
				,'adresse'=>$simulation->societe->address
				,'cpville'=>$simulation->societe->cp.' / '.$simulation->societe->ville
				,'siret'=>$simulation->societe->idprof2
				,'code_client'=>$simulation->societe->code_client
				,'display_score'=>$user->rights->financement->score->read && $simulation->societe->score->id > 0 ? 1 : 0
				,'score_date'=>empty($simulation->societe) ? '' : $simulation->societe->score->get_date('date_score')
				,'score'=>empty($simulation->societe) ? '' : $simulation->societe->score->score
				,'encours_cpro'=>empty($simulation->societe) ? '' : 0
				,'encours_conseille'=>empty($simulation->societe) ? '' : $simulation->societe->score->encours_conseille
				
				,'liste_dossier'=>_liste_dossier($ATMdb, $simulation)
			)
			,'view'=>array(
				'mode'=>$mode
				,'type'=>($simulation->fk_soc > 0) ? 'simul' : 'calcul'
				,'calcul'=>empty($simulation->cout_financement) ? 0 : 1
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}

function _calcul(&$simulation) {
	global $mesg, $error, $langs, $db;

	$options = array();
	foreach($_POST as $k => $v) {
		if(substr($k, 0, 4) == 'opt_') {
			if($v == 1) $options[] = $k;
			else 		$options[] = $v;
		} 
		${$k} = $v;
	}
	
	if(empty($duree)) {
		$mesg = $langs->trans('ErrorDureeRequired');
		$error = true;
	} else if(empty($montant) && empty($echeance)) {
		$mesg = $langs->trans('ErrorMontantOrEcheanceRequired');
		$error = true;
	} else {
		$grille = new TFin_grille_leaser;
		$ATMdb=new Tdb;
		$grille->get_grille($ATMdb, FIN_LEASER_DEFAULT, $simulation->fk_type_contrat, $simulation->opt_periodicite, $options); // Récupération de la grille pour les paramètre données
		$calcul = $grille->calcul_financement($simulation->montant, $simulation->duree, $simulation->echeance, $simulation->vr, $simulation->coeff); // Calcul du financement
		
		if(!$calcul) { // Si calcul non correct
			$mesg = $langs->trans($grille->error);
			$error = true;
		} else { // Sinon, vérification accord à partir du calcul
			$simulation->demande_accord();
		}
	}
}

function _liste_dossier(&$ATMdb, &$simulation) {
	global $langs,$conf, $db;
	$r = new TListviewTBS('dossier_list', './tpl/simulation.dossier.tpl.php');

	$sql = "SELECT a.rowid as 'IDAff', a.reference as 'N° affaire', d.montant as 'Montant', d.rowid as 'IDDoss',f.date_debut as 'Début', f.date_fin as 'Fin', ac.fk_user,";
	$sql.= " u.login as 'Utilisateur'";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement f ON f.fk_fin_dossier = d.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire_commercial ac ON ac.fk_fin_affaire = a.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON ac.fk_user = u.rowid";
	$sql.= " WHERE a.entity = ".$conf->entity;
	$sql.= " AND a.fk_soc = ".$simulation->fk_soc;
	
	//return $sql;
	
	$THide = array('IDAff', 'IDoss', 'fk_user');
	
	return $r->render($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'30'
		)
		,'orderBy'=>array(
			'N° affaire' => 'DESC'
		)
		,'link'=>array(
			'N° affaire'=>'<a href="affaire.php?id=@ID@">@val@</a>'
			,'Utilisateur'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user_author@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'translate'=>array(
			//'Financement : Nature'=>$import->TNatureFinancement
			//,'Type'=>$import->TTypeFinancement
		)
		,'hide'=>$THide
		,'type'=>array('Début'=>'date', 'Fin'=>'date')
		,'liste'=>array(
			'titre'=>'Liste des imports'
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun import à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			
		)
	));
}
