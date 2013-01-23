<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	require('./class/grille.class.php');
	
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
				if(isset($_REQUEST['fk_fin_affaire'])) {
					if($dossier->addAffaire($ATMdb, $_REQUEST['fk_fin_affaire'])) {
						$dossier->financement->montant = $_REQUEST['montant'];
						if($_REQUEST['nature_financement']=='EXTERNE') {
							unset($dossier->financement);
						}
						$dossier->save($ATMdb);
					}
					else {
						$mesg = '<div class="error">Impossible d\'ajouter créer un dossier dans cette affaire. </div>';
						$error=true;
						
						_liste($ATMdb, $dossier);
					}
				}
				else {
					$dossier->save($ATMdb);
				}
	
				
				_fiche($ATMdb,$dossier,'edit');
				
				break;	
			case 'edit'	:
			
				$dossier->load($ATMdb, $_REQUEST['id']);
				
				_fiche($ATMdb,$dossier,'edit');
				break;
				
			case 'save':
				//$ATMdb->db->debug=true;
				
				$dossier->load($ATMdb, $_REQUEST['id']);
				$dossier->set_values($_REQUEST);
				if(isset($dossier->financement))$dossier->financement->set_values($_REQUEST);
				$dossier->financementLeaser->reference = $_REQUEST['leaser_reference']; 
				//print_r($dossier->financementLeaser);
				$dossier->save($ATMdb);
				//print 'nature_financement:'.$dossier->nature_financement;exit;
				_fiche($ATMdb,$dossier,'view');
				
				break;
			
				
			case 'delete':
				//$ATMdb->db->debug=true;
				$dossier->load($ATMdb, $_REQUEST['id']);
				$dossier->delete($ATMdb);
				
				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";					
				</script>
				<?
				unset($dossier);
				
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
				
				_fiche($ATMdb,$dossier,'edit');
				
				break;
				
			case 'delete_affaire':
				//$ATMdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				$dossier->load($ATMdb, $_REQUEST['id']);
				
			
				if($dossier->deleteAffaire($ATMdb, $_REQUEST['id_affaire'])) {
					$mesg = '<div class="ok">Affaire retirée du dossier</div>';	
				}	
				
				$dossier->save($ATMdb);
				
				_fiche($ATMdb,$dossier,'edit');
				
				break;	
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$dossier->load($ATMdb, $_REQUEST['id']);
		_fiche($ATMdb,$dossier, 'view');
	}
	else {
		/*
		 * Liste
		 */
		_liste($ATMdb, $dossier);
	}
	
	
	
	llxFooter();
	
function _liste(&$db, &$dossier) {
	global $conf;
	
	llxHeader('','Dossiers');
	getStandartJS();
	
	$r = new TSSRenderControler($dossier);
	$sql="SELECT d.rowid as 'ID', f.reference as 'Dossier', a.fk_soc as 'fk_soc', s.nom as 'Société', f.montant as 'Montant financé'
	, f.duree as 'Durée', f.date_debut as 'Début', f.date_fin as 'Fin', f.incident_paiement as 'Incident de paiment' 
	FROM ((((@table@ d
	LEFT OUTER JOIN  llx_fin_dossier_affaire l ON (d.rowid=l.fk_fin_dossier))
		LEFT OUTER JOIN llx_fin_affaire a ON (l.fk_fin_affaire=a.rowid))
			LEFT OUTER JOIN llx_fin_dossier_financement f ON (d.rowid=f.fk_fin_dossier AND ((a.nature_financement='INTERNE' AND f.type='CLIENT') OR (a.nature_financement='EXTERNE' AND f.type='LEASER')) ))
						LEFT OUTER JOIN llx_societe s ON (a.fk_soc=s.rowid))
		
		WHERE a.entity=".$conf->entity;
				
				
	$TOrder = array('ID'=>'DESC','Dossier'=>'ASC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
				
			
	$r->liste($db, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'Société'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@"><img border="0" title="Afficher société: test" alt="Afficher société: test" src="'.DOL_URL_ROOT.'/theme/eldy/img/object_company.png"> @val@</a>'
			,'Dossier'=>'<a href="?id=@ID@">@val@</a>'
		)
		,'translate'=>array(
			'Incident de paiment'=>$dossier->TIncidentPaiement
		)
		,'hide'=>array('fk_soc')
		,'type'=>array('Début'=>'date','Fin'=>'date', 'Montant financé'=>'money')
		,'liste'=>array(
			'titre'=>"Liste des dossiers"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'noheader'=>FALSE
			,'messageNothing'=>"Il n'y a aucun dossier"
			)
		,'orderBy'=>$TOrder
		
	));
	
	
	llxFooter();
}	
	
