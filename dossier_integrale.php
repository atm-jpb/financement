<?php

require('config.php');
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/comm/propal/class/propal.class.php');
dol_include_once('/compta/facture/class/facture.class.php');

$langs->load('financement@financement');

if (!$user->rights->financement->integrale->read)	{ accessforbidden(); }

$dossier=new TFin_Dossier;
$PDOdb=new TPDOdb;
$action = GETPOST('action');
$id_dossier = GETPOST('id');
$TBS = new TTemplateTBS;

$dossier->load($PDOdb, $id_dossier);
$dossier->load_facture($PDOdb,true);

llxHeader('',$langs->trans('SuiviIntegral'));

$head = dossier_prepare_head($dossier);
$img_path = dol_buildpath('/financement/img/object_financeico.png', 2);
dol_fiche_head($head, 'integrale', $langs->trans("Dossier"),0, $img_path, 1);

if($action == 'addAvenantIntegrale'){
	$calcul = false;
	if(isset($_REQUEST['btSave'])) $file_path = _addAvenantIntegrale($dossier);
	_affichage($PDOdb, $TBS, $dossier, $file_path);
	_printFormAvenantIntegrale($PDOdb, $dossier, $TBS);
} elseif(empty($id_dossier)) {
	_liste($PDOdb, $dossier, $TBS);
} else {
	_affichage($PDOdb, $TBS, $dossier);
}

if($action == 'printDocIntegrale') {
	$affaire = &$dossier->TLien[0]->affaire;
	$TData = array('client'=>_getInfosClient($affaire->fk_soc));
	$file_path = _genDocEmpty($TData,true);

	if(!empty($file_path)) {
		?>
			<script>
				document.location.href="<?php echo $file_path; ?>";
			</script>
		<?php
	}
}

function _affichage(&$PDOdb, &$TBS, &$dossier, $file_path='') {

	global $db;

	_fiche($PDOdb, $db, $dossier, $TBS);

	if(!empty($file_path)) {
		?>
			<script>
				document.location.href="<?php echo $file_path; ?>";
			</script>
		<?php
	}

}

llxFooter();

function _liste(&$PDOdb, &$dossier) {
	global $conf, $user;

	$r = new TSSRenderControler($dossier);
	$sql ="SELECT d.rowid as 'ID', fc.reference as refDosCli, fl.reference as refDosLea, a.rowid as 'ID affaire', a.reference as 'Affaire', ";
	$sql.= "a.fk_soc, c.nom as nomCli, l.nom as nomLea, ";
	$sql.=" CASE WHEN a.nature_financement = 'INTERNE' THEN fc.duree ELSE fl.duree END as 'Durée', ";
	$sql.=" CASE WHEN a.nature_financement = 'INTERNE' THEN fc.montant ELSE fl.montant END as 'Montant', ";
	$sql.=" CASE WHEN a.nature_financement = 'INTERNE' THEN fc.echeance ELSE fl.echeance END as 'Echéance', ";
	$sql.=" CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_prochaine_echeance ELSE fl.date_prochaine_echeance END as 'Prochaine', ";
	$sql.=" CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_debut ELSE fl.date_debut END as 'date_debut', ";
	$sql.=" CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_fin ELSE fl.date_fin END as 'Fin' ";
	$sql.=" FROM ((((((@table@ d ";
	$sql.=" LEFT OUTER JOIN llx_fin_dossier_affaire da ON (d.rowid=da.fk_fin_dossier)) ";
	$sql.=" LEFT OUTER JOIN llx_fin_affaire a ON (da.fk_fin_affaire=a.rowid)) ";
	$sql.=" LEFT OUTER JOIN llx_fin_dossier_financement fc ON (d.rowid=fc.fk_fin_dossier AND fc.type='CLIENT')) ";
	$sql.=" LEFT OUTER JOIN llx_fin_dossier_financement fl ON (d.rowid=fl.fk_fin_dossier AND fl.type='LEASER')) ";
	$sql.=" LEFT OUTER JOIN llx_societe c ON (a.fk_soc=c.rowid)) ";
	$sql.=" LEFT OUTER JOIN llx_societe l ON (fl.fk_soc=l.rowid)) ";

	if (!$user->rights->financement->alldossier->read && $user->rights->financement->mydossier->read) {
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = c.rowid";
	}

    $sql.=" WHERE a.entity IN (".getEntity('fin_dossier', true).')';
	$sql.=" AND a.contrat='INTEGRAL' ";
	$sql.=" AND fc.duree > 0 ";
	$sql.=" AND fc.echeance > 0 ";
	$sql.=" AND fc.date_solde < '1970-00-00 00:00:00' ";

	if (!$user->rights->financement->alldossier->read && $user->rights->financement->mydossier->read) //restriction
	{
		$sql.= " AND sc.fk_user = " .$user->id;
	}

	$sql.=" ";

	//echo $sql;
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
	$aff = new TFin_affaire;

	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'30'
			,'global'=>'1000'
		)
		,'link'=>array(
			'nomCli'=>'<a href="'.DOL_URL_ROOT.'/societe/card.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'nomLea'=>'<a href="'.DOL_URL_ROOT.'/societe/card.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'refDosCli'=>'<a href="?id=@ID@">@val@</a>'
			,'refDosLea'=>'<a href="?id=@ID@">@val@</a>'
			,'Affaire'=>'<a href="'.DOL_URL_ROOT.'/custom/financement/affaire.php?id=@ID affaire@">@val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$aff->TNatureFinancement
		)
		,'hide'=>array('fk_soc','ID','ID affaire','refDosLea','Affaire','nomLea','Prochaine')
		,'type'=>array('date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant'=>'money', 'Echéance'=>'money')
		,'liste'=>array(
			'titre'=>"Liste des dossiers"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'noheader'=>FALSE
			,'messageNothing'=>"Il n'y a aucun dossier"
			,'picto_search'=>img_picto('','search.png', '', 0)
			)
		,'title'=>array(
			'refDosCli'=>'Contrat'
			,'refDosLea'=>'Contrat Leaser'
			,'nomCli'=>'Client'
			,'nomLea'=>'Leaser'
			,'nature_financement'=>'Nature'
			,'date_debut'=>'Début'
		)
		,'orderBy'=> array('ID'=>'DESC','fc.reference'=>'ASC')
		,'search'=>array(
			'refDosCli'=>array('recherche'=>true, 'table'=>'fc', 'field'=>'reference')
			,'refDosLea'=>array('recherche'=>true, 'table'=>'fl', 'field'=>'reference')
			,'nomCli'=>array('recherche'=>true, 'table'=>'c', 'field'=>'nom')
			,'nomLea'=>array('recherche'=>true, 'table'=>'l', 'field'=>'nom')
			,'nature_financement'=>array('recherche'=>$aff->TNatureFinancement,'table'=>'a')
			//,'date_debut'=>array('recherche'=>'calendars', 'table'=>'f')
		)
	));
	$form->end();
}

