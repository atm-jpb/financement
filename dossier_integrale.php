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
		,'hide'=>array('fk_soc','ID','ID affaire')
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
	
	
	$TIntegrale = array();
	foreach ($dossier->TFacture as $fac) {
		$integrale = new TIntegrale();
		$integrale->loadBy($PDOdb, $fac->ref, 'facnumber');
		
		$integrale->vol_noir_facture = ($integrale->vol_noir_engage > $integrale->vol_noir_realise) ? $integrale->vol_noir_engage : $integrale->vol_noir_realise;
		$integrale->vol_coul_facture = ($integrale->vol_coul_engage > $integrale->vol_coul_realise) ? $integrale->vol_coul_engage : $integrale->vol_coul_realise;
		
		$integrale->periode = substr($fin->periodicite,0,1);
		$integrale->periode.= ceil(date('n', $fac->date) / $fin->getiPeriode()) . ' ' . date('Y', $fac->date);
		
		$TIntegrale[] = $integrale;
	}
	
	
	
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