<?php
ini_set('display_errors', true);
require('config.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/lib/financement.lib.php');

$PDOdb=new TPDOdb;

$visaauto = GETPOST('visaauto');
$TRule = GETPOST('TRule');
$id_dossier = GETPOST('id_dossier');

// Moulinette pour vérifier automatiquement les renta neg
if(!empty($visaauto)) {
	set_time_limit(0);
	echo date('d/m/Y H:i:s').' - Début de la routine "renta négatives"<br>';
}

$TDossiersError = get_liste_dossier_renta_negative($PDOdb,$id_dossier,$visaauto,$TRule);

if(empty($visaauto)) {
	display_liste($PDOdb, $TDossiersError, $TRule);
}

function display_liste(&$PDOdb, &$TDossiersError, $TRule) {
	$dossier=new TFin_Dossier;
	
	/************************************************************************
	 * VIEW
	 ************************************************************************/
	llxHeader('','Dossiers renta négative');
	
	foreach($TDossiersError['all'] as $id_dossier) {
		$data = $TDossiersError['data'][$id_dossier];
		
		$TLinesFile[] = array(
			'refdoscli'					=> $data->refdoscli
			,'refdoslea'				=> $data->refdoslea
			,'affaire'					=> $data->refaffaire
			,'nomcli'					=> $data->nomcli
			,'nomlea'					=> $data->nomlea
			,'status_1'					=> (in_array($id_dossier, $TDossiersError['err1']) ? "Oui" : "Non")
			,'status_2'					=> (in_array($id_dossier, $TDossiersError['err2']) ? "Oui" : "Non")
			,'status_3'					=> (in_array($id_dossier, $TDossiersError['err3']) ? "Oui" : "Non")
			,'status_4'					=> (in_array($id_dossier, $TDossiersError['err4']) ? "Oui" : "Non")
			,'status_5'					=> (in_array($id_dossier, $TDossiersError['err5']) ? "Oui" : "Non")
			,'status_6'					=> (in_array($id_dossier, $TDossiersError['err6']) ? "Oui" : "Non")
			,'duree'					=> $data->duree
			,'periodicite'				=> $data->periodicite
			,'montant'					=> price($data->montant,0,'',1,-1,2)
			,'echeance'					=> price($data->echeance,0,'',1,2)
			,'date_prochaine'			=> date('d/m/y', strtotime($data->date_prochaine_echeance))
			,'date_debut'				=> date('d/m/y', strtotime($data->date_debut))
			,'date_fin'					=> date('d/m/y', strtotime($data->date_fin))
			,'renta_previsionnelle'		=> number_format($data->renta_previsionnelle,2, ',', ' ')
			,'marge_previsionnelle'		=> number_format($data->marge_previsionnelle,2)
			,'renta_attendue'			=> number_format($data->renta_attendue,2, ',', ' ')
			,'marge_attendue'			=> number_format($data->marge_attendue, 2)
			,'renta_reelle'				=> number_format($data->renta_reelle,2, ',', ' ')
			,'marge_reelle'				=> number_format($data->marge_reelle,2)
		);
		
		$TLinesDisp[] = array(
			'iddos' => $id_dossier
			,'refdos' => '<a href="dossier.php?id='.$id_dossier.'">'.$data->refdoscli .'<br>'. $data->refdoslea.'</a>'
			,'fk_affaire' => $data->fk_affaire
			,'affaire'=> $data->refaffaire
			,'noms' => '<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$data->fk_client.'">'.$data->nomcli.'</a><br><a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$data->fk_leaser.'">'.$data->nomlea.'</a>'
			,'status_1' => (in_array($id_dossier, $TDossiersError['err1']) ? "Oui" : "Non")
			,'status_2' => (in_array($id_dossier, $TDossiersError['err2']) ? "Oui" : "Non")
			,'status_3' => (in_array($id_dossier, $TDossiersError['err3']) ? "Oui" : "Non")
			,'status_4' => (in_array($id_dossier, $TDossiersError['err4']) ? "Oui" : "Non")
			,'status_5' => (in_array($id_dossier, $TDossiersError['err5']) ? "Oui" : "Non")
			,'status_6' => (in_array($id_dossier, $TDossiersError['err6']) ? "Oui" : "Non")
			,'montants' => price($data->montant,0,'',1,-1,2) . '<br>' . price($data->echeance,0,'',1,2) . '<br>' . $data->duree . ' ' . substr($data->periodicite, 0, 1) 
			,'dates' => date('d/m/y', strtotime($data->date_debut)) . '<br>' . date('d/m/y', strtotime($data->date_prochaine_echeance)) . '<br>' . date('d/m/y', strtotime($data->date_fin))
			,'renta_previsionnelle'=>number_format($data->renta_previsionnelle,2, ',', ' ').' <br> '.number_format($data->marge_previsionnelle,2).' %'
			,'renta_attendue'=>number_format($data->renta_attendue,2, ',', ' ').' <br> '.number_format($data->marge_attendue, 2).' %'
			,'renta_reelle'=>number_format($data->renta_reelle,2, ',', ' ').' <br> '.number_format($data->marge_reelle,2).' %'
		);
	}
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
	echo $form->hidden('liste_renta_negative', '1');
	
	echo $form->hidden('id_dossier', GETPOST('id_dossier'));
	echo $form->hidden('visaauto', GETPOST('visaauto'));
	
	echo $form->checkbox1('Règle 1', 'TRule[rule1]', 1, !empty($TRule['rule1']) ? $TRule['rule1'] : 0);
	echo $form->checkbox1('Règle 2', 'TRule[rule2]', 1, !empty($TRule['rule2']) ? $TRule['rule2'] : 0);
	echo $form->checkbox1('Règle 3', 'TRule[rule3]', 1, !empty($TRule['rule3']) ? $TRule['rule3'] : 0);
	echo $form->checkbox1('Règle 4', 'TRule[rule4]', 1, !empty($TRule['rule4']) ? $TRule['rule4'] : 0);
	echo $form->checkbox1('Règle 5', 'TRule[rule5]', 1, !empty($TRule['rule5']) ? $TRule['rule5'] : 0);
	echo $form->checkbox1('Règle 6', 'TRule[rule6]', 1, !empty($TRule['rule6']) ? $TRule['rule6'] : 0);
	
	echo $form->btsubmit('Lancer', 'run');
	if(!empty($TDossiersError['all'])) {
		echo $form->btsubmit('Exporter ('.count($TDossiersError['all']).' dossiers)', 'export');
	}
	
	$aff = new TFin_affaire;
	$dos = new TFin_dossier;
	
	$TErrorStatus=array(
		'error_1' => "Echéance Client < Echéance Leaser",
		'error_2' => "Facture Client < Loyer leaser",
		'error_3' => "Facture Client < Loyer client",
		'error_4' => "Facture Client impayée",
		'error_5' => "Echéance client non facturée",
		'error_6' => "Anomalie"
	);
	$TTitles = array(
		'refdos'=>'Contrat'
		,'refdoscli'=>'Contrat'
		,'refdoslea'=>'Contrat Leaser'
		,'affaire'=>'Affaire'
		,'nomcli'=>'Client'
		,'nomlea'=>'Leaser'
		,'noms' => 'Tiers'
		,'status_1'=>$TErrorStatus['error_1']
		,'status_2'=>$TErrorStatus['error_2']
		,'status_3'=>$TErrorStatus['error_3']
		,'status_4'=>$TErrorStatus['error_4']
		,'status_5'=>$TErrorStatus['error_5']
		,'status_6'=>$TErrorStatus['error_6']
		,'duree'=>'Durée'
		,'periodicite' => 'Périodicité'
		,'montant'=>'Montant'
		,'echeance'=>'Échéance'
		,'montants'=>'Montants'
		,'date_prochaine'=>'Prochaine'
		,'date_debut'=>'Début'
		,'date_fin'=>'Fin'
		,'dates'=>'Dates'
		,'renta_previsionnelle'=>'Renta Prévisionnelle'
		,'renta_attendue'=>'Renta Attendue'
		,'renta_reelle'=>'Renta Réelle'
		,'marge_previsionnelle'=>'Marge Prévisionnelle'
		,'marge_attendue'=>'Marge Attendue'
		,'marge_reelle'=>'Marge Réelle'
	);
	$limit = !empty($user->conf->MAIN_SIZE_LISTE_LIMIT) ? $user->conf->MAIN_SIZE_LISTE_LIMIT : $conf->global->MAIN_SIZE_LISTE_LIMIT;
	
	$r = new TListviewTBS('list_'.$dossier->get_table());
	print $r->renderArray($PDOdb, $TLinesDisp, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>$limit
		)
		,'link'=>array(
			'refdoscli'=>'<a href="?id=@iddos@">@val@</a>'
			,'refdoslea'=>'<a href="?id=@iddos@">@val@</a>'
			,'nomcli'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_client@">@val@</a>'
			,'nomlea'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_leaser@">@val@</a>'
			,'affaire'=>'<a href="'.dol_buildpath('/financement/affaire.php?id=@fk_affaire@',1).'">@val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$aff->TNatureFinancement
			,'visa_renta'=>$dos->Tvisa
		)
		,'hide'=>array('iddos', 'fk_client','fk_leaser','fk_affaire')
		,'type'=>array()//'date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant'=>'money', 'Echéance'=>'money')
		,'liste'=>array(
			'titre'=>"Liste des dossiers à rentabilité négative"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'noheader'=>FALSE
			,'messageNothing'=>"Il n'y a aucun dossier"
			,'picto_search'=>img_picto('','search.png', '', 0)
			)
		,'title'=>$TTitles
		,'orderBy'=> array('ID'=>'DESC','fc.reference'=>'ASC'
		)
		
	));
	
	$form->end();
	
	$action = GETPOST('action');
	if(!empty($_REQUEST['export']) && !empty($TDossiersError['all'])){
		_getExport($TLinesFile,$TTitles);
	}
	
	llxFooter();
}

function _getExport(&$TLines, $TTitles){
	$filename = 'export_liste_dossier_renta_neg.csv';
	$filepath = DOL_DATA_ROOT.'/financement/'.$filename;
	$file = fopen($filepath,'w');
	
	//Ajout première ligne libelle
	$l1 = $TLines[0];
	$TFirstline = array();
	foreach ($TTitles as $key => $value) {
		if(array_key_exists($key, $l1)) {
			$TFirstline[] = $value;
		}
	}
	
	fputcsv($file, $TFirstline,';','"');
	
	foreach($TLines as $line){
		fputcsv($file, $line,';','"');
	}
	
	fclose($file);
	
	?>
	<script language="javascript">
		document.location.href="<?php echo dol_buildpath("/document.php?modulepart=financement&entity=1&file=".$filename,2); ?>";
	</script>
	<?php
}	