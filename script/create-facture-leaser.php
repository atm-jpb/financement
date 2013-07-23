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
	
	echo 'Contrat client : '.$d->reference_contrat_interne.' - Contrat leaser : '.$f->reference.'<br />';
	
	while($f->date_prochaine_echeance < time() && $f->numero_prochaine_echeance <= $f->duree) { // On ne créé la facture que si l'échéance est passée et qu'il en reste
		$paid = $f->okPourFacturation == 'MANUEL' ? true : false;
		_createFacture($f, $d, $paid);
		
		if($f->okPourFacturation == 'OUI') $f->okPourFacturation='NON';
		$f->setEcheance();
	}

	echo '<hr>';
	
	$f->save($ATMdb);
}

function _createFacture(&$f, &$d, $paid = false) {
	global $user, $db, $conf;
	
	$tva = (FIN_TVA_DEFAUT-1)*100;
	//print $tva;
	$object =new FactureFournisseur($db);
	
	$object->ref           = $f->reference.'/'.($f->duree_passe+1); 
    $object->socid         = $f->fk_soc;
    $object->libelle       = "ECH DOS. ".$d->reference_contrat_interne." ".($f->duree_passe+1)."/".$f->duree;
    $object->date          = $f->date_prochaine_echeance;
    $object->date_echeance = $f->date_prochaine_echeance;
    $object->note_public   = '';
	$object->origin = 'dossier';
	$object->origin_id = $f->fk_fin_dossier;
	$id = $object->create($user);
	
	if($id > 0) {
		if($f->duree_passe==0) {
			/* Ajoute les frais de dossier uniquement sur la 1ère facture */
			print "Ajout des frais de dossier<br />";
			$result=$object->addline("", $f->frais_dossier, $tva, 0, 0, 1, FIN_PRODUCT_FRAIS_DOSSIER);
		}
		
		/* Ajout la ligne de l'échéance	*/
		$fk_product = 0;
		if(!empty($d->TLien[0]->affaire)) {
			if($d->TLien[0]->affaire->type_financement == 'ADOSSEE') $fk_product = FIN_PRODUCT_LOC_ADOSSEE;
			elseif($d->TLien[0]->affaire->type_financement == 'MANDATEE') $fk_product = FIN_PRODUCT_LOC_MANDATEE;
		}
		$result=$object->addline("Echéance de loyer banque", $f->echeance, $tva, 0, 0, 1, $fk_product);
	
		$result=$object->validate($user,'',0);
		
		if($paid) {
			$result=$object->set_paid($user); // La facture reste en impayée pour le moment, elle passera à payée lors de l'export comptable
		}
		
		print "Création facture fournisseur ($id) : ".$object->ref."<br />";
	} else {
		print "Erreur création facture fournisseur : ".$object->ref."<br />";
	}
}
