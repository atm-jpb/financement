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
$Tab = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'fin_dossier_financement',array('okPourFacturation'=>'OUI', 'date_solde'=>'0000-00-00 00:00:00'));

foreach($Tab as $id) {
	
	$f=new TFin_financement;
	$f->load($ATMdb, $id);
	
	_createFacture($f);
	
	$f->okPourFacturation='NON';
	$f->setNextEcheance();
	
	$f->save($ATMdb);
}

if(isset($_REQUEST['with-auto-facture'])) {
/*
 * Création des factures non contrôlée par import Leaser le 1er de chaque mois
 */		

	$Tab = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'fin_dossier_financement',array('okPourFacturation'=>'AUTO', 'date_solde'=>'0000-00-00 00:00:00'));
	
	foreach($Tab as $id) {
		
		$f=new TFin_financement;
		$f->load($ATMdb, $id);
		
		_createFacture($f);
		
		$f->setNextEcheance();
		
		$f->save($ATMdb);
	}
}
	
function _createFacture(&$f) {
	global $user, $db, $conf;
	
	$tva = (FIN_TVA_DEFAUT-1)*100;
	//print $tva;
	$object =new FactureFournisseur($db);
	
	$object->ref           = $f->reference.'/'.($f->duree_passe+1); 
    $object->socid         = $f->fk_soc;
    $object->libelle       = "Facture échéance loyer";
    $object->date          = time();
    $object->date_echeance = time();
    $object->note_public   = '';
	$object->origin = 'dossier';
	$object->origin_id = $f->fk_fin_dossier;
	$id = $object->create($user);
	
	if($f->duree_passe==0) {
		/* Ajoute les frais de dossier uniquement sur la 1ère facture */
		print "Ajout des frais de dossier<br>";
		$result=$object->addline("Frais de dossier", $f->frais_dossier, $tva, 0, 0, 1);
	}
	
	/* Ajout la ligne de l'échéance	*/
	$result=$object->addline("Echéance de loyer", $f->echeance, $tva, 0, 0, 1);

	$result = $object->validate($user,'',0);
	
	$result=$object->set_paid($user);
	
	print "Création facture fournisseur ($id) : ".$object->ref."<br/>";
}
