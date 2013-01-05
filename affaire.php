<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');

	$affaire=new TFin_Affaire;
	$ATMdb = new Tdb;
	$tbs = new TTemplateTBS;
	
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
				
				$ATMdb->db->debug=true;
				print_r($_REQUEST);
				
				$affaire->save($ATMdb);
				
				_fiche($affaire,'view');
				
				break;
			
				
			case 'delete':
				$affaire=new TAsset;
				$affaire->load($ATMdb, $_REQUEST['id']);
				//$ATMdb->db->debug=true;
				$affaire->delete($ATMdb);
				
				?>
				<script language="javascript">
					document.location.href="<?=dirname($_SERVER['PHP_SELF'])?>/liste.php?delete_ok=1";					
				</script>
				<?
				
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$affaire->load($ATMdb, $_REQUEST['id']);
		
		_fiche($affaire, 'view');
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
		'ligneParPage'=>'30'
		/*,'subQuery'=>array(
			'Société'=>"SELECT nom FROM llx_societe WHERE rowid=@val@"
		)*/
		,'link'=>array(
			'Société'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@"><img border="0" title="Afficher société: test" alt="Afficher société: test" src="'.DOL_URL_ROOT.'/theme/eldy/img/object_company.png"> @val@</a>'
			,'Numéro d\'affaire'=>'<a href="?action=view&id=@ID@">@val@</a>'
		)
		,'translate'=>array(
			'Financement : Nature'=>$affaire->TNatureFinancement
			,'Type'=>$affaire->TTypeFinancement
		)
		,'hide'=>array('fk_soc')
	));
	
	
	llxFooter();
}	
	
function _fiche(&$affaire, $mode) {
	
	llxHeader('','Affaires');
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $affaire->rowid);
	echo $form->hidden('action', 'save');
	
	require('./tpl/affaire.tpl.php');
	
	echo $form->end_form();
	// End of page
	
	llxFooter();
	
}
