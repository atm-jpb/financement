<?php
require('config.php');
require('./class/simulation.class.php');
require('./class/grille.class.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/dossier_integrale.class.php');
require('./class/score.class.php');
require('./lib/financement.lib.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');
dol_include_once('/user/class/usergroup.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

$langs->load('financement@financement');
$simulation=new TSimulation(true);
$ATMdb = new TPDOdb;
$tbs = new TTemplateTBS;

$mesg = '';
$error=false;
$action = GETPOST('action');
if(!empty($_REQUEST['calculate'])) $action = 'calcul';
if(!empty($_REQUEST['cancel'])) { // Annulation
	if(!empty($_REQUEST['id'])) { header('Location: '.$_SERVER['PHP_SELF'].'?id='.$_REQUEST['id']); exit; } // Retour sur simulation si mode modif
	if(!empty($_REQUEST['fk_soc'])) { header('Location: ?socid='.$_REQUEST['fk_soc']); exit; } // Retour sur client sinon
	header('Location: '.$_SERVER['PHP_SELF']); exit;
}
if(!empty($_REQUEST['from']) && $_REQUEST['from']=='wonderbase') { // On arrive de Wonderbase, direction nouvelle simulation
	if(!empty($_REQUEST['code_artis'])) { // Client
		$TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'societe', array('code_client'=>$_REQUEST['code_artis'],'client'=>1));
		// Si le client a une simulation en cours de validité, on va sur la liste de ses simulations
		$hasValidSimu = _has_valid_simulations($ATMdb, $TId[0]);
		if ($hasValidSimu) {
		    header('Location: ?socid='.$TId[0]); exit;
		} else {
		    header('Location: ?action=new&fk_soc='.$TId[0]); exit;
		}
		
	} else if(!empty($_REQUEST['code_wb'])) { // Prospect
		$TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'societe', array('code_client'=>$_REQUEST['code_wb'],'client'=>2));
		if(!empty($TId[0])) { 
		    // Si le prospect a une simulation en cours de validité, on va sur la liste de ses simulations
		    $hasValidSimu = _has_valid_simulations($ATMdb, $TId[0]);
		    if ($hasValidSimu) {
		        header('Location: ?socid='.$TId[0]); exit;
		    } else { header('Location: ?action=new&fk_soc='.$TId[0]); exit; }
		}
		//pre($_REQUEST);
		// Création du prospect s'il n'existe pas
		$societe = new Societe($db);
		$societe->code_client = $_REQUEST['code_wb'];
		$societe->name = $_REQUEST['nom'];
		$societe->address = $_REQUEST['adresse'];
		$societe->zip = $_REQUEST['cp'];
		$societe->town = $_REQUEST['ville'];
		$societe->country_id = 1;
		//$societe->country = $_REQUEST['pays'];
		$societe->idprof1 = substr($_REQUEST['siren'],0,9);
		$societe->idprof2 = $_REQUEST['siren'];
		$societe->idprof3 = $_REQUEST['naf'];
		$societe->client = 2;
		if($societe->create($user) > 0) {
			header('Location: ?action=new&fk_soc='.$societe->id); exit;
		} else {
			$action = 'new';
			$error = 1;
			$mesg = $langs->trans('UnableToCreateProspect');
		}
	}
}

// le problème de ce comportement c'est que si le tiers n'a pas de simulation valid et qu'on veut juste voir la liste de c'est simulations, on ne peut pas...
if(!empty($_REQUEST['from']) && $_REQUEST['from']=='search' && !empty($_REQUEST['socid'])) {
    $fk_soc = (int)$_REQUEST['socid'];
    $hasValidSimu = _has_valid_simulations($ATMdb, $fk_soc);
    if(!$hasValidSimu){
        header('Location: ?action=new&fk_soc='.$fk_soc); exit;
    } else {
        header('Location: ?socid='.$fk_soc); exit;
    }
}

$fk_soc = $_REQUEST['fk_soc'];

if(!empty($_REQUEST['mode_search']) && $_REQUEST['mode_search'] == 'search_matricule' && !empty($_REQUEST['search_matricule'])) {
	// Recherche du client associé au matricule pour ensuite créer une nouvelle simulation
	$TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'asset', array('serial_number' => $_REQUEST['search_matricule']), 'fk_soc');
	
	if(empty($TId)) { // Matricule non trouvé
		setEventMessage('Matricule '.$_REQUEST['search_matricule'].' non trouvé', 'warnings');
		header(header('Location: '.dol_buildpath('index.php',1))); exit;
	}
	
	if(count($TId) > 1) { // Plusieurs matricules trouvés
		setEventMessage('Plusieurs matricules trouvés pour la recherche '.$_REQUEST['search_matricule'].'. Merci de chercher par client', 'warnings');
		header(header('Location: '.dol_buildpath('index.php',1))); exit;
	}
	
	if(!empty($TId[0])) {
		$fk_soc = $TId[0];
		$action = 'new';
	}
}

if(!empty($fk_soc)) {
	$simulation->fk_soc = $fk_soc;
	$simulation->load_annexe($ATMdb, $db);

	// Si l'utilisateur n'a pas le droit d'accès à tous les tiers
	if(!$user->rights->societe->client->voir) {
		// On vérifie s'il est associé au tiers dans Dolibarr
		dol_include_once("/financement/class/commerciaux.class.php");
		$c=new TCommercialCpro;
		if(!$c->loadUserClient($ATMdb, $user->id, $simulation->fk_soc) > 0) {
			// On vérifie si l'utilisateur est associé au tiers dans Wonderbase
			$url = FIN_WONDERBASE_USER_RIGHT_URL.'?numArtis='.$simulation->societe->code_client.'&trigramme='.$user->login;
			$droit = file_get_contents($url);
			//$TInfo = json_decode(utf8_decode($droit));

			// Association du user au tiers si droits ok
			if(strpos($droit, '1') !== false) {
				$c->fk_soc = $simulation->fk_soc;
				$c->fk_user = $user->id;
				$c->save($ATMdb);
			}
		}
	}
	
	// Vérification par Dolibarr des droits d'accès du user à l'information relative au tiers
	$simulation->societe->getCanvas($socid);
	$canvas = $simulation->societe->canvas?$object->canvas:GETPOST("canvas");
	$objcanvas='';
	if (! empty($canvas))
	{
	    require_once DOL_DOCUMENT_ROOT.'/core/class/canvas.class.php';
	    $objcanvas = new Canvas($db, $action);
	    $objcanvas->getCanvas('thirdparty', 'card', $canvas);
	}
	
	$user_courant_est_admin_financement = TFinancementTools::user_courant_est_admin_financement();

	if(empty($user_courant_est_admin_financement)) {

		// Security check
		$result = restrictedArea($user, 'societe', $simulation->societe->id, '&societe', '', 'fk_soc', 'rowid', $objcanvas);

	}

}

if(empty($action) || $action == 'list') {
	$TDossierLink = _getListIDDossierByNumAccord();
	$TStatutSuivi = getAllStatutSuivi(); // Défini ici pour optimiser l'affichage des simulations
}

