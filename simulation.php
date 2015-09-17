<?php
require('config.php');
require('./class/simulation.class.php');
require('./class/grille.class.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/dossier_integrale.class.php');
require('./class/score.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

$langs->load('financement@financement');
$simulation=new TSimulation;
$ATMdb = new TPDOdb;
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
if(!empty($_REQUEST['from']) && $_REQUEST['from']=='wonderbase') { // On arrive de Wonderbase, direction nouvelle simulation
	if(!empty($_REQUEST['code_artis'])) { // Client
		$TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'societe', array('code_client'=>$_REQUEST['code_artis'],'client'=>1));
		header('Location: ?action=new&fk_soc='.$TId[0]); exit;
	} else if(!empty($_REQUEST['code_wb'])) { // Prospect
		$TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'societe', array('code_client'=>$_REQUEST['code_wb'],'client'=>2));
		if(!empty($TId[0])) { header('Location: ?action=new&fk_soc='.$TId[0]); exit; }
		//pre($_REQUEST);
		// Création du prospect s'il n'existe pas
		$societe = new Societe($db);
		$societe->code_client = $_REQUEST['code_wb'];
		$societe->name = $_REQUEST['nom'];
		$societe->address = $_REQUEST['adresse'];
		$societe->zip = $_REQUEST['cp'];
		$societe->town = $_REQUEST['ville'];
		//$societe->country = $_REQUEST['pays'];
		$societe->idprof1 = $_REQUEST['siren'];
		$societe->idprof2 = $_REQUEST['siren'];
		$societe->idprof3 = $_REQUEST['naf'];
		$societe->client = 2;
		if($societe->create($user) > 0) {
			header('Location: ?action=new&fk_soc='.$societe->id); exit;
		} else {
			$action = 'new';
			$error = 1;
			$mesg = $langs->trans('UnableToCreateProspect');
		}
	}
}

