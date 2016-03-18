<?php

require('config.php');
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

llxHeader('','Suivi intégrale');

if($action == 'formAvenantIntegrale') {
	_affichage($PDOdb, $TBS, $id_dossier);
	_printFormAvenantIntegrale($PDOdb, $dossier, $TBS);
} elseif($action == 'addAvenantIntegrale'){
	_addAvenantIntegrale();
	_affichage($PDOdb, $TBS, $id_dossier);
} elseif(empty($id_dossier)) {
	_liste($PDOdb, $dossier, $TBS);
} else {
	_affichage($PDOdb, $TBS, $id_dossier);
}

function _affichage(&$PDOdb, &$TBS, $id_dossier) {
	
	global $dossier, $db;
	
	$dossier->load($PDOdb, $id_dossier);
	$dossier->load_facture($PDOdb,true);
	_fiche($PDOdb, $db, $dossier, $TBS);
	
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
		
	$sql.=" WHERE a.entity=".$conf->entity;
	$sql.=" AND a.contrat='INTEGRAL' ";
	$sql.=" AND fc.duree > 0 ";
	$sql.=" AND fc.echeance > 0 ";
	$sql.=" AND fc.date_solde = '0000-00-00 00:00:00' ";
	
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
		)
		,'link'=>array(
			'nomCli'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'nomLea'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
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
	global $db;
	
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
	
	$fin = &$dossier->financement;
	
	$affaire = &$dossier->TLien[0]->affaire;
	$client = new Societe($doliDB);
	$client->fetch($affaire->fk_soc);
	
	// Affichage spé
	$dossier->url_therefore=FIN_THEREFORE_DOSSIER_URL;
	$fin->_affterme = $fin->TTerme[$fin->terme];
	$fin->_affperiodicite = $fin->TPeriodicite[$fin->periodicite];
	
	//pre($dossier->TFacture[6],true);
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
	
	//$dossier->load_facture($PDOdb,true);
	$dossier->format_facture_integrale($PDOdb);
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
					$fact->fetchObjectLinked('', 'propal', '', 'facture');
					if(!empty($fact->linkedObjects['propal'])) {
						foreach($fact->linkedObjects['propal'] as $p) $TIntegrale[$date_periode]->propal .= $p->getNomUrl(1).' '.$p->getLibStatut(3)."<br>";
					}
				}
			}
			else{
				$TIntegrale[$date_periode]->date_facture .= $facture->ref_client."<br>";
				$TIntegrale[$date_periode]->facnumber .= $facture->getNomUrl()."<br>";
				$facture->fetchObjectLinked('', 'propal', '', 'facture');
				if(!empty($facture->linkedObjects['propal'])) {
					foreach($facture->linkedObjects['propal'] as $p) $TIntegrale[$date_periode]->propal .= $p->getNomUrl(1).' '.$p->getLibStatut(3)."<br>";
				}
			}
			
		} // else{} TODO A voir comment faire car certaines factures sont des loyers intercalaires et ne sont pas associés à des périodes.
		
		//$TIntegrale[] = '';
	}
	
	foreach($TIntegrale as $date_periode => $integrale){
		if(empty($TIntegrale[$date_periode]->facnumber)){
			unset($TIntegrale[$date_periode]);
		}
	}
	//pre($TIntegrale,true);
	//array_pop($TIntegrale); //TODO c'est moche mais sa marche
	
	if(isset($_REQUEST['TRACE'])){
		foreach($TIntegrale as &$integrale) {
			foreach($integrale->TIds as $id_integral) {
				$integrale->facnumber.='<br /> log int. <a href="'.dol_buildpath('/financement/log/TIntegrale/'.$id_integral.'.log',1).'" target="_blank">'.$id_integral.'</a>';	
			}
			
		}
	}
	
	//pre($TIntegrale,true);
	echo $TBS->render('./tpl/dossier_integrale.tpl.php'
		,array(
			'integrale'=>$TIntegrale
		)
		,array(
			'dossier'=>$dossier
			,'fin'=>$fin
			,'client'=>$client
		)
	);
	
	print '<div class="tabsAction">';
	print '<a class="butAction" href="?id='.GETPOST('id').'&action=formAvenantIntegrale">Nouveau calcul d\'avenant</a>';
	print '</div>';
	
}