if(!empty($action)) {
	switch($action) {
		case 'list':
			_liste($ATMdb, $simulation);
			break;
		case 'add':
		case 'new':
			
			$simulation->set_values($_REQUEST);
			_fiche($ATMdb, $simulation,'edit');
			
			break;
		case 'calcul':
		    
			if(!empty($_REQUEST['id'])) $simulation->load($ATMdb, $db, $_REQUEST['id']);
			
			if(empty($simulation->modifs['montant']) && (float)$_REQUEST['montant'] !== $simulation->montant) $simulation->modifs['montant'] = $simulation->montant;
			if(empty($simulation->modifs['echeance']) && (float)$_REQUEST['echeance'] !== $simulation->echeance) $simulation->modifs['echeance'] = $simulation->echeance;
			if(empty($simulation->modifs['montant_presta_trim']) && (float)$_REQUEST['montant_presta_trim'] !== $simulation->montant_presta_trim) $simulation->modifs['montant_presta_trim'] = $simulation->montant_presta_trim;
			if(empty($simulation->modifs['type_materiel']) && $_REQUEST['type_materiel'] !== $simulation->type_materiel) $simulation->modifs['type_materiel'] = $simulation->type_materiel;
			if(empty($simulation->modifs['opt_periodicite']) && $_REQUEST['opt_periodicite'] !== $simulation->opt_periodicite) $simulation->modifs['opt_periodicite'] = $simulation->opt_periodicite;
			if(empty($simulation->modifs['duree']) && (int)$_REQUEST['duree'] !== $simulation->duree) $simulation->modifs['duree'] = $simulation->duree;
			if(empty($simulation->modifs['fk_type_contrat']) && $_REQUEST['fk_type_contrat'] !== $simulation->fk_type_contrat) $simulation->modifs['fk_type_contrat'] = $simulation->fk_type_contrat;
			if(empty($simulation->modifs['opt_mode_reglement']) && $_REQUEST['opt_mode_reglement'] !== $simulation->opt_mode_reglement) $simulation->modifs['opt_mode_reglement'] = $simulation->opt_mode_reglement;
			if(empty($simulation->modifs['opt_terme']) && $_REQUEST['opt_terme'] !== $simulation->opt_terme) $simulation->modifs['opt_terme'] = $simulation->opt_terme;
			if(empty($simulation->modifs['coeff']) && (float)$_REQUEST['coeff'] !== $simulation->coeff) $simulation->modifs['coeff'] = $simulation->coeff;
			
			$oldAccord = $simulation->accord;

			$simulation->set_values($_REQUEST);
			
			// Si on ne modifie que le montant, les autres champs ne sont pas présent, il faut conserver ceux de la simu
			if($_REQUEST['mode'] != 'edit_montant') {
			    // On vérifie que les dossiers sélectionnés n'ont pas été décochés
			    //if(empty($_REQUEST['dossiers'])) $simulation->dossiers = array();
			    if(empty($_REQUEST['dossiers_rachetes_m1'])) $simulation->dossiers_rachetes_m1 = array();
			    if(empty($_REQUEST['dossiers_rachetes_nr_m1'])) $simulation->dossiers_rachetes_nr_m1 = array();
			    if(empty($_REQUEST['dossiers_rachetes'])) $simulation->dossiers_rachetes = array();
			    if(empty($_REQUEST['dossiers_rachetes_p1'])) $simulation->dossiers_rachetes_p1 = array();
			    if(empty($_REQUEST['dossiers_rachetes_nr'])) $simulation->dossiers_rachetes_nr = array();
			    if(empty($_REQUEST['dossiers_rachetes_nr_p1'])) $simulation->dossiers_rachetes_nr_p1 = array();
			    if(empty($_REQUEST['dossiers_rachetes_perso'])) $simulation->dossiers_rachetes_perso = array();
			    
			    $simulation->opt_adjonction = (int)isset($_REQUEST['opt_adjonction']);
			    $simulation->opt_administration = (int)isset($_REQUEST['opt_administration']);
			    $simulation->opt_no_case_to_settle = (int)isset($_REQUEST['opt_no_case_to_settle']);
			}
			$simulation->_calcul($ATMdb);

			_fiche($ATMdb, $simulation,'edit');
			break;	
		case 'edit'	:
		
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			
			_fiche($ATMdb, $simulation,'edit');
			
			break;
			
		case 'clone':
		
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			$simulation->clone_simu();
			$simulation->save($ATMdb, $db, false);
			
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$simulation->getId());
			exit();
			
			break;
		
		case 'save_suivi':
			
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			if(!empty($_REQUEST['TSuivi'])) {
				foreach($_REQUEST['TSuivi'] as $id_suivi => $TVal) {
					if(!empty($simulation->TSimulationSuivi[$id_suivi])) {
						$simulation->TSimulationSuivi[$id_suivi]->numero_accord_leaser = $TVal['num_accord'];
						$simulation->TSimulationSuivi[$id_suivi]->coeff_leaser = $TVal['coeff_accord'];
						$simulation->TSimulationSuivi[$id_suivi]->commentaire = $TVal['commentaire'];
						$simulation->TSimulationSuivi[$id_suivi]->save($ATMdb);
					}
				}
				
				setEventMessage($langs->trans('DataSaved'));
			}
			
			_fiche($ATMdb, $simulation,'view');
			break;
		
		case 'save':
			//pre($_REQUEST,true);
			if(!empty($_REQUEST['id'])) $simulation->load($ATMdb, $db, $_REQUEST['id']);
			
			$oldAccord = $simulation->accord;
			$oldsimu = clone $simulation;
			
			$fk_type_contrat_old = $simulation->fk_type_contrat;
			
			$simulation->set_values($_REQUEST);
			
			$fk_type_contrat_new = $simulation->fk_type_contrat;
			
			// Si on ne modifie que le montant, les autres champs ne sont pas présent, il faut conserver ceux de la simu
			if($_REQUEST['mode'] != 'edit_montant') {
				// On vérifie que les dossiers sélectionnés n'ont pas été décochés
				//if(empty($_REQUEST['dossiers'])) $simulation->dossiers = array();
				if(empty($_REQUEST['dossiers_rachetes_m1'])) $simulation->dossiers_rachetes_m1 = array();
				if(empty($_REQUEST['dossiers_rachetes_nr_m1'])) $simulation->dossiers_rachetes_nr_m1 = array();
				if(empty($_REQUEST['dossiers_rachetes'])) $simulation->dossiers_rachetes = array();
				if(empty($_REQUEST['dossiers_rachetes_p1'])) $simulation->dossiers_rachetes_p1 = array();
				if(empty($_REQUEST['dossiers_rachetes_nr'])) $simulation->dossiers_rachetes_nr = array();
				if(empty($_REQUEST['dossiers_rachetes_nr_p1'])) $simulation->dossiers_rachetes_nr_p1 = array();
				if(empty($_REQUEST['dossiers_rachetes_perso'])) $simulation->dossiers_rachetes_perso = array();
				
				$simulation->opt_adjonction = (int)isset($_REQUEST['opt_adjonction']);
				$simulation->opt_administration = (int)isset($_REQUEST['opt_administration']);
				$simulation->opt_no_case_to_settle = (int)isset($_REQUEST['opt_no_case_to_settle']);
			}
			
			if($_REQUEST['mode'] == 'edit_montant') {
				// Si la simulation avait un coeff final de renseigné, il s'agit d'une dérogation
			    // On doit calculer donc la différence entre le coeff de la simiulation et celui de l'ancienne sans tenir compte de la dérogation
			    // Puis appliquer la différence sur le coeff final
			    if(!empty($simulation->coeff_final)) {
			    	$oldsimu->coeff_final = 0;
					$oldsimu->_calcul($ATMdb, 'calcul', array(), true);
					$cpysimu = clone $simulation;
					$cpysimu->coeff_final = 0;
					$cpysimu->_calcul($ATMdb, 'calcul', array(), true);
					$diffcoeff = $cpysimu->coeff - $oldsimu->coeff;
					
					if(!empty($diffcoeff)) {
						$simulation->coeff_final += $diffcoeff;
					}
			    }
			}
			
			// Si l'accord vient d'être donné (par un admin)
			if($simulation->accord == 'OK' && $simulation->accord != $oldAccord) {
				$simulation->date_accord = time();
				$simulation->montant_accord = $simulation->montant_total_finance;
				$simulation->accord_confirme = 1;
				$simulation->montant_accord = $_REQUEST['montant'];
				$simulation->setThirparty();
			} 
			else if($simulation->accord == 'KO' && $simulation->accord != $oldAccord) {
				$simulation->accord_confirme = 1;
			}
			else if($simulation->accord == 'SS') {
				$simulation->accord_confirme = 1; // #478 un gros WTF? sur cette fonction
			}
			
			// Si une donnée de préconisation a été remplie, on fige la simulation pour le commercial
			if($simulation->fk_leaser > 0 || $simulation->type_financement != '') {
				$simulation->accord_confirme = 1;
			}
			
			//pre($_REQUEST,true);
			
			// On refait le calcul avant d'enregistrer
			$simulation->_calcul($ATMdb, 'save');
			//var_dump(count($simulation->TSimulationSuivi), $error);exit;
			if($error && $simulation->error !== 'ErrorMontantModifNotAuthorized') {
				_fiche($ATMdb, $simulation,'edit');
			} else {
				// Modification du type de contrat => save du suivi
				if (strcmp($fk_type_contrat_old, $fk_type_contrat_new) != 0)
				{
				    if (empty($simulation->TSimulationSuivi)) $simulation->load_suivi_simulation($ATMdb);
				    if (!empty($simulation->TSimulationSuivi))
					{
						$now = time();
						$nowFr = date('d/m/Y H:i');
						foreach ($simulation->TSimulationSuivi as &$simuSuivi)
						{
							if ($simuSuivi->statut_demande == 0)
							{
								$simuSuivi->delete($ATMdb);
							}
							else 
							{							    
								if (!empty($simuSuivi->commentaire)) $simuSuivi->commentaire .= "\n";
								$simuSuivi->commentaire .= "[$fk_type_contrat_old] suivi historisé le $nowFr";
								$simuSuivi->date_historization = $now;
								
								$simuSuivi->save($ATMdb);
								
							}
						}
					}
					
					// Changement de type de contrat, on vide les préconisations
					$simulation->montant_accord = 0;
					$simulation->type_financement = '';
					$simulation->fk_leaser = 0;
					$simulation->coeff_final = 0;
					$simulation->numero_accord = '';
				}
				
				// Si le leaser préconisé est renseigné, on enregistre le montant pour le figer (+- 10%)
				if(empty($simulation->montant_accord) && $simulation->fk_leaser > 0) {
					$simulation->montant_accord = $simulation->montant_total_finance;
				}
				
				if($_REQUEST['mode'] == 'edit_montant') { // si le commercial a fait une modif
				
					if($simulation->accord == 'OK' || $simulation->accord == 'WAIT_MODIF') { // On enregistre les modifs que si on était déjà en accord ou en modif
						if(empty($simulation->modifs['montant']) && $simulation->montant !== $oldsimu->montant) $simulation->modifs['montant'] = $oldsimu->montant;
						if(empty($simulation->modifs['echeance']) && $simulation->echeance !== $oldsimu->echeance) $simulation->modifs['echeance'] = $oldsimu->echeance;
						if(empty($simulation->modifs['montant_presta_trim']) && $simulation->montant_presta_trim !== $oldsimu->montant_presta_trim) $simulation->modifs['montant_presta_trim'] = $oldsimu->montant_presta_trim;
						if(empty($simulation->modifs['type_materiel']) && $simulation->type_materiel !== $oldsimu->type_materiel) $simulation->modifs['type_materiel'] = $oldsimu->type_materiel;
						if(empty($simulation->modifs['opt_periodicite']) && $simulation->opt_periodicite !== $oldsimu->opt_periodicite) $simulation->modifs['opt_periodicite'] = $oldsimu->opt_periodicite;
						if(empty($simulation->modifs['duree']) && $simulation->duree !== $oldsimu->duree) $simulation->modifs['duree'] = $oldsimu->duree;
						if(empty($simulation->modifs['fk_type_contrat']) && $simulation->fk_type_contrat !== $oldsimu->fk_type_contrat) $simulation->modifs['fk_type_contrat'] = $oldsimu->fk_type_contrat;
						if(empty($simulation->modifs['opt_mode_reglement']) && $simulation->opt_mode_reglement !== $oldsimu->opt_mode_reglement) $simulation->modifs['opt_mode_reglement'] = $oldsimu->opt_mode_reglement;
						if(empty($simulation->modifs['opt_terme']) && $simulation->opt_terme !== $oldsimu->opt_terme) $simulation->modifs['opt_terme'] = $oldsimu->opt_terme;
						if(empty($simulation->modifs['coeff']) && $simulation->coeff !== $oldsimu->coeff) $simulation->modifs['coeff'] = $oldsimu->coeff;
						if(empty($simulation->modifs['coeff_final']) && $simulation->coeff_final !== $oldsimu->coeff_final) $simulation->modifs['coeff_final'] = $oldsimu->coeff_final;
					}
				    
				    if ($oldAccord == 'OK'){
				    	// Si il y avait un accord avant et qu'on fait une modif, on vérifie les règles suivantes pour passer ou non le statut à "MODIF"
						
				    	// Vérification de la variation du montant
				    	$diffmontant = abs($simulation->montant - $simulation->montant_accord);
			            if (empty($simulation->montant_accord)) $simulation->montant_accord = 1;
						$montantOK = ($diffmontant / $simulation->montant_accord) * 100 <= $conf->global->FINANCEMENT_PERCENT_MODIF_SIMUL_AUTORISE;
						
						// Si le montant ne respecte pas la règle (+- 10 %) => MODIF
						if(!$montantOK) {
							$simulation->accord = 'WAIT_MODIF';
						}
						
						// Si MANDATEE ou ADOSSEE, on passe en modif uniquement si changement de durée / périodicité
						if ($simulation->type_financement == 'MANDATEE' || $simulation->type_financement == 'ADOSSEE') {
							if(!empty($simulation->modifs['duree']) || !empty($simulation->modifs['opt_periodicite'])) {
								$simulation->accord = 'WAIT_MODIF';
							}
						}
						// Sinon on passe en modif si autre chose que le montant a été modifié (montant, echeance, coeff)
						else {
							$keepAccord = array('montant', 'echeance', 'coeff', 'coeff_final');
							foreach ($simulation->modifs as $k =>$v){ // cherche les modifs qui font passer en accord modif
								if (!in_array($k, $keepAccord)) $simulation->accord = 'WAIT_MODIF';
							}
						}
					} elseif ($oldAccord == 'WAIT' || $oldAccord == 'WAIT_LEASER' || $oldAccord == 'WAIT_SELLER') {
						$simulation->accord = 'WAIT_MODIF';
						$simulation->coeff_final = 0;
					}
				} 
				
				/*if ($_REQUEST['mode'] == 'edit_montant'
				    && ($simulation->type_financement == 'MANDATEE' || $simulation->type_financement == 'ADOSSEE')
				    && $oldAccord == 'OK'
				    && $simulation->error == 'ErrorMontantModifNotAuthorized') // diff montant > 10%
				{
				    $simulation->accord = 'WAIT_MODIF';
				}*/
				
				if($simulation->accord == 'OK'){
				    $simulation->montant_accord = $simulation->montant;
				}
				
				if(empty($simulation->accord) || empty($simulation->rowid)) {
					$simulation->accord = 'WAIT';
				}
				
				/*if ($simulation->accord !== 'WAIT_MODIF'){
				    $simulation->modifs = array();
				} else {
				    $oldsimu->accord = 'WAIT_MODIF';
				    $simulation = $oldsimu;
				}*/
				
				//$ATMdb->db->debug=true;
				$simulation->save($ATMdb, $db);
				//echo $simulation->opt_calage; exit;
				// Si l'accord vient d'être donné (par un admin)
				if(($simulation->accord == 'OK' || $simulation->accord == 'KO') && $simulation->accord != $oldAccord) {
					$simulation->send_mail_vendeur();
				}
				
				if (empty($oldAccord) || ($oldAccord !== $simulation->accord)) {
				    $simulation->historise_accord($ATMdb);
				}
				
				$simulation->load_annexe($ATMdb, $db);
				
				_fiche($ATMdb, $simulation,'view');
				
				setEventMessage('Simulation enregistrée : '.$simulation->getRef(),'mesgs');
			}
			
			break;
		
		case 'changeAccord':
		    $newAccord = GETPOST('accord');
		    $simulation->load($ATMdb, $db, $_REQUEST['id']);
		    
		    if ($newAccord == 'OK') $simulation->montant_accord = $simulation->montant_total_finance;

		    $simulation->accord = $newAccord;
		    $simulation->save($ATMdb, $db);
		    $simulation->historise_accord($ATMdb);
		    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$simulation->id); exit;
		    break;
		    
		case 'send_accord':
			if(!empty($_REQUEST['id'])) {
				$simulation->load($ATMdb, $db, $_REQUEST['id']);
				if($simulation->accord == 'OK') {
					$simulation->send_mail_vendeur();
				}
			}
			
			_fiche($ATMdb, $simulation,'view');
			break;
		
		case 'delete':
			$simulation->load($ATMdb, $db, $_REQUEST['id']);
			//$ATMdb->db->debug=true;
			$simulation->delete_accord_history($ATMdb);
			$simulation->delete($ATMdb);
			
			?>
			<script language="javascript">
				document.location.href="?delete_ok=1";
			</script>
			<?php
			
			break;
		case 'trywebservice':
			$simulation->load($ATMdb, $db, GETPOST('id'));
			$id_suivi = GETPOST('id_suivi');
			$simulation->TSimulationSuivi[$id_suivi]->debug = true;
			$simulation->TSimulationSuivi[$id_suivi]->doAction($ATMdb, $simulation, 'demander');
			$simulation->TSimulationSuivi[$id_suivi]->_sendDemandeAuto($ATMdb);
			
			_fiche($ATMdb, $simulation, 'view');
			break;
		default:
		    
			//Actions spécifiques au suivi financement leaser
			$id_suivi = GETPOST('id_suivi');
			if($id_suivi){
				
				$simulation->load($ATMdb, $db, GETPOST('id'));
				foreach ($simulation->TSimulationSuivi as $k => $simulationSuivi) {
				    if ($simulationSuivi->rowid == $id_suivi){
				        $id_suivi = $k;
				        break;
				    }
				}
				$simulation->TSimulationSuivi[$id_suivi]->doAction($ATMdb,$simulation,$action);
					
				if(!empty($simulation->TSimulationSuivi[$id_suivi]->errorLabel)){
					setEventMessage($simulation->TSimulationSuivi[$id_suivi]->errorLabel,'errors');
				}
				
				if($action == 'demander'){
					//$simulation->accord = 'WAIT_LEASER';
					// Suite retours PR1512_1187, on ne garde plus que le statut WAIT (En étude)
					$simulation->accord = 'WAIT';
					
					// Si une demande est formulée auprès d'un leaser, on fige le montant (+- 10%)
					if(empty($simulation->montant_accord)) {
						$simulation->montant_accord = $simulation->montant_total_finance;
					}
					$simulation->save($ATMdb, $db);
				}
				
				_fiche($ATMdb, $simulation, 'view');
			}
			
			break;
	}
	
}
elseif(isset($_REQUEST['id'])) {
    $simulation->load($ATMdb, $db, $_REQUEST['id']);
	_fiche($ATMdb, $simulation, 'view');
}
else {
	 _liste($ATMdb, $simulation);
}