function _formatIntegrale(&$integrale){

	$integrale->date_facture = $integrale->get_date('date_facture','d/m/Y');

	return $integrale;

}
// TODO à refaire, fonction nid à couillons
function addInTIntegrale(&$PDOdb,&$facture,&$TIntegrale,&$dossier){
	global $db,$conf;

	dol_include_once('/core/class/html.formfile.class.php');
	$formfile = new FormFile($db);

	$integrale = new TIntegrale;
	$integrale->loadBy($PDOdb, $facture->ref, 'facnumber');

	$integrale->date_facture = $facture->date;
	$integrale->date_periode = strtotime(implode('-', array_reverse(explode('/', $facture->ref_client))));

	//Si la facture a une date de facturation dans la ref_client
	if($dossier->_get_num_echeance_from_date($integrale->date_periode) != '-1'){
		$integrale->date_periode = date('d/m/Y',strtotime($dossier->getDateDebutPeriode($dossier->_get_num_echeance_from_date($integrale->date_periode),'CLIENT')));
	}
	else{
		$integrale->date_periode = $facture->ref_client;
	}

	$integrale->facnumber = $facture->getNomUrl();

	if(!empty($TIntegrale[$integrale->date_periode])){

		//Pour certains champs on concatène
		/*echo $facnumber.'<br>';
		pre($facidavoir,true);*/
		$TIntegrale[$integrale->date_periode]->date_facture .= "<br />".$integrale->get_date('date_facture','d/m/Y');
		$TIntegrale[$integrale->date_periode]->facnumber .= "<br />".$integrale->facnumber;
		$TIntegrale[$integrale->date_periode]->TIds[] = $integrale->getId();


		//Cas avoir PARTIEL
		if($facture->type == 2){
			//re($TIntegrale[$integrale->date_periode],true);exit;
			foreach($TIntegrale[$integrale->date_periode]->TChamps as $key => $val){
				if($key != "total_ht_facture" && $key != "ecart"){
					if(!strpos($key, 'coul') && !strpos($key, 'noir')) $TIntegrale[$integrale->date_periode]->{$key} .=" €";
					//pre($integrale->facnumber,true);
					//_factureAnnuleParAvoir($integrale->facnumber);
					if(!_factureAnnuleParAvoir($facture->ref))$TIntegrale[$integrale->date_periode]->{$key} .= '<br>0';
				}
			}
			$TIntegrale[$integrale->date_periode]->total_ht_facture .= ' €<br>'.number_format($integrale->total_ht_facture,2);

		}
		else{
			//Addition des champs qui vont bien
			if($TIntegrale[$integrale->date_periode]->vol_noir_engage < $integrale->vol_noir_engage){
				$TIntegrale[$integrale->date_periode]->vol_noir_engage = $integrale->vol_noir_engage;
			}
			if($integrale->vol_noir_engage < 0) $TIntegrale[$integrale->date_periode]->vol_noir_realise -= $integrale->vol_noir_realise;
			else $TIntegrale[$integrale->date_periode]->vol_noir_realise += $integrale->vol_noir_realise;

			$TIntegrale[$integrale->date_periode]->vol_noir_facture += $integrale->vol_noir_facture;

			if($integrale->cout_unit_noir > $TIntegrale[$integrale->date_periode]->cout_unit_noir){
				$TIntegrale[$integrale->date_periode]->cout_unit_noir = $integrale->cout_unit_noir;
			}

			if($TIntegrale[$integrale->date_periode]->vol_coul_engage < $integrale->vol_coul_engage){
				$TIntegrale[$integrale->date_periode]->vol_coul_engage = $integrale->vol_coul_engage;
			}

			if($integrale->vol_coul_engage < 0) $TIntegrale[$integrale->date_periode]->vol_coul_realise -= $integrale->vol_coul_realise;
			else $TIntegrale[$integrale->date_periode]->vol_coul_realise += $integrale->vol_coul_realise;

			$TIntegrale[$integrale->date_periode]->vol_coul_facture += $integrale->vol_coul_facture;
			//echo $integrale->date_periode." ".$TIntegrale[$integrale->date_periode]->vol_coul_realise.' '.$integrale->facnumber.'<br>';
			if($integrale->cout_unit_coul > $TIntegrale[$integrale->date_periode]->cout_unit_coul){
				$TIntegrale[$integrale->date_periode]->cout_unit_coul = $integrale->cout_unit_coul;
			}

			$TIntegrale[$integrale->date_periode]->fas += $integrale->fas;
			$TIntegrale[$integrale->date_periode]->fass += $integrale->fass;
			$TIntegrale[$integrale->date_periode]->frais_dossier += $integrale->frais_dossier;
			$TIntegrale[$integrale->date_periode]->frais_bris_machine += $integrale->frais_bris_machine;
			$TIntegrale[$integrale->date_periode]->frais_facturation += $integrale->frais_facturation;
			$TIntegrale[$integrale->date_periode]->total_ht_engage += $integrale->total_ht_engage;
			$TIntegrale[$integrale->date_periode]->total_ht_realise += $integrale->total_ht_realise;
			$TIntegrale[$integrale->date_periode]->total_ht_facture += $integrale->total_ht_facture;

			$TIntegrale[$integrale->date_periode]->ecart += $integrale->ecart;
			$TIntegrale[$integrale->date_periode]->nb_ecart += 1;
		}
	}
	else{
		$integrale->date_facture = $integrale->get_date('date_facture','d/m/Y');
		$integrale->cout_unit_noir = number_format($integrale->cout_unit_noir,5);
		$integrale->cout_unit_coul = number_format($integrale->cout_unit_coul,5);
		$integrale->fas = number_format($integrale->fas,2);
		$integrale->fass = number_format($integrale->fass,2);
		$integrale->frais_dossier = number_format($integrale->frais_dossier,2);
		$integrale->frais_bris_machine = number_format($integrale->frais_bris_machine,2);
		$integrale->frais_facturation = number_format($integrale->frais_facturation,2);
		$integrale->total_ht_engage = number_format($integrale->total_ht_engage,2);
		$integrale->total_ht_realise = number_format($integrale->total_ht_realise,2);
		$integrale->total_ht_facture = number_format($integrale->total_ht_facture,2);

		$TIntegrale[$integrale->date_periode] = $integrale;
		$TIntegrale[$integrale->date_periode]->nb_ecart += 1;
		$TIntegrale[$integrale->date_periode]->TIds = array(0 => $integrale->getId());
	}

    $old_entity = $conf->entity;
    switchEntity($dossier->entity);
	$facture->fetchObjectLinked('', 'propal', $facture->id, 'facture');

	if(!empty($facture->linkedObjects['propal'])) {
		foreach($facture->linkedObjects['propal'] as $p) {
			$filename=dol_sanitizeFileName($p->ref);
			$filedir=$conf->propal->dir_output . '/' . dol_sanitizeFileName($p->ref);

			$links = $p->getNomUrl(1);
			if($p->fin_validite >= strtotime(date('Y-m-d'))) { // Affichage du PDF si encore valide
				$links.= $formfile->getDocumentsLink($p->element, $filename, $filedir);
			}
			$links.= "<br>";
			$TIntegrale[$integrale->date_periode]->propal .= $links;
		}
	}
    switchEntity($old_entity);

	return $TIntegrale;

}

function _factureAnnuleParAvoir($facnumber){
	global $db;
	//echo $facnumber.'<br>';
	$avoir = new Facture($db);
	$avoir->fetch('',$facnumber);
	//pre($facnumber,true);
	if($avoir->type == 2){ //avoir
		//$facture->fetchObjectLinked();
		$facture = new Facture($db);
		$facture->fetch($avoir->fk_facture_source);
		//echo $facture->total_ht." ".$avoir->total_ht;
		if($facture->total_ht == -$avoir->total_ht) return true;
		else return false;
		//pre($facture,true);
	}
}

