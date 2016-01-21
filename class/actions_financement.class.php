<?php

class ActionsFinancement
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */ 
    function doActions($parameters, &$object, &$action, $hookmanager) 
    {
        if (in_array('thirdpartycard',explode(':',$parameters['context']))) 
        { 
          // do something only for the context 'somecontext'
        }
 
        /*$this->results=array('myreturn'=>$myvalue);
        $this->resprints='';
 */
        return 0;
    }
	
	function printSearchForm($parameters, &$object, &$action, $hookmanager) {
		global $langs, $hookmanager;
		 
		 $res = printSearchForm(DOL_URL_ROOT.'/custom/financement/dossier.php', DOL_URL_ROOT.'/custom/financement/dossier.php', img_picto('',dol_buildpath('/financement/img/object_financeico.png', 1), '', true).' '.$langs->trans("Dossiers"), 'searchdossier', 'searchdossier');
		 $res .= printSearchForm(DOL_URL_ROOT.'/compta/facture/list.php', DOL_URL_ROOT.'/compta/facture/list.php', img_object('','invoice').' '.$langs->trans("Factures Clients"), 'products', 'search_ref');
		 $res .= printSearchForm(DOL_URL_ROOT.'/fourn/facture/list.php', DOL_URL_ROOT.'/fourn/facture/list.php', img_object('','invoice').' '.$langs->trans("Factures Leasers"), 'products', 'search_ref');
		 
		 $hookmanager->resPrint.= $res;
		 
		 return 0;
	}
    
	function formObjectOptions($parameters, &$object, &$action, $hookmanager) {
		global $user, $db;
		  
		  
		  
		if (in_array('thirdpartycard',explode(':',$parameters['context']))) 
        { 
         
		  $listsalesrepresentatives=$object->getSalesRepresentatives($user);
		   
			
		  foreach($listsalesrepresentatives as $commercial) {
			  $sql = "SELECT type_activite_cpro FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc=".$object->id." AND fk_user=".$commercial['id'];
			  if($resql=$db->query($sql)) {
			      $obj = $db->fetch_object($resql);
				  if($obj->type_activite_cpro!='') {
						
					?><script type="text/javascript">
						/*alert("<?=$obj->type_activite_cpro ?>");*/
						$(document).ready(function(){
							
							$('a').each(function(){
								if($(this).html()=="<?=$commercial['firstname'].' '.$commercial['lastname'] ?>") {
									$(this).append(" [<?=$obj->type_activite_cpro ?>]");
								}
							});
							
						});
						
						
					</script>
					<?
					
				  }
			  	
			  }

		  }
		   
        }
		
		if (in_array('salesrepresentativescard',explode(':',$parameters['context']))) 
        { 
         
		  
		  $id = isset($object->rowid) ? $object->rowid : $object->id;
		  
		  $sql = "SELECT type_activite_cpro FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc=".$parameters['socid']." AND fk_user=".$id." AND rowid = ".$object->id_link;
		  
		  if( $resql=$db->query($sql)) {
			  $obj = $db->fetch_object($resql);
			  if($obj->type_activite_cpro!='') {
				  $object->lastname.=' ['.$obj->type_activite_cpro.']';	
				  if(isset($object->name)) $object->name.=' ['.$obj->type_activite_cpro.']';
			  }			
		  	
		  }

        }
		
		// Affichage du dossier de financement relatif à la facture de location ou de l'affaire relative à la facture de matériel
		if (in_array('invoicecard',explode(':',$parameters['context']))) {
			$sql = "SELECT sourcetype, fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target=".$object->id." AND targettype='facture'";
			if($resql=$db->query($sql)) {
				$obj = $db->fetch_object($resql);
				if($obj->sourcetype == 'affaire') {
					$link = '<a href="'.dol_buildpath('/financement/affaire.php?id='.$obj->fk_source, 1).'">Voir l\'affaire</a>';
					echo '<tr><td >Facture de matériel</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
				} else if($obj->sourcetype == 'dossier') {
					$link = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$obj->fk_source, 1).'">Voir le dossier de financement</a>';
					echo '<tr><td >Facture de location</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
				}
			}
		}
		
		if (in_array('invoicesuppliercard',explode(':',$parameters['context']))) {
			
			//Affichage des champs date début période et date fin période
			$sql = "SELECT date_debut_periode, date_fin_periode FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid = ".$object->id;
			
			if($resql=$db->query($sql)) {
				
				$obj = $db->fetch_object($resql);
				
				?>
				<tr>
					<td>Date début période</td>
					<td><?php echo date('d/m/Y',strtotime($obj->date_debut_periode)); ?></td>
				</tr>
				<tr>
					<td>Date fin période</td>
					<td><?php echo date('d/m/Y',strtotime($obj->date_fin_periode)); ?></td>
				</tr>
				<?php
			}
			
			// Affichage du dossier de financement relatif à la facture fournisseur
			$sql = "SELECT sourcetype, fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target=".$object->id." AND targettype='invoice_supplier'";
			
			if($resql=$db->query($sql)) {
				
				$obj = $db->fetch_object($resql);
				
				if($obj->sourcetype == 'dossier') {
					$link = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$obj->fk_source, 1).'">Voir le dossier de financement</a>';
					echo '<tr><td >Facture de loyer leaser</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
					
					// Affichage bouton permettant de créer un avoir directement
					if($object->type != 2) {
						$url = dol_buildpath('/financement/dossier.php?action=create_avoir&id_facture_fournisseur='.$object->id.'&id_dossier='.$obj->fk_source, 1);
						?>
						<script type="text/javascript">
							$(document).ready(function(){
								$('div.tabsAction').append('<a class="butAction" href="<?php echo $url ?>">Créer un avoir</a>');
							});
						</script>
						<?php
					}
				}
			}
		}
	}
}