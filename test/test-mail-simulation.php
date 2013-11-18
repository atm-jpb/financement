<?php

require('../config.php');
require('../class/simulation.class.php');
require('../class/grille.class.php');
require('../class/affaire.class.php');
require('../class/dossier.class.php');
require('../class/score.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

$langs->load('financement@financement');
$simulation=new TSimulation;


	$ATMdb=new TPDOdb;
	$simulation->load($ATMdb, 12); // chargement test
	
	$simulation->send_mail_vendeur(false, 'd.cottier@cpro.fr');
	
	$mesg = "Bonjour test \n\n";
	$mesg.= 'Vous trouverez ci-joint l\'accord de financement concernant votre simulation n° '.$simulation->reference.'.'."\n\n";
	$mesg.= 'Cordialement,'."\n\n";
	$mesg.= 'La cellule financement'."\n\n";
	
	dol_include_once('/core/class/html.formmail.class.php');
		dol_include_once('/core/lib/files.lib.php');
		dol_include_once('/core/class/CMailFile.class.php');
		
		$PDFName = dol_sanitizeFileName($simulation->getRef()).'.pdf';
		$PDFPath = $conf->financement->dir_output . '/' . dol_sanitizeFileName($simulation->getRef());
		
	$formmail = new FormMail($db);
	$formmail->clear_attached_files();
	$formmail->add_attached_files($PDFPath.'/'.$PDFName,$PDFName,dol_mimetype($PDFName));
	
	$attachedfiles=$formmail->get_attached_files();
	$filepath = $attachedfiles['paths'];
	$filename = $attachedfiles['names'];
	$mimetype = $attachedfiles['mimes'];
	
	
	$r=new TReponseMail('test@financement.com', 'd.cottier@cpro.fr', 'Ceci est un test pour module financement', $mesg);
	
	$r->add_piece_jointe($filename, $filepath);