function _fiche(&$PDOdb, &$doliDB, &$dossier, &$TBS) {
	global $user;

	$fin = &$dossier->financement;

	$affaire = &$dossier->TLien[0]->affaire;
	$client = new Societe($doliDB);
	$client->fetch($affaire->fk_soc);

	// Affichage spé
	$dossier->url_therefore=FIN_THEREFORE_DOSSIER_URL;
	$fin->_affterme = $fin->TTerme[$fin->terme];
	$fin->_affperiodicite = $fin->TPeriodicite[$fin->periodicite];

	// ETAPE 1 : on ne conserve que les factures qui nous intéressent
	$dossier->format_facture_integrale($PDOdb);
	//pre($dossier->TFacture[6],true);

	// ETAPE 2 : on fait tous les calculs pour le tableau intégral
	$TIntegrale = array();
	foreach ($dossier->TFacture as $fac) {

		//Cas plusieurs factures sur la même échéance
		if(is_array($fac)){
			foreach($fac as $facture){
				$TIntegrale = addInTIntegrale($PDOdb,$facture,$TIntegrale,$dossier);
			}
		}
		else{
			$TIntegrale = addInTIntegrale($PDOdb,$fac,$TIntegrale,$dossier);
		}
	}

	//On modifie la 1ere et 2nde valeur du tableau si on est à échoir
	if($dossier->financement->_affterme == 'A Echoir' && $dossier->contrat == 'INTEGRAL'){
		$TIntegrale  = updateFirstVals($TIntegrale);
	}

	// ETAPE 3 : on finalise le formatage pour l'affichage

	//$dossier->load_facture($PDOdb,true);
	//pre($dossier->TFacture,true);
	//pre($TIntegrale,true);
	foreach($dossier->TFacture as $echeance => $facture){
		$date_periode = date('d/m/Y',strtotime($dossier->getDateDebutPeriode($echeance,'CLIENT')));

		if(isset($TIntegrale[$date_periode])) {

			$TIntegrale[$date_periode]->date_facture = '';
			$TIntegrale[$date_periode]->facnumber = '';

			if(is_array($facture)){
				foreach($facture as $fact){
					$TIntegrale[$date_periode]->date_facture .= $fact->ref_client."<br>";
					$TIntegrale[$date_periode]->facnumber .= $fact->getNomUrl()."<br>";
				}
			}
			else{
				$TIntegrale[$date_periode]->date_facture .= $facture->ref_client."<br>";
				$TIntegrale[$date_periode]->facnumber .= $facture->getNomUrl()."<br>";
			}

			// Fond de couleur si facturé > engagé
			$TIntegrale[$date_periode]->alert_noir_depassement = '';
			if($TIntegrale[$date_periode]->vol_noir_facture > $TIntegrale[$date_periode]->vol_noir_engage) {
				$TIntegrale[$date_periode]->alert_noir_depassement = ' style="background-color: #FF8080;"';
			}
			$TIntegrale[$date_periode]->alert_coul_depassement = '';
			if($TIntegrale[$date_periode]->vol_coul_facture > $TIntegrale[$date_periode]->vol_coul_engage) {
				$TIntegrale[$date_periode]->alert_coul_depassement = ' style="background-color: #FF8080;"';
			}

			// Ne pas afficher le réalisé si < 0
			if($TIntegrale[$date_periode]->vol_noir_realise < 0) {
				$TIntegrale[$date_periode]->vol_noir_realise = '';
			}
			if($TIntegrale[$date_periode]->vol_coul_realise < 0) {
				$TIntegrale[$date_periode]->vol_coul_realise = '';
			}

		} // else{} TODO A voir comment faire car certaines factures sont des loyers intercalaires et ne sont pas associés à des périodes.

		//$TIntegrale[] = '';
	}

	foreach($TIntegrale as $date_periode => $integrale){
		if(empty($TIntegrale[$date_periode]->facnumber)){
			unset($TIntegrale[$date_periode]);
		}
	}

	// MKO 2016.12.20 : pas d'affichage intégrale si type de régul non trimestriel
	$error = 0;
	$errmsg = '';
	if(!empty($dossier->type_regul)) {
		$TIntegrale = regularisation($dossier,$TIntegrale);
	}

	// 2017.03.21 MKO : si type regul trimestriel, on affiche le tableau intégral, sinon non
	if($dossier->type_regul != 3) {
		$error = 1;
		$errmsg = 'Régule autre que trimestrielle, merci de consulter vos VMM et factures sur Cristal';
	}

	if (!empty($user->rights->financement->admin->write)) $error = 0;

	echo $TBS->render('./tpl/dossier_integrale.tpl.php'
		,array(
			'integrale'=>$TIntegrale
		)
		,array(
			'dossier'=>$dossier
			,'fin'=>$fin
			,'client'=>$client
			,'error'=>$error
			,'errormsg'=>$errmsg
		)
	);

	// 2016.11.10 MKO : Nouvelles règles :
	// - avenant impossible si somme des coûts unitaires détaillés différente du cout unitaire
	// - avenant impossible si cout unitaire loyer à 0
	$avenantOK = true;
	$facIntegral = array_pop($TIntegrale);
	// 2019.12.11 : MKO, revu avec E. Roussard, modification règle de dispo
	if($facIntegral->cout_unit_noir == 0 && $facIntegral->cout_unit_coul == 0) $avenantOK = false; // Non dispo si cout vendu à 0
	if($facIntegral->cout_unit_noir_tech == 0 && $facIntegral->cout_unit_coul_tech == 0) $avenantOK = false; // Non dispo si cout tech à 0
	$totalCoutNoir = $facIntegral->cout_unit_noir_loyer + $facIntegral->cout_unit_noir_mach + $facIntegral->cout_unit_noir_tech;
	$totalCoutCouleur = $facIntegral->cout_unit_coul_loyer + $facIntegral->cout_unit_coul_mach + $facIntegral->cout_unit_coul_tech;
	if($totalCoutNoir == 0 && $totalCoutCouleur == 0) $avenantOK = false;

    if($dossier->type_regul != 3) $avenantOK = false;

	dol_fiche_end();
	print '<div class="tabsAction">';
	if (!empty($user->rights->financement->integrale->create_new_avenant) && $avenantOK) {
		$label = (GETPOST('action') === 'addAvenantIntegrale') ? 'Réinitialiser simulateur' : 'Nouveau calcul d\'avenant';
		print '<a class="butAction" href="?id='.GETPOST('id').'&action=addAvenantIntegrale">'.$label.'</a>';
	} else if (!empty($user->rights->financement->admin->write)) {
		$label = (GETPOST('action') === 'addAvenantIntegrale') ? 'Réinitialiser simulateur' : 'Nouveau calcul d\'avenant';
		print '<a class="butAction" href="?id='.GETPOST('id').'&action=addAvenantIntegrale" style="color: red;">'.$label.'</a>';
	} else {
		echo 'Avenant impossible. Merci de contacter le service financement';
	}

	if(empty($TIntegrale)) {
		print '<a class="butAction" href="?id='.GETPOST('id').'&action=printDocIntegrale">Imprimer le doc</a>';
	}
	print '</div>';
}

