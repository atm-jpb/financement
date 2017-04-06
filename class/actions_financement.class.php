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
    	global $user;
		
        if (in_array('propalcard',explode(':',$parameters['context']))) 
        {
        	if($object->fin_validite < strtotime(date('Y-m-d')) && empty($user->rights->financement->integrale->see_past_propal)) {
        		dol_include_once('/core/lib/security.lib.php');
				$mess = 'Vous ne pouvez consulter une proposition dont la date de fin de validité est dépassée.';
				accessforbidden($mess, 1);
        	}
        }

        return 0;
    }
	
	function printSearchForm($parameters, &$object, &$action, $hookmanager) {
		global $langs, $hookmanager;
		 
		 $res = printSearchForm(DOL_URL_ROOT.'/custom/financement/dossier.php', DOL_URL_ROOT.'/custom/financement/dossier.php', img_picto('',dol_buildpath('/financement/img/object_financeico.png', 1), '', true).' '.$langs->trans("Dossiers"), 'searchdossier', 'searchdossier');
		 $res .= printSearchForm(DOL_URL_ROOT.'/compta/facture/list.php', DOL_URL_ROOT.'/compta/facture/list.php', img_object('','invoice').' '.$langs->trans("Factures Clients"), 'products', 'search_ref');
		 $res .= printSearchForm(DOL_URL_ROOT.'/fourn/facture/list.php', DOL_URL_ROOT.'/fourn/facture/list.php', img_object('','invoice').' '.$langs->trans("Factures Leasers"), 'products', 'search_ref');
		 $res .= printSearchForm(DOL_URL_ROOT.'/custom/financement/simulation.php', DOL_URL_ROOT.'/custom/financement/simulation.php', img_object('','invoice').' N° étude / Accord Leaser', 'searchnumetude', 'searchnumetude');
		 $hookmanager->resPrint.= $res;
		 
		 return 0;
	}
    
	function formObjectOptions($parameters, &$object, &$action, $hookmanager) {
		global $user, $db;
		  
		  
		  
		if (in_array('thirdpartycard',explode(':',$parameters['context'])) && $action !== 'create') 
        { 
         
		  $listsalesrepresentatives=$object->getSalesRepresentatives($user);
		   
			
		  foreach($listsalesrepresentatives as $commercial) {
			  $sql = "SELECT type_activite_cpro FROM ".MAIN_DB_PREFIX."societe_commerciaux WHERE fk_soc=".$object->id." AND fk_user=".$commercial['id'];
			  if($resql=$db->query($sql)) {
			      $obj = $db->fetch_object($resql);
				  if($obj->type_activite_cpro!='') {
						
					?><script type="text/javascript">
						/*alert("<?php echo $obj->type_activite_cpro ?>");*/
						$(document).ready(function(){
							
							$('a').each(function(){
								if($(this).html()=="<?php echo $commercial['firstname'].' '.$commercial['lastname'] ?>") {
									$(this).append(" [<?php echo $obj->type_activite_cpro ?>]");
								}
							});
							
						});
						
						
					</script>
					<?php
					
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

		if (in_array('propalcard',explode(':',$parameters['context']))) {
			
			$object->fetchObjectLinked();
			
			if(!empty($object->linkedObjects['facture'])) {
				
				define('INC_FROM_DOLIBARR', true);
				dol_include_once('/financement/config.php');
				dol_include_once('/financement/class/dossier_integrale.class.php');
				
				$sql = 'SELECT fk_source FROM '.MAIN_DB_PREFIX.'element_element WHERE sourcetype="dossier" AND targettype="facture" AND fk_target='.$object->linkedObjects['facture'][0]->id.' LIMIT 1';
				$resql = $db->query($sql);
				$res = $db->fetch_object($resql);
				
				if($res->fk_source > 0) {
					print '<tr>';
					print '<td>';
					print 'Suivi intégrale';
					print '</td>';
					print '<td>';
					print '<a href="'.dol_buildpath('/financement/dossier_integrale.php?id='.$res->fk_source, 1).'">Voir le suivi intégrale associé</a>';
					print '</td>';
					print '</tr>';
				}
				
				// Détail du nouveau coût unitaire (uniquement si le user a les droits)
				if(!empty($user->rights->financement->integrale->detail_couts)) {
					
					$PDOdb = new TPDOdb;
					$integrale = new TIntegrale;
					$integrale->loadBy($PDOdb, $object->linkedObjects['facture'][0]->ref, 'facnumber');
					if($integrale->rowid > 0) {
						
						$line_engagement_noir = TIntegrale::get_line_from_propal($object, 'E_NOIR');
						$line_engagement_coul = TIntegrale::get_line_from_propal($object, 'E_COUL');
						
						$TDataCalculNoir = $integrale->calcul_detail_cout($line_engagement_noir->qty, $line_engagement_noir->subprice);
						
						$TDataCalculCouleur = $integrale->calcul_detail_cout($line_engagement_coul->qty, $line_engagement_coul->subprice, 'coul');
						
						print '<tr>'.'<td>';
						print '<STRONG>Détail nouvel engagement noir</STRONG>';
						print '</td>'.'<td>';
						print '';
						print '</td>'.'</tr>';
						
						print '<tr>'.'<td>';
						print '- Tech';
						print '</td>'.'<td>';
						print $TDataCalculNoir['nouveau_cout_unitaire_tech'];
						print '</td>'.'</tr>'.'<tr>'.'<td>';
						print '- Mach';
						print '</td>'.'<td>';
						print $TDataCalculNoir['nouveau_cout_unitaire_mach'];
						print '</td>'.'</tr>'.'<tr>'.'<td>';
						print '- Loyer';
						print '</td>'.'<td>';
						print $TDataCalculNoir['nouveau_cout_unitaire_loyer'];
						print '</td>'.'</tr>';
						
						print '<tr>'.'<td>';
						print '<STRONG>Détail nouvel engagement couleur</STRONG>';
						print '</td>'.'<td>';
						print '';
						print '</td>'.'</tr>';
						
						print '<tr>'.'<td>';
						print '- Tech';
						print '</td>'.'<td>';
						print $TDataCalculCouleur['nouveau_cout_unitaire_tech'];
						print '</td>'.'</tr>'.'<tr>'.'<td>';
						print '- Mach';
						print '</td>'.'<td>';
						print $TDataCalculCouleur['nouveau_cout_unitaire_mach'];
						print '</td>'.'</tr>'.'<tr>'.'<td>';
						print '- Loyer';
						print '</td>'.'<td>';
						print $TDataCalculCouleur['nouveau_cout_unitaire_loyer'];
						print '</td>'.'</tr>';
						
					}

				}
				
			}
			
		}
	}

	// Affichage valeur spéciale dans dictionnaire
	function createDictionaryFieldlist($parameters, &$object, &$action, $hookmanager) {
		global $db,$form;
		
		define('INC_FROM_DOLIBARR', true);
		dol_include_once('/financement/config.php');
		dol_include_once('/financement/class/affaire.class.php');
		$aff = new TFin_affaire();
	
		foreach ($parameters['fieldlist'] as $field => $value)
		{
			if ($value == 'fk_type_contrat') {
				print '<td>';
				print $form->selectarray($value, $aff->TContrat,$object->$value);
				print '</td>';
			}
			elseif ($value == 'base_solde') {
				print '<td>';
				print $form->selectarray($value, $aff->TBaseSolde,$object->$value);
				print '</td>';
			}
			else
			{
				print '<td>';
				$size='';
				if ($value=='periode') $size='size="10" ';
				if ($value=='percent') $size='size="10" ';
				print '<input type="text" '.$size.' class="flat" value="'.(isset($object->$value)?$object->$value:'').'" name="'.$value.'">';
				print '</td>';
			}
		}

		$hookmanager->resPrint = '1';
		return 1;
	}
	
	function editDictionaryFieldlist($parameters, &$object, &$action, $hookmanager) {
		global $db,$form;
		
		define('INC_FROM_DOLIBARR', true);
		dol_include_once('/financement/config.php');
		dol_include_once('/financement/class/affaire.class.php');
		$aff = new TFin_affaire();
	
		foreach ($parameters['fieldlist'] as $field => $value)
		{
			if ($value == 'fk_type_contrat') {
				print '<td>';
				print $form->selectarray($value, $aff->TContrat,$object->$value);
				print '</td>';
			}
			elseif ($value == 'base_solde') {
				print '<td>';
				print $form->selectarray($value, $aff->TBaseSolde,$object->$value);
				print '</td>';
			}
			else
			{
				print '<td>';
				$size='';
				if ($value=='periode') $size='size="10" ';
				if ($value=='percent') $size='size="10" ';
				print '<input type="text" '.$size.' class="flat" value="'.(isset($object->$value)?$object->$value:'').'" name="'.$value.'">';
				print '</td>';
			}
		}

		$hookmanager->resPrint = '1';
		return 1;
	}
	
	function viewDictionaryFieldlist($parameters, &$object, &$action, $hookmanager) {
		global $db,$form;
		
		define('INC_FROM_DOLIBARR', true);
		dol_include_once('/financement/config.php');
		dol_include_once('/financement/class/affaire.class.php');
		$aff = new TFin_affaire();
		
		foreach ($parameters['fieldlist'] as $field => $value)
		{
			if ($value == 'fk_type_contrat') {
				print '<td>';
				print $aff->TContrat[$object->$value];
				print '</td>';
			}
			elseif ($value == 'base_solde') {
				print '<td>';
				print $aff->TBaseSolde[$object->$value];
				print '</td>';
			}
			else
			{
				print '<td>';
				print $object->$value;
				print '</td>';
			}
		}

		$hookmanager->resPrint = '1';
		return 1;
	}
}