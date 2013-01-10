<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	$langs->load('financement@financement');
	$dossier=new TFin_Dossier;
	$ATMdb = new Tdb;
	$tbs = new TTemplateTBS;
	
	$mesg = '';
	$error=false;
	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'add':
			case 'new':
				
				$dossier->set_values($_REQUEST);
	
				$dossier->save($ATMdb);
				_fiche($dossier,'edit');
				
				break;	
			case 'edit'	:
			
				$dossier->load($ATMdb, $_REQUEST['id']);
				
				_fiche($dossier,'edit');
				break;
				
			case 'save':
				$dossier->load($ATMdb, $_REQUEST['id']);
				$dossier->set_values($_REQUEST);
				
				//$ATMdb->db->debug=true;
				//print_r($_REQUEST);
				
				$dossier->save($ATMdb);
				
				_fiche($dossier,'view');
				
				break;
			
				
			case 'delete':
				$dossier->load($ATMdb, $_REQUEST['id']);
				//$ATMdb->db->debug=true;
				$dossier->delete($ATMdb);
				
				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";					
				</script>
				<?
				
				
				break;
				
			case 'add_affaire':
			//$ATMdb->db->debug=true;
				$dossier->load($ATMdb, $_REQUEST['id']);
				$dossier->set_values($_REQUEST);
			
				if(!$dossier->addAffaire($ATMdb, null, $_REQUEST['affaire_to_add'])) {
					$mesg = '<div class="error">Impossible d\'ajouter cette affaire au dossier. </div>';
					$error=true;
					
				}	
				else {
					$mesg = '<div class="ok">Affaire ajoutée au dossier</div>';
				}
				//exit($mesg);
				$dossier->save($ATMdb);
				
				_fiche($dossier,'edit');
				
				break;
				
			case 'delete_affaire':
				//$ATMdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				$dossier->load($ATMdb, $_REQUEST['id']);
				
			
				if($dossier->deleteAffaire($ATMdb, $_REQUEST['id_affaire'])) {
					$mesg = '<div class="ok">Affaire retirée du dossier</div>';	
				}	
				
				$dossier->save($ATMdb);
				
				_fiche($dossier,'edit');
				
				break;	
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$dossier->load($ATMdb, $_REQUEST['id']);
		
		_fiche($dossier, 'view');
	}
	else {
		/*
		 * Liste
		 */
		 _liste($ATMdb, $dossier);
	}
	
	
	
	llxFooter();
	
function _liste(&$db, &$dossier) {
	llxHeader('','Dossiers');
	getStandartJS();
	
	$r = new TSSRenderControler($dossier);
	$sql="SELECT d.rowid as 'ID', d.reference as 'Numéro dossier', a.fk_soc as 'fk_soc', s.nom as 'Société', d.montant as 'Montant financé'
	, duree as 'Durée', date_debut as 'Début', date_fin as 'Fin',incident_paiement as 'Incident de paiment' 
	FROM (((@table@ d 
	LEFT OUTER JOIN  llx_fin_dossier_affaire l ON (d.rowid=l.fk_fin_dossier))
		LEFT OUTER JOIN llx_fin_affaire a ON (l.fk_fin_affaire=a.rowid))
			LEFT OUTER JOIN llx_societe s ON (a.fk_soc=s.rowid))";
			
			
	$r->liste($db, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'Société'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@"><img border="0" title="Afficher société: test" alt="Afficher société: test" src="'.DOL_URL_ROOT.'/theme/eldy/img/object_company.png"> @val@</a>'
			,'Numéro dossier'=>'<a href="?id=@ID@">@val@</a>'
		)
		,'translate'=>array(
			'Incident de paiment'=>$dossier->TIncidentPaiement
		)
		,'hide'=>array('fk_soc')
		,'type'=>array('Début'=>'date','Fin'=>'date')
		,'liste'=>array(
			'titre'=>"Liste des dossiers"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
		)
	));
	
	
	llxFooter();
}	
	