function _printFormAvenantIntegrale(&$PDOdb, &$dossier, &$TBS) {
	
	global $user, $langs;
	
	$TFacture = &$dossier->TFacture;
	if(empty($dossier->TFacture)) {
		setEventMessage('Aucune facture intégrale trouvée', 'warnings');
		return 0;
	}
	
	$f = is_object(end($TFacture)) ? end($TFacture) : end(end($TFacture)); // Dans certains cas, il y a plusieurs factures pour une période, on veut la dernière
	
	$integrale = new TIntegrale;
	$integrale->loadBy($PDOdb, $f->ref, 'facnumber');
	//pre($integrale, true);
	//pre($dossier->TFacture, true);
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formAvenantIntegrale', 'POST');
	print $form->hidden('action', 'addAvenantIntegrale');
	print $form->hidden('id', GETPOST('id'));
	print $form->hidden('fk_facture', $f->id);
	print $form->hidden('fk_soc', $f->socid);
	
	print $TBS->render('./tpl/avenant_integrale.tpl.php'
		,array()
		,array(
			'noir'=>array(
				'engage'=>$integrale->vol_noir_engage
				,'nouvel_engagement'=>$form->texte('','nouvel_engagement_noir',$integrale->vol_noir_engage,10)
				,'montant_total'=>$form->texteRO('','montant_total_noir',0,10,'','style="background-color: #C0C0C0"')
				,'cout_unitaire'=>$integrale->cout_unit_noir
				,'cout_unit_tech'=>$integrale->cout_unit_noir_tech
				,'cout_unit_mach'=>$integrale->cout_unit_noir_mach
				,'cout_unit_loyer'=>$integrale->cout_unit_noir_loyer
				,'nouveau_cout_unitaire'=>$form->texte('','nouveau_cout_unitaire_noir',$integrale->cout_unit_noir,10)
				,'nouveau_cout_unit_tech'=>$form->texteRO('','nouveau_cout_unit_noir_tech',0,10,'','style="background-color: #C0C0C0"')
				,'nouveau_cout_unit_mach'=>$form->texteRO('','nouveau_cout_unit_noir_mach',0,10,'','style="background-color: #C0C0C0"')
				,'nouveau_cout_unit_loyer'=>$form->texteRO('','nouveau_cout_unit_noir_loyer',0,10,'','style="background-color: #C0C0C0"')
			),
			'couleur'=>array(
				'engage'=>$integrale->vol_coul_engage
				,'nouvel_engagement'=>$form->texte('','nouvel_engagement_couleur',$integrale->vol_coul_engage,10)
				,'montant_total'=>$form->texteRO('','montant_total_couleur',0,10,'','style="background-color: #C0C0C0"')
				,'cout_unitaire'=>$integrale->cout_unit_coul
				,'cout_unit_tech'=>$integrale->cout_unit_coul_tech
				,'cout_unit_mach'=>$integrale->cout_unit_coul_mach
				,'cout_unit_loyer'=>$integrale->cout_unit_coul_loyer
				,'nouveau_cout_unitaire'=>$form->texte('','nouveau_cout_unitaire_couleur',$integrale->cout_unit_coul,10)
				,'nouveau_cout_unit_tech'=>$form->texteRO('','nouveau_cout_unit_coul_tech',0,10,'','style="background-color: #C0C0C0"')
				,'nouveau_cout_unit_mach'=>$form->texteRO('','nouveau_cout_unit_coul_mach',0,10,'','style="background-color: #C0C0C0"')
				,'nouveau_cout_unit_loyer'=>$form->texteRO('','nouveau_cout_unit_coul_loyer',0,10,'','style="background-color: #C0C0C0"')
			),
			'global'=>array(
				'FAS'=>$form->texteRO('','fas',$integrale->fas,10,'','style="background-color: #C0C0C0"')
				,'FASS'=>$form->texteRO('','fass',$integrale->fass,10,'','style="background-color: #C0C0C0"')
				,'frais_bris_machine'=>$form->texteRO('','frais_bris_machine',$integrale->frais_bris_machine,10,'','style="background-color: #C0C0C0"')
				,'frais_facturation'=>$form->texteRO('','ftc',$integrale->frais_facturation,10,'','style="background-color: #C0C0C0"')
				,'total_global'=>$form->texteRO('','total_global',$integrale->vol_noir_engage*$integrale->cout_unit_noir
																+$integrale->vol_coul_engage*$integrale->cout_unit_coul
																+$integrale->fas
																+$integrale->fass
																+$integrale->frais_bris_machine
																+$integrale->frais_facturation,10,'','style="background-color: #C0C0C0"')
			),
			'rights'=>array(
				'voir_couts_unitaires'=>(int)$user->rights->financement->integrale->detail_couts
			)
		)
	);
	
	print '<div class="tabsAction">';
	print $form->btsubmit($langs->trans('Save'), '', '', 'butAction');
	print '</div>';
	
}

