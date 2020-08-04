<?php
	
define('INC_FROM_CRON_SCRIPT', true);

require '../config.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/grille.class.php');

global $langs, $user;

$langs->load('main');				// To load language file for default language
@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
if(empty($user->id)) {
	$result = $user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
	if (! $result > 0) { dol_print_error('', $user->error); exit; }
	$user->getrights();
}

$PDOdb = new TPDOdb;
$fk_dossier = GETPOST('fk_dossier', 'int');

/*
 * Création des factures bon pour facturation
 */

$sql = 'SELECT rowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement';
$sql.= " WHERE (date_solde < '1970-01-01 00:00:00' OR date_solde IS NULL)";
$sql.= " AND okPourFacturation IN ('OUI', 'AUTO', 'MANUEL')";
$sql.= " AND reference IS NOT NULL AND reference <> ''";    // Si le numéro de contrat leaser n'est pas rempli, on passe au dossier suivant
$sql.= ' AND echeance <> 0.00';
if(! empty($fk_dossier)) $sql.= ' AND fk_fin_dossier = '.$fk_dossier;
$Tab = TRequeteCore::_get_id_by_sql($PDOdb, $sql);

foreach($Tab as $id) {
	$f = new TFin_financement;
	$f->load($PDOdb, $id);
	
	$d = new TFin_dossier;
	$d->load($PDOdb, $f->fk_fin_dossier);
	
	echo 'Contrat client : '.$d->reference_contrat_interne.' - Contrat leaser : '.$f->reference.' ('.date('d/m/Y', $f->date_prochaine_echeance).' - '.$f->numero_prochaine_echeance.')<br />';
	
	$paid = $f->okPourFacturation == 'MANUEL';
	
	if($d->nature_financement == 'INTERNE') $d->generate_factures_leaser($paid);
	
	if($f->okPourFacturation == 'OUI') $f->okPourFacturation = 'NON';
	$f->save($PDOdb);
	$d->save($PDOdb); // Sauvegarde le dossier et le financement leaser qui a été modifié par la génération de facture

	echo '<hr>';
	
	flush();
}