llxFooter();
	
function _liste(&$ATMdb, &$simulation) {
	global $langs, $db, $conf, $user;
	$searchnumetude = GETPOST('searchnumetude');
	
	$affaire = new TFin_affaire();
	
	llxHeader('','Simulations');
	
	$r = new TSSRenderControler($simulation);
	
	$THide = array('fk_soc', 'fk_user_author', 'rowid');
	
	//$sql = "SELECT DISTINCT s.rowid, s.reference, e.rowid as entity_id, s.fk_soc, soc.nom, s.fk_user_author, s.fk_type_contrat, s.montant_total_finance as 'Montant', s.echeance as 'Echéance',";
	$sql = "SELECT DISTINCT s.rowid, s.reference, e.rowid as entity_id, s.fk_soc, CONCAT(SUBSTR(soc.nom, 1, 25), '...') as nom, s.fk_user_author, s.fk_type_contrat, s.montant_total_finance, s.echeance,";
	$sql.= " CONCAT(s.duree, ' ', CASE WHEN s.opt_periodicite = 'MOIS' THEN 'M' WHEN s.opt_periodicite = 'ANNEE' THEN 'A' WHEN s.opt_periodicite = 'SEMESTRE' THEN 'S' ELSE 'T' END) as 'duree',";
	$sql.= " s.date_simul, s.date_validite, u.login, s.accord, s.type_financement, lea.nom as leaser, s.attente, '' as suivi, '' as loupe";
	$sql.= " FROM @table@ s ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (s.fk_user_author = u.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON (s.fk_soc = soc.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as lea ON (s.fk_leaser = lea.rowid) ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX.'entity as e ON (e.rowid = s.entity) ';
	
	if (!$user->rights->societe->client->voir || !$_REQUEST['socid']) {
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON (sc.fk_soc = soc.rowid)";
	}
	if(!empty($searchnumetude)){
		$sql.= "LEFT JOIN ".MAIN_DB_PREFIX."fin_simulation_suivi as fss ON (fk_simulation = s.rowid)";
	}
	//$sql.= " WHERE s.entity = ".$conf->entity;
	$sql.= " WHERE 1=1 ";
	if ((!$user->rights->societe->client->voir || !$_REQUEST['socid']) && !$user->rights->financement->allsimul->simul_list) //restriction
	{
		$sql.= " AND sc.fk_user = " .$user->id;
	}
	if(!empty($searchnumetude)){
		$sql.=" AND fss.numero_accord_leaser='".$searchnumetude."'";
	}
	

	if(isset($_REQUEST['socid'])) {
		$sql.= ' AND s.fk_soc='.$_REQUEST['socid'];
		$societe = new Societe($db);
		$societe->fetch($_REQUEST['socid']);
		
		// Affichage résumé client
		$formDoli = new Form($db);
		
		$TBS=new TTemplateTBS();
		
		// Infos sur SIREN
		$info = '';
		if(!empty($societe->idprof1)) {
			if ($societe->id_prof_check(1,$societe) > 0) $info = ' &nbsp; '.$societe->id_prof_url(1,$societe);
			else $info = ' <font class="error">('.$langs->trans("ErrorWrongValue").')</font>';
		}
	
		print $TBS->render('./tpl/client_entete.tpl.php'
			,array(
				
			)
			,array(
				'client'=>array(
					'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'simulation', $langs->trans("ThirdParty"),0,'company')
					,'showrefnav'=>$formDoli->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom')
					,'code_client'=>$societe->code_client
					,'idprof1'=>$societe->idprof1 . $info
					,'adresse'=>$societe->address
					,'cpville'=>$societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town
					,'pays'=>picto_from_langcode($societe->country_code).' '.$societe->country
				)
				,'view'=>array(
					'mode'=>'view'
				)
			)
		);
		
		$THide[] = 'Client';
	}
	
	$sql.= ' AND s.entity IN('.getEntity('fin_simulation', TFinancementTools::user_courant_est_admin_financement()).')';
	
	if(!$user->rights->financement->allsimul->suivi_leaser){
		$THide[] = 'suivi';
		$THide[] = 'attente';
	}
	
	$THide[] = 'type_financement';
	$THide[] = 'date_validite';
	
	$TOrder = array('date_simul'=>'DESC');
	if(isset($_REQUEST['orderDown']))$TOrder = array($_REQUEST['orderDown']=>'DESC');
	if(isset($_REQUEST['orderUp']))$TOrder = array($_REQUEST['orderUp']=>'ASC');
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formSimulation', 'GET');
	
	$TEntityName = TFinancementTools::build_array_entities();
	TFinancementTools::add_css();
	
	$tab = array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'30'
			,'global'=>'1000'
		)
		,'link'=>array(
			'reference'=>'<a href="?id=@rowid@">@val@</a>'
			,'nom'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_picto('','object_company.png', '', 0).' @val@</a>'
			,'login'=>'<a href="'.DOL_URL_ROOT.'/user/card.php?id=@fk_user_author@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'translate'=>array(
			'fk_type_contrat'=>$affaire->TContrat
			,'accord'=>$simulation->TStatutShort
		)
		,'hide'=>$THide
		,'type'=>array('date_simul'=>'date','montant_total_finance'=>'money','echeance'=>'money')
		,'liste'=>array(
			'titre'=>'Liste des simulations'
			,'image'=>img_picto('','simul32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=>"Il n'y a aucune simulation à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'orderBy'=>$TOrder
		,'title'=>array(
			'rowid'=>'N°'
			,'nom'=>'Client'
			,'reference'=>'Ref.'
			,'entity_id'=>'Partenaire'
			,'duree'=>'Durée'
			,'montant_total_finance'=>'Montant'
			,'echeance'=>'Échéance'
			,'login'=>'Utilisateur'
			,'fk_type_contrat'=> 'Type<br>de<br>contrat'
			,'date_simul'=>'Date<br>simulation'
			,'accord'=>'Statut'
			,'type_financement'=>'Type<br>financement'
			,'leaser'=>'Leaser'
		    ,'attente' => 'Délai'
			,'loupe'=>''
		)
		,'search'=>array(
			'nom'=>array('recherche'=>true, 'table'=>'soc')
			,'login'=>array('recherche'=>true, 'table'=>'u')
			,'entity_id'=>array( 'recherche'=>$TEntityName, 'table'=>'e', 'field'=>'rowid')
			,'fk_type_contrat'=>$affaire->TContrat
			//,'type_financement'=>$affaire->TTypeFinancementShort
			,'date_simul'=>'calendar'
			,'accord'=>$simulation->TStatutShort
			,'leaser'=>array('recherche'=>true, 'table'=>'lea', 'field'=>'nom')
			,'reference'=>array('recherche'=>true, 'table'=>'s', 'field'=>'reference')
		)
		,'eval'=>array(
			'entity_id' => 'TFinancementTools::get_entity_translation(@entity_id@)'
		    ,'attente' => 'print_attente(@val@)'
		    ,'loupe' => '_simu_edit_link(@rowid@, \'@date_validite@\')'
		)
		,'size'=>array(
			'width'=>array(
				'entity_id'=>'100px'
				,'login'=>'50px'
				,'type_financement'=>'100px'
				,'leaser'=>'270px'
			)
		)
		,'position'=>array(
			'text-align'=>array(
				'rowid'=>'center'
				,'nom'=>'center'
				,'reference'=>'center'
				,'entity_id'=>'center'
				,'duree'=>'center'
				,'montant_total_finance'=>'center'
				,'echeance'=>'center'
				,'login'=>'center'
				,'fk_type_contrat'=>'center'
				,'date_simul'=>'center'
				,'accord'=>'center'
				,'type_financement'=>'center'
				,'leaser'=>'center'
				,'suivi'=>'center'
				,'attente'=>'center'
			)
		)
	);
	
	if($user->rights->financement->allsimul->suivi_leaser) {
		$tab['title']['suivi'] = 'Statut<br>Leaser';
		$tab['eval']['suivi'] = 'getStatutSuivi(@rowid@);';
	}
	
	$r->liste($ATMdb, $sql, $tab);
	
	$form->end();
	
	if(isset($_REQUEST['socid'])) {
		?><div class="tabsAction"><a href="?action=new&fk_soc=<?php echo $_REQUEST['socid'] ?>" class="butAction">Nouvelle simulation</a></div><?php
	}
	
	llxFooter();
}

