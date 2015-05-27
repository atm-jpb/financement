<?php

require('config.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/dossier_integrale.class.php');
require('./class/grille.class.php');

$langs->load('financement@financement');

if (!$user->rights->financement->integrale->read)	{ accessforbidden(); }

$dossier=new TFin_Dossier;
$PDOdb=new TPDOdb;

$id_dossier = GETPOST('id');

llxHeader('','Suivi intégrale');

if(empty($id_dossier)) {
	_liste($PDOdb, $dossier);
} else {
	$dossier->load($PDOdb, $id_dossier);
	$dossier->load_facture($PDOdb,true);
	_fiche($PDOdb, $db, $dossier);
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
	
	if (!$user->rights->societe->client->voir) {
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON sc.fk_soc = c.rowid";
	}
		
	$sql.=" WHERE a.entity=".$conf->entity;
	$sql.=" AND a.contrat='INTEGRAL' ";
	$sql.=" AND fc.duree > 0 ";
	$sql.=" AND fc.echeance > 0 ";
	$sql.=" AND fc.date_solde = '0000-00-00 00:00:00' ";
	
	if (!$user->rights->societe->client->voir) //restriction
	{
		$sql.= " AND sc.fk_user = " .$user->id;
	}
	
	$sql.=" ";
	
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
	else{
		$integrale->date_facture = $integrale->get_date('date_facture','d/m/Y');
		$integrale->cout_unit_noir = $integrale->cout_unit_noir;
		$integrale->cout_unit_coul = $integrale->cout_unit_coul;
		$TIntegrale[$integrale->date_periode] = $integrale;
		$TIntegrale[$integrale->date_periode]->nb_ecart += 1;
		$TIntegrale[$integrale->date_periode]->TIds = array(0 => $integrale->getId());
	}
	
	return $TIntegrale;
	
}

function _fiche(&$PDOdb, &$doliDB, &$dossier) {

	$TBS = new TTemplateTBS;
	
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
				}
			}
			else{
				$TIntegrale[$date_periode]->date_facture .= $facture->ref_client."<br>";
				$TIntegrale[$date_periode]->facnumber .= $facture->getNomUrl()."<br>";
			}
			
		}
		
		//$TIntegrale[] = '';
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
}