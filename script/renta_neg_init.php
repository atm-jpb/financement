<?php
ini_set('display_errors', true);

require('../config.php');
dol_include_once("/financement/class/affaire.class.php");
dol_include_once('/financement/class/dossier.class.php');
dol_include_once("/financement/class/grille.class.php");

@set_time_limit(0);					// No timeout for this script

// Load user and its permissions
$result=$user->fetch('',DOL_ADMIN_USER);	// Load user for login 'admin'. Comment line to run as anonymous user.
if (! $result > 0) { dol_print_error('',$user->error); exit; }
$user->getrights();

$start = time();
$PDOdb=new TPDOdb();

$TLine = file(dol_buildpath('/financement/files/renta_neg_init.csv'));

$d = new TFin_dossier();
foreach($TLine as $line) {
	$data = explode(';', $line);
	$ref_dossier = str_pad($data[0], 8, '0', STR_PAD_LEFT);
	
	$d->loadReference($PDOdb, $ref_dossier,true);
	
	if($d->getId() > 0) {
		echo '<br>Dossier '.$ref_dossier.' trouvé : '.$d->getId();
		$d->visa_renta = 1;
		$d->save($PDOdb);
		
		$nbf = 0;
		foreach($d->TFacture as $TFact) {
			if(is_array($TFact)) {
				foreach ($TFact as $f) {
					if(!empty($f->ref_client)) {
						$TDate = explode('/', $f->ref_client);
						$time = mktime(0,0,0,$TDate[1],$TDate[0],$TDate[2]);
						if($time < strtotime('2016-04-01')) {
							$f->array_options['options_visa_renta_loyer_client'] = 1;
							$f->array_options['options_visa_renta_loyer_leaser'] = 1;
							$f->insertExtraFields();
							$nbf++;
						}
					}
				}
			} else {
				$f = $TFact;
				if(!empty($f->ref_client)) {
					$TDate = explode('/', $f->ref_client);
					$time = mktime(0,0,0,$TDate[1],$TDate[0],$TDate[2]);
					if($time < strtotime('2016-04-01')) {
						$f->array_options['options_visa_renta_loyer_client'] = 1;
						$f->array_options['options_visa_renta_loyer_leaser'] = 1;
						$f->insertExtraFields();
						$nbf++;
					}
				}
			}
		}
		echo ' - Factures : '.$nbf;
	} else {
		echo '<br>Dossier '.$ref_dossier.' non trouvé';
	}
}

echo '<hr>TOTAL : '.count($TLine) - 1;

$end = time();
echo '<hr>TIME : '.date('i:s', $end - $start);
