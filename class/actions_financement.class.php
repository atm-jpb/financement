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
    
	function formObjectOptions($parameters, &$object, &$action, $hookmanager) {
		global $user, $db;
		  
		  
		  
		if (in_array('thirdpartycard',explode(':',$parameters['context']))) 
        { 
         
		  $listsalesrepresentatives=$object->getSalesRepresentatives($user);
		   
			
		  foreach($listsalesrepresentatives as $commercial) {
			  $sql = "SELECT type_activite_cpro FROM llx_societe_commerciaux WHERE fk_soc=".$object->id." AND fk_user=".$commercial['id'];
			  if($resql=$db->query($sql)) {
			      $obj = $db->fetch_object($resql);
				  if($obj->type_activite_cpro!='') {
						
					?><script type="text/javascript">
						/*alert("<?=$obj->type_activite_cpro ?>");*/
						$(document).ready(function(){
							
							$('a').each(function(){
								if($(this).html()=="<?=$commercial['firstname'].' '.$commercial['name'] ?>") {
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
		  
		  $sql = "SELECT type_activite_cpro FROM llx_societe_commerciaux WHERE fk_soc=".$parameters['socid']." AND fk_user=".$id;
		  
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
			$sql = "SELECT sourcetype, fk_source FROM llx_element_element WHERE fk_target=".$object->id." AND targettype='facture'";
			if($resql=$db->query($sql)) {
				$obj = $db->fetch_object($resql);
				if($obj->sourcetype == 'affaire') {
					$link = '<a href="'.DOL_URL_ROOT_ALT.'/financement/affaire.php?id='.$obj->fk_source.'">Voir l\'affaire</a>';
					echo '<tr><td >Facture de matériel</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
				} else if($obj->sourcetype == 'dossier') {
					$link = '<a href="'.DOL_URL_ROOT_ALT.'/financement/dossier.php?id='.$obj->fk_source.'">Voir le dossier de financement</a>';
					echo '<tr><td >Facture de location</td><td'.$parameters['colspan'].'>'.$link.'</td></tr>';
				}
			}
		}
	}
}