<?php
require('config.php');
require('./class/simulation.class.php');
require('./class/grille.class.php');
require('./class/affaire.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
dol_include_once('/financement/class/html.formfinancement.class.php');
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

$langs->load('financement@financement');
$simulation=new TSimulation;
$simulation->init();
$simulation->fk_user_author = $user->id;
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
			if(!empty($_REQUEST['id'])) $simulation->load($ATMdb, $_REQUEST['id']);
			$simulation->set_values($_REQUEST);
			_calcul($simulation);
			_fiche($ATMdb, $simulation,'edit');
		
			break;	
		case 'edit'	:
		
			$simulation->load($ATMdb, $_REQUEST['id']);
			
			_fiche($ATMdb, $simulation,'edit');
			break;
			
		case 'save':
			$simulation->load($ATMdb, $_REQUEST['id']);
			$simulation->set_values($_REQUEST);
			
			//$ATMdb->db->debug=true;
			//print_r($_REQUEST);
			
			$simulation->save($ATMdb);
			
			_fiche($ATMdb, $simulation,'view');
			
			break;
		
			
		case 'delete':
			$simulation->load($ATMdb, $_REQUEST['id']);
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
	$simulation->load($ATMdb, $_REQUEST['id']);
	
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
	$sql.= " s.date_simul as 'Date simulation', u.login as 'Utilisateur'";
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
		$societe->fetch($_REQUEST['fk_soc']);
		$head = societe_prepare_head($societe);
		dol_fiche_head($head, 'simulation', $langs->trans("ThirdParty"),0,'company');
		
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
	
	if(empty($simulation->societe) && !empty($simulation->fk_soc)) {
		$simulation->societe = new Societe($db);
		$simulation->societe->fetch($simulation->fk_soc);
		
		if($user->rights->financement->score->read) {
			// Récupération du score du client si droits ok
			$sql = "SELECT s.score, s.date, s.encours_max";
			$sql.= " FROM ".MAIN_DB_PREFIX."fin_score as s";
			$sql.= " WHERE s.fk_soc = ".$simulation->fk_soc;
			$sql.= " ORDER BY s.date DESC";
			$sql.= " LIMIT 1";
			
			$score=$db->query($sql);
			if($score) {
				$obj = $db->fetch_object($dossier_list);
				$simulation->societe->score = $obj->score;
				$simulation->societe->score_date = $obj->date;
				$simulation->societe->encours_max = $obj->encours_max;
				$simulation->societe->encours_cpro = 0;
			}
		}
		
		if ($user->rights->financement->alldossier->read || $user->rights->financement->mydossier->read) {
			// Récupération des dossiers du client
			$sql = "SELECT d.ref, d.montant, d.datedeb, d.datefin";
			$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier as d, ".MAIN_DB_PREFIX."societe as s";
			$sql.= " WHERE d.fk_soc = s.rowid";
			$sql.= " AND s.entity = ".$conf->entity;
			
			if (! $user->rights->financement->alldossier->read) //restriction
			{
				$sql.= " AND d.fk_user_author = " .$user->id;
			}
			
			$sql.= " ORDER BY d.datedeb DESC";
			$dossier_list=$db->query($sql);
		}
		
		if(empty($simulation->user)) {
			$simulation->user = new User($db);
			$simulation->user->fetch($simulation->fk_user_author);
		}
	}

	$extrajs = array('/financement/js/financement.js');
	llxHeader('',$langs->trans("Simulation"),'','','','',$extrajs);
	
	$affaire = new TFin_affaire;
	$grille = new Grille($db);
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
	
	//require('./tpl/affaire.tpl.php');
	$TBS=new TTemplateTBS();
	
	print $TBS->render('./tpl/simulation.tpl.php'
		,array(
			
		)
		,array(
			'simulation'=>array(
				'titre_simul'=>load_fiche_titre($langs->trans("Simulator"),'','simul32.png@financement')
				,'titre_calcul'=>load_fiche_titre($langs->trans("Calculator"),'','simul32.png@financement')
				
				,'id'=>$simulation->rowid
				,'fk_soc'=>$simulation->fk_soc
				,'fk_type_contrat'=>$form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat,1,'','','flat')
				,'opt_administration'=>$form->checkbox1('', 'opt_administration', 1, $simulation->opt_administration) 
				,'opt_periodicite'=>$form->combo('', 'opt_periodicite', $affaire->TPeriodicite, $simulation->opt_periodicite,1,'','','flat') 
				,'opt_creditbail'=>$form->checkbox1('', 'opt_creditbail', 1, $simulation->opt_creditbail)
				,'opt_mode_reglement'=>$form->combo('', 'opt_mode_reglement', $affaire->TModeReglement, $simulation->opt_mode_reglement,1,'','','flat')
				,'opt_terme'=>$form->combo('', 'opt_terme', $affaire->TTerme, $simulation->opt_mode_reglement,1,'','','flat')
				,'montant'=>$form->texte('', 'montant', $simulation->montant, 10)
				,'duree'=>$form->combo('', 'duree', $grille->get_duree(1), $simulation->duree,1,'','','flat')
				,'echeance'=>$form->texte('', 'echeance', $simulation->echeance, 10)
				,'vr'=>$form->texte('', 'vr', $simulation->vr, 10)
				,'coeff'=>$form->texteRO('', 'coeff', $simulation->coeff, 5)
				,'cout_financement'=>$simulation->cout_financement
				,'accord'=>($simulation->accord ? 'Accord OK' : 'Accord en attente')
				
				,'user'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$simulation->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$simulation->user->login.'</a>'
				,'date'=>$simulation->date_simul
			)
			,'client'=>array(
				'societe'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$simulation->fk_soc.'">'.img_picto('','object_company.png', '', 0).' '.$simulation->societe->nom.'</a>'
				,'adresse'=>$simulation->societe->address
				,'cpville'=>$simulation->societe->cp.' / '.$simulation->societe->ville
				,'siret'=>$simulation->societe->idprof2
				,'code_client'=>$simulation->societe->code_client
				,'score_date'=>$simulation->societe->score_date
				,'score'=>$simulation->societe->score
				,'encours_cpro'=>$simulation->societe->encours_cpro
				,'encours_max'=>$simulation->societe->encours_max
				
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

	if(empty($simulation->societe) && !empty($simulation->fk_soc)) {
		$simulation->societe = new Societe($db);
		$simulation->societe->fetch($simulation->fk_soc);
	}

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
		$grille = new Grille($db);
		$grille->get_grille(1, $simulation->fk_type_contrat, $simulation->opt_periodicite, $options); // Récupération de la grille pour les paramètre données
		$calcul = $grille->calcul_financement($simulation->montant, $simulation->duree, $simulation->echeance, $simulation->vr, $simulation->coeff); // Calcul du financement
		
		if(!$calcul) { // Si calcul non correct
			$mesg = $langs->trans($grille->error);
			$error = true;
		} else { // Sinon, vérification accord à partir du calcul
			$simulation->cout_financement = $simulation->echeance * $simulation->duree - $simulation->montant;
			// TODO : Revoir validation financement avec les règles finales
			if(!(empty($simulation->fk_soc))) {
				$simulation->accord = false;
				if($simulation->societe->score > 50 && $simulation->societe->encours_max > ($simulation->societe->encours_cpro + $simulation-Wmontant) * 0.8) {
					$simulation->accord = true;
				}
			}
		}
	}
}

function _liste_dossier(&$ATMdb, &$simulation) {
	global $langs,$conf, $db;
	$r = new TListviewTBS('dossier_list', './tpl/html.list.tbs.php');

	$sql = "SELECT a.rowid as 'IDAff', a.reference as 'N° affaire', d.montant as 'Montant', d.rowid as 'IDDoss', d.datedeb as 'Début', d.datefin as 'Fin', ac.fk_user,";
	$sql.= " u.login as 'Utilisateur'";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
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
