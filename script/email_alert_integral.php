<?php
/*
 * Script lancé en auto chaque soir à 23h
 * Récupère les informations des factures de dossier integral intégrés la veille ayant un écart de + de X % (conf)
 * Envoie un e-mail au vendeur concerné pour le prévenir de l'écart de facturation
 */

//ini_set('display_errors', true);
define('INC_FROM_CRON_SCRIPT',true);

require('../config.php');
require('../class/dossier_integrale.class.php');

set_time_limit(0);

$user=new User($db);
$user->fetch('', DOL_ADMIN_USER);
$user->getrights();

$ATMdb=new TPDOdb;

/*
 * Les alertes ne concernent pas les factures intégrées en automatique à 22h mais celle de la veille
 * Les alertes sont donc envoyées à J+1 par rapport à la facturation ce qui permet de ne rien envoyer si 
 * entretemps la facture est corrigée ou fait l'objet d'un avoir
 */
$time = strtotime('-1 day');
$date = date('Y-m-d', $time);

$sql = "SELECT fi.rowid ";
$sql.= "FROM ".MAIN_DB_PREFIX."fin_facture_integrale fi ";
$sql.= "WHERE fi.date_maj LIKE '".$date."%' ";
$sql.= "AND fi.ecart >= ".$conf->global->FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL." ";

//echo $sql;
$ATMdb->Execute($sql);
$Tab = $ATMdb->Get_all();

$TMailToSend = array();
foreach($Tab as $row) {
	$integral = new TIntegrale();
	$integral->load($ATMdb, $row->rowid, true);
	$TIdAvoir = $integral->facture->getListIdAvoirFromInvoice();
	if(!empty($TIdAvoir)) continue;
	
	$link = '<a href="'.dol_buildpath('/financement/dossier_integrale.php?id='.$integral->dossier->getId(),2).'">'.$integral->dossier->financement->reference.'</a>';
	
	// Données du suivi intégral qui seront envoyée ensuite au vendeur
	$data = array(
		'client' => $integral->client->name
		,'contrat' => $link
		,'ref_contrat' => $integral->dossier->financement->reference
		,'facture' => $integral->facnumber
		,'montant_engage' => $integral->total_ht_engage
		,'montant_facture' => $integral->total_ht_facture
		,'ecart' => $integral->ecart
		,'1-Copieur'=>''
		,'2-Traceur'=>''
		,'3-Solution'=>''
	);
	
	// Récupération des vendeurs associés au client
	$sql = "SELECT u.rowid as id_user, u.firstname, u.name, u.email, u.login, ";
	$sql.= "CASE sc.type_activite_cpro WHEN 'Copieur' THEN '1-Copieur' WHEN 'Traceur' THEN '2-Traceur' WHEN 'Solution' THEN '3-Solution' END activite ";
	$sql.= "FROM llx_facture f ";
	$sql.= "LEFT JOIN llx_societe s ON s.rowid = f.fk_soc " ;
	$sql.= "LEFT JOIN llx_societe_commerciaux sc ON sc.fk_soc = s.rowid ";
	$sql.= "LEFT JOIN llx_user u ON u.rowid = sc.fk_user ";
	$sql.= "WHERE f.facnumber = '".$integral->facnumber."' ";
	$sql.= "AND sc.type_activite_cpro IN ('Copieur','Traceur','Solution') ";
	$sql.= "ORDER BY activite, u.login";
	
	//echo $sql;
	$ATMdb->Execute($sql);
	$TRes = $ATMdb->Get_All();
	
	// On ne prend que le 1er vendeur comme destinataire
	if(!empty($TRes[0])) {
		$email = $TRes[0]->email;
		$name = $TRes[0]->firstname.' '.$TRes[0]->name;
		$id_user = $TRes[0]->id_user;
	} else {
		$email = 'financement@cpro.fr';
		$name = 'Cellule financement';
		$id_user = 999999;
	}
	
	// Permet de voir les vendeurs associés au client par activité avant de décider à qui envoyer
	foreach($TRes as $vendeur) {
		$data[$vendeur->activite][] = $vendeur->login;
	}
	if(!empty($data['1-Copieur'])) $data['1-Copieur'] = implode(', ', $data['1-Copieur']);
	if(!empty($data['2-Traceur'])) $data['2-Traceur'] = implode(', ', $data['2-Traceur']);
	if(!empty($data['3-Solution'])) $data['3-Solution'] = implode(', ', $data['3-Solution']);
	
	// Pour chaque vendeur on va envoyer un mail avec les données intégral compilées
	foreach($TRes as $vendeur) {
		$TMailToSend[$vendeur->id_user]['usermail'] = $vendeur->email;
		$TMailToSend[$vendeur->id_user]['username'] = $vendeur->firstname.' '.$vendeur->name;
		$TMailToSend[$vendeur->id_user]['content'][] = $data;
	}
}

//pre($TMailToSend, true);

$tpl = dol_buildpath('/financement/tpl/email_alert_integral.tpl.php');
$tbs = new TTemplateTBS();
foreach($TMailToSend as $dataMail) {
	$html = $tbs->render(
		$tpl
		,array(
			'content' => $dataMail['content']
		)
		,array(
			'dataMail' => $dataMail
			,'conf' => $conf
		)
	);
	
	$mailto = $dataMail['usermail'];
	// Mail to service financment pour le moment
	$mailto = 'financement@cpro.fr';
	$subjectMail = '[Lease Board] - Alertes facturation int&eacute;grale pour '.$data['username'];
	$contentMail = $html;
	
	$r=new TReponseMail($conf->notification->email_from, $mailto, $subjectMail, $contentMail);
	$r->send(true);
	
	echo $html;
	echo '<hr>';
}