function _printFormAvenantIntegraleOLD(&$PDOdb, &$dossier, &$TBS) {

	global $user, $langs;

	$TFacture = &$dossier->TFacture;
	if(empty($TFacture)) {
		setEventMessage('Aucune facture intégrale trouvée', 'warnings');
		return 0;
	}

	$f = is_object(end($TFacture)) ? end($TFacture) : end(end($TFacture)); // Dans certains cas, il y a plusieurs factures pour une période, on veut la dernière

	$integrale = new TIntegrale;
	$integrale->loadBy($PDOdb, $f->ref, 'facnumber');

	$form=new TFormCore($_SERVER['PHP_SELF'].'#calculateur', 'formAvenantIntegrale', 'POST');

	// Si simulation sur le mois de décembre, +6% sur tous les coûts
	$pourcentage_sup_mois_decembre = ((int)date('m') == 12) ? 1.06 : 1;

	$new_engagement_noir = GETPOST('nouvel_engagement_noir');
	$new_engagement_couleur = GETPOST('nouvel_engagement_couleur');
	$old_engagement_noir = GETPOST('old_engagement_noir');
	$old_engagement_couleur = GETPOST('old_engagement_couleur');
	$new_cout_noir = GETPOST('nouveau_cout_unitaire_noir');
	$new_cout_couleur = GETPOST('nouveau_cout_unitaire_couleur');
	$old_cout_noir = GETPOST('old_cout_unitaire_noir');
	$old_cout_couleur = GETPOST('old_cout_unitaire_couleur');
	$new_fas = GETPOST('nouveau_fas');
	$old_fas = GETPOST('old_fas');
	$old_repartition_noir = GETPOST('old_repartition_noir');
	$old_repartition_couleur = GETPOST('old_repartition_couleur');
	$new_repartition_noir = GETPOST('nouvelle_repartition_noir');
	$new_repartition_couleur = GETPOST('nouvelle_repartition_couleur');
	$new_fas_noir = 0;
	$new_fas_couleur = 0;

	if(empty($new_engagement_noir)) $new_engagement_noir = $integrale->vol_noir_engage;
	if(empty($new_engagement_couleur)) $new_engagement_couleur = $integrale->vol_coul_engage;
	if(empty($new_cout_noir)) $new_cout_noir = $integrale->cout_unit_noir * $pourcentage_sup_mois_decembre;
	if(empty($new_cout_couleur)) $new_cout_couleur = $integrale->cout_unit_coul * $pourcentage_sup_mois_decembre;
	if(empty($new_fas)) $new_fas = $integrale->fas;
	if(empty($new_repartition_couleur)) {
		$new_repartition_couleur = $integrale->calcul_percent_couleur($TDetailCoutNoir, $new_engagement_noir, $TDetailCoutCouleur, $new_engagement_couleur);
		$new_repartition_noir = 100 - $new_repartition_couleur;
	}
	$TDetailCoutNoir = $integrale->calcul_detail_cout($new_engagement_noir, $new_cout_noir, 'noir');
	$TDetailCoutCouleur = $integrale->calcul_detail_cout($new_engagement_couleur, $new_cout_couleur, 'coul');


	/* Si on modifie les FAS manuellement, il faut vérifier qu'on ne dépasse pas les fas max
	 * Si on dépasse, il faut les rabaisser au montant maximum
	 */
	/*if((!empty($new_fas) && !empty($old_fas) && $new_fas != $old_fas)
		|| (!empty($new_fas) && !empty($old_fas) && $new_fas != $old_fas))
	{
		$new_fas = $integrale->calcul_fas_max($new_fas);
	}


	// GESTION DU NOIR
	if(!empty($new_engagement_noir) && !empty($old_engagement_noir) && $new_engagement_noir != $old_engagement_noir) {
		// Calcul new cout
		$new_cout_noir = $integrale->calcul_cout_unitaire($new_engagement_noir, 'noir');
		$new_cout_noir *= $pourcentage_sup_mois_decembre;
		// Get detail
		$TDetailCoutNoir = $integrale->calcul_detail_cout($new_engagement_noir, $new_cout_noir, 'noir');
	} else if(!empty($new_cout_noir) && !empty($old_cout_noir) && $new_cout_noir != $old_cout_noir) {
		// Calcul new cout
		$cout_noir_calcule = $integrale->calcul_cout_unitaire($new_engagement_noir, 'noir');
		// Get detail
		$TDetailCoutNoir = $integrale->calcul_detail_cout($new_engagement_noir, $cout_noir_calcule, 'noir');
		// Calcul FAS
		$new_fas_noir = $integrale->calcul_fas($TDetailCoutNoir, $new_cout_noir, $new_engagement_noir);
		//echo $new_engagement_noir.' : '. $cout_noir_calcule;
	} elseif((!empty($new_fas) && !empty($old_fas) && $new_fas != $old_fas) || (!empty($new_repartition_noir) && !empty($old_repartition_noir) && $new_repartition_noir != $old_repartition_noir)) {
		// Calcul supplémentaire si FAS modifiés
		//echo $new_engagement_noir.' : '.$new_cout_noir.'<br />';
		$cout_noir_calcule = $integrale->calcul_cout_unitaire($new_engagement_noir, 'noir');
		$TDetailCoutNoir = $integrale->calcul_detail_cout($new_engagement_noir, $cout_noir_calcule, 'noir');
		$new_cout_noir = $integrale->calcul_cout_unitaire_by_fas($new_engagement_noir, $TDetailCoutNoir, $new_fas, $old_fas, $new_repartition_noir);
		$new_cout_noir *= $pourcentage_sup_mois_decembre;

	}
	// Get detail
	$TDetailCoutNoir = $integrale->calcul_detail_cout($new_engagement_noir, $new_cout_noir, 'noir');


	// GESTION DE LA COULEUR
	if(!empty($new_engagement_couleur) && !empty($old_engagement_couleur) && $new_engagement_couleur != $old_engagement_couleur) {
		// Calcul new cout
		$new_cout_couleur = $integrale->calcul_cout_unitaire($new_engagement_couleur, 'coul');
		$new_cout_couleur *= $pourcentage_sup_mois_decembre;
		// Get detail
		$TDetailCoutCouleur = $integrale->calcul_detail_cout($new_engagement_couleur, $new_cout_couleur, 'coul');
	} else if(!empty($new_cout_couleur) && !empty($old_cout_couleur) && $new_cout_couleur != $old_cout_couleur) {
		// Calcul new cout
		$cout_coul_calcule = $integrale->calcul_cout_unitaire($new_engagement_couleur, 'coul');
		// Get detail
		$TDetailCoutCouleur = $integrale->calcul_detail_cout($new_engagement_couleur, $cout_coul_calcule, 'coul');
		// Calcul FAS
		$new_fas_couleur = $integrale->calcul_fas($TDetailCoutCouleur['nouveau_cout_unitaire_loyer'], $new_cout_couleur);
	} elseif((!empty($new_fas) && !empty($old_fas) && $new_fas != $old_fas) || (!empty($new_repartition_couleur) && !empty($old_repartition_couleur) && $new_repartition_couleur != $old_repartition_couleur)) {
		// Calcul supplémentaire si FAS modifiés
		$cout_coul_calcule = $integrale->calcul_cout_unitaire($new_engagement_couleur, 'coul');
		$TDetailCoutCouleur = $integrale->calcul_detail_cout($new_engagement_couleur, $cout_coul_calcule, 'coul');
		$new_cout_couleur = $integrale->calcul_cout_unitaire_by_fas($new_engagement_couleur, $TDetailCoutCouleur, $new_fas, $old_fas, $new_repartition_couleur);
		$new_cout_couleur *= $pourcentage_sup_mois_decembre;

	}
	// Get detail
	$TDetailCoutCouleur = $integrale->calcul_detail_cout($new_engagement_couleur, $new_cout_couleur, 'coul');

	if(empty($new_repartition_couleur)) {
		$new_repartition_couleur = $integrale->calcul_percent_couleur($TDetailCoutNoir, $new_engagement_noir, $TDetailCoutCouleur, $new_engagement_couleur);
		$new_repartition_noir = 100 - $new_repartition_couleur;
	}
	//if(empty($new_repartition_noir)) $new_repartition_noir = 50;


	// Nouvelle méthode de calcul en fonction des répartitions en %tage.
	/*if(!empty($new_repartition_noir) && !empty($old_repartition_noir) && $new_repartition_noir != $old_repartition_noir) {

		$new_cout_noir = $integrale->calcul_cout_unitaire_by_repartition($new_engagement_noir,
																			$TDetailCoutNoir['nouveau_cout_unitaire_mach'],
																			$TDetailCoutNoir['nouveau_cout_unitaire_loyer'],
																			$TDetailCoutNoir['nouveau_cout_unitaire_tech'],
																			$new_engagement_couleur,
																			$TDetailCoutCouleur['nouveau_cout_unitaire_mach'],
																			$TDetailCoutCouleur['nouveau_cout_unitaire_loyer'],
																			$TDetailCoutCouleur['nouveau_cout_unitaire_tech'],
																			$new_repartition_noir, $type='noir');

		$TDetailCoutNoir = $integrale->calcul_detail_cout($new_engagement_noir, $new_cout_noir, 'noir');

	}

	if(!empty($new_repartition_couleur) && !empty($old_repartition_couleur) && $new_repartition_couleur != $old_repartition_couleur) {

		$new_cout_couleur = $integrale->calcul_cout_unitaire_by_repartition($new_engagement_noir,
																			$TDetailCoutNoir['nouveau_cout_unitaire_mach'],
																			$TDetailCoutNoir['nouveau_cout_unitaire_loyer'],
																			$TDetailCoutNoir['nouveau_cout_unitaire_tech'],
																			$new_engagement_couleur,
																			$TDetailCoutCouleur['nouveau_cout_unitaire_mach'],
																			$TDetailCoutCouleur['nouveau_cout_unitaire_loyer'],
																			$TDetailCoutCouleur['nouveau_cout_unitaire_tech'],
																			$new_repartition_couleur, 'couleur');

		$TDetailCoutCouleur = $integrale->calcul_detail_cout($new_engagement_couleur, $new_cout_couleur, 'coul');

	}*/
/*$new_cout_couleur = $integrale->calcul_cout_unitaire_by_repartition($TDetailCoutNoir, $new_engagement_noir, $TDetailCoutCouleur, $new_engagement_couleur, $new_repartition_couleur, 'couleur');
echo $new_cout_couleur;*/
	$total_noir = $new_engagement_noir * $new_cout_noir;
	$total_couleur = $new_engagement_couleur * $new_cout_couleur;

	print $form->hidden('action', 'addAvenantIntegrale');
	print $form->hidden('id', GETPOST('id'));
	print $form->hidden('id_integrale', $integrale->getId());
	print $form->hidden('fk_facture', $f->id);
	print $form->hidden('fk_soc', $f->socid);

	// On a également besoin d'afficher 2 hidden contenant la même valeur que les champs Coût unitaire noir & Coût unitaire couleur, pour ensuite vérifier s'ils ont été modifiés à la main par l'utilisateur
	print $form->hidden('old_engagement_noir', $new_engagement_noir);
	print $form->hidden('old_engagement_couleur', $new_engagement_couleur);
	print $form->hidden('old_cout_unitaire_noir', $new_cout_noir);
	print $form->hidden('old_cout_unitaire_couleur', $new_cout_couleur);
	print $form->hidden('old_fas', $new_fas);
	print $form->hidden('old_repartition_noir', $new_repartition_noir);
	print $form->hidden('old_repartition_couleur', $new_repartition_couleur);

	$style = 'style="background-color: #C0C0C0"';

	print '<div id="calculateur">';

	//echo 'total : '.$total_noir.' + '.$total_couleur.' + '.$new_fas .' + '. $new_fas_noir .' + '. $new_fas_couleur.' + '.$integrale->fass.' + '.$integrale->frais_bris_machine.' + '.$integrale->frais_facturation.'<br>';

	print $TBS->render('./tpl/avenant_integrale.tpl.php'
		,array()
		,array(
			'noir'=>array(
				'engage'=>$integrale->vol_noir_engage
				,'nouvel_engagement'=>$form->texte('','nouvel_engagement_noir',$new_engagement_noir,10,0,' engagement_type="noir" autocomplete="off"')
				,'montant_total'=>$form->texteRO('','montant_total_noir',$total_noir,10,'',$style)
				,'cout_unitaire'=>$integrale->cout_unit_noir
				,'cout_unit_tech'=>$integrale->cout_unit_noir_tech
				,'cout_unit_mach'=>$integrale->cout_unit_noir_mach
				,'cout_unit_loyer'=>$integrale->cout_unit_noir_loyer
				,'repartition'=>!empty($new_engagement_couleur) ? $form->texte('','nouvelle_repartition_noir',$new_repartition_noir,3) : $form->texte('','nouvelle_repartition_noir',100,3,0,$style)
				,'nouveau_cout_unitaire'=>$form->texteRO('','nouveau_cout_unitaire_noir', $new_cout_noir,10,'',$style)
				,'nouveau_cout_unit_tech'=>$form->texteRO('','nouveau_cout_unit_noir_tech', $TDetailCoutNoir['nouveau_cout_unitaire_tech'],10,'',$style)  // Identique à l'ancien dans tous les cas
				,'nouveau_cout_unit_mach'=>$form->texteRO('','nouveau_cout_unit_noir_mach', $TDetailCoutNoir['nouveau_cout_unitaire_mach'],10,'',$style)
				,'nouveau_cout_unit_loyer'=>$form->texteRO('','nouveau_cout_unit_noir_loyer', $TDetailCoutNoir['nouveau_cout_unitaire_loyer'],10,'',$style)
			),
			'couleur'=>array(
				'engage'=>$integrale->vol_coul_engage
				,'nouvel_engagement'=>!empty($new_engagement_couleur) ? $form->texte('','nouvel_engagement_couleur',$new_engagement_couleur,10,0,' engagement_type="coul" autocomplete="off"') : $form->texteRO('','nouvel_engagement_couleur',$new_engagement_couleur,10,'',$style)
				,'montant_total'=>$form->texteRO('','montant_total_couleur',$total_couleur,10,'',$style)
				,'cout_unitaire'=>$integrale->cout_unit_coul
				,'cout_unit_tech'=>$integrale->cout_unit_coul_tech
				,'cout_unit_mach'=>$integrale->cout_unit_coul_mach
				,'cout_unit_loyer'=>$integrale->cout_unit_coul_loyer
				,'repartition'=>!empty($new_engagement_couleur) ? $form->texte('','nouvelle_repartition_couleur',$new_repartition_couleur,3) : $form->texteRO('','nouvelle_repartition_couleur',0,3,0,$style)
				,'nouveau_cout_unitaire'=>$form->texteRO('','nouveau_cout_unitaire_coul', $new_cout_couleur,10,'',$style)
				,'nouveau_cout_unit_tech'=>$form->texteRO('','nouveau_cout_unit_coul_tech', $TDetailCoutCouleur['nouveau_cout_unitaire_tech'],10,'',$style) // Identique à l'ancien dans tous les cas
				,'nouveau_cout_unit_mach'=>$form->texteRO('','nouveau_cout_unit_coul_mach', $TDetailCoutCouleur['nouveau_cout_unitaire_mach'],10,'',$style)
				,'nouveau_cout_unit_loyer'=>$form->texteRO('','nouveau_cout_unit_coul_loyer', $TDetailCoutCouleur['nouveau_cout_unitaire_loyer'],10,'',$style)
			),
			'global'=>array(
				'FAS'=>$form->texte('','nouveau_fas', ($new_fas + $new_fas_noir + $new_fas_couleur) * $pourcentage_sup_mois_decembre,10,'')
				,'FASS'=>$form->texteRO('','fass', $integrale->fass * $pourcentage_sup_mois_decembre,10,'',$style)
				,'frais_bris_machine'=>$form->texteRO('','frais_bris_machine',$integrale->frais_bris_machine  * $pourcentage_sup_mois_decembre,10,'',$style)
				,'frais_facturation'=>$form->texteRO('','ftc',$integrale->frais_facturation * $pourcentage_sup_mois_decembre,10,'',$style)
				,'total_global'=>$form->texteRO('','total_global',$total_noir
																+$total_couleur
																+$new_fas + $new_fas_noir + $new_fas_couleur
																+$integrale->fass
																+$integrale->frais_bris_machine
																+$integrale->frais_facturation,10,'',$style)
				,'total_hors_frais'=>$form->texteRO('','total_hors_frais',$total_noir
																+$total_couleur
																+$new_fas + $new_fas_noir + $new_fas_couleur
																+$integrale->fass,10,'',$style)
			),
			'rights'=>array(
				'voir_couts_unitaires'=>(int)$user->rights->financement->integrale->detail_couts
			)
		)
	);

	print '</div>';

	print '<div class="tabsAction">';
	print $form->btsubmit($langs->trans('Calculer'), 'btCalcul', '', 'butAction');
	// On n'utilise plus la solution en dessous
	//print $form->checkbox1('Ne pas imprimer bloc locataire', 'no_print_bloc_locataire', 1, $pDefault);
	print $form->btsubmit($langs->trans('Save'), 'btSave', '', 'butAction');
	print '</div>';

	print '<script type="text/javascript" src="./js/avenant_integral.js"></script>';
}

