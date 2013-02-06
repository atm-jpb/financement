<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	
	require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
	require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

	$langs->load('financement@financement');
	
	
	
	if (!$user->rights->financement->affaire->read)	{ accessforbidden(); }
	
	$affaire=new TFin_Affaire;
	$ATMdb = new Tdb;
	$tbs = new TTemplateTBS;
	
	$mesg = '';
	$error=false;
	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'add':
			case 'new':
				
				$affaire->set_values($_REQUEST);
	
				//$affaire->save($ATMdb);
				_fiche($ATMdb, $affaire,'edit');
				
				break;	
			case 'edit'	:
			
				$affaire->load($ATMdb, $_REQUEST['id']);
				
				_fiche($ATMdb, $affaire,'edit');
				break;
				
			case 'save':
				$affaire->load($ATMdb, $_REQUEST['id']);
				$affaire->set_values($_REQUEST);
				
				//$ATMdb->db->debug=true;
				//print_r($_REQUEST);
				
				$affaire->save($ATMdb);
				
				_fiche($ATMdb, $affaire,'view');
				
				break;
			
				
			case 'delete':
				$affaire->load($ATMdb, $_REQUEST['id']);
				//$ATMdb->db->debug=true;
				$affaire->delete($ATMdb);
				
				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";					
				</script>
				<?
				
				
				break;
			case 'add_dossier':
			//$ATMdb->db->debug=true;
				$affaire->load($ATMdb, $_REQUEST['id']);
				$affaire->set_values($_REQUEST);
			
				if(!$affaire->addDossier($ATMdb, null, $_REQUEST['dossier_to_add'])) {
					$mesg = '<div class="error">Impossible d\'ajouter ce dossier à l\'affaire. </div>';
					$error=true;
					
				}	
				else {
					$mesg = '<div class="ok">Dossier ajouté à l\'affaire</div>';
				}
				//exit($mesg);
				$affaire->save($ATMdb);
				
				_fiche($ATMdb, $affaire,'edit');
				
				break;
				
			case 'delete_dossier':
				//$ATMdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				$affaire->load($ATMdb, $_REQUEST['id']);
				
			
				if($affaire->deleteDossier($ATMdb, $_REQUEST['id_dossier'])) {
					$mesg = '<div class="ok">Dossier retiré de l\'affaire</div>';	
				}	
				
				$affaire->save($ATMdb);
				
				_fiche($ATMdb, $affaire,'edit');
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$affaire->load($ATMdb, $_REQUEST['id']);
		
		_fiche($ATMdb, $affaire, 'view');global $mesg, $error;
	}
	else {
		/*
		 * Liste
		 */
		 _liste($ATMdb, $affaire);
	}
	
	
	
	llxFooter();
	
function _liste(&$ATMdb, &$affaire) {
global $langs,$conf, $db;	
	
	llxHeader('','Affaires');
	getStandartJS();
	
	$r = new TSSRenderControler($affaire);
	$sql="SELECT a.rowid as 'ID', a.reference as 'Numéro d\'affaire', a.montant as 'Montant', a.fk_soc, s.nom as 'Société', a.nature_financement as 'Financement : Nature', a.type_financement as 'Type', a.contrat as 'Type de contrat', a.date_affaire as 'Date de l\'affaire'
		FROM @table@ a LEFT JOIN llx_societe s ON (a.fk_soc=s.rowid)
		WHERE a.entity=".$conf->entity;
	
	$THide = array('fk_soc');
	
	if(isset($_REQUEST['socid'])) {
		$sql.= ' WHERE a.fk_soc='.$_REQUEST['socid'];
		$societe = new Societe($db);
		$societe->fetch($_REQUEST['socid']);
		$head = societe_prepare_head($societe);
		dol_fiche_head($head, 'affaire', $langs->trans("ThirdParty"),0,'company');
		
		$THide[] = 'Société';
	}
	
	$TOrder = array('ID'=>'DESC','Numéro d\'affaire'=>'ASC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
				
	$page = isset($_REQUEST['page']) ? $_REQUEST['page'] : 1;			
	//print $page;
	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>$page
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'Société'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_picto('','object_company.png', '', 0).' @val@</a>'
			,'Numéro d\'affaire'=>'<a href="?id=@ID@">@val@</a>'
		)
		,'translate'=>array(
			'Financement : Nature'=>$affaire->TNatureFinancement
			,'Type'=>$affaire->TTypeFinancement
		)
		,'hide'=>$THide
		,'type'=>array('Date de l\'affaire'=>'date', 'Montant'=>'money')
		,'liste'=>array(
			'titre'=>'Liste des affaires'
			,'image'=>img_picto('','title.png', '', 0)
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
		?><div class="tabsAction"><a href="?action=new&fk_soc=<?=$_REQUEST['socid'] ?>" class="butAction">Créer une affaire</a></div><?
	}
	
	llxFooter();
}	
	
