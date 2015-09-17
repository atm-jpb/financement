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


$ATMdb=new TPDOdb;
$tva = (FIN_TVA_DEFAUT-1)*100;

$sql = "SELECT rowid
		FROM ".MAIN_DB_PREFIX."facture_fourn
		WHERE datec > '2013-07-01'";
$Tab = TRequeteCore::_get_id_by_sql($ATMdb, $sql);

foreach($Tab as $rowid) {
	echo 'Facture '.$rowid.'<br/>';
	$facture = new FactureFournisseur($db);
	$facture->fetch($rowid);
	
	$facture->fetchObjectLinked();
	foreach($facture->linkedObjectsIds as $type => $data) {
		if($type == 'dossier') {
			$id_dossier = $data[0];
		}
	}

	if(!empty($id_dossier)) {
		$d = new TFin_dossier();
		$d->load($ATMdb, $id_dossier);
		
		$fk_product = 0;
		if(!empty($d->TLien[0]->affaire)) {
			if($d->TLien[0]->affaire->type_financement == 'ADOSSEE') $fk_product = FIN_PRODUCT_LOC_ADOSSEE;
			elseif($d->TLien[0]->affaire->type_financement == 'MANDATEE') $fk_product = FIN_PRODUCT_LOC_MANDATEE;
		}
		
		$facture->set_draft($user);
		if(!empty($fk_product)) {
			
			$f = &$d->financementLeaser;
			foreach($facture->lines as $line) {
				if($line->description == 'Echéance de loyer banque') {
					$facture->deleteline($line->rowid);
					$facture->addline("Echéance de loyer banque", $f->echeance, $tva, 0, 0, 1, $fk_product);
				}
			}
			echo 'PRODUCT OK<br>';
		} else {
			echo 'PRODUCT KO<br>';
		}
		$facture->label = "ECH DOS. ".$d->reference_contrat_interne." ".($f->duree_passe)."/".$f->duree;
		$facture->update();
		$facture->set_unpaid($user);
		$result=$facture->validate($user,'',0);
		echo 'FACTURE OK<hr>';
	} else {
		echo 'Dossier non trouvé<hr>';
	}
	/*pre($d);
	pre($facture);
	return;*/
}