function _printFormAvenantIntegrale(&$PDOdb, &$dossier, &$TBS) {

	global $user, $langs;

	$TFacture = &$dossier->TFacture;
	if(empty($TFacture)) {
		setEventMessage($langs->trans('NoInvoiceIntegralFound'), 'warnings');
		return 0;
	}

	$f = is_object(end($TFacture)) ? end($TFacture) : end(end($TFacture)); // Dans certains cas, il y a plusieurs factures pour une période, on veut la dernière

	$integrale = new TIntegrale;
	$integrale->loadBy($PDOdb, $f->ref, 'facnumber');

	$form=new TFormCore($_SERVER['PHP_SELF'].'#calculateur', 'formAvenantIntegrale', 'POST');

	// Si simulation sur le mois de décembre, +6% sur tous les coûts
	$pourcentage_sup_mois_decembre = ((int)date('m') == 12) ? 1.06 : 1;

	/*$new_engagement_noir = GETPOST('nouvel_engagement_noir');
	$new_engagement_couleur = GETPOST('nouvel_engagement_couleur');
	$old_engagement_noir = GETPOST('old_engagement_noir');
	$old_engagement_couleur = GETPOST('old_engagement_couleur');
	$new_cout_noir = GETPOST('nouveau_cout_unitaire_noir');
	$new_cout_couleur = GETPOST('nouveau_cout_unitaire_couleur');
	$old_cout_noir = GETPOST('old_cout_unitaire_noir');
	$old_cout_couleur = GETPOST('old_cout_unitaire_couleur');
	$new_fas = GETPOST('nouveau_fas');
	$old_fas = GETPOST('old_fas');
	$old_repartition_noir = GETPOST('old_repartition_noir');
	$old_repartition_couleur = GETPOST('old_repartition_couleur');
	$new_repartition_noir = GETPOST('nouvelle_repartition_noir');
	$new_repartition_couleur = GETPOST('nouvelle_repartition_couleur');
	$new_fas_noir = 0;
	$new_fas_couleur = 0;*/

	$engagement_noir = $integrale->vol_noir_engage;
	$engagement_couleur = $integrale->vol_coul_engage;
	$cout_noir = $integrale->cout_unit_noir * $pourcentage_sup_mois_decembre;
	$cout_couleur = $integrale->cout_unit_coul * $pourcentage_sup_mois_decembre;
	$fas = $integrale->fas;

	$TDetailCoutNoir = $integrale->calcul_detail_cout(0, 0, 'noir');
	$TDetailCoutCouleur = $integrale->calcul_detail_cout(0, 0, 'coul');

	$repartition_couleur = $integrale->calcul_percent_couleur();
	$repartition_noir = 100 - $repartition_couleur;

	$total_noir = $engagement_noir * $cout_noir;
	$total_couleur = $engagement_couleur * $cout_couleur;

	$fas_min = $integrale->fas;
	$fas_max = $integrale->calcul_fas_max($TDetailCoutNoir, $TDetailCoutCouleur, $engagement_noir, $engagement_couleur);
	$fas_max = max($fas_max, $integrale->fas);

	$total_global = $integrale->calcul_total_global($TDetailCoutNoir, $TDetailCoutCouleur);
	$total_hors_frais = $total_global - $integrale->frais_bris_machine - $integrale->frais_facturation;

	print $form->hidden('action', 'addAvenantIntegrale');
	print $form->hidden('id', GETPOST('id'));
	print $form->hidden('id_integrale', $integrale->getId());
	print $form->hidden('fk_facture', $f->id);
	print $form->hidden('fk_soc', $f->socid);

	// Calcul de la période concernée par l'avenant
	$fin = &$dossier->financement;
	$ip = $fin->getiPeriode();
	$nb = ((date('n') - 1) % $ip) * -1;
	if($fin->terme == 1) $nb += $ip; // A échoir, on prend la période suivante
	$deb_periode = strtotime('first day of '.$nb.' month');
	$fin_periode = strtotime('last day of +'.($ip - 1).' month',$deb_periode);

	print $form->hidden('date_deb_periode', $deb_periode);
	print $form->hidden('date_fin_periode', $fin_periode);

	// On a également besoin d'afficher 2 hidden contenant la même valeur que les champs Coût unitaire noir & Coût unitaire couleur, pour ensuite vérifier s'ils ont été modifiés à la main par l'utilisateur
	/*print $form->hidden('old_engagement_noir', $new_engagement_noir);
	print $form->hidden('old_engagement_couleur', $new_engagement_couleur);
	print $form->hidden('old_cout_unitaire_noir', $new_cout_noir);
	print $form->hidden('old_cout_unitaire_couleur', $new_cout_couleur);
	print $form->hidden('old_fas', $new_fas);
	print $form->hidden('old_repartition_noir', $new_repartition_noir);
	print $form->hidden('old_repartition_couleur', $new_repartition_couleur);*/

	$style = 'style="background-color: #C0C0C0; text-align: right;"';
	if($integrale->vol_coul_engage > 0) {
		if(TFinancementTools::user_courant_est_admin_financement()) {
			$input_engagement_couleur = $form->texte('','nouvel_engagement_couleur',$engagement_couleur,10,0,' style="text-align: center;" engagement_type="coul" autocomplete="off"');
		} else {
			$input_engagement_couleur = '<input type="number" id="nouvel_engagement_couleur" name="nouvel_engagement_couleur" min="'.$engagement_couleur.'" value="'.$engagement_couleur.'" step=1" style="text-align: center;" />';
		}
	} else {
		$input_engagement_couleur = $form->texteRO('','nouvel_engagement_couleur',$engagement_couleur,10,0,' style="text-align: center; background-color: #C0C0C0;" engagement_type="coul" autocomplete="off"');
	}

	if(TFinancementTools::user_courant_est_admin_financement()) {
		$input_engagement_noir = $form->texte('','nouvel_engagement_noir',$engagement_noir,10,0,' style="text-align: center;" engagement_type="noir" autocomplete="off"');
	} else {
		$input_engagement_noir = '<input type="number" id="nouvel_engagement_noir" name="nouvel_engagement_noir" min="'.$engagement_noir.'" value="'.$engagement_noir.'" step=1" style="text-align: center;" />';
	}

	print '<div id="calculateur">';

	//echo 'total : '.$total_noir.' + '.$total_couleur.' + '.$new_fas .' + '. $new_fas_noir .' + '. $new_fas_couleur.' + '.$integrale->fass.' + '.$integrale->frais_bris_machine.' + '.$integrale->frais_facturation.'<br>';

	print $TBS->render('./tpl/avenant_integrale.tpl.php'
		,array()
		,array(
			'noir'=>array(
				'engage'=>$integrale->vol_noir_engage
				,'cout_unitaire'=>$integrale->cout_unit_noir
				,'cout_unit_tech'=>$integrale->cout_unit_noir_tech
				,'cout_unit_mach'=>$integrale->cout_unit_noir_mach
				,'cout_unit_loyer'=>$integrale->cout_unit_noir_loyer
				,'nouvel_engagement'=>$input_engagement_noir
				,'nouveau_cout_unitaire'=>$form->texteRO('','nouveau_cout_unitaire_noir', $TDetailCoutNoir['cout_unitaire'],10,'',$style)
				,'nouveau_cout_unit_tech'=>$form->texteRO('','nouveau_cout_unit_noir_tech', $TDetailCoutNoir['nouveau_cout_unitaire_tech'],10,'',$style)  // Identique à l'ancien dans tous les cas
				,'nouveau_cout_unit_mach'=>$form->texteRO('','nouveau_cout_unit_noir_mach', $TDetailCoutNoir['nouveau_cout_unitaire_mach'],10,'',$style)
				,'nouveau_cout_unit_loyer'=>$form->texteRO('','nouveau_cout_unit_noir_loyer', $TDetailCoutNoir['nouveau_cout_unitaire_loyer'],10,'',$style)
				,'montant_total'=>$form->texteRO('','montant_total_noir',$total_noir,10,'',$style)
				,'repartition'=>!empty($engagement_couleur) ? $form->texte('','nouvelle_repartition_noir',$repartition_noir,3) : $form->texte('','nouvelle_repartition_noir',100,3,0,$style)
			),
			'couleur'=>array(
				'engage'=>$integrale->vol_coul_engage
				,'cout_unitaire'=>$integrale->cout_unit_coul
				,'cout_unit_tech'=>$integrale->cout_unit_coul_tech
				,'cout_unit_mach'=>$integrale->cout_unit_coul_mach
				,'cout_unit_loyer'=>$integrale->cout_unit_coul_loyer
				,'nouvel_engagement'=>$input_engagement_couleur
				,'nouveau_cout_unitaire'=>$form->texteRO('','nouveau_cout_unitaire_coul', $TDetailCoutCouleur['cout_unitaire'],10,'',$style)
				,'nouveau_cout_unit_tech'=>$form->texteRO('','nouveau_cout_unit_coul_tech', $TDetailCoutCouleur['nouveau_cout_unitaire_tech'],10,'',$style) // Identique à l'ancien dans tous les cas
				,'nouveau_cout_unit_mach'=>$form->texteRO('','nouveau_cout_unit_coul_mach', $TDetailCoutCouleur['nouveau_cout_unitaire_mach'],10,'',$style)
				,'nouveau_cout_unit_loyer'=>$form->texteRO('','nouveau_cout_unit_coul_loyer', $TDetailCoutCouleur['nouveau_cout_unitaire_loyer'],10,'',$style)
				,'montant_total'=>$form->texteRO('','montant_total_coul',$total_couleur,10,'',$style)
				,'repartition_input'=>!empty($engagement_couleur) ? $form->texte('','nouvelle_repartition_couleur',$repartition_couleur,3) : $form->texteRO('','nouvelle_repartition_couleur',0,3,0,$style)
				,'repartition'=>$repartition_couleur
			),
			'global'=>array(
				'FAS'=>$form->texte('','nouveau_fas', ($fas + $fas_noir + $fas_couleur) * $pourcentage_sup_mois_decembre,10,'')
				,'FAS'=>'<input type="number" id="nouveau_fas" name="nouveau_fas" min="'.$fas_min.'" max="'.$fas_max.'" value="'.$fas.'" step="0.01" style="text-align: center;" />'
				,'FASS'=>$form->texteRO('','fass', $integrale->fass * $pourcentage_sup_mois_decembre,10,'',$style)
				,'frais_bris_machine'=>$form->texteRO('','frais_bris_machine',$integrale->frais_bris_machine  * $pourcentage_sup_mois_decembre,10,'',$style)
				,'frais_facturation'=>$form->texteRO('','ftc',$integrale->frais_facturation * $pourcentage_sup_mois_decembre,10,'',$style)
				,'total_global'=>$form->texteRO('','total_global',$total_global,10,'',$style)
				,'total_hors_frais'=>$form->texteRO('','total_hors_frais',$total_hors_frais,10,'',$style)
			),
			'rights'=>array(
				'voir_couts_unitaires'=>(int)$user->rights->financement->integrale->detail_couts
			)
		)
	);

	print '</div>';

	print '<div class="tabsAction">';
	//print $form->btsubmit($langs->trans('Calculer'), 'btCalcul', '', 'butAction');
	// On n'utilise plus la solution en dessous
	//print $form->checkbox1('Ne pas imprimer bloc locataire', 'no_print_bloc_locataire', 1, $pDefault);
	print $form->btsubmit($langs->trans('Save'), 'btSave', '', 'butAction');
	print '</div>';

	print '<script type="text/javascript" src="./js/avenant_integral.js"></script>';
}