/*function getStatutSuivi($idSimulation){
	global $db;

	$ATMdb = new TPDOdb;

	$sql = "SELECT statut, date_selection 
			FROM ".MAIN_DB_PREFIX."fin_simulation_suivi
			WHERE fk_simulation = ".$idSimulation;
	$ATMdb->Execute($sql);

	$res = '';
	while($ATMdb->Get_line()){
		if($ATMdb->Get_field('statut') == 'OK' && $ATMdb->Get_field('date_selection') != '0000-00-00 00:00:00'){
			return $res =  '<img title="Accord" src="'.dol_buildpath('/financement/img/OK.png',1).'" />';
		}
		else if($ATMdb->Get_field('statut') == 'WAIT'){
			$res =  '<img title="En étude" src="'.dol_buildpath('/financement/img/WAIT.png',1).'" />';
		}
	} 
	
	return $res;

	$ATMdb->close();

	return $res;
}*/

function print_attente($compteur){
    global $conf;

    $style ='';
    $min = (int)($compteur / 60);
    if (!empty($conf->global->FINANCEMENT_FIRST_WAIT_ALARM) && $min >= (int)$conf->global->FINANCEMENT_FIRST_WAIT_ALARM) $style = 'color:orange';
    if (!empty($conf->global->FINANCEMENT_SECOND_WAIT_ALARM) && $min >= (int)$conf->global->FINANCEMENT_SECOND_WAIT_ALARM) $style = 'color:red';
    
    
    //var_dump($TTimes);
    $min = ($compteur / 60) % 60;
    $heures = abs(round((($compteur / 60)-$min)/60));
    
    $ret = '';
    $ret .= (!empty($heures) ? $heures : "0");
	$ret .= "h";
    $ret .= (($min < 10) ? "0" : "") . $min;
    
    if (!empty($style)) $ret = '<span style="'.$style.'">'.$ret.'</span>';
    
    return  $ret;
}

function getStatutSuivi($idSimulation) {
	
	global $TStatutSuivi;
	
	return $TStatutSuivi[$idSimulation];

}

function getAllStatutSuivi() {
	global $db, $TDossierLink, $langs;

	$ATMdb = new TPDOdb;

	$sql = "SELECT fk_simulation, statut, date_selection 
			FROM ".MAIN_DB_PREFIX."fin_simulation_suivi
			WHERE statut != ''
			AND date_historization < '1970-00-00 00:00:00'";
	$ATMdb->Execute($sql);
	
	$TStatutSuivi = array();
	$TStatutSuiviFinal = array();
	
	$res = '';
	while($ATMdb->Get_line()) $TStatutSuivi[$ATMdb->Get_field('fk_simulation')][] = array('statut'=>$ATMdb->Get_field('statut'), 'date_selection'=>$ATMdb->Get_field('date_selection'));
	
	$TAccords = array();
	$sql = "SELECT rowid, accord FROM " . MAIN_DB_PREFIX . "fin_simulation WHERE rowid in (" . implode(",", array_keys($TStatutSuivi)) . ")";
	$ATMdb->Execute($sql);
	
	while($ATMdb->Get_line()) $TAccords[$ATMdb->Get_field('rowid')] = $ATMdb->Get_field('accord');
	
	foreach ($TStatutSuivi as $fk_simulation => $TStatut) {
		
		$super_ok = false;
		$nb_ok = 0;
		$nb_wait = 0;
		$nb_refus = 0;
		$nb_err = 0;
		
		foreach($TStatut as $TData) {
			
			if(!empty($TDossierLink[$fk_simulation])) {
				$TStatutSuiviFinal[$fk_simulation] = '<a href="'.$TDossierLink[$fk_simulation].'#suivi_leaser">';
				$TStatutSuiviFinal[$fk_simulation].= '<FONT size="4">€</FONT>';
				$TStatutSuiviFinal[$fk_simulation].= '</a>';
				$super_ok = true;
				break;
			}
			elseif($TData['statut'] == 'OK' && $TData['date_selection'] > '1970-00-00 00:00:00'){
				$TStatutSuiviFinal[$fk_simulation] = '<a href="'.dol_buildpath('/financement/simulation.php?id='.$fk_simulation, 1).'#suivi_leaser">';
				$TStatutSuiviFinal[$fk_simulation].= '<img title="Accord" src="'.dol_buildpath('/financement/img/super_ok.png',1).'" />';
				$TStatutSuiviFinal[$fk_simulation].= '</a>';
				$super_ok = true;
				$nb_ok++;
				break;
			}
			elseif($TData['statut'] == 'OK') $nb_ok++;
			elseif($TData['statut'] == 'WAIT') $nb_wait++;
			elseif($TData['statut'] == 'KO') $nb_refus++;
			elseif($TData['statut'] == 'ERR') $nb_err++;
		
		}
		
		if(!$super_ok) {
			if($nb_ok > 0 || $nb_wait > 0 || $nb_refus > 0 || $nb_err > 0) {
				$TStatutSuiviFinal[$fk_simulation] = '<a href="'.dol_buildpath('/financement/simulation.php?id='.$fk_simulation, 1).'#suivi_leaser">';
				if ($TAccords[$fk_simulation] == 'WAIT_SELLER') $TStatutSuiviFinal[$fk_simulation].= '<img title="'.$langs->trans('Etude_Vendeur').'" src="'.dol_buildpath('/financement/img/WAIT_VENDEUR.png',1).'" />';
				elseif ($TAccords[$fk_simulation] == 'WAIT_LEASER') $TStatutSuiviFinal[$fk_simulation].= '<img title="'.$langs->trans('Etude_Leaser').'" src="'.dol_buildpath('/financement/img/WAIT_LEASER.png',1).'" />';
				elseif($nb_ok > 0) $TStatutSuiviFinal[$fk_simulation].= '<img title="'.$langs->trans('Etude').'" src="'.dol_buildpath('/financement/img/OK.png',1).'" />';
				elseif($nb_refus > 0) $TStatutSuiviFinal[$fk_simulation].= '<img title="'.$langs->trans('Refus').'" src="'.dol_buildpath('/financement/img/KO.png',1).'" />';
				elseif($nb_wait > 0) $TStatutSuiviFinal[$fk_simulation].= '<img title="'.$langs->trans('Etude').'" src="'.dol_buildpath('/financement/img/WAIT.png',1).'" />';
				elseif($nb_err > 0) $TStatutSuiviFinal[$fk_simulation].= '<img title="Erreur" src="'.dol_buildpath('/financement/img/ERR.png',1).'" />';
				else $TStatutSuiviFinal[$fk_simulation].= '<img title="'.$langs->trans('Etude').'" src="'.dol_buildpath('/financement/img/KO.png',1).'" />';
				$TStatutSuiviFinal[$fk_simulation].= '</a>';
			}
		}

		$TStatutSuiviFinal[$fk_simulation].= ' <span style="color: #00AA00;">' . $nb_ok . '</span>';
		$TStatutSuiviFinal[$fk_simulation].= ' <span style="color: #FF0000;">' . $nb_refus . '</span>';
		$TStatutSuiviFinal[$fk_simulation].= ' <span>' . ($nb_ok + $nb_refus + $nb_wait + $nb_err) . '</span>';
		
		//$TStatutSuiviFinal[$fk_simulation] = '<center>' . $TStatutSuiviFinal[$fk_simulation] . '</center>';

	}
	
	return $TStatutSuiviFinal;
}
	
