<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');

	
	
	$id=GETPOST("id");
	$socid=GETPOST("socid");
	$action=GETPOST("action");
	$cancel=GETPOST("cancel");
	
	$affaire=new TFin_Affaire;
	$ATMdb = new Tdb;
	$tbs = new TTemplateTBS;
	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'add':
				
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
		 _liste();
	}
	
	
	
	llxFooter();
	
function _liste() {
	
	
	
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