function regularisation($dossier,$TIntegrale){
	global $langs;
	$periodicite = 0;
	//Conversion periodicite en nombre

	switch($dossier->financement->periodicite){
		case 'MOIS':
			$periodicite=1;
			break;
		case 'TRIMESTRE':
			$periodicite=3;
			break;
		case 'SEMESTRE':
			$periodicite=6;
			break;
		case 'ANNEE':
			$periodicite=12;
			break;
	}

	//On vérifie que la période de régularisation est supérieur à la periode de facturation
	if( $dossier->type_regul > $periodicite && !empty($dossier->month_regul)){

		$dateTemp = '';//date temporaire
		$compteur=0;
		$volNoir= 0;
		$volCoul=0;
		$volNoirEngag=0;
		$volCoulEngag=0;
		$isIntercal=true;
		/*$ifError = array();
		foreach($TIntegrale as $k=>$v){
			$ifError[$k] = clone ($v);
		}
		*/
		/*$trimestre='';
		$trimestre3='';
		$semestre = '';
		if($dossier->type_regul==6){
			$semestre='07';
		}
		if($dossier->type_regul==3){
			$trimestre='04';
			$trimestre3='10';
			$semestre='07';
		}*/

		$TMonthRegul = array($dossier->month_regul);
		if($dossier->type_regul==6){
			$TMonthRegul[] = ($dossier->month_regul + 6) % 12;
		}
		if($dossier->type_regul==3){
			$TMonthRegul[] = ($dossier->month_regul + 3) % 12;
			$TMonthRegul[] = ($dossier->month_regul + 6) % 12;
			$TMonthRegul[] = ($dossier->month_regul + 9) % 12;
		}

	/*	$keys = array_keys($TIntegrale);
		$tabTemp = explode('/',$keys[0]);
		$timecompare = dol_mktime(0, 0, 0, $tabTemp[1], $tabTemp[0], $tabTemp[2]);
		*/
		foreach($TIntegrale as &$tab){
			if($isIntercal && !empty($dossier->financement->loyer_intercalaire) && !empty($dossier->TFacture[-1])){//Vérification loyer intercalaire et existance facture

				$isIntercal = false;

			}else {
				$theDate = explode('/',$tab->date_periode);
				$periode = new DateTime($theDate[2].'-'.$theDate[1].'-'.$theDate[0]);//On met la date au bon format
			/*	if(empty($dateTemp)){//Si c'est le premier passage
					$dateTemp = $periode;
					$volNoir+= $tab->vol_noir_realise;
					$volCoul+= $tab->vol_coul_realise;
				//	$volNoirEngag+=$tab->vol_noir_engage;
					//$volCoulEngag+=$tab->vol_coul_engage;
					//$tab->vol_noir_realise = 0;
					$tab->vol_noir_facture = $tab->vol_noir_engage;
				//	$tab->vol_coul_realise = 0;
					$tab->vol_coul_facture = $tab->vol_coul_engage;
				} else {*/
				//	$dateCompare = ($periode->diff($dateTemp));//On compare la date actuelle avec la date d'avant
				//	if($dateCompare->days <=$periodicite*31 && $dateCompare->days>=$periodicite*30){//On regarde si le nombre de jours est environ au bon nombre de mois
						$compteur++;
						//if(($theDate[1]!=$trimestre)&&($theDate[1]!=$semestre)&&($theDate[1]!=$trimestre3)&& ($theDate[1]!='01') ){//On compare les mois
						if(!in_array((int)$theDate[1], $TMonthRegul) ){//On compare les mois
							$volNoir+= $tab->vol_noir_realise;
							$volCoul+= $tab->vol_coul_realise;
							$volNoirEngag+=$tab->vol_noir_engage;
							$volCoulEngag+=$tab->vol_coul_engage;
						//	$tab->vol_noir_realise = 0;
							$tab->vol_noir_facture = $tab->vol_noir_engage;
							//$tab->vol_coul_realise = 0;
							$tab->vol_coul_facture = $tab->vol_coul_engage;
							//setBilledVol($tab);
						}
						else {
						//	$volNoir+= $tab->vol_noir_realise;//On n'oublie pas de bien prendre en compte les valeurs de la ligne actuelle
						//	$volCoul+= $tab->vol_coul_realise;
						//	$volNoirEngag+=$tab->vol_noir_engage;
						//	$volCoulEngag+=$tab->vol_coul_engage;
							$tab->vol_noir_facture = max(array($tab->vol_noir_realise - $volNoirEngag,$tab->vol_noir_engage));
							$tab->vol_coul_facture = max(array($tab->vol_coul_realise - $volCoulEngag,$tab->vol_coul_engage));
							$tab->vol_noir_realise -= $volNoir;
							$tab->vol_coul_realise -= $volCoul;
							$compteur = 0;
							//setBilledVol($tab);
							//Reinitialisation des variables
							$volNoir=0;
							$volCoul=0;
							$volNoirEngag=0;
							$volCoulEngag=0;
						}

				/*	}	else {
						//
						setEventMessages($langs->trans('DateProblem'),$err,'errors');
						$volNoir=0;
						$volCoul=0;
						$volNoirEngag=0;
						$volCoulEngag=0;
						return $ifError;

					}*/

				//	$dateTemp = $periode;//On récupère la date actuelle
			//	}
			}
		}
	}
	return $TIntegrale;
}
function setBilledVol(&$tab){
		if($tab->vol_noir_realise<$tab->vol_noir_engage){//On compare l'engagé  avec le réalisé

				$tab->vol_noir_facture = $tab->vol_noir_engage;
		} else {

				$tab->vol_noir_facture = $tab->vol_noir_realise;
		}

		if($tab->vol_coul_realise<$tab->vol_coul_engage){
				$tab->vol_coul_facture = $tab->vol_coul_engage;
		} else {

				$tab->vol_coul_facture = $tab->vol_coul_realise;
		}
}