function _fiche(&$ATMdb, &$simulation, $mode) {
	global $db, $langs, $user, $conf, $action;
	
	TFinancementTools::check_user_rights($simulation);
	
	// Si simulation déjà préco ou demande faite, le "montant_accord" est renseigné, le vendeur ne peux modifier que certains champs
	if($mode == 'edit') {
		if($simulation->modifiable === 0 && empty($user->rights->financement->admin->write)) {
			$mode = 'view';
		}
		if(!empty($simulation->montant_accord) && empty($user->rights->financement->admin->write)
			&& $simulation->modifiable == 2) {
			$mode = 'edit_montant';
		}
	}
	/*pre($_REQUEST,true);
	pre($simulation->dossiers,true);*/
	
	if( $simulation->getId() == 0) {
			
		$simulation->duree = __get('duree', $simulation->duree, 'integer');
	//	$simulation->echeance = __get('echeance', $simulation->echeance, 'float');
		
	}
	
	$extrajs = array('/financement/js/financement.js', '/financement/js/dossier.js');
	llxHeader('',$langs->trans("Simulation"),'','','','',$extrajs);

	$affaire = new TFin_affaire;
	$financement = new TFin_financement;
	$grille = new TFin_grille_leaser();
	$html=new Form($db);
	$form=new TFormCore($_SERVER['PHP_SELF'].'#calculateur','formSimulation','POST'); //,FALSE,'onsubmit="return soumettreUneSeuleFois(this);"'
	$form->Set_typeaff($mode);
	//$form->Set_typeaff('edit');

	echo $form->hidden('id', $simulation->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $simulation->fk_soc);
	echo $form->hidden('fk_user_author', !empty($simulation->fk_user_author) ? $simulation->fk_user_author : $user->id);
	echo $form->hidden('entity', $conf->entity);
	echo $form->hidden('idLeaser', FIN_LEASER_DEFAULT);
	echo $form->hidden('mode', $mode);

	$TBS=new TTemplateTBS();
	$ATMdb=new TPDOdb;
	
	dol_include_once('/core/class/html.formfile.class.php');
	$formfile = new FormFile($db);
	$filename = dol_sanitizeFileName($simulation->getRef());
	$filedir = $simulation->getFilePath();
	
	$TDuree = (Array)$grille->get_duree($ATMdb,FIN_LEASER_DEFAULT,$simulation->fk_type_contrat,$simulation->opt_periodicite,$simulation->entity);
	//var_dump($TDuree);
	$can_preco = ($user->rights->financement->allsimul->simul_preco && $simulation->fk_soc > 0) ? 1 : 0;
	
	// 2017.01.04 MKO : simulation modifiable par un admin ou si pas de préco ou demande sur un leaser catégorie Cession
	$can_modify = 0;
	if(!empty($user->rights->financement->admin->write)) $can_modify = 1;
	if($simulation->modifiable > 0) $can_modify = 1;
	
	// Chargement des groupes configurés dans multi entité
	$TGroupEntity = unserialize($conf->global->MULTICOMPANY_USER_GROUP_ENTITY);
	$TGroupEntities = array();
	
	if(!empty($TGroupEntity)) {
		foreach($TGroupEntity as $tab) {
			$g = new UserGroup($db);
			if(!in_array($tab['group_id'], array_keys($TGroupEntities))) {
				$g->fetch($tab['group_id']);
				if($g->id > 0) $TGroupEntities[$tab['group_id']] = "'".$g->name."'";
			}
		}
		
	}
	
	//var_dump($TGroupEntity, $TGroupEntities);
	if($user->rights->financement->admin->write && ($mode == "add" || $mode == "new" || $mode == "edit")){
		$formdolibarr = new Form($db);
		$rachat_autres = "texte";
		$TUserInclude = array();
		
		$sql ="SELECT u.rowid 
				FROM ".MAIN_DB_PREFIX."user as u 
					LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user as ugu ON (ugu.fk_user = u.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."usergroup as ug ON (ug.rowid = ugu.fk_usergroup)
				WHERE ug.nom IN (".implode(',', $TGroupEntities).") ";
		
		$TUserInclude = TRequeteCore::_get_id_by_sql($ATMdb, $sql);

		//pre($TUserExculde,true); exit;
		$link_user = $formdolibarr->select_dolusers($simulation->fk_user_author,'fk_user_author',1,'',0,$TUserInclude,'',getEntity('fin_simulation', 1));
		
		$TUserInclude = TRequeteCore::_get_id_by_sql($ATMdb, "SELECT u.rowid 
															FROM ".MAIN_DB_PREFIX."user as u 
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user as ugu ON (ugu.fk_user = u.rowid)
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup as ug ON (ug.rowid = ugu.fk_usergroup)
															WHERE ug.nom = 'GSL_DOLIBARR_FINANCEMENT_ADMIN'");
		
		$link_user_suivi = $formdolibarr->select_dolusers($simulation->fk_user_suivi,'fk_user_suivi',1,'',0,$TUserInclude,'',$conf->entity);
	}
	else{
		$rachat_autres = "texteRO";
		$link_user = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$simulation->fk_user_author.'">'.img_picto('','object_user.png', '', 0).' '.$simulation->user->login.'</a>';
		$link_user_suivi = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$simulation->fk_user_suivi.'">'.img_picto('','object_user.png', '', 0).' '.$simulation->user_suivi->login.'</a>';
	}
	
	$e = new DaoMulticompany($db);
	$e->getEntities();
	$TEntities = array();
	foreach($e->entities as $obj_entity) $TEntities[$obj_entity->id] = $obj_entity->label;
	
	$entity = empty($simulation->entity) ? getEntity('fin_dossier') : $simulation->entity;
	
	if(TFinancementTools::user_courant_est_admin_financement() && empty($conf->global->FINANCEMENT_DISABLE_SELECT_ENTITY)){
		$entity_field = $form->combo('', 'entity', TFinancementTools::build_array_entities(), $entity);
	} else {
		$entity_field = TFinancementTools::get_entity_translation($entity).$form->hidden('entity', $entity);
	}
	
	$id_dossier = _getIDDossierByNumAccord($simulation->numero_accord);
	if(empty($id_dossier)) $link_dossier = $simulation->numero_accord;
	else $link_dossier = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$id_dossier, 2).'" >'.$simulation->numero_accord.'</a>';
	
	$TOptCalageLabel = array('' => '', '1M'=>'1 mois', '2M'=>'2 mois', '3M'=>'3 mois');
	
	/**
	 * Calcul à la volé pour connaitre le coef en fonction de la périodicité
	 */
	$tempCoeff = $simulation->coeff;
	
	if ($simulation->opt_periodicite == 'MOIS') $coeff = $tempCoeff / 3;
	elseif ($simulation->opt_periodicite == 'SEMESTRE') $coeff = $tempCoeff * 2;
	elseif ($simulation->opt_periodicite == 'ANNEE') $coeff = $tempCoeff * 4;
	else $coeff = $tempCoeff; // TRIMESTRE
	
	if($simulation->montant_decompte_copies_sup < 0) $simulation->montant_decompte_copies_sup = 0;
	
	$accordIcon = (!empty($simulation->accord)) ? img_picto('accord', $simulation->TStatutIcons[$simulation->accord], '', 1) : '';
	
	$simuArray = array(
		'titre_simul'=>load_fiche_titre($langs->trans("CustomerInfo"),'','object_company.png')
		,'titre_calcul'=>load_fiche_titre($langs->trans("Simulator"),'','object_simul.png@financement')
		,'titre_dossier'=>load_fiche_titre($langs->trans("DossierList"),'','object_financementico.png@financement')
		
		,'id'=>$simulation->rowid
		,'entity'=>$entity_field
		,'entity_partenaire'=>$simulation->entity
		,'ref'=>$simulation->reference
		,'doc'=>($simulation->getId() > 0) ? $formfile->getDocumentsLink('financement', $filename, $filedir, 1) : ''
		,'fk_soc'=>$simulation->fk_soc

	    ,'fk_type_contrat'=>$form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat).(!empty($simulation->modifs['fk_type_contrat']) ? ' (Ancienne valeur : '.$affaire->TContrat[$simulation->modifs['fk_type_contrat']].')' : '')
		,'opt_administration'=>$form->checkbox1('', 'opt_administration', 1, $simulation->opt_administration) 
		,'opt_adjonction'=>$form->checkbox1('', 'opt_adjonction', 1, $simulation->opt_adjonction) 
	    ,'opt_periodicite'=>$form->combo('', 'opt_periodicite', $financement->TPeriodicite, $simulation->opt_periodicite) .(!empty($simulation->modifs['opt_periodicite']) ? ' (Ancienne valeur : '.$financement->TPeriodicite[$simulation->modifs['opt_periodicite']].')' : '')
		//,'opt_creditbail'=>$form->checkbox1('', 'opt_creditbail', 1, $simulation->opt_creditbail)
	    ,'opt_mode_reglement'=>$form->combo('', 'opt_mode_reglement', $financement->TReglement, $simulation->opt_mode_reglement) .(!empty($simulation->modifs['opt_mode_reglement']) ? ' (Ancienne valeur : '.$financement->TReglement[$simulation->modifs['opt_mode_reglement']].')' : '')
		,'opt_calage_label'=>$form->combo('', 'opt_calage_label', $TOptCalageLabel, $simulation->opt_calage, 0, '', TFinancementTools::user_courant_est_admin_financement() ? '' : 'disabled')
		,'opt_calage'=>$form->hidden('opt_calage', $simulation->opt_calage)
	    ,'opt_terme'=>$form->combo('', 'opt_terme', $financement->TTerme, $simulation->opt_terme) .(!empty($simulation->modifs['opt_terme']) ? ' (Ancienne valeur : '.$financement->TTerme[$simulation->modifs['opt_terme']].')' : '')
		,'date_demarrage'=>$form->calendrier('', 'date_demarrage', $simulation->get_date('date_demarrage'), 12)
	    ,'montant'=>$form->texte('', 'montant', $simulation->montant, 10) .(!empty($simulation->modifs['montant']) ? ' (Ancienne valeur : '.$simulation->modifs['montant'].')' : '')

		,'montant_rachete'=>$form->texteRO('', 'montant_rachete', $simulation->montant_rachete, 10)
		,'montant_decompte_copies_sup'=>$form->texteRO('', 'montant_decompte_copies_sup', $simulation->montant_decompte_copies_sup, 10)
		,'montant_rachat_final'=>$form->texteRO('', 'montant_rachat_final', $simulation->montant_rachat_final, 10)
		,'montant_rachete_concurrence'=>$form->texte('', 'montant_rachete_concurrence', $simulation->montant_rachete_concurrence, 10)
	    ,'duree'=>$form->combo('', 'duree', $TDuree, $simulation->duree) .(!empty($simulation->modifs['duree']) ? ' (Ancienne valeur : '.$TDuree[$simulation->modifs['duree']].')' : '')
	    ,'echeance'=>$form->texte('', 'echeance', $simulation->echeance, 10) .(!empty($simulation->modifs['echeance']) ? ' (Ancienne valeur : '.$simulation->modifs['echeance'].')' : '')
		,'vr'=>price($simulation->vr)
	    ,'coeff'=>$form->texteRO('', 'coeff', $coeff, 6) .(!empty($simulation->modifs['coeff']) ? ' (Ancienne valeur : '.$simulation->modifs['coeff'].')' : '')
		,'coeff_final'=>($can_preco ? $form->texte('', 'coeff_final', $simulation->coeff_final, 6) : $simulation->coeff_final) .(!empty($simulation->modifs['coeff_final']) ? ' (Ancienne valeur : '.$simulation->modifs['coeff_final'].')' : '')
	    ,'montant_presta_trim'=>$form->texte('', 'montant_presta_trim', $simulation->montant_presta_trim, 10) .(!empty($simulation->modifs['montant_presta_trim']) ? ' (Ancienne valeur : '.$simulation->modifs['montant_presta_trim'].')' : '')
		,'cout_financement'=>$simulation->cout_financement
	    ,'accord'=> $accordIcon . '<br />' . ($user->rights->financement->allsimul->simul_preco ? $form->combo('', 'accord', $simulation->TStatut, $simulation->accord) : $simulation->TStatut[$simulation->accord]) . '<br>'
		,'can_resend_accord'=>$simulation->accord
		,'date_validite'=>$simulation->accord == 'OK' ? 'Validité : '.$simulation->get_date('date_validite') : ''
		,'commentaire'=>$form->zonetexte('', 'commentaire', $mode == 'edit' ? $simulation->commentaire : nl2br($simulation->commentaire), 50,3)
		,'accord_confirme'=>$simulation->accord_confirme
		,'total_financement'=>$simulation->montant_total_finance
	    ,'type_materiel'=>$form->texte('','type_materiel', $simulation->type_materiel, 50) .(!empty($simulation->modifs['type_materiel']) ? ' (Ancienne valeur : '.$simulation->modifs['type_materiel'].')' : '')
		,'marque_materiel'=>(!in_array($simulation->marque_materiel, $simulation->TMarqueMateriel) && !empty($simulation->marque_materiel) ? $langs->trans('Simulation_marque_not_more_available', $simulation->marque_materiel).' - ' : '') . $form->combo('','marque_materiel',$simulation->TMarqueMateriel,$simulation->marque_materiel)
		,'numero_accord'=>($can_preco && GETPOST('action') == 'edit') ? $form->texte('','numero_accord',$simulation->numero_accord, 20) : $link_dossier
		,'attente' => $simulation->get_attente($ATMdb, ($action=='calcul' ? 1 : 0))
	    ,'attente_style' => (empty($simulation->attente_style)) ? 'none' : $simulation->attente_style
		,'no_case_to_settle'=>$form->checkbox1('', 'opt_no_case_to_settle', 1, $simulation->opt_no_case_to_settle) 
		
		,'accord_val'=>$simulation->accord
		,'can_preco'=>$can_preco
		,'can_modify'=>$can_modify
		
		,'user'=>$link_user
		,'user_suivi'=>$link_user_suivi
		,'date'=>$simulation->date_simul
		,'bt_calcul'=>$form->btsubmit('Calculer', 'calculate')
		,'bt_cancel'=>$form->btsubmit('Annuler', 'cancel')
		,'bt_save'=>$form->btsubmit('Enregistrer simulation', 'validate_simul') //'onclick="$(this).remove(); $("#formSimulation").submit();"'
		
		,'display_preco'=>$can_preco
		,'type_financement'=>$can_preco ? $form->combo('', 'type_financement', array_merge(array(''=> ''), $affaire->TTypeFinancement), $simulation->type_financement) : $simulation->type_financement
		,'leaser'=>($mode=='edit' && $can_preco) ? $html->select_company($simulation->fk_leaser,'fk_leaser','fournisseur=1',1,0,1) : (($simulation->fk_leaser > 0) ? $simulation->leaser->getNomUrl(1) : '')
		
		//,'pct_vr'=>$form->texte('', 'pct_vr', vatrate($simulation->pct_vr), 10)
		,'pct_vr'=>($mode == 'edit') ? '<input name="pct_vr" type="number" value="'.$simulation->pct_vr.'" min="0" max="100" '.(TFinancementTools::user_courant_est_admin_financement() ? '' : 'readonly').' />' : $simulation->pct_vr
		,'mt_vr'=>$form->texte('', 'mt_vr', price2num($simulation->mt_vr), 10)
		,'info_vr'=>$html->textwithpicto('', $langs->transnoentities('simulation_info_vr'), 1, 'info', '', 0, 3)
		,'fk_categorie_bien'=>$mode == 'edit' ? $html->selectarray('fk_categorie_bien', TFinancementTools::getCategorieId(), $simulation->fk_categorie_bien) : TFinancementTools::getCategorieLabel($simulation->fk_categorie_bien)
		,'fk_nature_bien'=>$mode == 'edit' ? $html->selectarray('fk_nature_bien', TFinancementTools::getNatureId(), $simulation->fk_nature_bien) : TFinancementTools::getNatureLabel($simulation->fk_nature_bien)
	);
	
	if($mode == 'edit_montant') {
		$mode = 'edit';
		$form->Set_typeaff($mode);
		$simuArray['montant'] = $form->texte('', 'montant', $simulation->montant, 10).(!empty($simulation->modifs['montant']) ? ' (Ancienne valeur : '.$simulation->modifs['montant'].')' : '');
		$simuArray['echeance'] = $form->texte('', 'echeance', $simulation->echeance, 10).(!empty($simulation->modifs['echeance']) ? ' (Ancienne valeur : '.$simulation->modifs['echeance'].')' : '');
		$simuArray['montant_presta_trim'] = $form->texte('', 'montant_presta_trim', $simulation->montant_presta_trim, 10).(!empty($simulation->modifs['montant_presta_trim']) ? ' (Ancienne valeur : '.$simulation->modifs['montant_presta_trim'].')' : '');
		$simuArray['type_materiel'] = $form->texte('','type_materiel', $simulation->type_materiel, 50).(!empty($simulation->modifs['type_materiel']) ? ' (Ancienne valeur : '.$simulation->modifs['type_materiel'].')' : '');
		$simuArray['opt_periodicite'] = $form->combo('', 'opt_periodicite', $financement->TPeriodicite, $simulation->opt_periodicite).(!empty($simulation->modifs['opt_periodicite']) ? ' (Ancienne valeur : '.$financement->TPeriodicite[$simulation->modifs['opt_periodicite']].')' : '');
		$simuArray['duree'] = $form->combo('', 'duree', $TDuree, $simulation->duree).(!empty($simulation->modifs['duree']) ? ' (Ancienne valeur : '.$TDuree[$simulation->modifs['duree']].')' : '');
		$simuArray['fk_type_contrat'] = $form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat).(!empty($simulation->modifs['fk_type_contrat']) ? ' (Ancienne valeur : '.$affaire->TContrat[$simulation->modifs['fk_type_contrat']].')' : '');
		$simuArray['opt_mode_reglement'] = $form->combo('', 'opt_mode_reglement', $financement->TReglement, $simulation->opt_mode_reglement).(!empty($simulation->modifs['opt_mode_reglement']) ? ' (Ancienne valeur : '.$financement->TReglement[$simulation->modifs['opt_mode_reglement']].')' : '');
		$simuArray['opt_terme'] = $form->combo('', 'opt_terme', $financement->TTerme, $simulation->opt_terme).(!empty($simulation->modifs['opt_terme']) ? ' (Ancienne valeur : '.$financement->TTerme[$simulation->modifs['opt_terme']].')' : '');
		$simuArray['coeff'] = $form->texteRO('', 'coeff', $coeff, 6).(!empty($simulation->modifs['coeff']) ? ' (Ancienne valeur : '.$simulation->modifs['coeff'].')' : '');
	}
	
	if(TFinancementTools::user_courant_est_admin_financement()) {
	    $simuArray['accord'] .= '<br />';
	    foreach ($simulation->TStatutIcons as $k => $icon) {
	        if ($k !== $simulation->accord) $simuArray['accord'] .= '<a href="'.$_SERVER['PHP_SELF'].'?id='.$simulation->id.'&action=changeAccord&accord='.$k.'">'.img_picto('Changer vers ' . $simulation->TStatut[$k], $icon, '', 1) . '</a>&nbsp;&nbsp;';
	    }
	}
	// Recherche par SIREN
	$search_by_siren = true;
	if(!empty($simulation->societe->array_options['options_no_regroup_fin_siren'])) {
		$search_by_siren = false;
	}
	
	print $TBS->render('./tpl/simulation.tpl.php'
		,array(
			
		)
		,array(
			'simulation'=>$simuArray
			,'client'=>array(
				'societe'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$simulation->fk_soc.'">'.img_picto('','object_company.png', '', 0).' '.(!empty($simulation->thirdparty_name) ? $simulation->thirdparty_name : $simulation->societe->nom).'</a>'
				,'autres_simul'=>'<a href="'.DOL_URL_ROOT.'/custom/financement/simulation.php?socid='.$simulation->fk_soc.'">(autres simulations)</a>'
				,'adresse'=>($simulation->accord == 'OK' && !empty($simulation->thirdparty_address)) ? $simulation->thirdparty_address : $simulation->societe->address
				,'cpville'=>( ($simulation->accord == 'OK' && !empty($simulation->thirdparty_zip)) ? $simulation->thirdparty_zip : $simulation->societe->zip ) .' / '. ( ($simulation->accord == 'OK' && !empty($simulation->thirdparty_town)) ? $simulation->thirdparty_town : $simulation->societe->town )
				,'siret'=>($simulation->accord == 'OK' && !empty($simulation->thirdparty_idprof2_siret)) ? $simulation->thirdparty_idprof2_siret : $simulation->societe->idprof2
				,'naf'=>($simulation->accord == 'OK' && !empty($simulation->thirdparty_idprof3_naf)) ? $simulation->thirdparty_idprof3_naf : $simulation->societe->idprof3
				,'code_client'=>($simulation->accord == 'OK' && !empty($simulation->thirdparty_code_client)) ? $simulation->thirdparty_code_client : $simulation->societe->code_client
				,'display_score'=>$user->rights->financement->score->read ? 1 : 0
				,'score_date'=>empty($simulation->societe) ? '' : $simulation->societe->score->get_date('date_score')
				,'score'=>empty($simulation->societe) ? '' : $simulation->societe->score->score
				,'encours_cpro'=>empty($simulation->societe) ? 0 : $simulation->societe->encours_cpro
				,'encours_conseille'=>empty($simulation->societe) ? '' : $simulation->societe->score->encours_conseille
				
				,'contact_externe'=>empty($simulation->societe) ? '' : $simulation->societe->score->get_nom_externe()
				
				,'liste_dossier'=>_liste_dossier($ATMdb, $simulation, $mode, $search_by_siren)
				
				,'nom'=>$simulation->societe->nom
				,'siren'=>(($simulation->societe->idprof1) ? $simulation->societe->idprof1 : $simulation->societe->idprof2)
				
			)
			,'view'=>array(
				'mode'=>$mode
				,'type'=>($simulation->fk_soc > 0) ? 'simul' : 'calcul'
				,'calcul'=>empty($simulation->montant_total_finance) ? 0 : 1
				,'pictoMail'=>img_picto('','stcomm0.png', '', 0)
			)
			
			,'user'=>$user
			
		),
		array(),
		array('charset'=>'utf-8')
	);
	
	echo $form->end_form();
	// End of page
	
	if($user->rights->financement->allsimul->suivi_leaser){
		_fiche_suivi($ATMdb, $simulation, $mode);
	}
	
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}

