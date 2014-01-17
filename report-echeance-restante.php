<?php

/*
 * Ticket #371 
Nous avons besoin d'avoir un rapport supplémentaire pour la gestion de notre trésorerie.
Celui-ci est dans le même esprit que le rapport des CCA sauf qu'au lieu de se baser sur les factures qui sont émises, 
ce rapport devra nous récapituler les échéances restantes de chaque dossier ADOSSE et MANDATE.

Les colonnes sont :
- Num Affaire
- Type de Financement
- Client
- Code client
- Référence du contrat leaser
- Leaser
- Code Leaser
- Date de l'Echéance ou date de l'écriture
- Libellé Echéance
- Périodicité
- Montant de l'échéance à venir
- Date de début de l'échéance (qui doit être égale je pense à la date d'échéance)
- Date de Fin de l'échéance
 * 
 * 
 */
 	
 	require('config.php');
	
	set_time_limit(0);
 	ini_set('memory_limit','512M');
 
	
	require('class/dossier.class.php');
	require('class/affaire.class.php');
	require('class/grille.class.php');
	
	$ATMdb = new TPDOdb;
 
 	$date_debut = __get('date_debut','');
 	$date_fin = __get('date_fin','');
 
 	$sql =  "SELECT a.reference NumAffaire, d.rowid as 'idDossier',f.duree,f.numero_prochaine_echeance as 'numero_prochaine_echeance', a.type_financement TypeFin, client.nom Client, client.code_compta CodeClient, f.reference RefContratLeaser, leaser.nom Leaser, leaser.code_compta_fournisseur CodeLeaser, f.periodicite Periodicite
		FROM llx_fin_dossier_financement f
		LEFT JOIN llx_fin_dossier d ON d.rowid = f.fk_fin_dossier
		LEFT JOIN llx_fin_dossier_affaire da ON da.fk_fin_dossier = d.rowid
		LEFT JOIN llx_fin_affaire a ON a.rowid = da.fk_fin_affaire
		LEFT JOIN llx_societe leaser ON leaser.rowid = f.fk_soc
		LEFT JOIN llx_societe client ON client.rowid = a.fk_soc
		WHERE f.type = 'LEASER'
		AND a.type_financement IN ('ADOSSEE', 'MANDATEE') AND f.date_solde='0000-00-00'";
		
	if(!empty($date_debut)) {
		$sql.=" AND ff.datef BETWEEN STR_TO_DATE(".$date_debut.", '%d/%m/%Y') AND STR_TO_DATE(".$date_fin.", '%d/%m/%Y')   ";
	}
		
	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_All();
	
	$TResult = array();
	
	foreach($Tab as &$row) {
			
			/*
			 * 
			 * 
			- Date de l'Echéance ou date de l'écriture
			- Libellé Echéance
			*** - Périodicité
			- Montant de l'échéance à venir
			- Date de début de l'échéance (qui doit être égale je pense à la date d'échéance)
			- Date de Fin de l'échéance
			 */
			
			$d=new TFin_dossier;
			$d->load($ATMdb, $row->idDossier ,false);
			
			$TEcheance = $d->echeancier($ATMdb,'LEASER', $row->numero_prochaine_echeance ,true,false);
			
			$iPeriode = $d->financementLeaser->getiPeriode();
			
			foreach($TEcheance['ligne'] as $k=>$echeance) {
								
					$n_echeance = $row->numero_prochaine_echeance+$k;		
					$date_fin = ''; 
					
					$dt = DateTime::createFromFormat('d/m/Y', $echeance['date']);		
					if($dt) $date_fin = date('d/m/Y', strtotime('+'.$iPeriode.' month -1day' , $dt->getTimestamp() ) );					
											
					
					$TResult[] = array(
						'NumAffaire'=>$row->NumAffaire
						,'TypeFin'=>$row->TypeFin
						,'Client'=>$row->Client
						, 'CodeClient'=>$row->CodeClient
						, 'RefContratLeaser'=>$row->RefContratLeaser
						, 'Leaser'=>$row->Leaser
						, 'CodeLeaser'=>$row->CodeLeaser
						, 'dateEcheance'=>$echeance['date']
						, 'LibelleEcheance'=>'Echéance n°'.$n_echeance
						, 'MontantEcheance'=>$echeance['loyerHT']
						, 'Periodicite'=>$row->Periodicite
						, 'dateEcheanceDeb'=>$echeance['date']
						, 'dateEcheanceFin'=>$date_fin
						
					
					);
				
				
			}
		
	} 


	if(isset($_REQUEST['download'])) {
		
		          $first = true;
                                        
                  foreach($TResult as &$ligne) {
                                                
                              if( $first ) {
                                                        foreach($ligne as $key=>$value) $TEntete[]=$key;
                                                        $first=false;
                              }       
             
		              foreach($ligne as $key=>&$value) { $value = strip_tags($value); }

                   }

		header("Content-disposition: attachment; filename=".$_REQUEST['rapport'].'.csv');
		header("Content-Type: application/force-download");
		header("Content-Transfer-Encoding: application/octet-stream");
		header("Pragma: no-cache");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
		header("Expires: 0");

		print implode(';', $TEntete)."\n";
		foreach($TResult as $ligne) {
			print implode(';', $ligne)."\n";
		}
		exit;
		
		
	}

	
	$aff=new TFin_affaire;
	
	llxHeader('','Rapport');
	
	$r=new TListviewTBS('lreport');
	
	$form=new TFormCore('auto', 'formReport','post');
	
	echo $form->hidden('download', '1' );
	echo $form->hidden('rapport', 'echeance-restante' );
	
	
	echo $form->btsubmit('Télécharger au format CSV', 'btsub');
	
	$form->end();
	
	
	
	print $r->renderArray($ATMdb, $TResult, array(
		'limit'=>array(
			'page'=>1
			,'nbLine'=>'100'
		)
		
		,'liste'=>array(
			'titre'=>"Echéances futures des dossiers LEASER"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'noheader'=>FALSE
			,'messageNothing'=>"Il n'y a aucune échéance"
			,'picto_search'=>img_picto('','search.png', '', 0)
			)
		,'title'=>array(
			'NumAffaire'=>'Numéro d\'affaire'
			,'TypeFin'=>'Type de financement'
			,'RefContratLeaser'=>'Contrat Leaser'
			,'dateEcheance'=>'Date échéance'
			,'LibelleEcheance'=>'Libellé'
			,'MontantEcheance'=>'Montant'
			,'dateEcheanceDeb'=>'Début de l\'échéance'
			,'dateEcheanceFin'=>'Fin de l\'échéance'
		)
		
	));
	
 	
 	llxFooter();