if(!empty($_REQUEST['fk_soc'])) {
	$simulation->fk_soc = $_REQUEST['fk_soc'];
	$simulation->load_annexe($ATMdb, $db);

	// Si l'utilisateur n'a pas le droit d'accès à tous les tiers
	if(!$user->rights->societe->client->voir) {
		// On vérifie s'il est associé au tiers dans Dolibarr
		dol_include_once("/financement/class/commerciaux.class.php");
		$c=new TCommercialCpro;
		if(!$c->loadUserClient($ATMdb, $user->id, $simulation->fk_soc) > 0) {
			// On vérifie si l'utilisateur est associé au tiers dans Wonderbase
			$url = FIN_WONDERBASE_USER_RIGHT_URL.'?numArtis='.$simulation->societe->code_client.'&trigramme='.$user->login;
			$droit = file_get_contents($url);
			//$TInfo = json_decode(utf8_decode($droit));

			// Association du user au tiers si droits ok
			if(strpos($droit, '1') !== false) {
				$c->fk_soc = $simulation->fk_soc;
				$c->fk_user = $user->id;
				$c->save($ATMdb);
			}
		}
	}
	
	// Vérification par Dolibarr des droits d'accès du user à l'information relative au tiers
	$simulation->societe->getCanvas($socid);
	$canvas = $simulation->societe->canvas?$object->canvas:GETPOST("canvas");
	$objcanvas='';
	if (! empty($canvas))
	{
	    require_once DOL_DOCUMENT_ROOT.'/core/class/canvas.class.php';
	    $objcanvas = new Canvas($db, $action);
	    $objcanvas->getCanvas('thirdparty', 'card', $canvas);
	}
	
	// Security check
	$result = restrictedArea($user, 'societe', $simulation->societe->id, '&societe', '', 'fk_soc', 'rowid', $objcanvas);
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
			
			// On vérifie que les dossiers sélectionnés n'ont pas été décochés
			if(empty($_REQUEST['dossiers'])) $simulation->dossiers = array();
			if(empty($_REQUEST['dossiers_rachetes'])) $simulation->dossiers_rachetes = array();
			if(empty($_REQUEST['dossiers_rachetes_p1'])) $simulation->dossiers_rachetes_p1 = array();
			if(empty($_REQUEST['dossiers_rachetes_nr'])) $simulation->dossiers_rachetes_nr = array();
			if(empty($_REQUEST['dossiers_rachetes_nr_p1'])) $simulation->dossiers_rachetes_nr_p1 = array();
			if(empty($_REQUEST['dossiers_rachetes_perso'])) $simulation->dossiers_rachetes_perso = array();

			$simulation->opt_adjonction = (int)isset($_REQUEST['opt_adjonction']);
			$simulation->opt_administration = (int)isset($_REQUEST['opt_administration']);
			$simulation->opt_no_case_to_settle = (int)isset($_REQUEST['opt_no_case_to_settle']);

			_calcul($simulation);
			//C'est dégueu mais sa marche
			$simulation->commentaire = utf8_decode($simulation->commentaire);
			_fiche($ATMdb, $simulation,'edit');

			break;	
		case 'edit'	:
		
			$simulation->load($ATMdb, $db, $_REQUEST['id']);

			_fiche($ATMdb, $simulation,'edit');
			break;
		
		case 'save_suivi':
			
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			$simulation_suivi = new TSimulationSuivi;
			
			//pre($_REQUEST,true);exit;
			
			foreach($_REQUEST as $key => $value){
				if($key == 'TSuivi'){
					foreach ($value as $id_suivi => $Tval) {
						$simulation_suivi->load($ATMdb, $id_suivi);
						
						$Tab['numero_accord_leaser'] = $Tval['num_accord'];
						$Tab['coeff_leaser'] = $Tval['coeff_accord'];
						$simulation_suivi->set_values($Tab);
						$simulation_suivi->save($ATMdb);	
					}
				}
			}

			_fiche($ATMdb, $simulation,'view');
			break;
		
		case 'save':
			//pre($_REQUEST,true);
			if(!empty($_REQUEST['id'])) $simulation->load($ATMdb, $db, $_REQUEST['id']);
			$oldAccord = $simulation->accord;
			//pre($_REQUEST,true);
			$simulation->set_values($_REQUEST);
			
			$simulation->opt_adjonction = (int)isset($_REQUEST['opt_adjonction']);
			$simulation->opt_administration = (int)isset($_REQUEST['opt_administration']);
			$simulation->opt_no_case_to_settle = (int)isset($_REQUEST['opt_no_case_to_settle']);
			
			if($simulation->opt_calage != '') {
				$simulation->set_date('date_demarrage',$_REQUEST['date_demarrage']);
			}
			else{
				$simulation->set_date('date_demarrage','');
			}
			
			// Si l'accord vient d'être donné (par un admin)
			if($simulation->accord == 'OK' && $simulation->accord != $oldAccord) {
				$simulation->date_validite = strtotime('+ 2 months');
				$simulation->date_accord = time();
				$simulation->accord_confirme = 1;
			} 
			else if($simulation->accord == 'KO' && $simulation->accord != $oldAccord) {
				$simulation->accord_confirme = 1;
			}
			else if($simulation->accord == 'SS') {
				$simulation->accord_confirme = 1; // #478 un gros WTF? sur cette fonction
			}
			
			// Si une donnée de préconisation a été remplie, on fige la simulation pour le commercial
			if($simulation->fk_leaser > 0 || $simulation->type_financement != '') {
				$simulation->accord_confirme = 1;
			}
			
			// On vérifie que les dossiers sélectionnés n'ont pas été décochés
			if(empty($_REQUEST['dossiers'])) $simulation->dossiers = array();
			if(empty($_REQUEST['dossiers_rachetes'])) $simulation->dossiers_rachetes = array();
			if(empty($_REQUEST['dossiers_rachetes_p1'])) $simulation->dossiers_rachetes_p1 = array();
			if(empty($_REQUEST['dossiers_rachetes_nr'])) $simulation->dossiers_rachetes_nr = array();
			if(empty($_REQUEST['dossiers_rachetes_nr_p1'])) $simulation->dossiers_rachetes_nr_p1 = array();
			if(empty($_REQUEST['dossiers_rachetes_perso'])) $simulation->dossiers_rachetes_perso = array();
			
			
			
			// On refait le calcul avant d'enregistrer
			_calcul($simulation, 'save');
			if($error) {
				_fiche($ATMdb, $simulation,'edit');
			} else {
				//$ATMdb->db->debug=true;
				$simulation->save($ATMdb, $db);
				//echo $simulation->opt_calage; exit;
				// Si l'accord vient d'être donné (par un admin)
				if(($simulation->accord == 'OK' || $simulation->accord == 'KO') && $simulation->accord != $oldAccord) {
					$simulation->send_mail_vendeur();
				}
				
				$simulation->load_annexe($ATMdb, $db);
				
				_fiche($ATMdb, $simulation,'view');
				
				setEventMessage('Simulation enregistrée : '.$simulation->getRef(),'mesgs');
			}
			
			break;
		
		case 'send_accord':
			if(!empty($_REQUEST['id'])) {
				$simulation->load($ATMdb, $db, $_REQUEST['id']);
				if($simulation->accord == 'OK') {
					$simulation->send_mail_vendeur();
				}
			}
			
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
		
		default:
			
			//Actions spécifiques au suivi financement leaser
			$id_suivi = GETPOST('id_suivi');
			if($id_suivi){
				$simulation->load($ATMdb, $db, $_REQUEST['id']);
				$simulation->TSimulationSuivi[$id_suivi]->doAction($ATMdb,$simulation,$action);
				
				if(!empty($simulation->TSimulationSuivi[$id_suivi]->errorLabel)){
					setEventMessage($simulation->TSimulationSuivi[$id_suivi]->errorLabel,'errors');
				}
				
				if($action == 'demander'){
					$simulation->accord = 'WAIT_LEASER';
					$simulation->save($ATMdb, $db);
				}
				
				_fiche($ATMdb, $simulation, 'view');
			}
			
			break;
	}
	
}
elseif(isset($_REQUEST['id'])) {
	$simulation->load($ATMdb, $db, $_REQUEST['id']);
	_fiche($ATMdb, $simulation, 'view');
}
else {
	 _liste($ATMdb, $simulation);
}

llxFooter();
	