function _getIDDossierByNumAccord($num_accord) {
	
	global $db;
	
	$num_accord = trim($num_accord);
	if(empty($num_accord)) return 0;
	
	$sql = 'SELECT fk_fin_dossier
			FROM '.MAIN_DB_PREFIX.'fin_dossier_financement
			WHERE type = "LEASER"
			AND reference = "'.$num_accord.'"';
	
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	
	return $res->fk_fin_dossier;
	
}

/**
 * Retourne un tableau avec en clef l'id de la simu et en valeur le lien vers le dossier associé
 */
function _getListIDDossierByNumAccord() {
	
	global $db;
	
	$TDossierLink = array();
	
	$sql = 'SELECT s.rowid, d.fk_fin_dossier
			FROM '.MAIN_DB_PREFIX.'fin_dossier_financement d
			INNER JOIN '.MAIN_DB_PREFIX.'fin_simulation s ON(d.reference = s.numero_accord)
			WHERE d.reference <> ""
			AND d.reference IS NOT NULL';
	//echo $sql;exit;
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	
	while($res = $db->fetch_object($resql)) $TDossierLink[$res->rowid] = dol_buildpath('/financement/dossier.php?id='.$res->fk_fin_dossier, 2);
	
	return $TDossierLink;
	
}
	


function _fiche_suivi(&$ATMdb, &$simulation, $mode){
	global $conf, $db, $langs;
	
	$form=new TFormCore($_SERVER['PHP_SELF'].'#suivi_leaser','form_suivi_simulation','POST');
	$form->Set_typeaff('edit');
	
	echo $form->hidden('action', 'save_suivi');
	echo $form->hidden('id', $simulation->getId());
	$TLignes = $simulation->get_suivi_simulation($ATMdb,$form);
	$TLigneHistorized = $simulation->get_suivi_simulation_historized($ATMdb,$form);
	//pre($TLignes,true);exit;
	
	$TBS=new TTemplateTBS;
	
	print $TBS->render('./tpl/simulation_suivi.tpl.php'
		,array(
			'ligne' => $TLignes
			,'TLigneHistorized' => $TLigneHistorized
		)
		,array(
			'view'=>array(
				'mode'=>$mode
				,'type'=>($simulation->fk_soc > 0) ? 'simul' : 'calcul'
				,'titre'=>load_fiche_titre($langs->trans("SimulationSuivi"),'','object_simul.png@financement')
				,'titre_history'=>load_fiche_titre($langs->trans("SimulationSuiviHistory"),'','object_simul.png@financement')
			)
		)
	);
	
	$form->end_form();
}