function _fiche(&$dossier, $mode) {
	/*
	 * Liste des dossiers rattachés à cette affaire
	 */ 
	$TAffaire=array();
	foreach($dossier->TLien as &$lien) {
		$affaire = &$lien->affaire;
		
		$TAffaire[]=array(
			'id'=>$affaire->getId()
			,'reference'=>$affaire->reference
			,'date_affaire'=>$affaire->get_date('date_affaire')
			,'montant'=>$affaire->montant
			,'nature_financement'=>$affaire->TNatureFinancement [ $affaire->nature_financement ]
			,'type_financement'=>$affaire->TTypeFinancement [ $affaire->type_financement ]
			,'type_materiel'=>$affaire->TTypeMateriel [ $affaire->type_materiel ]
			,'contrat'=>$affaire->TContrat [ $affaire->contrat ]
		);
	}
	
	/*
	 * Pour autocomplete ajout dossier
	 */
	$otherAffaire='';
	if($mode=='edit') {
		$db=new Tdb;
		$Tab = TRequeteCore::get_id_from_what_you_want($db,'llx_fin_affaire', "solde>0" ,'reference');
		$otherAffaire = '["'. implode('","', $Tab). '"]';
		$db->close(); 
	}
	
	llxHeader('','Dossier');
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $dossier->rowid);
	echo $form->hidden('action', 'save');
	
	//require('./tpl/affaire.tpl.php');
	$TBS=new TTemplateTBS();
	
	$financement=&$dossier->financement;
	$financementLeaser=&$dossier->financementLeaser;
	if($financementLeaser>0) {
		$TFinancementLeaser=array(
				'id'=>$financementLeaser->getId()
				,'montant'=>$form->texte('', 'leaser.montant', $financementLeaser->montant, 20,255,'','','à saisir').' &euro;' 
				,'taux'=>$form->texte('', 'leaser.taux', $financementLeaser->taux, 5,255,'','','à saisir').' %' 
				,'echeance1'=>$form->texte('', 'leaser.echeance1', $financementLeaser->echeance1, 20,255,'','','à saisir').' &euro;' 
				,'echeance'=>$form->texte('', 'leaser.echeance', $financementLeaser->echeance, 20,255,'','','à saisir') .' &euro;'
				,'reste'=>$form->texte('', 'leaser.reste', $financementLeaser->reste, 20,255,'','','à saisir').' &euro;' 
				,'montant_prestation'=>$form->texte('', 'leaser.montant_prestation', $financementLeaser->montant_prestation, 20,255,'','','à saisir').' &euro;' 
					
				,'numero_prochaine_echeance'=>$form->texte('', 'leaser.numero_prochaine_echeance', $financementLeaser->numero_prochaine_echeance, 5,255,'','','à saisir') 
				,'duree'=>$form->texte('', 'duree', $financementLeaser->duree, 5,255,'','','à saisir')
									
								
				,'periodicite'=>$form->combo('', 'leaser.periodicite', $financementLeaser->TPeriodicite , $financementLeaser->periodicite)
				,'reglement'=>$form->combo('', 'leaser.reglement', $financementLeaser->TReglement , $financementLeaser->reglement)
				,'incident_paiement'=>$form->combo('', 'leaser.incident_paiement', $financementLeaser->TIncidentPaiement , $financementLeaser->incident_paiement) 
				
				,'date_debut'=>$form->calendrier('', 'leaser.date_debut', $financementLeaser->get_date('date_debut'),10)
				,'date_fin'=>$form->calendrier('', 'leaser.date_fin', $financementLeaser->get_date('date_fin'),10)
				,'date_prochaine_echeance'=>$form->calendrier('', 'leaser.date_prochaine_echeance', $financementLeaser->get_date('date_prochaine_echeance'),10)
				
				
		);
		
	}
	else {
		$TFinancementLeaser= array();
	}
	
	
	print $TBS->render('./tpl/dossier.tpl.php'
		,array(
			'affaire'=>$TAffaire
		)
		,array(
			'dossier'=>array(
				'id'=>$dossier->rowid
				,'reference'=>$form->texte('', 'reference', $dossier->reference, 100,255,'','','à saisir') 
				,'date_relocation'=>$form->calendrier('', 'date_relocation', $dossier->get_date('date_relocation'),10)
				,'date_maj'=>$dossier->get_date('date_maj','d/m/Y à H:i:s')
				,'date_cre'=>$dossier->get_date('date_cre','d/m/Y')
				,'solde'=>$dossier->solde
				,'montant_ok'=>$dossier->somme_affaire
				
				
				)
			,'financement'=>array(
				'montant'=>$form->texte('', 'montant', $financement->montant, 20,255,'','','à saisir').' &euro;' 
				,'taux'=>$form->texte('', 'taux', $financement->taux, 5,255,'','','à saisir').' %' 
				,'echeance1'=>$form->texte('', 'echeance1', $financement->echeance1, 20,255,'','','à saisir').' &euro;' 
				,'echeance'=>$form->texte('', 'echeance', $financement->echeance, 20,255,'','','à saisir') .' &euro;'
				,'reste'=>$form->texte('', 'reste', $financement->reste, 20,255,'','','à saisir').' &euro;' 
				,'montant_prestation'=>$form->texte('', 'montant_prestation', $financement->montant_prestation, 20,255,'','','à saisir').' &euro;' 
					
				,'numero_prochaine_echeance'=>$form->texte('', 'numero_prochaine_echeance', $financement->numero_prochaine_echeance, 5,255,'','','à saisir') 
				,'duree'=>$form->texte('', 'duree', $financement->duree, 5,255,'','','à saisir')
									
								
				,'periodicite'=>$form->combo('', 'periodicite', $financement->TPeriodicite , $financement->periodicite)
				,'reglement'=>$form->combo('', 'reglement', $financement->TReglement , $financement->reglement)
				,'incident_paiement'=>$form->combo('', 'incident_paiement', $financement->TIncidentPaiement , $financement->incident_paiement) 
				
				,'date_debut'=>$form->calendrier('', 'date_debut', $financement->get_date('date_debut'),10)
				,'date_fin'=>$form->calendrier('', 'date_fin', $financement->get_date('date_fin'),10)
				,'date_prochaine_echeance'=>$form->calendrier('', 'date_prochaine_echeance', $financement->get_date('date_prochaine_echeance'),10)
				
				
			)
			,'financementLeaser'=>$TFinancementLeaser
			
			,'view'=>array(
				'mode'=>$mode
				,'otherAffaire'=>$otherAffaire
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	
	llxFooter();
	
}
