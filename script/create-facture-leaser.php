<?php
	
//define('INC_FROM_CRON_SCRIPT', true);

require('../config.php');

require('../class/affaire.class.php');
require('../class/dossier.class.php');
require('../class/grille.class.php');

include_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");

$langs->load("main");				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();


$ATMdb=new Tdb;

/*
 * Création des factures bon pour facturation
 */
$TabOui = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'fin_dossier_financement',array('okPourFacturation'=>'OUI', 'date_solde'=>'0000-00-00 00:00:00'));
$TabAuto = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'fin_dossier_financement',array('okPourFacturation'=>'AUTO', 'date_solde'=>'0000-00-00 00:00:00'));
$TabManuel = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'fin_dossier_financement',array('okPourFacturation'=>'MANUEL', 'date_solde'=>'0000-00-00 00:00:00'));
$Tab = array_merge($TabOui, $TabAuto, $TabManuel);

foreach($Tab as $id) {
	$f=new TFin_financement;
	$f->load($ATMdb, $id);
	
	$d=new TFin_dossier;
	$d->load($ATMdb, $f->fk_fin_dossier);
	
	echo 'Contrat client : '.$d->reference_contrat_interne.' - Contrat leaser : '.$f->reference.' ('.date('d/m/Y',$f->date_prochaine_echeance).' - '.$f->numero_prochaine_echeance.')<br />';
	
	$paid = $f->okPourFacturation == 'MANUEL' ? true : false;
	// Si le numéro de contrat leaser n'est pas rempli, on passe au dossier suivant
	if(empty($f->reference)) continue;
	
	echo $d->generate_factures_leaser($paid);
	
	if($f->okPourFacturation == 'OUI') $f->okPourFacturation='NON';
	$f->save($ATMdb);

	echo '<hr>';
	
	flush();
}