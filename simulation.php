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
$ATMdb = new Tdb;
$tbs = new TTemplateTBS;

$mesg = '';
$error=false;

$action = GETPOST('action');
if(!empty($_REQUEST['calculate'])) $action = 'calcul';

if(!empty($action)) {
	switch($action) {
		case 'add':
		case 'new':
			
			$simulation->set_values($_REQUEST);
			_fiche($ATMdb, $simulation,'edit');
			
			break;
		case 'calcul':
		
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
	global $langs, $db, $conf;	
	
	llxHeader('','Simulations');
	getStandartJS();
	
	$r = new TSSRenderControler($simulation);
	
	$sql = "SELECT s.rowid as 'ID', s.date_simul as 'Date simulation', s.fk_soc, s.fk_user_author, s.fk_type_contrat,";
	$sql.= " u.login as 'Utilisateur', soc.nom as 'Client'";
	$sql.= " FROM @table@ s ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON s.fk_user_author = u.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON s.fk_soc = soc.rowid";
	$sql.= " WHERE s.entity = ".$conf->entity;
	
	$THide = array('fk_soc', 'fk_user_author');
	
	if(isset($_REQUEST['socid'])) {
		$sql.= ' AND s.fk_soc='.$_REQUEST['socid'];
		$societe = new Societe($db);
		$societe->fetch($_REQUEST['fk_soc']);
		$head = societe_prepare_head($societe);
		dol_fiche_head($head, 'simulation', $langs->trans("ThirdParty"),0,'company');
		
		$THide[] = 'Client';
	}
	
	$TOrder = array('Date simulation'=>'ASC');
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
			'Financement : Nature'=>$simulation->TNatureFinancement
			,'Type'=>$simulation->TTypeFinancement
		)
		,'hide'=>$THide
		,'type'=>array('Date de l\'affaire'=>'date')
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
	}

	$extrajs = array('/financement/js/financement.js');
	llxHeader('',$langs->trans("Simulation"),'','','','',$extrajs);
	
	$affaire = new TFin_affaire;
	$formfin = new FormFinancement($db);
	$form=new TFormCore($_SERVER['PHP_SELF'],'formSimulation','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $simulation->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $simulation->fk_soc);
	echo $form->hidden('fk_user_author', $user->id);
	echo $form->hidden('entity', $conf->entity);
	echo $form->hidden('idLeaser', 1);
	
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
				,'fk_type_contrat'=>$form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat)
				,'opt_administration'=>$form->checkbox1('', 'opt_administration', 1, $simulation->opt_administration) 
				,'opt_periodicite'=>$form->combo('', 'opt_periodicite', $formfin->select_penalite('opt_periodicite', $opt_periodicite, 'opt_periodicite', true), $simulation->opt_periodicite) 
				,'opt_creditbail'=>$form->checkbox1('', 'opt_creditbail', 1, $simulation->opt_creditbail)
				,'opt_mode_reglement'=>$form->combo('', 'opt_mode_reglement', $formfin->select_penalite('opt_mode_reglement', $opt_mode_reglement, 'opt_mode_reglement', true), $simulation->opt_mode_reglement)
				,'opt_terme'=>$form->combo('', 'opt_terme', $formfin->select_penalite('opt_terme', $opt_mode_reglement, 'opt_terme', true), $simulation->opt_mode_reglement)
				,'montant'=>$form->texte('', 'montant', $simulation->montant, 10).' &euro;'
				,'duree'=>$form->combo('', 'duree', $formfin->array_duree($simulation->fk_type_contrat, $simulation->opt_periodicite), $simulation->duree)
				,'echeance'=>$form->texte('', 'echeance', $simulation->echeance, 10).' &euro;'
				,'vr'=>$form->texte('', 'echeance', $simulation->vr, 10).' &euro;'
				,'coefficient'=>$form->texteRO('', 'echeance', $simulation->coeff, 5).' %'
				,'cout_financement'=>$simulation->cout_financement
				,'accord'=>$simulation->accord
			)
			,'client'=>array(
				'titre_client'=>load_fiche_titre($langs->trans('CustomerInfos'), '', '')
				,'societe'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$simulation->fk_soc.'">'.img_picto('','object_company.png', '', 0).' '.$simulation->societe->nom.'</a>'
				,'adresse'=>$simulation->societe->address
				,'cpville'=>$simulation->societe->cp.' / '.$simulation->societe->ville
				,'siret'=>$simulation->societe->idprof2
				,'code_client'=>$simulation->societe->code_client
				,'score_date'=>$simulation->societe->score_date
				,'score'=>$simulation->societe->score
				,'encours_cpro'=>$simulation->societe->encours_cpro
				,'encours_max'=>$simulation->societe->encours_max
			)
			,'view'=>array(
				'mode'=>$mode
				,'type'=>($simulation->fk_soc > 0) ? 'simul' : 'calcul'
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
		if(substr($k, 0, 4) == 'opt_') $options[] = $v;
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