function _addAvenantIntegrale() {
	
	global $db, $user;
	
	$p = new Propal($db);
	$p->socid = GETPOST('fk_soc');
	$p->date = strtotime(date('Y-m-d'));
	$p->duree_validite = 30;
	
	$p->cond_reglement_id = 0;
	$p->mode_reglement_id = 0;
	
	$res = $p->create($user);
	
	if($res > 0) {
		
		_addLines($p);
		$p->valid($user);
		setEventMessage('Avenant <a href="'.dol_buildpath('/comm/propal.php?id='.$p->id, 1).'">'.$p->ref.'</a> créé avec succès !');
		$f = new Facture($db);
		$f->id = GETPOST('fk_facture');
		$f->element = 'facture';
		$f->add_object_linked('propal', $p->id);
		
	}
	
}

function _addLines(&$p) {
	
	global $db;
	//pre($_REQUEST, true);exit;
	$TProduits = _getIDProducts();
	
	if(!empty($TProduits['E_NOIR'])) $p->addline('Nouvel engagement noir', GETPOST('nouveau_cout_unitaire_noir'), GETPOST('nouvel_engagement_noir'), 20, 0.0, 0.0, $TProduits['E_NOIR']);
	if(!empty($TProduits['E_COUL'])) $p->addline('Nouvel engagement couleur', GETPOST('nouveau_cout_unitaire_couleur'), GETPOST('nouvel_engagement_couleur'), 20, 0.0, 0.0, $TProduits['E_COUL']);
	
	if(!empty($TProduits['FAS'])) $p->addline('FAS', GETPOST('fas'), 1, 20, 0.0, 0.0, $TProduits['FAS']);
	if(!empty($TProduits['FASS'])) $p->addline('FASS', GETPOST('fass'), 1, 20, 0.0, 0.0, $TProduits['FASS']);
	if(!empty($TProduits['FTC'])) $p->addline('FTC', GETPOST('ftc'), 1, 20, 0.0, 0.0, $TProduits['FTC']);
	
}

function _getIDProducts() {
	
	global $db;
	
	// 037004 = Frais brise de machine
	$sql = 'SELECT ref, rowid FROM '.MAIN_DB_PREFIX.'product WHERE ref IN("037004", "FAS", "FASS", "FTC", "E_NOIR", "E_COUL")';
	$resql = $db->query($sql);
	$TProduits = array();
	while($res = $db->fetch_object($resql)) $TProduits[$res->ref] = $res->rowid; 
	
	return $TProduits;
	
}