function _liste_dossier(&$ATMdb, &$simulation, $mode, $search_by_siren=true) {
	//if(!empty($simulation->date_accord) && $simulation->date_accord < strtotime('-15 days')) return ''; // Ticket 916 -15 jours
	
	//pre($simulation,true);
	
	global $langs,$conf, $db, $bc;
	$r = new TListviewTBS('dossier_list', './tpl/simulation.dossier.tpl.php');

	$sql = "SELECT a.rowid as 'IDAff', a.reference as 'N° affaire', e.rowid as 'entityDossier', a.contrat as 'Type contrat'";
	$sql.= " , d.rowid as 'IDDoss', f.incident_paiement";
	//$sql.= " , f.reference as 'N° contrat', f.date_debut as 'Début', f.date_fin as 'Fin'";
	//$sql.= " , ac.fk_user";
	//$sql.= " , u.login as 'Utilisateur'";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement f ON (f.fk_fin_dossier = d.rowid AND type='LEASER')";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX.'entity e ON (e.rowid = d.entity) ';
	//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = a.fk_soc)";
	//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire_commercial ac ON ac.fk_fin_affaire = a.rowid";
	//$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user u ON ac.fk_user = u.rowid";
	//$sql.= " WHERE a.entity = ".$conf->entity;
	$sql.= ' WHERE a.entity IN('.getEntity('fin_dossier', TFinancementTools::user_courant_est_admin_financement()).')';
	//$sql.= " AND a.fk_soc = ".$simulation->fk_soc;
	$sql.= " AND (a.fk_soc = ".$simulation->fk_soc;
	if(!empty($simulation->societe->idprof1) && $search_by_siren) {
		$sql.= " OR a.fk_soc IN
					(
						SELECT s.rowid 
						FROM ".MAIN_DB_PREFIX."societe as s
							LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON (se.fk_object = s.rowid)
						WHERE
						(
							s.siren = '".$simulation->societe->idprof1."'
							AND s.siren != ''
						) 
						OR
						(
							se.other_siren LIKE '%".$simulation->societe->idprof1."%'
							AND se.other_siren != ''
						)
					)";
	}
	$sql .=" )";
	//$sql.= " AND s.rowid = ".$simulation->fk_soc;
	//$sql.= " AND f.type = 'CLIENT'";
	
	//$sql.= " AND d.montant < 50000";
	
	//return $sql;
	
	//return $sql;
	$TDossier = array();
	$form=new TFormCore;
	$form->Set_typeaff($mode);
	$ATMdb->Execute($sql);
	$ATMdb2 = new TPDOdb;
	$var = true;
	$min_amount_to_see = price2num($conf->global->FINANCEMENT_MAX_AMOUNT_TO_SHOW_SOLDE);
	if (empty($min_amount_to_see)) $min_amount_to_see = 50000;
	
	//$TDossierUsed = $simulation->get_list_dossier_used(true);
	// 2017.04.14 MKO : on ne vérifie plus si un dossie est déjà utilisé dans une autre simul
	$TDossierUsed = array();
	
	//pre($ATMdb->Get_field('IDDoss'),true);
	//echo $sql;
	while ($ATMdb->Get_line()) {
		$affaire = new TFin_affaire;
		$dossier=new TFin_Dossier;
		$dossier->load($ATMdb2, $ATMdb->Get_field('IDDoss'));
		$leaser = new Societe($db);
		$leaser->fetch($dossier->financementLeaser->fk_soc);
		
		// Chargement des équipements
		if(!empty($dossier->TLien[0])) {
			dol_include_once('/asset/class/asset.class.php');
			$dossier->TLien[0]->affaire->loadEquipement($ATMdb2);
			$TSerial = array();
			
			foreach($dossier->TLien[0]->affaire->TAsset as $linkAsset) {
				$serial = $linkAsset->asset->serial_number;
				$TSerial[] = $serial;
				if(count($TSerial) >= 3) {
					$TSerial[] = '...';
					break;
				}
			}
		}
		
		if($dossier->nature_financement == 'INTERNE') {
			$fin = &$dossier->financement;
			/*$soldeR = round($dossier->getSolde($ATMdb2, 'SRCPRO'),2);
			$soldeNR = round($dossier->getSolde($ATMdb2, 'SNRCPRO'),2);
			$soldeR1 = round($dossier->getSolde($ATMdb2, 'SRCPRO', $fin->duree_passe + 1),2);
			$soldeNR1 = round($dossier->getSolde($ATMdb2, 'SNRCPRO', $fin->duree_passe + 1),2);*/
		} else {
			$fin = &$dossier->financementLeaser;
			/*$soldeR = round($dossier->getSolde($ATMdb2, 'SRBANK'),2);
			$soldeNR = round($dossier->getSolde($ATMdb2, 'SNRBANK'),2);
			$soldeR1 = round($dossier->getSolde($ATMdb2, 'SRBANK', $fin->duree_passe + 1),2);
			$soldeNR1 = round($dossier->getSolde($ATMdb2, 'SNRBANK', $fin->duree_passe + 1),2);*/
		}
		//echo $fin->reference.'<br>';
		//if($fin->duree <= $fin->numero_prochaine_echeance) continue;
		
		if($fin->date_solde > 0 && $fin->date_solde < time() && empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_m1[$ATMdb->Get_field('IDDoss')]['checked'])
		&& empty($simulation->dossiers_rachetes_nr_m1[$ATMdb->Get_field('IDDoss')]['checked'])) continue;
		//if($fin->duree <= $fin->numero_prochaine_echeance) continue;
		if(empty($dossier->financementLeaser->reference)) continue;
		
		//Calcul du Solde Renouvelant et Non Renouvelant CPRO 
		/*$dossier->financement->capital_restant = $dossier->financement->montant;
		$dossier->financement->total_loyer = $dossier->financement->montant;
		for($i=0; $i<$dossier->financement->numero_prochaine_echeance;$i++){
			$capital_amortit = $dossier->financement->amortissement_echeance( $i+1 ,$dossier->financement->capital_restant);
			$part_interet = $dossier->financement->echeance - $capital_amortit;
			$dossier->financement->capital_restant-=$capital_amortit;
			
			$dossier->financement->total_loyer -= $dossier->financement->echeance;
		}*/
		
		//echo $dossier->financementLeaser->numero_prochaine_echeance.'<br>';
		//pre($simulation,true);
		if($dossier->nature_financement == 'INTERNE') {
			$soldeRM1 = (!empty($simulation->dossiers_rachetes_m1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_m1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO',$dossier->financement->numero_prochaine_echeance - 2),2); //SRCPRO
			$soldeNRM1 = (!empty($simulation->dossiers_rachetes_nr_m1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr_m1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO',$dossier->financement->numero_prochaine_echeance - 2),2); //SNRCPRO
			$soldeR = (!empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO',$dossier->financement->numero_prochaine_echeance - 1),2); //SRCPRO
			$soldeNR = (!empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO',$dossier->financement->numero_prochaine_echeance - 1),2); //SNRCPRO
			$soldeR1 = (!empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO',$dossier->financement->numero_prochaine_echeance),2); //SRCPRO
			$soldeNR1 = (!empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO',$dossier->financement->numero_prochaine_echeance),2); //SNRCPRO
			$soldeperso = round($dossier->getSolde($ATMdb2, 'perso'),2);
		}
		else{
			$soldeRM1 = (!empty($simulation->dossiers_rachetes_m1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_m1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO',$dossier->financementLeaser->numero_prochaine_echeance - 2),2);
			$soldeNRM1 = (!empty($simulation->dossiers_rachetes_nr_m1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr_m1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO',$dossier->financementLeaser->numero_prochaine_echeance -2 ),2);
			$soldeR = (!empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO',$dossier->financementLeaser->numero_prochaine_echeance - 1),2);
			$soldeNR = (!empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO',$dossier->financementLeaser->numero_prochaine_echeance -1 ),2);
			$soldeR1 = (!empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance ),2);
			$soldeNR1 = (!empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'])) ? $simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['montant'] : round($dossier->getSolde($ATMdb2, 'SNRCPRO', $dossier->financementLeaser->numero_prochaine_echeance ),2);
			$soldeperso = round($dossier->getSolde($ATMdb2, 'perso'),2);
		}

		//Suite PR1504-0764, Solde R et NR deviennent identique
		/*$soldeNR = $soldeR;
		$soldeNR1 = $soldeR1;*/
		
		if(empty($dossier->display_solde)) {
			$soldeRM1 = 0;
			$soldeNRM1 = 0;
			$soldeR = 0;
			$soldeNR = 0;
			$soldeR1 = 0;
			$soldeNR1 = 0;
			$soldeperso = 0;
		}
		
		$dossier_for_integral = new TFin_dossier;
		$dossier_for_integral->load($ATMdb2, $dossier->getId());
		$dossier_for_integral->load_facture($ATMdb2,true);
		//$dossier_for_integral->format_facture_integrale($PDOdb);
		//pre($dossier_for_integral->TFacture,true);
		$sommeRealise = $sommeNoir = $sommeCouleur = $sommeCopieSupCouleur = $sommeCopieSupNoir = 0;
		//list($sommeRealise,$sommeNoir,$sommeCouleur) = $dossier_for_integral->getSommesIntegrale($PDOdb);
		list($sommeCopieSupNoir,$sommeCopieSupCouleur) = $dossier_for_integral->getSommesIntegrale($ATMdb2,true);
		
		$decompteCopieSupNoir = $sommeCopieSupNoir * $dossier_for_integral->quote_part_noir;
		$decompteCopieSupCouleur = $sommeCopieSupCouleur * $dossier_for_integral->quote_part_couleur;
		
		$soldepersointegrale = $decompteCopieSupCouleur + $decompteCopieSupNoir;
		
		if(!$dossier->getSolde($ATMdb2, 'perso')){
			$soldeperso = ($soldepersointegrale * (FINANCEMENT_PERCENT_RETRIB_COPIES_SUP/100)); //On ne prend que 80% conformément  la règle de gestion
		}
		
		/*
		$checked = in_array($ATMdb->Get_field('IDDoss'), $simulation->dossiers_rachetes) ? true : false;
		$checkbox_more = 'solde_r="'.$soldeR.'"';
		$checkbox_more.= ' solde_nr="'.$soldeNR.'"';
		$checkbox_more.= ' contrat="'.$ATMdb->Get_field('Type contrat').'"';
		$checkbox_more.= in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		$checked1 = in_array($ATMdb->Get_field('IDDoss'), $simulation->dossiers_rachetes_p1) ? true : false;
		$checkbox_more1 = 'solde_r="'.$soldeR1.'"';
		$checkbox_more1.= ' solde_nr="'.$soldeNR1.'"';
		$checkbox_more1.= ' contrat="'.$ATMdb->Get_field('Type contrat').'"';
		$checkbox_more1.= in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		*/
		//echo $ATMdb->Get_field('IDDoss')." ";
		
		$checkedrm1 = (!empty($simulation->dossiers_rachetes_m1[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkednrm1 = (!empty($simulation->dossiers_rachetes_nr_m1[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkbox_moreRM1 = 'solde="'.$soldeRM1.'" style="display: none;"';
		$checkbox_moreRM1.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		$checkbox_moreNRM1 = ' solde="'.$soldeNRM1.'" style="display: none;"';
		$checkbox_moreNRM1.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		// Changement du 13.09.02 : les 4 soldes sont "cochables"
		$checkedr = (!empty($simulation->dossiers_rachetes[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkednr = (!empty($simulation->dossiers_rachetes_nr[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkbox_moreR = 'solde="'.$soldeR.'" style="display: none;"';
		$checkbox_moreR.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		$checkbox_moreNR = ' solde="'.$soldeNR.'" style="display: none;"';
		$checkbox_moreNR.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		$checkedr1 = (!empty($simulation->dossiers_rachetes_p1[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkednr1 = (!empty($simulation->dossiers_rachetes_nr_p1[$ATMdb->Get_field('IDDoss')]['checked'])) ? true : false;
		$checkbox_moreR1 = 'solde="'.$soldeR1.'" style="display: none;"';
		$checkbox_moreR1.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		$checkbox_moreNR1 = ' solde="'.$soldeNR1.'" style="display: none;"';
		$checkbox_moreNR1.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		$checkedperso = (is_array($simulation->dossiers_rachetes_perso) && in_array($ATMdb->Get_field('IDDoss'), $simulation->dossiers_rachetes_perso)) ? true : false;
		$checkbox_moreperso = 'solde="'.$soldeperso.'" style="display: none;"';
		$checkbox_moreperso.= (in_array($ATMdb->Get_field('IDDoss'), $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';
		
		/*
		 * Mise en commentaire des ancienne règle d'afficahge des soldes suite PR1504-0764 avec gestion des soldes V2
		 */
		if($ATMdb->Get_field('incident_paiement')=='OUI' && $dossier->nature_financement == 'EXTERNE') $dossier->display_solde = 0;
		//if($dossier->nature_financement == 'INTERNE') $dossier->display_solde = 0; // Ticket 447
		//if($leaser->code_client == '024242') $dossier->display_solde = 0; // Ticket 447, suite
		if($dossier->montant >= $min_amount_to_see) $dossier->display_solde = 0;// On ne prends que les dossiers < 50 000€ pour faire des tests
		if($dossier->soldepersodispo == 2) $dossier->display_solde = 0;
		
		/* 
		 * 2016.11.15 MKO : Règle d'affichage du solde d'un dossier :
		 *  - Si age < FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH => Montant financé (règle appliquée dans getSolde())
		 *  - Si age < FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH => Non dispo
		 *  - Sinon, on affiche le solde
		 */
		if($dossier->display_solde != 0) {
			if ($dossier->nature_financement == 'INTERNE') 
			{
				$nb_month_passe = ($dossier->financement->numero_prochaine_echeance - 1) * $dossier->financement->getiPeriode();
			} else {
				$nb_month_passe = ($dossier->financementLeaser->numero_prochaine_echeance - 1) * $dossier->financementLeaser->getiPeriode();
			}
			
			if ($nb_month_passe <= $conf->global->FINANCEMENT_SEUIL_SOLDE_DISPO_MONTH
				&& $nb_month_passe >= $conf->global->FINANCEMENT_SEUIL_SOLDE_CPRO_FINANCEMENT_LEASER_MONTH) {
				$dossier->display_solde = 0;
			}
		}
		
		//Ne pas laissé disponible un dossier dont la dernière facture client est impayée
		$cpt = 0;
		$TFactures = array_reverse($dossier->TFacture,true);
		foreach ($TFactures as $echeance => $facture) {
			if(is_array($facture)){
				foreach ($facture as $key => $fact) {
					if($fact->paye == 0){
						$cpt ++;
						if($cpt > FINANCEMENT_NB_INVOICE_UNPAID){
							$dossier->display_solde = 0;
						}
					}
				}
			}
			else{
				if($fact->paye == 0){
					$cpt ++;
					if($cpt > FINANCEMENT_NB_INVOICE_UNPAID){
						$dossier->display_solde = 0;
					}
				}
			}
			break;
		}

		$numcontrat_entity_leaser = ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['num_contrat']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['num_contrat'] :$fin->reference;
		$numcontrat_entity_leaser = '<a href="dossier.php?id='.$ATMdb->Get_field('IDDoss').'">'.$numcontrat_entity_leaser.'</a> / '.TFinancementTools::get_entity_translation($ATMdb->Get_field('entityDossier'));
		$numcontrat_entity_leaser.= '<br>'.$leaser->getNomUrl(0);
		$row = array(
			'id_affaire' => $ATMdb->Get_field('IDAff')
			,'num_affaire' => $ATMdb->Get_field('N° affaire')
			,'entityDossier' => $ATMdb->Get_field('entityDossier')
			,'id_dossier' => $dossier->getId()
			,'num_contrat' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['num_contrat']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['num_contrat'] :$fin->reference
			,'type_contrat' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['type_contrat']) ? $affaire->TContrat[$simulation->dossiers[$ATMdb->Get_field('IDDoss')]['type_contrat']] : $affaire->TContrat[$ATMdb->Get_field('Type contrat')]
			,'duree' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['duree']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['duree'] :$fin->duree.' '.substr($fin->periodicite,0,1)
			,'echeance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['echeance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['echeance'] : $fin->echeance
			,'loyer_actualise' => ($dossier->nature_financement == 'INTERNE') ? ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['loyer_actualise']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['loyer_actualise'] : $fin->loyer_actualise : ''
			,'debut' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_debut']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_debut'] :$fin->date_debut
			,'fin' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_fin']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_fin'] : $fin->date_fin
			,'prochaine_echeance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_prochaine_echeance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['date_prochaine_echeance'] : $fin->date_prochaine_echeance
			,'avancement' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['numero_prochaine_echeance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['numero_prochaine_echeance'] : $fin->numero_prochaine_echeance.'/'.$fin->duree
			,'terme' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['terme']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['terme'] : $fin->TTerme[$fin->terme]
			,'reloc' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['reloc']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['reloc'] : $fin->reloc
			,'solde_rm1' => $soldeRM1
			,'solde_nrm1' => $soldeNRM1
			,'solde_r' => $soldeR
			,'solde_nr' => $soldeNR
			,'solde_r1' => $soldeR1
			,'solde_nr1' => $soldeNR1
			,'soldeperso' => $soldeperso
			,'display_solde' => $dossier->display_solde
			,'fk_user' => $ATMdb->Get_field('fk_user')
			,'user' => $ATMdb->Get_field('Utilisateur')
			,'leaser' => $leaser->getNomUrl(0)
			,'choice_solde' => ($simulation->contrat == $ATMdb->Get_field('Type contrat')) ? 'solde_r' : 'solde_nr'
			,'checkboxrm1'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_m1['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkedrm1, $checkbox_moreRM1) : ''
			,'checkboxnrm1'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_nr_m1['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkednrm1, $checkbox_moreNRM1) : ''
			,'checkboxr'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkedr, $checkbox_moreR) : ''
			,'checkboxnr'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_nr['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkednr, $checkbox_moreNR) : ''
			,'checkboxr1'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_p1['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkedr1, $checkbox_moreR1) : ''
			,'checkboxnr1'=>($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_nr_p1['.$ATMdb->Get_field('IDDoss').'][checked]', $ATMdb->Get_field('IDDoss'), $checkednr1, $checkbox_moreNR1) : ''
			,'montantrm1'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_m1['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeRM1, $checkbox_moreRM1) : ''
			,'montantnrm1'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_nr_m1['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeNRM1, $checkbox_moreNRM1) : ''
			,'montantr'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeR, $checkbox_moreR) : ''
			,'montantnr'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_nr['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeNR, $checkbox_moreNR) : ''
			,'montantr1'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_p1['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeR1, $checkbox_moreR1) : ''
			,'montantnr1'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_nr_p1['.$ATMdb->Get_field('IDDoss').'][montant]', $soldeNR1, $checkbox_moreNR1) : ''
			,'checkboxperso'=>($mode == 'edit') ? $form->hidden('dossiers_rachetes_perso['.$ATMdb->Get_field('IDDoss').']', $ATMdb->Get_field('IDDoss'),$checkbox_moreperso) : ''
			,'checkedperso'=>$checkedperso
			,'checkedrm1'=>$checkedrm1
			,'checkednrm1'=>$checkednrm1
			,'checkedr'=>$checkedr
			,'checkednr'=>$checkednr
			,'checkedr1'=>$checkedr1
			,'checkednr1'=>$checkednr1
			
			,'maintenance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['maintenance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['maintenance'] : $fin->montant_prestation
			,'assurance' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance'] :$fin->assurance
			,'assurance_actualise' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance_actualise']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['assurance_actualise'] :$fin->assurance_actualise
			,'montant' => ($simulation->dossiers[$ATMdb->Get_field('IDDoss')]['montant']) ? $simulation->dossiers[$ATMdb->Get_field('IDDoss')]['montant'] : $fin->montant
			
			,'class' => $bc[$var]
			
			,'incident_paiement'=>$incident_paiement
			,'numcontrat_entity_leaser'=>$numcontrat_entity_leaser
			
			,'serial' => implode(', ', $TSerial)
		);
		if($row['type_contrat'] == 'Intégral'){
			$row['type_contrat']='<a href="dossier_integrale.php?id='.$ATMdb->Get_field('IDDoss').'">Intégral</a>';
		}
		//pre($row,true);
		$TDossier[$dossier->getId()] = $row;

		$var = !$var;
	}
	
	$THide = array('IDAff', 'IDDoss', 'fk_user', 'Type contrat');
	
	TFinancementTools::add_css();
	
	//pre($simulation,true);
	//pre($TDossier,true);exit;
	return $r->renderArray($ATMdb, $TDossier, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'150'
		)
		,'orderBy'=>array(
			'num_affaire' => 'DESC'
		)
		,'link'=>array(
			'num_affaire'=>'<a href="affaire.php?id=@id_affaire@">@val@</a>'
			,'num_contrat'=>'<a href="dossier.php?id=@id_dossier@">@val@</a>'
			,'user'=>'<a href="'.DOL_URL_ROOT.'/user/card.php?id=@fk_user@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'hide'=>$THide
		,'type'=>array('Début'=>'date', 'Fin'=>'date')
		,'liste'=>array(
			'titre'=>'Liste des imports'
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun dossier à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'display_montant' => ($conf->entity == 6) ? 0 : 1
		)
	));
	
	$THide = array('IDAff', 'IDDoss', 'fk_user');
	
	return $r->render($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 0)
			,'nbLine'=>'150'
		)
		,'orderBy'=>array(
			'N° affaire' => 'DESC'
		)
		,'link'=>array(
			'N° affaire'=>'<a href="affaire.php?id=@IDAff@">@val@</a>'
			,'Utilisateur'=>'<a href="'.DOL_URL_ROOT.'/user/card.php?id=@fk_user@">'.img_picto('','object_user.png', '', 0).' @val@</a>'
		)
		,'hide'=>$THide
		,'type'=>array('Début'=>'date', 'Fin'=>'date')
		,'liste'=>array(
			'titre'=>'Liste des imports'
			,'image'=>img_picto('','import32.png@financement', '', 0)
			,'picto_precedent'=>img_picto('','back.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> 0
			,'messageNothing'=>"Il n'y a aucun dossier à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			
		)
	));
}

function _has_valid_simulations(&$ATMdb, $socid){
    
    global $db;

    $simu = new TSimulation();
    $TSimulations = $simu->load_by_soc($ATMdb, $db, $socid);
    
    foreach ($TSimulations as $simulation){
        if($simulation->date_validite > dol_now()) {
            return true;
        }
    }
        return false;
    
}

function _simu_edit_link($simulId, $date){
    
    global $db, $ATMdb;
    
    if(strtotime($date) > dol_now()){
        $return = '<a href="?id='.$simulId.'&action=edit">'.img_picto('modifier','./img/pencil.png', '', 1).'</a>';
    } else {
        $return = '';
    }
    return $return;
    
}