function _addAvenantIntegrale(&$dossier) {

	global $db, $user, $conf, $mysoc;

	$old_conf = $conf;
	switchEntity($dossier->entity);

	$p = new Propal($db);
	$p->socid = GETPOST('fk_soc');
	$p->date = strtotime(date('Y-m-d'));
	$p->duree_validite = 30;

	$p->cond_reglement_id = 0;
	$p->mode_reglement_id = 0;

	$p->array_options['options_repartition_noir'] = GETPOST('nouvelle_repartition_noir');
	$p->array_options['options_repartition_couleur'] = GETPOST('nouvelle_repartition_couleur');

	$res = $p->create($user);

	if($res > 0) {

		_addLines($p);
		$p->valid($user);
		//setEventMessage('Avenant <a href="'.dol_buildpath('/comm/propal.php?id='.$p->id, 1).'">'.$p->ref.'</a> créé avec succès !');
		$f = new Facture($db);
		$f->id = GETPOST('fk_facture');
		$f->element = 'facture';
		$f->add_object_linked('propal', $p->id);

		//$no_print_bloc_locataire = GETPOST('no_print_bloc_locataire');

		$file_path = _genPDF($p, array(
									'engagement_noir'=>GETPOST('nouvel_engagement_noir')
									,'cout_unitaire_noir'=>GETPOST('nouveau_cout_unitaire_noir')
									,'engagement_couleur'=>GETPOST('nouvel_engagement_couleur')
									,'cout_unitaire_couleur'=>GETPOST('nouveau_cout_unitaire_coul')
									,'FAS'=>GETPOST('nouveau_fas')
									,'FASS'=>GETPOST('fass')
									,'ref_dossier'=>$dossier->financement->reference
									,'total_global'=>GETPOST('total_global')
									,'total_hors_frais'=>GETPOST('total_hors_frais')
									,'date_deb_periode'=>GETPOST('date_deb_periode')
									,'date_fin_periode'=>GETPOST('date_fin_periode')
									,'client'=>_getInfosClient($p->socid)
								  ));

		switchEntity($old_conf->entity);
		return $file_path;

	}

	return 0;

}