function _fiche(&$ATMdb, &$affaire, $mode) {
global $db,$user;
	
	$societe = new Societe($db);
	$societe->fetch($affaire->fk_soc); 
	 
	 /*
	 * Liste des dossiers rattachés à cette affaire
	 */ 
	$TDossier=array();
	foreach($affaire->TLien as &$lien) {
		$dossier = &$lien->dossier;
		$dossier->load_financement($ATMdb);
		$TDossier[]=array(
			'id'=>$dossier->getId()
			,'reference'=>$dossier->financementLeaser->reference
			,'date_debut'=>$dossier->get_date('date_debut')
			,'date_fin'=>$dossier->get_date('date_fin')
			,'montant'=>$dossier->montant
			,'taux'=>$dossier->taux
			,'incident_paiement'=>$dossier->TIncidentPaiement [ $dossier->incident_paiement ]
			,'echeance1'=>$dossier->echeance1			
			,'echeance'=>$dossier->echeance			
		);
	}
	
	/*
	 * Pour autocomplete ajout dossier
	 */
	$otherDossier='';
	if($mode=='edit') {
		$ATMdb=new Tdb;
		$Tab = TRequeteCore::get_id_from_what_you_want($ATMdb,'llx_fin_dossier', "solde>0" ,'reference');
		$otherDossier = '["'. implode('","', $Tab). '"]';
		$ATMdb->close(); 
	}
	
	llxHeader('','Affaires');
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $affaire->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $affaire->fk_soc);
	
	//require('./tpl/affaire.tpl.php');
	$TBS=new TTemplateTBS();
	
	print $TBS->render('./tpl/affaire.tpl.php'
		,array(
			'dossier'=>$TDossier
		)
		,array(
			'affaire'=>array(
				'id'=>$affaire->rowid
				,'reference'=>$form->texte('', 'reference', $affaire->reference, 100,255,'','','à saisir') 
				,'nature_financement'=>$form->combo('', 'nature_financement', $affaire->TNatureFinancement , $affaire->nature_financement)
				,'type_financement'=>$form->combo('', '', $affaire->TTypeFinancement , $affaire->type_financement)
				,'contrat'=>$form->combo('', 'contrat', $affaire->TContrat , $affaire->contrat) 
				,'type_materiel'=>$form->combo('', '', $affaire->TTypeMateriel , $affaire->type_materiel)
				,'date_affaire'=>$form->calendrier('', 'date_affaire', $affaire->get_date('date_affaire'),10)
				,'montant'=>$form->texte('', 'montant', $affaire->montant, 20,255,'','','à saisir').' &euro;' 
				,'montant_ok'=>$affaire->somme_dossiers.' &euro;' // somme des dossiers rattachés
				,'solde'=>$affaire->solde.' &euro;' // montant à financer - somme des dossiers	
				,'date_maj'=>$affaire->get_date('date_maj','d/m/Y à H:i:s')
				,'date_cre'=>$affaire->get_date('date_cre','d/m/Y')
				
				,'societe'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$affaire->fk_soc.'">'.img_picto('','object_company.png', '', 0).' '.$societe->nom.'</a>'
				,'montant_val'=>$affaire->montant
				,'nature_financement_val'=>$affaire->nature_financement
				
				,'addDossierButton'=>(($affaire->montant>0 && $affaire->nature_financement!='') ? 1 : 0)
				
			)
			,'view'=>array(
				'mode'=>$mode
				,'otherDossier'=>$otherDossier
				,'userRight'=>((int)$user->rights->financement->affaire->write)
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}