function _liste(&$ATMdb, &$simulation) {
	global $langs, $db, $conf, $user;
	
	$affaire = new TFin_affaire();
	
	llxHeader('','Simulations');
	
	$r = new TSSRenderControler($simulation);
	
	$THide = array('fk_soc', 'fk_user_author', 'rowid');
	
	$sql = "SELECT DISTINCT s.rowid, s.reference, s.fk_soc, soc.nom, s.fk_user_author, s.fk_type_contrat, s.montant_total_finance as 'Montant', s.echeance as 'Echéance',";
	$sql.= " CONCAT(s.duree, ' ', CASE WHEN s.opt_periodicite = 'MOIS' THEN 'mois' WHEN s.opt_periodicite = 'ANNEE' THEN 'années' ELSE 'trimestres' END) as 'Durée',";
	$sql.= " s.date_simul, u.login, s.accord, s.type_financement, lea.nom as leaser, '' as suivi";
	$sql.= " FROM @table@ s ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON s.fk_user_author = u.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON s.fk_soc = soc.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as lea ON s.fk_leaser = lea.rowid ";
	
	if (!$user->rights->societe->client->voir && !$_REQUEST['socid']) {
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = soc.rowid";
	}
	
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
					'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'simulation', $langs->trans("ThirdParty"),0,'company')
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
	
	if(!$user->rights->financement->allsimul->suivi_leaser){
		$THide[] = 'suivi';
	}
	
	$TOrder = array('date_simul'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formSimulation', 'GET');
	
	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'reference'=>'<a href="?id=@rowid@">@val@</a>'
			,'nom'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_picto('','object_company.png', '', 0).' @val@</a>'
			,'login'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user_author@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'translate'=>array(
			'fk_type_contrat'=>$affaire->TContrat
			,'accord'=>$simulation->TStatut
		)
		,'hide'=>$THide
		,'type'=>array('date_simul'=>'date','Montant'=>'money','Echéance'=>'money')
		,'liste'=>array(
			'titre'=>'Liste des simulations'
			,'image'=>img_picto('','simul32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=>"Il n'y a aucune simulation à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'orderBy'=>$TOrder
		,'title'=>array(
			'rowid'=>'N°'
			,'nom'=>'Client'
			,'reference'=>'Ref.'
			,'login'=>'Utilisateur'
			,'fk_type_contrat'=> 'Type<br>de<br>contrat'
			,'date_simul'=>'Date<br>simulation'
			,'accord'=>'Statut'
			,'type_financement'=>'Type<br>financement'
			,'leaser'=>'Leaser'
			,'suivi'=>'Accord<br>Leaser'
		)
		,'search'=>array(
			'nom'=>array('recherche'=>true, 'table'=>'soc')
			,'login'=>array('recherche'=>true, 'table'=>'u')
			,'fk_type_contrat'=>$affaire->TContrat
			,'type_financement'=>$affaire->TTypeFinancement
			,'date_simul'=>'calendar'
			,'accord'=>$simulation->TStatut
			,'leaser'=>array('recherche'=>true, 'table'=>'lea', 'field'=>'nom')
		)
		,'eval'=>array(
			'suivi' => 'getStatutSuivi(@rowid@);'
		)
	));
	
	$form->end();
	
	if(isset($_REQUEST['socid'])) {
		?><div class="tabsAction"><a href="?action=new&fk_soc=<?=$_REQUEST['socid'] ?>" class="butAction">Nouvelle simulation</a></div><?
	}
	
	llxFooter();
}

function getStatutSuivi($idSimulation){
	global $db;

	$ATMdb = new TPDOdb;

	$sql = "SELECT statut, date_selection 
			FROM ".MAIN_DB_PREFIX."fin_simulation_suivi
			WHERE fk_simulation = ".$idSimulation;
	$ATMdb->Execute($sql);

	$res = '';
	while($ATMdb->Get_line()){
		if($ATMdb->Get_field('statut') == 'OK' && $ATMdb->Get_field('date_selection') != '0000-00-00 00:00:00'){
			return $res =  '<img title="Accord" src="'.dol_buildpath('/financement/img/OK.png',1).'" />';
		}
		else if($ATMdb->Get_field('statut') == 'WAIT'){
			$res =  '<img title="En étude" src="'.dol_buildpath('/financement/img/WAIT.png',1).'" />';
		}
	} 
	
	return $res;

	$ATMdb->close();

	return $res;
}
	
function _fiche(&$ATMdb, &$simulation, $mode) {
	global $db, $langs, $user, $conf;
	
	if( $simulation->getId() == 0) {
			
		$simulation->duree = __get('duree', $simulation->duree, 'integer');
	//	$simulation->echeance = __get('echeance', $simulation->echeance, 'float');
		
	}

	if(empty($simulation->fk_soc)) $simulation->opt_no_case_to_settle = 1;
	
	$extrajs = array('/financement/js/financement.js', '/financement/js/dossier.js');
	llxHeader('',$langs->trans("Simulation"),'','','','',$extrajs);

	$affaire = new TFin_affaire;
	$financement = new TFin_financement;
	$grille = new TFin_grille_leaser();
	$html=new Form($db);
	$form=new TFormCore($_SERVER['PHP_SELF'].'#calculateur','formSimulation','POST'); //,FALSE,'onsubmit="return soumettreUneSeuleFois(this);"'
	$form->Set_typeaff($mode);

	echo $form->hidden('id', $simulation->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $simulation->fk_soc);
	echo $form->hidden('fk_user_author', !empty($simulation->fk_user_author) ? $simulation->fk_user_author : $user->id);
	echo $form->hidden('entity', $conf->entity);
	echo $form->hidden('idLeaser', FIN_LEASER_DEFAULT);

	$TBS=new TTemplateTBS();
	$ATMdb=new TPDOdb;
	
	dol_include_once('/core/class/html.formfile.class.php');
	$formfile = new FormFile($db);
	$filename = dol_sanitizeFileName($simulation->getRef());
	$filedir = $conf->financement->dir_output . '/' . dol_sanitizeFileName($simulation->getRef());
	
	$TDuree = $grille->get_duree($ATMdb,FIN_LEASER_DEFAULT,$simulation->fk_type_contrat,$simulation->opt_periodicite);
	//var_dump($TDuree);
	$can_preco = ($user->rights->financement->allsimul->simul_preco && $simulation->fk_soc > 0) ? 1 : 0;
	
	if($user->rights->financement->admin->write && ($mode == "add" || $mode == "new" || $mode == "edit")){
		$formdolibarr = new Form($db);
		$rachat_autres = "texte";
		$TUserInclude = array();
		$TUserInclude = TRequeteCore::_get_id_by_sql($ATMdb, "SELECT u.rowid 
															FROM ".MAIN_DB_PREFIX."user as u 
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user as ugu ON (ugu.fk_user = u.rowid)
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup as ug ON (ug.rowid = ugu.fk_usergroup)
															WHERE ug.nom = 'GSL_DOLIBARR_FINANCEMENT_ADMIN' OR 
																  ug.nom = 'GSL_DOLIBARR_FINANCEMENT_ADV' OR
																  ug.nom = 'GSL_DOLIBARR_FINANCEMENT_COMMERCIAL'");
		//pre($TUserExculde,true); exit;
		$link_user = $formdolibarr->select_dolusers($simulation->fk_user_author,'fk_user_author',1,'',0,$TUserInclude,'',$conf->entity);
		
		$TUserInclude = TRequeteCore::_get_id_by_sql($ATMdb, "SELECT u.rowid 
															FROM ".MAIN_DB_PREFIX."user as u 
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user as ugu ON (ugu.fk_user = u.rowid)
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup as ug ON (ug.rowid = ugu.fk_usergroup)
															WHERE ug.nom = 'GSL_DOLIBARR_FINANCEMENT_ADMIN'");
		
		$link_user_suivi = $formdolibarr->select_dolusers($simulation->fk_user_suivi,'fk_user_suivi',1,'',0,$TUserInclude,'',$conf->entity);
	}
	else{
		$rachat_autres = "texteRO";
		$link_user = '<a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$simulation->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$simulation->user->login.'</a>';
		$link_user_suivi = '<a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$simulation->fk_user_suivi.'">'.img_picto('','object_user.png', '', 0).' '.$simulation->user_suivi->login.'</a>';
	}
	
	print $TBS->render('./tpl/simulation.tpl.php'
		,array(
			
		)
		,array(
			'simulation'=>array(
				'titre_simul'=>load_fiche_titre($langs->trans("CustomerInfo"),'','object_company.png')
				,'titre_calcul'=>load_fiche_titre($langs->trans("Simulator"),'','object_simul.png@financement')
				,'titre_dossier'=>load_fiche_titre($langs->trans("DossierList"),'','object_financementico.png@financement')
				
				,'id'=>$simulation->rowid
				,'ref'=>$simulation->reference
				,'doc'=>$formfile->getDocumentsLink('financement', $filename, $filedir)
				,'fk_soc'=>$simulation->fk_soc
				,'fk_type_contrat'=>$form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat)
				,'opt_administration'=>$form->checkbox1('', 'opt_administration', 1, $simulation->opt_administration) 
				,'opt_adjonction'=>$form->checkbox1('', 'opt_adjonction', 1, $simulation->opt_adjonction) 
				,'opt_periodicite'=>$form->combo('', 'opt_periodicite', $financement->TPeriodicite, $simulation->opt_periodicite) 
				//,'opt_creditbail'=>$form->checkbox1('', 'opt_creditbail', 1, $simulation->opt_creditbail)
				,'opt_mode_reglement'=>$form->combo('', 'opt_mode_reglement', $financement->TReglement, $simulation->opt_mode_reglement)
				,'opt_calage'=>$form->combo('', 'opt_calage', $financement->TCalage, $simulation->opt_calage)
				,'opt_terme'=>$form->combo('', 'opt_terme', $financement->TTerme, $simulation->opt_terme)
				,'date_demarrage'=>$form->calendrier('', 'date_demarrage', $simulation->get_date('date_demarrage'), 12)
				,'montant'=>$form->texte('', 'montant', $simulation->montant, 10)
				,'montant_rachete'=>$form->texteRO('', 'montant_rachete', $simulation->montant_rachete, 10)
				,'montant_decompte_copies_sup'=>$form->texteRO('', 'montant_decompte_copies_sup', $simulation->montant_decompte_copies_sup, 10)
				,'montant_rachat_final'=>$form->texteRO('', 'montant_rachat_final', $simulation->montant_rachat_final, 10)
				,'montant_rachete_concurrence'=>$form->texte('', 'montant_rachete_concurrence', $simulation->montant_rachete_concurrence, 10)
				,'duree'=>$form->combo('', 'duree', $TDuree, $simulation->duree)
				,'echeance'=>$form->texte('', 'echeance', $simulation->echeance, 10)
				,'vr'=>$form->texte('', 'vr', $simulation->vr, 10)
				,'coeff'=>$form->texteRO('', 'coeff', $simulation->coeff, 5)
				,'coeff_final'=>$can_preco ? $form->texte('', 'coeff_final', $simulation->coeff_final, 5) : $simulation->coeff_final
				,'montant_presta_trim'=>$form->texte('', 'montant_presta_trim', $simulation->montant_presta_trim, 10)
				,'cout_financement'=>$simulation->cout_financement
				,'accord'=>$user->rights->financement->allsimul->simul_preco ? $form->combo('', 'accord', $simulation->TStatut, $simulation->accord) : $simulation->TStatut[$simulation->accord]
				,'can_resend_accord'=>$simulation->accord
				,'date_validite'=>$simulation->accord == 'OK' ? 'Validité : '.$simulation->get_date('date_validite') : ''
				,'commentaire'=>$form->zonetexte('', 'commentaire', $simulation->commentaire, 50,3)
				,'accord_confirme'=>$simulation->accord_confirme
				,'total_financement'=>$simulation->montant_total_finance
				,'type_materiel'=>$form->texte('','type_materiel',$simulation->type_materiel, 50)
				,'marque_materiel'=>$form->combo('','marque_materiel',$simulation->TMarqueMateriel,$simulation->marque_materiel)
				,'numero_accord'=>$can_preco ? $form->texte('','numero_accord',$simulation->numero_accord, 20) : $simulation->numero_accord
				
				,'no_case_to_settle'=>$form->checkbox1('', 'opt_no_case_to_settle', 1, $simulation->opt_no_case_to_settle) 
				
				,'accord_val'=>$simulation->accord
				,'can_preco'=>$can_preco
				
				,'user'=>$link_user
				,'user_suivi'=>$link_user_suivi
				,'date'=>$simulation->date_simul
				,'bt_calcul'=>$form->btsubmit('Calculer', 'calculate')
				,'bt_cancel'=>$form->btsubmit('Annuler', 'cancel')
				,'bt_save'=>$form->btsubmit('Enregistrer simulation', 'validate_simul') //'onclick="$(this).remove(); $("#formSimulation").submit();"'
				
				,'display_preco'=>$can_preco
				,'type_financement'=>$can_preco ? $form->combo('', 'type_financement', array_merge(array(''=> ''), $affaire->TTypeFinancement), $simulation->type_financement) : $simulation->type_financement
				,'leaser'=>($mode=='edit' && $can_preco) ? $html->select_company($simulation->fk_leaser,'fk_leaser','fournisseur=1',1,0,1) : (($simulation->fk_leaser > 0) ? $simulation->leaser->getNomUrl(1) : '')
			)
			,'client'=>array(
				'societe'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$simulation->fk_soc.'">'.img_picto('','object_company.png', '', 0).' '.$simulation->societe->nom.'</a>'
				,'autres_simul'=>'<a href="'.DOL_URL_ROOT.'/custom/financement/simulation.php?socid='.$simulation->fk_soc.'">(autres simulations)</a>'
				,'adresse'=>$simulation->societe->address
				,'cpville'=>$simulation->societe->cp.' / '.$simulation->societe->ville
				,'siret'=>$simulation->societe->idprof2
				,'naf'=>$simulation->societe->idprof3
				,'code_client'=>$simulation->societe->code_client
				,'display_score'=>$user->rights->financement->score->read ? 1 : 0
				,'score_date'=>empty($simulation->societe) ? '' : $simulation->societe->score->get_date('date_score')
				,'score'=>empty($simulation->societe) ? '' : $simulation->societe->score->score
				,'encours_cpro'=>empty($simulation->societe) ? 0 : $simulation->societe->encours_cpro
				,'encours_conseille'=>empty($simulation->societe) ? '' : $simulation->societe->score->encours_conseille
				
				,'contact_externe'=>empty($simulation->societe) ? '' : $simulation->societe->score->get_nom_externe()
				
				,'liste_dossier'=>_liste_dossier($ATMdb, $simulation, $mode)
				
				,'nom'=>$simulation->societe->nom
				,'siren'=>(($simulation->societe->idprof1) ? $simulation->societe->idprof1 : $simulation->societe->idprof2)
				
			)
			,'view'=>array(
				'mode'=>$mode
				,'type'=>($simulation->fk_soc > 0) ? 'simul' : 'calcul'
				,'calcul'=>empty($simulation->montant_total_finance) ? 0 : 1
				,'pictoMail'=>img_picto('','stcomm0.png', '', 0)
			)
			
			,'user'=>$user
			
		)
	);
	
	echo $form->end_form();
	// End of page
	
	if($user->rights->financement->allsimul->suivi_leaser){
		_fiche_suivi($ATMdb, $simulation, $mode);
	}
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}

function _fiche_suivi(&$ATMdb, &$simulation, $mode){
	global $conf, $db, $langs;
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'form_suivi_simulation','POST');
	$form->Set_typeaff('edit');
	
	echo $form->hidden('action', 'save_suivi');
	echo $form->hidden('id', $simulation->getId());
	$TLignes = $simulation->get_suivi_simulation($ATMdb,$form);
	
	//pre($TLignes,true);exit;
	
	$TBS=new TTemplateTBS;
	
	print $TBS->render('./tpl/simulation_suivi.tpl.php'
		,array(
			'ligne' => $TLignes
		)
		,array(
			'view'=>array(
				'mode'=>$mode
				,'type'=>($simulation->fk_soc > 0) ? 'simul' : 'calcul'
				,'titre'=>load_fiche_titre($langs->trans("SimulationSuivi"),'','object_simul.png@financement')
			)
		)
	);
	
	$form->end_form();
}


function _calcul(&$simulation, $mode='calcul') {
	global $mesg, $error, $langs, $db;

	$options = array();
	foreach($_POST as $k => $v) {
		if(substr($k, 0, 4) == 'opt_') {
			$options[$k] = $v;
		}
	}
	
	$ATMdb=new TPDOdb;
	$calcul = $simulation->calcul_financement($ATMdb, FIN_LEASER_DEFAULT, $options); // Calcul du financement
		
	if(!$calcul) { // Si calcul non correct
		$simulation->montant_total_finance = 0;
		$mesg = $langs->trans($simulation->error);
		$error = true;
	} else if($simulation->accord_confirme == 0) { // Sinon, vérification accord à partir du calcul
		$simulation->demande_accord();
		if($simulation->accord == 'OK') {
			$simulation->date_accord = time();
			$simulation->date_validite = strtotime('+ 3 months');
		}
		if($mode == 'save' && ($simulation->accord == 'OK' || $simulation->accord == 'KO')) { // Si le vendeur enregistre sa simulation est OK automatique, envoi mail
			$simulation->send_mail_vendeur(true);
		}
	}
}

function _liste_dossier(&$ATMdb, &$simulation, $mode) {
	//if(!empty($simulation->date_accord) && $simulation->date_accord < strtotime('-15 days')) return ''; // Ticket 916 -15 jours
	
	//pre($simulation,true);
	
	global $langs,$conf, $db, $bc;
	$r = new TListviewTBS('dossier_list', './tpl/simulation.dossier.tpl.php');

	$sql = "SELECT a.rowid as 'IDAff', a.reference as 'N° affaire', a.contrat as 'Type contrat'";
	$sql.= " , d.rowid as 'IDDoss', f.incident_paiement";
	//$sql.= " , f.reference as 'N° contrat', f.date_debut as 'Début', f.date_fin as 'Fin'";
	//$sql.= " , ac.fk_user";
	//$sql.= " , u.login as 'Utilisateur'";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement f ON (f.fk_fin_dossier = d.rowid AND type='LEASER')";
	//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = a.fk_soc)";
	//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire_commercial ac ON ac.fk_fin_affaire = a.rowid";
	//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON ac.fk_user = u.rowid";
	$sql.= " WHERE a.entity = ".$conf->entity;
	//$sql.= " AND a.fk_soc = ".$simulation->fk_soc;
	$sql.= " AND a.fk_soc IN (
				SELECT s.rowid 
				FROM ".MAIN_DB_PREFIX."societe as s
					LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON (se.fk_object = s.rowid)
				WHERE (s.siren = (
							SELECT siren 
							from ".MAIN_DB_PREFIX."societe 
							WHERE rowid = ".$simulation->fk_soc."
							) 
					   AND s.siren != '') 
					   OR (se.other_siren = (
					   		SELECT other_siren 
					   		FROM ".MAIN_DB_PREFIX."societe_extrafields 
					   		WHERE fk_object = ".$simulation->fk_soc."
					   		) AND se.other_siren != ''))";
	//$sql.= " AND s.rowid = ".$simulation->fk_soc;
	//$sql.= " AND f.type = 'CLIENT'";
	
	//$sql.= " AND d.montant < 50000";
	
	//return $sql;
	
	//return $sql;
	$TDossier = array();
	$form=new TFormCore;
	$form->Set_typeaff($mode);
	$ATMdb->Execute($sql);
	$ATMdb2 = new TPDOdb;
	$var = true;
	
	$TDossierUsed = $simulation->get_list_dossier_used(true);
	//pre($ATMdb->Get_field('IDDoss'),true);
	//echo $sql;
	while ($ATMdb->Get_line()) {
		$affaire = new TFin_affaire;
		$dossier=new TFin_Dossier;
		$dossier->load($ATMdb2, $ATMdb->Get_field('IDDoss'));
		$leaser = new Societe($db);
		$leaser->fetch($dossier->financementLeaser->fk_soc);

		
		if($dossier->nature_financement == 'INTERNE') {
			$fin = &$dossier->financement;
			/*$soldeR = round($dossier->getSolde($ATMdb2, 'SRCPRO'),2);
			$soldeNR = round($dossier->getSolde($ATMdb2, 'SNRCPRO'),2);
			$soldeR1 = round($dossier->getSolde($ATMdb2, 'SRCPRO', $fin->duree_passe + 1),2);
			$soldeNR1 = round($dossier->getSolde($ATMdb2, 'SNRCPRO', $fin->duree_passe + 1),2);*/
		} else {
			$fin = &$dossier->financementLeaser;
			/*$soldeR = round($dossier->getSolde($ATMdb2, 'SRBANK'),2);
			$soldeNR = round($dossier->getSolde($ATMdb2, 'SNRBANK'),2);
			$soldeR1 = round($dossier->getSolde($ATMdb2, 'SRBANK', $fin->duree_passe + 1),2);
			$soldeNR1 = round($dossier->getSolde($ATMdb2, 'SNRBANK', $fin->duree_passe + 1),2);*/
		}
		//echo $fin->reference.'<br>';
		//if($fin->duree <= $fin->numero_prochaine_echeance) continue;
		
		if($fin->date_solde > 0 && empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['checked'])) continue;
		//if($fin->duree <= $fin->numero_prochaine_echeance) continue;
		if(empty($dossier->financementLeaser->reference)) continue;
		
		//Calcul du Solde Renouvelant et Non Renouvelant CPRO 
		/*$dossier->financement->capital_restant = $dossier->financement->montant;
		$dossier->financement->total_loyer = $dossier->financement->montant;
		for($i=0; $i<$dossier->financement->numero_prochaine_echeance;$i++){
			$capital_amortit = $dossier->financement->amortissement_echeance( $i+1 ,$dossier->financement->capital_restant);
			$part_interet = $dossier->financement->echeance - $capital_amortit;
			$dossier->financement->capital_restant-=$capital_amortit;
			
			$dossier->financement->total_loyer -= $dossier->financement->echeance;
		}*/
		
		//pre($simulation,true);
		if($dossier->nature_financement == 'INTERNE') {
			$soldeR = (!empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRNRSAME',$dossier->_get_num_echeance_from_date(time())+1),2); //SRCPRO
			$soldeNR = (!empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRNRSAME',$dossier->_get_num_echeance_from_date(time())+1),2); //SNRCPRO
			$soldeR1 = (!empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRNRSAME',$dossier->_get_num_echeance_from_date(time())+2),2); //SRCPRO
			$soldeNR1 = (!empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRNRSAME',$dossier->_get_num_echeance_from_date(time())+2),2); //SNRCPRO
			$soldeperso = round($dossier->getSolde($ATMdb2, 'perso'),2);
		}
		else{
			$soldeR = (!empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO'),2);
			$soldeNR = (!empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO'),2);
			$soldeR1 = (!empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $fin->duree_passe + 1),2);
			$soldeNR1 = (!empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO', $fin->duree_passe + 1),2);
			$soldeperso = round($dossier->getSolde($ATMdb2, 'perso'),2);
		}

		//Suite PR1504-0764, Solde R et NR deviennent identique
		/*$soldeNR = $soldeR;
		$soldeNR1 = $soldeR1;*/
		
		if(empty($dossier->display_solde)) {
			$soldeR = 0;
			$soldeNR = 0;
			$soldeR1 = 0;
			$soldeNR1 = 0;
			$soldeperso = 0;
		}
		
		$dossier_for_integral = new TFin_dossier;
		$dossier_for_integral->load($ATMdb2, $dossier->getId());
		$dossier_for_integral->load_facture($ATMdb2,true);
		//$dossier_for_integral->format_facture_integrale($PDOdb);
		//pre($dossier_for_integral->TFacture,true);
		$sommeRealise = $sommeNoir = $sommeCouleur = $sommeCopieSupCouleur = $sommeCopieSupNoir = 0;
		//list($sommeRealise,$sommeNoir,$sommeCouleur) = $dossier_for_integral->getSommesIntegrale($PDOdb);
		list($sommeCopieSupNoir,$sommeCopieSupCouleur) = $dossier_for_integral->getSommesIntegrale($ATMdb2,true);
		
		$decompteCopieSupNoir = $sommeCopieSupNoir * $dossier_for_integral->quote_part_noir;
		$decompteCopieSupCouleur = $sommeCopieSupCouleur * $dossier_for_integral->quote_part_couleur;
		
		$soldepersointegrale = $decompteCopieSupCouleur + $decompteCopieSupNoir;
		
		if(!$dossier->getSolde($ATMdb2, 'perso')){
			$soldeperso = ($soldepersointegrale * (FINANCEMENT_PERCENT_RETRIB_COPIES_SUP/100)); //On ne prend que 80% conformément  la règle de gestion
		}
		
		/*
		$checked = in_array($ATMdb->Get_field('IDDoss'), $simulation->dossiers_rachetes) ? true : false;
		$checkbox_more = 'solde_r="'.$soldeR.'"';
		$checkbox_more.= ' solde_nr="'.$soldeNR.'"';
		$checkbox_more.= ' contrat="'.$ATMdb->Get_field('Type contrat').'"';
		$checkbox_more.= in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		$checked1 = in_array($ATMdb->Get_field('IDDoss'), $simulation->dossiers_rachetes_p1) ? true : false;
		$checkbox_more1 = 'solde_r="'.$soldeR1.'"';
		$checkbox_more1.= ' solde_nr="'.$soldeNR1.'"';
		$checkbox_more1.= ' contrat="'.$ATMdb->Get_field('Type contrat').'"';
		$checkbox_more1.= in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		*/
		//echo $ATMdb->Get_field('IDDoss')." ";
		// Changement du 13.09.02 : les 4 soldes sont "cochables"
		$checkedr = (!empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkednr = (!empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkbox_moreR = 'solde="'.$soldeR.'" style="display: none;"';
		$checkbox_moreR.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		$checkbox_moreNR = ' solde="'.$soldeNR.'" style="display: none;"';
		$checkbox_moreNR.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		$checkedr1 = (!empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkednr1 = (!empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkbox_moreR1 = 'solde="'.$soldeR1.'" style="display: none;"';
		$checkbox_moreR1.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		$checkbox_moreNR1 = ' solde="'.$soldeNR1.'" style="display: none;"';
		$checkbox_moreNR1.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		$checkedperso = (is_array($simulation->dossiers_rachetes_perso) && in_array($ATMdb->Get_field('IDDoss'), $simulation->dossiers_rachetes_perso)) ? true : false;
		$checkbox_moreperso = 'solde="'.$soldeperso.'" style="display: none;"';
		$checkbox_moreperso.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		/*
		 * Mise en commentaire des ancienne règle d'afficahge des soldes suite PR1504-0764 avec gestion des soldes V2
		 */
		//if($ATMdb->Get_field('incident_paiement')=='OUI') $dossier->display_solde = 0;
		//if($dossier->nature_financement == 'INTERNE') $dossier->display_solde = 0; // Ticket 447
		//if($leaser->code_client == '024242') $dossier->display_solde = 0; // Ticket 447, suite
		if($dossier->montant >= 50000 && $dossier->nature_financement == 'INTERNE') $dossier->display_solde = 0;// On ne prends que les dossiers < 50 000€ pour faire des tests
		if($dossier->soldepersodispo == 2) $dossier->display_solde = 0;
		
		//Ne pas laissé disponible un dossier dont la dernière facture client est impayée
		$cpt = 0;
		$TFactures = array_reverse($dossier->TFacture,true);
		foreach ($TFactures as $echeance => $facture) {
			if(is_array($facture)){
				foreach ($facture as $key => $fact) {
					if($fact->paye == 0){
						$cpt ++;
						if($cpt > FINANCEMENT_NB_INVOICE_UNPAID){
							$dossier->display_solde = 0;
						}
					}
				}
			}
			else{
				if($fact->paye == 0){
					$cpt ++;
					if($cpt > FINANCEMENT_NB_INVOICE_UNPAID){
						$dossier->display_solde = 0;
					}
				}
			}
			break;
		}

		$row = array(
			'id_affaire' => $ATMdb->Get_field('IDAff')
			,'num_affaire' => $ATMdb->Get_field('N° affaire')
			,'id_dossier' => $dossier->getId()
			,'num_contrat' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['num_contrat']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['num_contrat'] :$fin->reference
			,'type_contrat' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['type_contrat']) ? $affaire->TContrat[$simulation->dossiers[$ATMdb->Get_field('IDDoss')]['type_contrat']] : $affaire->TContrat[$ATMdb->Get_field('Type contrat')]
			,'duree' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['duree']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['duree'] :$fin->duree.' '.substr($fin->periodicite,0,1)
			,'echeance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['echeance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['echeance'] : $fin->echeance
			,'loyer_actualise' => ($dossier->nature_financement == 'INTERNE') ? ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['loyer_actualise']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['loyer_actualise'] : $fin->loyer_actualise : ''
			,'debut' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_debut']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_debut'] :$fin->date_debut
			,'fin' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_fin']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_fin'] : $fin->date_fin
			,'prochaine_echeance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_prochaine_echeance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_prochaine_echeance'] : $fin->date_prochaine_echeance
			,'avancement' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['numero_prochaine_echeance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['numero_prochaine_echeance'] : $fin->numero_prochaine_echeance.'/'.$fin->duree
			,'terme' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['terme']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['terme'] : $fin->TTerme[$fin->terme]
			,'reloc' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['reloc']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['reloc'] : $fin->reloc
			,'solde_r' => $soldeR
			,'solde_nr' => $soldeNR
			,'solde_r1' => $soldeR1
			,'solde_nr1' => $soldeNR1
			,'soldeperso' => $soldeperso
			,'display_solde' => $dossier->display_solde
			,'fk_user' => $ATMdb->Get_field('fk_user')
			,'user' => $ATMdb->Get_field('Utilisateur')
			,'leaser' => $leaser->getNomUrl(0)
			,'choice_solde' => ($simulation->contrat == $ATMdb->Get_field('Type contrat')) ? 'solde_r' : 'solde_nr'
			,'checkboxr'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkedr, $checkbox_moreR) : ''
			,'checkboxnr'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_nr['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkednr, $checkbox_moreNR) : ''
			,'checkboxr1'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_p1['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkedr1, $checkbox_moreR1) : ''
			,'checkboxnr1'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_nr_p1['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkednr1, $checkbox_moreNR1) : ''
			,'montantr'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeR, $checkbox_moreR) : ''
			,'montantnr'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_nr['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeNR, $checkbox_moreNR) : ''
			,'montantr1'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_p1['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeR1, $checkbox_moreR1) : ''
			,'montantnr1'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_nr_p1['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeNR1, $checkbox_moreNR1) : ''
			,'checkboxperso'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_perso['.$ATMdb->Get_field('IDDoss').']', $ATMdb->Get_field('IDDoss'),$checkbox_moreperso) : ''
			,'checkedperso'=>$checkedperso
			,'checkedr'=>$checkedr
			,'checkednr'=>$checkednr
			,'checkedr1'=>$checkedr1
			,'checkednr1'=>$checkednr1
			
			,'maintenance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['maintenance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['maintenance'] : $fin->montant_prestation
			,'assurance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance'] :$fin->assurance
			,'assurance_actualise' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance_actualise']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance_actualise'] :$fin->assurance_actualise
			,'montant' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['montant']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['montant'] : $fin->montant
			
			,'class' => $bc[$var]
			
			,'incident_paiement'=>$incident_paiement
		);
		//pre($row,true);
		$TDossier[$dossier->getId()] = $row;

		$var = !$var;
	}
	
	$THide = array('IDAff', 'IDDoss', 'fk_user', 'Type contrat');

	//pre($simulation,true);
	//pre($TDossier,true);exit;
	return $r->renderArray($ATMdb, $TDossier, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'10'
		)
		,'orderBy'=>array(
			'num_affaire' => 'DESC'
		)
		,'link'=>array(
			'num_affaire'=>'<a href="affaire.php?id=@id_affaire@">@val@</a>'
			,'num_contrat'=>'<a href="dossier.php?id=@id_dossier@">@val@</a>'
			,'user'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'hide'=>$THide
		,'type'=>array('Début'=>'date', 'Fin'=>'date')
		,'liste'=>array(
			'titre'=>'Liste des imports'
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun dossier à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			
		)
	));
	
	$THide = array('IDAff', 'IDDoss', 'fk_user');
	
	return $r->render($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'10'
		)
		,'orderBy'=>array(
			'N° affaire' => 'DESC'
		)
		,'link'=>array(
			'N° affaire'=>'<a href="affaire.php?id=@IDAff@">@val@</a>'
			,'Utilisateur'=>'<a href="'.DOL_URL_ROOT.'/user/fiche.php?id=@fk_user@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'hide'=>$THide
		,'type'=>array('Début'=>'date', 'Fin'=>'date')
		,'liste'=>array(
			'titre'=>'Liste des imports'
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun dossier à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			
		)
	));
}
