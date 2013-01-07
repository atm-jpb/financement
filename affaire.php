<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	$langs->load('financement@financement');
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
	
				$affaire->save($ATMdb);
				_fiche($affaire,'edit');
				
				break;	
			case 'edit'	:
			
				$affaire->load($ATMdb, $_REQUEST['id']);
				
				_fiche($affaire,'edit');
				break;
				
			case 'save':
				$affaire->load($ATMdb, $_REQUEST['id']);
				$affaire->set_values($_REQUEST);
				
				//$ATMdb->db->debug=true;
				//print_r($_REQUEST);
				
				$affaire->save($ATMdb);
				
				_fiche($affaire,'view');
				
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
				
				_fiche($affaire,'edit');
				
				break;
				
			case 'delete_dossier':
				//$ATMdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				$affaire->load($ATMdb, $_REQUEST['id']);
				
			
				if($affaire->deleteDossier($ATMdb, $_REQUEST['id_dossier'])) {
					$mesg = '<div class="ok">Dossier retiré de l\'affaire</div>';	
				}	
				
				$affaire->save($ATMdb);
				
				_fiche($affaire,'edit');
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$affaire->load($ATMdb, $_REQUEST['id']);
		
		_fiche($affaire, 'view');global $mesg, $error;
	}
	else {
		/*
		 * Liste
		 */
		 _liste($ATMdb, $affaire);
	}
	
	
	
	llxFooter();
	
function _liste(&$db, &$affaire) {
	llxHeader('','Affaires');
	getStandartJS();
	
	$r = new TSSRenderControler($affaire);
	$sql="SELECT a.rowid as 'ID', a.reference as 'Numéro d\'affaire', a.fk_soc, s.nom as 'Société', a.nature_financement as 'Financement : Nature', a.type_financement as 'Type', a.contrat as 'Type de contrat', a.date_affaire as 'Date de l\'affaire'
	FROM @table@ a LEFT JOIN llx_societe s ON (a.fk_soc=s.rowid)";
	$r->liste($db, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'30'
		)
		/*,'subQuery'=>array(
			'Type de contrat'=>"SELECT code FROM llx_fin_const WHERE type='type_contrat'"
		)*/
		,'link'=>array(
			'Société'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@"><img border="0" title="Afficher société: test" alt="Afficher société: test" src="'.DOL_URL_ROOT.'/theme/eldy/img/object_company.png"> @val@</a>'
			,'Numéro d\'affaire'=>'<a href="?id=@ID@">@val@</a>'
		)
		,'translate'=>array(
			'Financement : Nature'=>$affaire->TNatureFinancement
			,'Type'=>$affaire->TTypeFinancement
		)
		,'hide'=>array('fk_soc')
		,'type'=>array('Date de l\'affaire'=>'date')
		,'liste'=>array(
			'titre'=>"Liste des affaires"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
		)
	));
	
	
	llxFooter();
}	
	
function _fiche(&$affaire, $mode) {
	/*
	 * Liste des dossiers rattachés à cette affaire
	 */ 
	$TDossier=array();
	foreach($affaire->TLien as &$lien) {
		$dossier = &$lien->dossier;
		
		$TDossier[]=array(
			'id'=>$dossier->getId()
			,'reference'=>$dossier->reference
			,'date_debut'=>$dossier->get_date('date_debut')
			,'date_fin'=>$dossier->get_date('date_fin')
			,'montant'=>$dossier->montant
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
		$db=new Tdb;
		$Tab = TRequeteCore::get_id_from_what_you_want($db,'llx_fin_dossier',array(),'reference');
		$otherDossier = '["'. implode('","', $Tab). '"]';
		$db->close(); 
	}
	
	llxHeader('','Affaires');
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $affaire->rowid);
	echo $form->hidden('action', 'save');
	
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
				,'montant_reste'=>($affaire->montant-$affaire->somme_dossiers).' &euro;' // montant à financer - somme des dossiers	
			)
			,'view'=>array(
				'mode'=>$mode
				,'otherDossier'=>$otherDossier
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
	
}