function _fiche(&$ATMdb, &$dossier, $mode) {
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
		
		if($affaire->nature_financement=='INTERNE' && !isset($dossier->financement) ) {
			$dossier->financementLeaser = new TFin_financement;
			$dossier->financementLeaser->fk_fin_dossier = $dossier->getId();
			$dossier->financementLeaser->type='CLIENT';
			$dossier->financementLeaser->save($ATMdb);
		}  
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

	$TFinancementLeaser=array(
			'id'=>$financementLeaser->getId()
			,'reference'=>$form->texte('', 'leaser_reference', $financementLeaser->reference, 30,255,'','','à saisir')
			,'montant'=>$financementLeaser->montant.' &euro;' 
			,'taux'=> $financementLeaser->taux.' %' 
			,'echeance1'=> $financementLeaser->echeance1.' &euro;' 
			,'echeance'=> $financementLeaser->echeance .' &euro;'
			,'reste'=> $financementLeaser->reste.' &euro;' 
			,'montant_prestation'=>$financementLeaser->montant_prestation.' &euro;' 
				
			,'numero_prochaine_echeance'=> $financementLeaser->numero_prochaine_echeance
			,'duree'=>$financementLeaser->duree
								
							
			,'periodicite'=>$financementLeaser->TPeriodicite[$financementLeaser->periodicite]
			,'reglement'=>$financementLeaser->TReglement[ $financementLeaser->reglement]
			,'incident_paiement'=>$financementLeaser->TIncidentPaiement[ $financementLeaser->incident_paiement] 
			
			,'date_debut'=> $financementLeaser->get_date('date_debut')
			,'date_fin'=>$financementLeaser->get_date('date_fin')
			,'date_prochaine_echeance'=>$financementLeaser->get_date('date_prochaine_echeance')
			
			
	);
		
	

	
	if(isset($financement)) {
		$TFinancement = array(
			'montant'=>$form->texte('', 'montant', $financement->montant, 20,255,'','','à saisir').' &euro;' 
			,'reference'=>$dossier->getId().'/'.$financement->getId()
			
			,'taux'=>$form->texte('', 'taux', $financement->taux, 5,255,'','','à saisir').' %' 

			,'assurance'=>$form->texte('', 'assurance', $financement->assurance, 20,255,'','','à saisir').' &euro;' 
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
			
			
		);
	}
	else {
		$TFinancement= array('id'=>0,
				'reference'=>''
				,'montant'=>0
				,'taux'=> 0
				,'echeance1'=> 0
				,'echeance'=> 0
				,'reste'=> 0
				,'montant_prestation'=>0
					
				,'numero_prochaine_echeance'=> 0
				,'duree'=>0
									
				,'assurance'=>0	
								
				,'periodicite'=>0
				,'reglement'=>0
				,'incident_paiement'=>0
				
				,'date_debut'=> 0
				,'date_fin'=>0
				,'date_prochaine_echeance'=>0
			);
	}
	
	print $TBS->render('./tpl/dossier.tpl.php'
		,array(
			'affaire'=>$TAffaire
		)
		,array(
			'dossier'=>array(
				'id'=>$dossier->rowid
				/*,'reference'=>$form->texte('', 'reference', $dossier->reference, 100,255,'','','à saisir')*/ 
				,'date_relocation'=>$form->calendrier('', 'date_relocation', $dossier->get_date('date_relocation'),10)
				,'date_maj'=>$dossier->get_date('date_maj','d/m/Y à H:i:s')
				,'date_cre'=>$dossier->get_date('date_cre','d/m/Y')
				,'solde'=>$dossier->solde
				,'montant_ok'=>$dossier->somme_affaire
				,'nature_financement'=>$dossier->nature_financement
				
			)
			,'financement'=>$TFinancement
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
