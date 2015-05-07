<?php
	define('INC_FROM_CRON_SCRIPT',true);

	require('../config.php');
	require('../class/affaire.class.php');
	require('../class/dossier.class.php');
	require('../class/grille.class.php');
	dol_include_once("/fourn/class/fournisseur.facture.class.php");

	set_time_limit(0);

	$user=new User($db);
	$user->fetch('', DOL_ADMIN_USER);
	$user->getrights();
	print $user->lastname.'<br />';

	$ATMdb=new TPDOdb;

	//Récupération de tous les financement leaser INTERNE non soldé
	$sql="SELECT df.rowid 
		  FROM `".MAIN_DB_PREFIX."fin_dossier_financement` df
		  	LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (d.rowid = df.fk_fin_dossier)
		  WHERE d.nature_financement = 'INTERNE' 
		  	AND df.type = 'LEASER' 
		  	AND (df.date_solde = '0000-00-00 00:00:00' OR df.date_solde IS NULL)
		  	AND (df.reference IS NOT NULL AND df.reference != '')
		  	AND df.okPourFacturation != 'NON'
		  	AND df.date_prochaine_echeance BETWEEN '2015-01-01 00:00:00' AND '2099-01-01 00:00:00'";

	
	$ATMdb->Execute($sql);
	$Tab = $ATMdb->Get_all();
	
	//Pour chaque financement on va chercher à voir si pour toutes les échéances passé on a bien une facture
	foreach($Tab as $row) {
		
		$financemementLeaser=new TFin_financement;
		$financemementLeaser->load($ATMdb, $row->rowid);
		
		$dossier = new TFin_dossier;
		$dossier->load($ATMdb, $financemementLeaser->fk_fin_dossier,false);
		$dossier->load_factureFournisseur($ATMdb,true);
		
		$duree = $financemementLeaser->duree - $financemementLeaser->duree_restante;

		//Pour chaque échéance on regarde si on a une facture
		for($echeance=1;$echeance<=$duree;$echeance++){
				
			if(!array_key_exists($echeance-1, $dossier->TFactureFournisseur)){
				/*echo $echeance.' '.$dossier->rowid.'<br>';
				pre($dossier->TFactureFournisseur,true);exit;*/
				$date = strtotime($dossier->getDateDebutPeriode($echeance));
				if($date > strtotime('2015-01-01')){
					$TError[$dossier->rowid] = $dossier->financement->reference." / ".$dossier->financementLeaser->reference;
					$cpt ++;
				}
			}
		}
	}
	
	pre($TError,true);
	echo "total : ".$cpt;
