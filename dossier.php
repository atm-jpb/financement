<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	$langs->load('financement@financement');
	$dossier=new TFin_Dossier;
	$ATMdb = new Tdb;
	$tbs = new TTemplateTBS;
	
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
	
	llxHeader('','Dossier');
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $dossier->rowid);
	echo $form->hidden('action', 'save');
	
	//require('./tpl/affaire.tpl.php');
	$TBS=new TTemplateTBS();
	
	print $TBS->render('./tpl/dossier.tpl.php'
		,array()
		,array(
			'dossier'=>array(
				'id'=>$dossier->rowid
				,'reference'=>$form->texte('', 'reference', $dossier->reference, 100,255,'','','à saisir') 
				
				,'montant'=>$form->texte('', 'montant', $dossier->montant, 20,255,'','','à saisir').' &euro;' 
				,'echeance1'=>$form->texte('', 'montant', $dossier->echeance1, 20,255,'','','à saisir').' &euro;' 
				,'echeance'=>$form->texte('', 'echeance', $dossier->echeance, 20,255,'','','à saisir') .' &euro;'
				,'reste'=>$form->texte('', 'reste', $dossier->reste, 20,255,'','','à saisir').' &euro;' 
				,'montant_prestation'=>$form->texte('', 'montant_prestation', $dossier->montant_prestation, 20,255,'','','à saisir').' &euro;' 
					
				,'numero_prochaine_echeance'=>$form->texte('', 'numero_prochaine_echeance', $dossier->numero_prochaine_echeance, 5,255,'','','à saisir') 
				,'duree'=>$form->texte('', 'duree', $dossier->duree, 5,255,'','','à saisir')
									
								
				,'periodicite'=>$form->combo('', 'periodicite', $dossier->TPeriodicite , $dossier->periodicite)
				,'reglement'=>$form->combo('', '', $dossier->TReglement , $dossier->reglement)
				,'incident_paiement'=>$form->combo('', 'incident_paiement', $dossier->TIncidentPaiement , $dossier->incident_paiement) 
				
				,'date_debut'=>$form->calendrier('', 'date_debut', $dossier->get_date('date_debut'),10)
				,'date_fin'=>$form->calendrier('', 'date_fin', $dossier->get_date('date_fin'),10)
				,'date_prochaine_echeance'=>$form->calendrier('', 'date_prochaine_echeance', $dossier->get_date('date_prochaine_echeance'),10)
				,'date_relocation'=>$form->calendrier('', 'date_relocation', $dossier->get_date('date_relocation'),10)
				
				
				
			)
			,'view'=>array(
				'mode'=>$mode
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	
	llxFooter();
	
}