function _getInfosClient($fk_soc) {

	global $db;

	dol_include_once('/societe/class/societe.class.php');

	$s = new Societe($db);
	$s->fetch($fk_soc);

	$TData['raison_sociale'] = $s->name;
	$TData['adresse'] = $s->getFullAddress();
	$TData['siren'] = $s->idprof1;
	$TData['dirigeant'] = '';

	return $TData;

}
//Pour le cas A Echoir modifie les 2 premieres valeurs du tableau
function updateFirstVals($TIntegrale){
	$temp = 0; //valeur temporaire afin de modifier uniquement la 1re et 2nde valeur
	$valNoirEngage = 0;//valeur contenant le noir engagé de la 1ere facture
	$valCoulEngage = 0;//valeur contenant la couleur engagée de la 1ere facture
	if(!empty($TIntegrale)){
		foreach($TIntegrale as $tab){
			if($temp==0){
				$tab->vol_noir_realise = 0;
				$tab->vol_coul_realise = 0;
				$valNoirEngage=$tab->vol_noir_engage;
				$valCoulEngage=$tab->vol_coul_engage;
			} else if($temp==1){
				$tab->vol_noir_realise = $tab->vol_noir_realise+$tab->vol_noir_engage-$valNoirEngage;
				$tab->vol_coul_realise = $tab->vol_coul_realise+$tab->vol_coul_engage-$valCoulEngage;
			}
			$temp++;
		}
	}
	return $TIntegrale;
}

function _addLines(&$p) {

	global $db;
	//pre($_REQUEST, true);exit;
	$TProduits = _getIDProducts();

	if(!empty($TProduits['E_NOIR'])) $p->addline('Nouvel engagement noir', GETPOST('nouveau_cout_unitaire_noir'), GETPOST('nouvel_engagement_noir'), 20, 0.0, 0.0, $TProduits['E_NOIR']);
	if(!empty($TProduits['E_COUL'])) $p->addline('Nouvel engagement couleur', GETPOST('nouveau_cout_unitaire_coul'), GETPOST('nouvel_engagement_couleur'), 20, 0.0, 0.0, $TProduits['E_COUL']);

	if(!empty($TProduits['FAS'])) $p->addline('FAS', GETPOST('nouveau_fas'), 1, 20, 0.0, 0.0, $TProduits['FAS']);
	if(!empty($TProduits['FASS'])) $p->addline('FASS', GETPOST('fass'), 1, 20, 0.0, 0.0, $TProduits['FASS']);
	if(!empty($TProduits['FTC'])) $p->addline('FTC', GETPOST('ftc'), 1, 20, 0.0, 0.0, $TProduits['FTC']);

}

function _getIDProducts() {

	global $db;

	// 037004 = Frais bris de machine
	$sql = 'SELECT ref, rowid FROM '.MAIN_DB_PREFIX.'product WHERE ref IN("037004", "FAS", "FASS", "FTC", "E_NOIR", "E_COUL")';
	$resql = $db->query($sql);
	$TProduits = array();
	while($res = $db->fetch_object($resql)) $TProduits[$res->ref] = $res->rowid;

	return $TProduits;

}

function _genPDF(&$propal, $TData, $print_bloc_locataire=true) {

	global $conf, $mysoc;

	$TBS=new TTemplateTBS();

	$dir = $conf->propal->dir_output.'/'.$propal->ref;
	@dol_mkdir($dir);

	$file_name = $propal->ref.'_avenant_'.date('Ymd');

	$file_path = $TBS->render(dol_buildpath('/financement/tpl/doc/modele_avenant.odt')
		,array()
		,array(
			'avenant'=>array(
				'ref'=>$propal->ref
			)
			,'copies_noires'=>array(
				'engagement'=>$TData['engagement_noir']
				,'cout_unitaire'=>price($TData['cout_unitaire_noir'])
			)
			,'copies_couleur'=>array(
				'engagement'=>$TData['engagement_couleur']
				,'cout_unitaire'=>price($TData['cout_unitaire_couleur'])
			)
			,'global'=>array(
				'FAS'=>price($TData['FAS'])
				,'FASS'=>price($TData['FASS'])
				,'ref_dossier'=>$TData['ref_dossier']
				,'total_global'=>price($TData['total_global'])
				,'total_hors_frais'=>price($TData['total_hors_frais'])
				,'date_deb_periode'=>date('d/m/Y', $TData['date_deb_periode'])
				,'date_fin_periode'=>date('d/m/Y', $TData['date_fin_periode'])
			)
			,'bloc_locataire'=>array(
				'raison_sociale'=>$print_bloc_locataire ? $TData['client']['raison_sociale'] : ''
				,'adresse'=>$print_bloc_locataire ? $TData['client']['adresse'] : ''
				,'siren'=>$print_bloc_locataire ? $TData['client']['siren'] : ''
				,'dirigeant'=>$print_bloc_locataire ? $TData['client']['dirigeant'] : ''
			)
			,'mysoc'=>$mysoc
		)
		,array()
		,array(
			'outFile'=>$dir.'/'.$file_name.'.odt'
			,"convertToPDF"=>true
		)

	);

	return dol_buildpath('/document.php?modulepart=propal&entity='.$conf->entity.'&file='.$propal->ref.'/'.$file_name.'.pdf', 2);

}

function _genDocEmpty($TData, $print_bloc_locataire=true) {

	global $conf,$user,$mysoc;

	$TBS=new TTemplateTBS();

	$dir = $conf->user->dir_output.'/'.$user->id;
	@mkdir($dir);

	$file_name = 'Avenant_'.date('Ymd');

	$file_path = $TBS->render(dol_buildpath('/financement/tpl/doc/modele_avenant.odt')
		,array()
		,array(
			'avenant'=>array(
				'ref'=>''
			)
			,'copies_noires'=>array(
				'engagement'=>''
				,'cout_unitaire'=>''
			)
			,'copies_couleur'=>array(
				'engagement'=>''
				,'cout_unitaire'=>''
			)
			,'global'=>array(
				'FAS'=>''
				,'FASS'=>''
				,'ref_dossier'=>''
				,'total_global'=>''
				,'total_hors_frais'=>''
				,'date_deb_periode'=>''
				,'date_fin_periode'=>''
			)
			,'bloc_locataire'=>array(
				'raison_sociale'=>$print_bloc_locataire ? $TData['client']['raison_sociale'] : ''
				,'adresse'=>$print_bloc_locataire ? $TData['client']['adresse'] : ''
				,'siren'=>$print_bloc_locataire ? $TData['client']['siren'] : ''
				,'dirigeant'=>$print_bloc_locataire ? $TData['client']['dirigeant'] : ''
			)
			,'mysoc'=>$mysoc
		)
		,array()
		,array(
			'outFile'=>$dir.'/'.$file_name.'.odt'
		)

	);

	return dol_buildpath('/document.php?modulepart=user&entity='.$conf->entity.'&file='.$user->id.'/'.$file_name.'.odt', 2);

}

