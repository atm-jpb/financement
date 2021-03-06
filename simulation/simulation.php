<?php
require('../config.php');
dol_include_once('/financement/class/simulation.class.php');
dol_include_once('/financement/class/grille.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/dossier_integrale.class.php');
dol_include_once('/financement/class/score.class.php');
dol_include_once('/financement/class/conformite.class.php');
dol_include_once('/financement/lib/financement.lib.php');
dol_include_once('/multicompany/class/dao_multicompany.class.php');
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

$langs->load('financement@financement');
$simulation = new TSimulation(true);
$ATMdb = new TPDOdb;
$tbs = new TTemplateTBS;

$serialNumber = GETPOST('sall');

$mesg = '';
$error = false;
$action = GETPOST('action');
if(! empty($_REQUEST['calculate'])) $action = 'calcul';
if(! empty($_REQUEST['cancel'])) { // Annulation
    if(! empty($_REQUEST['id'])) {
        header('Location: '.$_SERVER['PHP_SELF'].'?id='.$_REQUEST['id']);
        exit;
    } // Retour sur simulation si mode modif
    if(! empty($_REQUEST['fk_soc'])) {
        header('Location: ?socid='.$_REQUEST['fk_soc']);
        exit;
    } // Retour sur client sinon
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}
if(! empty($_REQUEST['from']) && $_REQUEST['from'] == 'wonderbase') { // On arrive de Wonderbase, direction nouvelle simulation
    if(! empty($_REQUEST['code_artis'])) { // Client
        $socid = 0;

        $sql = 'SELECT rowid';
        $sql .= ' FROM '.MAIN_DB_PREFIX.'societe';
        $sql .= ' WHERE client = 1';
        $sql .= " AND code_client = '".$db->escape($_REQUEST['code_artis'])."'";
        $sql .= " AND entity IN (".getEntity('societe', true).")";

        $resql = $db->query($sql);
        if(! $resql) {
            dol_print_error($db);
            exit;
        }

        if($obj = $db->fetch_object($resql)) {
            $socid = $obj->rowid;
        }

        // Si le client a une simulation en cours de validité, on va sur la liste de ses simulations
        $hasValidSimu = _has_valid_simulations($ATMdb, $socid);
        if($hasValidSimu) {
            header('Location: ?socid='.$socid);
            exit;
        }
        else {
            header('Location: ?action=new&fk_soc='.$socid);
            exit;
        }
    }
    else if(! empty($_REQUEST['code_wb'])) { // Prospect
        $TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'societe', array('code_client' => $_REQUEST['code_wb'], 'client' => 2));
        if(! empty($TId[0])) {
            // Si le prospect a une simulation en cours de validité, on va sur la liste de ses simulations
            $hasValidSimu = _has_valid_simulations($ATMdb, $TId[0]);
            if($hasValidSimu) {
                header('Location: ?socid='.$TId[0]);
                exit;
            }
            else {
                header('Location: ?action=new&fk_soc='.$TId[0]);
                exit;
            }
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
        $societe->idprof1 = substr($_REQUEST['siren'], 0, 9);
        $societe->idprof2 = $_REQUEST['siren'];
        $societe->idprof3 = $_REQUEST['naf'];
        $societe->client = 2;
        if($societe->create($user) > 0) {
            header('Location: ?action=new&fk_soc='.$societe->id);
            exit;
        }
        else {
            $action = 'new';
            $error = 1;
            $mesg = $langs->trans('UnableToCreateProspect');
        }
    }
}

// le problème de ce comportement c'est que si le tiers n'a pas de simulation valid et qu'on veut juste voir la liste de c'est simulations, on ne peut pas...
if(! empty($_REQUEST['from']) && $_REQUEST['from'] == 'search' && ! empty($_REQUEST['socid'])) {
    $fk_soc = (int)$_REQUEST['socid'];
    $hasValidSimu = _has_valid_simulations($ATMdb, $fk_soc);
    if(! $hasValidSimu) {
        header('Location: ?action=new&fk_soc='.$fk_soc);
        exit;
    }
    else {
        header('Location: ?socid='.$fk_soc);
        exit;
    }
}

$fk_soc = $_REQUEST['fk_soc'];

if(! empty($_REQUEST['mode_search']) && $_REQUEST['mode_search'] == 'search_matricule' && ! empty($serialNumber)) {
    // Recherche du client associé au matricule pour ensuite créer une nouvelle simulation
    $TId = TRequeteCore::get_id_from_what_you_want($ATMdb, MAIN_DB_PREFIX.'assetatm', array('serial_number' => $serialNumber), 'fk_soc');

    if(empty($TId)) { // Matricule non trouvé
        setEventMessage('Matricule '.$serialNumber.' non trouvé', 'warnings');
        header(header('Location: '.dol_buildpath('index.php', 1)));
        exit;
    }

    if(count($TId) > 1) { // Plusieurs matricules trouvés
        setEventMessage('Plusieurs matricules trouvés pour la recherche '.$serialNumber.'. Merci de chercher par client', 'warnings');
        header(header('Location: '.dol_buildpath('index.php', 1)));
        exit;
    }

    if(! empty($TId[0])) {
        $fk_soc = $TId[0];
        $action = 'new';
    }
}

if(! empty($fk_soc)) {
    $simulation->fk_soc = $fk_soc;
    $simulation->load_annexe($ATMdb);

    // Si l'utilisateur n'a pas le droit d'accès à tous les tiers
    if(! $user->rights->societe->client->voir) {
        // On vérifie s'il est associé au tiers dans Dolibarr
        dol_include_once("/financement/class/commerciaux.class.php");
        $c = new TCommercialCpro;
        if(! $c->loadUserClient($ATMdb, $user->id, $simulation->fk_soc) > 0) {
            // On vérifie si l'utilisateur est associé au tiers dans Wonderbase
            $url = FIN_WONDERBASE_USER_RIGHT_URL.'?numArtis='.$simulation->societe->code_client.'&trigramme='.$user->login;
            $droit = file_get_contents($url);

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
    $canvas = $simulation->societe->canvas ? $object->canvas : GETPOST("canvas");
    $objcanvas = '';
    if(! empty($canvas)) {
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

if(! empty($action)) {
    switch($action) {
        case 'list':
            _liste($ATMdb, $simulation);
            break;
        case 'add':
        case 'new':
            $simulation->set_values($_REQUEST);
            _fiche($ATMdb, $simulation, 'edit');
            break;
        case 'calcul':
            if(! empty($_REQUEST['id'])) $simulation->load($ATMdb, $_REQUEST['id']);

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

            _fiche($ATMdb, $simulation, 'edit');
            break;
        case 'edit'    :
            $simulation->load($ATMdb, $_REQUEST['id']);
            $simulation->set_values_from_cristal($_REQUEST);
            _fiche($ATMdb, $simulation, 'edit');
            break;
        case 'clone':
            $simulation->load($ATMdb, $_REQUEST['id']);
            $simulation->accord = 'SS';
            $simulation->save($ATMdb);
            $simulation->clone_simu();
            $simulation->save($ATMdb);

            header('Location: '.$_SERVER['PHP_SELF'].'?id='.$simulation->getId().'&action=edit');
            exit();
            break;
        case 'save_suivi':
            $simulation->load($ATMdb, $_REQUEST['id']);
            if(! empty($_REQUEST['TSuivi'])) {
                foreach($_REQUEST['TSuivi'] as $id_suivi => $TVal) {
                    if(! empty($simulation->TSimulationSuivi[$id_suivi])) {
                        $simulation->TSimulationSuivi[$id_suivi]->numero_accord_leaser = $TVal['num_accord'];
                        $simulation->TSimulationSuivi[$id_suivi]->coeff_leaser = $TVal['coeff_accord'];
                        $simulation->TSimulationSuivi[$id_suivi]->commentaire_interne = $TVal['commentaire_interne'];
                        $simulation->TSimulationSuivi[$id_suivi]->save($ATMdb);
                    }
                }
                setEventMessage($langs->trans('DataSaved'));
            }

            _fiche($ATMdb, $simulation, 'view');
            break;
        case 'save':
            if(! empty($_REQUEST['id'])) $simulation->load($ATMdb, $_REQUEST['id']);

            $oldAccord = $simulation->accord;
            $oldsimu = clone $simulation;

            $fk_type_contrat_old = $simulation->fk_type_contrat;

            $simulation->set_values($_REQUEST);

            $fk_type_contrat_new = $simulation->fk_type_contrat;

            // Si on ne modifie que le montant, les autres champs ne sont pas présent, il faut conserver ceux de la simu
            if($_REQUEST['mode'] != 'edit_montant') {
                // On vérifie que les dossiers sélectionnés n'ont pas été décochés
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
                if(! empty($simulation->coeff_final)) {
                    $oldsimu->coeff_final = 0;
                    $oldsimu->_calcul($ATMdb, 'calcul', array(), true);
                    $cpysimu = clone $simulation;
                    $cpysimu->coeff_final = 0;
                    $cpysimu->_calcul($ATMdb, 'calcul', array(), true);
                    $diffcoeff = $cpysimu->coeff - $oldsimu->coeff;

                    if(! empty($diffcoeff)) {
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

            // On refait le calcul avant d'enregistrer
            $simulation->_calcul($ATMdb, 'save');

            if($error && $simulation->error !== 'ErrorMontantModifNotAuthorized') {
                _fiche($ATMdb, $simulation, 'edit');
            }
            else {
                // Modification du type de contrat => save du suivi
                if(strcmp($fk_type_contrat_old, $fk_type_contrat_new) != 0 && ! empty($fk_type_contrat_old)) {
                    if(empty($simulation->TSimulationSuivi)) $simulation->load_suivi_simulation($ATMdb);
                    if(! empty($simulation->TSimulationSuivi)) {
                        $now = time();
                        $nowFr = date('d/m/Y H:i');
                        foreach($simulation->TSimulationSuivi as &$simuSuivi) {
                            if($simuSuivi->statut_demande == 0) {
                                $simuSuivi->delete($ATMdb);
                            }
                            else if($simuSuivi->date_historization <= 0) {
                                if(! empty($simuSuivi->commentaire)) $simuSuivi->commentaire .= "\n";
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

                    // On créé un nouvel aiguillage (suivi simulation)
                    $simulation->create_suivi_simulation($ATMdb);
                }

                $simulation->calculAiguillageSuivi($ATMdb, true);

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

                    if($oldAccord == 'OK') {
                        // Si il y avait un accord avant et qu'on fait une modif, on vérifie les règles suivantes pour passer ou non le statut à "MODIF"

                        // Vérification de la variation du montant
                        $diffmontant = abs($simulation->montant - $simulation->montant_accord);
                        if(empty($simulation->montant_accord)) $simulation->montant_accord = 1;
                        $montantOK = ($diffmontant / $simulation->montant_accord) * 100 <= $conf->global->FINANCEMENT_PERCENT_MODIF_SIMUL_AUTORISE;

                        // Si le montant ne respecte pas la règle (+- 10 %) => MODIF
                        if(! $montantOK) {
                            $simulation->accord = 'WAIT_MODIF';
                        }

                        // Si MANDATEE ou ADOSSEE, on passe en modif uniquement si changement de durée / périodicité
                        if($simulation->type_financement == 'MANDATEE' || $simulation->type_financement == 'ADOSSEE') {
                            if(! empty($simulation->modifs['duree']) || ! empty($simulation->modifs['opt_periodicite'])) {
                                $simulation->accord = 'WAIT_MODIF';
                            }
                        }
                        // Sinon on passe en modif si autre chose que le montant a été modifié (montant, echeance, coeff)
                        else {
                            $keepAccord = array('montant', 'echeance', 'coeff', 'coeff_final');
                            foreach($simulation->modifs as $k => $v) { // cherche les modifs qui font passer en accord modif
                                if(! in_array($k, $keepAccord)) $simulation->accord = 'WAIT_MODIF';
                            }
                        }
                    }
                    else if($oldAccord == 'WAIT' || $oldAccord == 'WAIT_LEASER' || $oldAccord == 'WAIT_SELLER') {
                        $simulation->accord = 'WAIT_MODIF';
                        $simulation->coeff_final = 0;
                    }
                }

                if($simulation->accord == 'OK') {
                    $simulation->montant_accord = $simulation->montant;
                }

                if(empty($simulation->accord) || empty($simulation->rowid)) {
                    $simulation->accord = 'WAIT';
                }

                $simulation->save($ATMdb);
                $simulation->generatePDF($ATMdb);

                // Si l'accord vient d'être donné (par un admin)
                if(($simulation->accord == 'OK' || $simulation->accord == 'KO') && $simulation->accord != $oldAccord) {
                    $simulation->send_mail_vendeur();

                    if($simulation->accord == 'OK' && in_array($simulation->entity, array(18, 25, 28)) && empty($simulation->opt_no_case_to_settle)) {
                        $simulation->send_mail_vendeur_esus();
                    }
                }

                if(empty($oldAccord) || ($oldAccord !== $simulation->accord)) {
                    $simulation->historise_accord($ATMdb);
                }

                header('Location: '.$_SERVER['PHP_SELF'].'?id='.$simulation->getId());
                exit;
            }
            break;
        case 'changeAccord':
            $newAccord = GETPOST('accord');
            $simulation->load($ATMdb, $_REQUEST['id']);

            if($newAccord == 'OK') $simulation->montant_accord = $simulation->montant_total_finance;

            $simulation->accord = $newAccord;
            $simulation->save($ATMdb);
            $simulation->generatePDF($ATMdb);
            if(in_array($newAccord, array('KO', 'WAIT_AP'))) $simulation->send_mail_vendeur();
            $simulation->historise_accord($ATMdb);

            if($simulation->fk_action_manuelle > 0) {
                $simulation->fk_action_manuelle = 0;
                $simulation->save($ATMdb);
            }

            header('Location: '.$_SERVER['PHP_SELF'].'?id='.$simulation->id);
            exit;
            break;
        case 'send_accord':
            if(! empty($_REQUEST['id'])) {
                $simulation->load($ATMdb, $_REQUEST['id']);
                if($simulation->accord == 'OK') {
                    $simulation->send_mail_vendeur();

                    if(in_array($simulation->entity, array(18, 25, 28)) && empty($simulation->opt_no_case_to_settle)) {
                        $simulation->send_mail_vendeur_esus();
                    }
                }
            }

            _fiche($ATMdb, $simulation, 'view');
            break;
        case 'delete':
            $simulation->load($ATMdb, $_REQUEST['id']);

            $simulation->delete_accord_history($ATMdb);
            $simulation->delete($ATMdb);

            ?>
            <script language="javascript">
                document.location.href = '?delete_ok=1';
            </script>
            <?php

            break;
        case 'trywebservice':
            $simulation->load($ATMdb, GETPOST('id'));
            $id_suivi = GETPOST('id_suivi');
            $simulation->TSimulationSuivi[$id_suivi]->doAction($ATMdb, $simulation, 'demander', true);
            $simulation->TSimulationSuivi[$id_suivi]->_sendDemandeAuto($ATMdb, true);

            _fiche($ATMdb, $simulation, 'view');
            break;
        default:
            //Actions spécifiques au suivi financement leaser
            $id_suivi = GETPOST('id_suivi');
            if($id_suivi) {
                $simulation->load($ATMdb, GETPOST('id'));
                foreach($simulation->TSimulationSuivi as $k => $simulationSuivi) {
                    if($simulationSuivi->rowid == $id_suivi) {
                        $id_suivi = $k;
                        break;
                    }
                }
                $simulation->TSimulationSuivi[$id_suivi]->doAction($ATMdb, $simulation, $action);

                if(! empty($simulation->TSimulationSuivi[$id_suivi]->errorLabel)) {
                    setEventMessage($simulation->TSimulationSuivi[$id_suivi]->errorLabel, 'errors');
                }

                if($action == 'demander') {
                    // Si une demande est formulée auprès d'un leaser, on fige le montant (+- 10%)
                    if(empty($simulation->montant_accord)) {
                        $simulation->montant_accord = $simulation->montant_total_finance;
                    }

                    $simulation->save($ATMdb);
                    $simulation->generatePDF($ATMdb);
                }

                _fiche($ATMdb, $simulation, 'view');
            }
            break;
    }
}
else if(isset($_REQUEST['id'])) {
    $simulation->load($ATMdb, $_REQUEST['id']);
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

    llxHeader('', 'Simulations');
    print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

    $r = new TSSRenderControler($simulation);

    $THide = array('fk_soc', 'fk_user_author', 'rowid');

    $sql = "SELECT DISTINCT s.rowid, s.reference, e.label as entity_label, s.fk_soc, CONCAT(SUBSTR(soc.nom, 1, 25), '...') as nom, s.fk_user_author, s.fk_type_contrat, s.montant_total_finance, s.echeance,";
    $sql .= " CONCAT(s.duree, ' ', CASE WHEN s.opt_periodicite = 'MOIS' THEN 'M' WHEN s.opt_periodicite = 'ANNEE' THEN 'A' WHEN s.opt_periodicite = 'SEMESTRE' THEN 'S' ELSE 'T' END) as 'duree',";
    $sql .= " s.date_simul, s.date_validite, u.login, s.accord, s.type_financement, lea.nom as leaser, s.attente, s.fk_fin_dossier";
    $sql .= " ,SUM(CASE WHEN ss.statut = 'OK' THEN 1 ELSE 0 END) as nb_ok";
    $sql .= " ,SUM(CASE WHEN ss.statut = 'KO' THEN 1 ELSE 0 END) as nb_refus";
    $sql .= " ,SUM(CASE WHEN ss.statut = 'WAIT' THEN 1 ELSE 0 END) as nb_wait";
    $sql .= " ,SUM(CASE WHEN ss.statut = 'ERR' THEN 1 ELSE 0 END) as nb_err";
    $sql .= " , '' as suivi, '' as loupe";
    $sql .= " FROM @table@ s ";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON (s.fk_user_author = u.rowid)";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as soc ON (s.fk_soc = soc.rowid)";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as lea ON (s.fk_leaser = lea.rowid) ";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX.'entity as e ON (e.rowid = s.entity) ';
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX.'fin_simulation_suivi as ss ON (s.rowid = ss.fk_simulation) ';

    if(! $user->rights->societe->client->voir) {
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON (sc.fk_soc = soc.rowid)";
    }
    $sql .= " WHERE 1=1 ";
    $sql .= " AND (ss.date_historization < '1970-00-00 00:00:00' OR ss.date_historization IS NULL) ";
    if(! $user->rights->societe->client->voir) //restriction
    {
        $sql .= " AND sc.fk_user = ".$user->id;
    }
    if($user->rights->societe->client->voir && ! $user->rights->financement->allsimul->simul_list) {
        $sql .= " AND s.fk_user_author = ".$user->id;
    }
    if(! empty($searchnumetude)) {
        $sql .= " AND ss.numero_accord_leaser='".$searchnumetude."'";
    }

    if(isset($_REQUEST['socid'])) {
        $societe = new Societe($db);
        $societe->fetch($_REQUEST['socid']);

        // Recherche par SIREN
        $search_by_siren = true;
        if(! empty($societe->array_options['options_no_regroup_fin_siren'])) {
            $search_by_siren = false;
        }

        $sql .= " AND (s.fk_soc = ".$societe->id;
        if(! empty($societe->idprof1) && $search_by_siren) {
            $sql .= " OR s.fk_soc IN
						(
							SELECT s.rowid 
							FROM ".MAIN_DB_PREFIX."societe as s
								LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields as se ON (se.fk_object = s.rowid)
							WHERE
							(
								s.siren = '".$societe->idprof1."'
								AND s.siren != ''
							) 
							OR
							(
								se.other_siren LIKE '%".$societe->idprof1."%'
								AND se.other_siren != ''
							)
						)";
        }
        $sql .= " )";

        // Affichage résumé client
        $formDoli = new Form($db);

        $TBS = new TTemplateTBS();

        // Infos sur SIREN
        $info = '';
        if(! empty($societe->idprof1)) {
            if($societe->id_prof_check(1, $societe) > 0) $info = ' &nbsp; '.$societe->id_prof_url(1, $societe);
            else $info = ' <font class="error">('.$langs->trans("ErrorWrongValue").')</font>';
        }

        print $TBS->render('./tpl/client_entete.tpl.php', array(),
            array(
                'client' => array(
                    'dolibarr_societe_head' => dol_get_fiche_head(societe_prepare_head($societe), 'simulation', $langs->trans("ThirdParty"), 0, 'company'),
                    'showrefnav' => $formDoli->showrefnav($societe, 'socid', '', ($user->societe_id ? 0 : 1), 'rowid', 'nom'),
                    'code_client' => $societe->code_client,
                    'idprof1' => $societe->idprof1.$info,
                    'adresse' => $societe->address,
                    'cpville' => $societe->zip.($societe->zip && $societe->town ? " / " : "").$societe->town,
                    'pays' => picto_from_langcode($societe->country_code).' '.$societe->country
                ),
                'view' => array(
                    'mode' => 'view'
                )
            )
        );

        $THide[] = 'Client';
    }

    $sql .= ' AND s.entity IN('.getEntity('fin_simulation', true).')';
    $sql .= ' GROUP BY s.rowid';

    if(! $user->rights->financement->allsimul->suivi_leaser) {
        $THide[] = 'suivi';
        $THide[] = 'attente';
    }

    $THide[] = 'type_financement';
    $THide[] = 'date_validite';
    $THide[] = 'fk_fin_dossier';
    $THide[] = 'nb_ok';
    $THide[] = 'nb_refus';
    $THide[] = 'nb_wait';
    $THide[] = 'nb_err';

    $TOrder = array('date_simul' => 'DESC');
    if(isset($_REQUEST['orderDown'])) $TOrder = array($_REQUEST['orderDown'] => 'DESC');
    if(isset($_REQUEST['orderUp'])) $TOrder = array($_REQUEST['orderUp'] => 'ASC');

    $form = new TFormCore($_SERVER['PHP_SELF'], 'formSimulation', 'GET');

    $TEntityName = TFinancementTools::build_array_entities();
    TFinancementTools::add_css();

    $tab = array(
        'limit' => array(
            'page' => (isset($_REQUEST['page']) ? $_REQUEST['page'] : 1),
            'nbLine' => '30',
            'global' => '1000'
        ),
        'link' => array(
            'reference' => '<a href="?id=@rowid@">@val@</a>',
            'nom' => '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid=@fk_soc@">'.img_picto('', 'object_company.png', '', 0).' @val@</a>',
            'login' => '<a href="'.DOL_URL_ROOT.'/user/card.php?id=@fk_user_author@">'.img_picto('', 'object_user.png', '', 0).' @val@</a>'
        ),
        'translate' => array(
            'fk_type_contrat' => $affaire->TContrat,
            'accord' => $simulation->TStatutShort
        ),
        'hide' => $THide,
        'type' => array('date_simul' => 'date', 'montant_total_finance' => 'money', 'echeance' => 'money'),
        'liste' => array(
            'titre' => 'Liste des simulations',
            'image' => img_picto('', 'simul32.png@financement', '', 0),
            'picto_precedent' => img_picto('', 'back.png', '', 0),
            'picto_suivant' => img_picto('', 'next.png', '', 0),
            'noheader' => (int)isset($_REQUEST['socid']),
            'messageNothing' => "Il n'y a aucune simulation à afficher",
            'order_down' => img_picto('', '1downarrow.png', '', 0),
            'order_up' => img_picto('', '1uparrow.png', '', 0),
            'picto_search' => img_picto('', 'search.png', '', 0)
        ),
        'orderBy' => $TOrder,
        'title' => array(
            'rowid' => 'N°',
            'nom' => 'Client',
            'reference' => 'Ref.',
            'entity_label' => 'Partenaire',
            'duree' => 'Durée',
            'montant_total_finance' => 'Montant',
            'echeance' => 'Échéance',
            'login' => 'Utilisateur',
            'fk_type_contrat' => 'Type<br>de<br>contrat',
            'date_simul' => 'Date<br>simulation',
            'accord' => 'Statut',
            'type_financement' => 'Type<br>financement',
            'leaser' => 'Leaser',
            'attente' => 'Délai',
            'fk_fin_dossier' => 'Dossier financé',
            'nb_ok' => 'nb_ok',
            'nb_ko' => 'nb_refus',
            'nb_wait' => 'nb_wait',
            'nb_err' => 'nb_err',
            'loupe' => ''
        ),
        'search' => array(
            'nom' => array('recherche' => true, 'table' => 'soc'),
            'login' => array('recherche' => true, 'table' => 'u'),
            'entity_label' => array('recherche' => $TEntityName, 'table' => 'e', 'field' => 'rowid'),
            'fk_type_contrat' => $affaire->TContrat,
            'date_simul' => 'calendar',
            'accord' => $simulation->TStatutShort,
            'leaser' => array('recherche' => true, 'table' => 'lea', 'field' => 'nom'),
            'reference' => array('recherche' => true, 'table' => 's', 'field' => 'reference')
        ),
        'operator' => array(
            'entity_label' => '='
        ),
        'eval' => array(
            'attente' => 'print_attente(@val@)',
            'loupe' => '_simu_edit_link(@rowid@, \'@date_validite@\')'
        ),
        'size' => array(
            'width' => array(
                'entity_id' => '100px',
                'entity_label' => '50px',
                'type_financement' => '100px',
                'leaser' => '270px'
            )
        ),
        'position' => array(
            'text-align' => array(
                'rowid' => 'center',
                'nom' => 'center',
                'reference' => 'center',
                'entity_label' => 'center',
                'duree' => 'center',
                'montant_total_finance' => 'center',
                'echeance' => 'center',
                'login' => 'center',
                'fk_type_contrat' => 'center',
                'date_simul' => 'center',
                'accord' => 'center',
                'type_financement' => 'center',
                'leaser' => 'center',
                'suivi' => 'center',
                'attente' => 'center'
            )
        )
    );

    if($user->rights->financement->allsimul->suivi_leaser) {
        $tab['title']['suivi'] = 'Statut<br>Leaser';
        $tab['eval']['suivi'] = 'getStatutSuivi(@rowid@, \'@accord@\', @fk_fin_dossier@, @nb_ok@, @nb_refus@, @nb_wait@, @nb_err@);';
    }

    $r->liste($ATMdb, $sql, $tab);

    $form->end();

    if(isset($_REQUEST['socid'])) {
        $href = '?action=new&fk_soc='.$_REQUEST['socid'];
        foreach($_POST as $k => $v) $href .= '&'.$k.'='.$v;

        ?>
        <div class="tabsAction"><a href="<?php echo $href; ?>" class="butAction">Nouvelle simulation</a></div><?php
    }
}

function print_attente($compteur) {
    global $conf;

    $style = '';
    $min = (int)($compteur / 60);
    if(! empty($conf->global->FINANCEMENT_FIRST_WAIT_ALARM) && $min >= (int)$conf->global->FINANCEMENT_FIRST_WAIT_ALARM) $style = 'color:orange';
    if(! empty($conf->global->FINANCEMENT_SECOND_WAIT_ALARM) && $min >= (int)$conf->global->FINANCEMENT_SECOND_WAIT_ALARM) $style = 'color:red';

    $min = ($compteur / 60) % 60;
    $heures = abs(round((($compteur / 60) - $min) / 60));

    $ret = '';
    $ret .= (! empty($heures) ? $heures : "0");
    $ret .= "h";
    $ret .= (($min < 10) ? "0" : "").$min;

    if(! empty($style)) $ret = '<span style="'.$style.'">'.$ret.'</span>';

    return $ret;
}

function getStatutSuivi($idSimulation, $statut, $fk_fin_dossier, $nb_ok, $nb_refus, $nb_wait, $nb_err) {
    global $langs, $db;
    if(! function_exists('get_picto')) dol_include_once('/financement/lib/financement.lib.php');

    $suivi_leaser = '';
    $PDOdb = new TPDOdb;
    $s = new TSimulation;
    $s->load($PDOdb, $idSimulation, false);

    if($s->fk_action_manuelle > 0) {
        $title = '';
        $color = 'deeppink';
        if($s->fk_action_manuelle == 2) $color = 'green';
        $sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_action_manuelle WHERE rowid = '.$s->fk_action_manuelle;
        $resql = $db->query($sql);

        if($obj = $db->fetch_object($resql)) {
            $title = $langs->trans($obj->label);
        }

        $suivi_leaser .= get_picto('manual', $title, $color);

        $db->free($resql);
    }
    else {
        $suivi_leaser .= '<a href="'.dol_buildpath('/financement/simulation/simulation.php?id='.$idSimulation, 1).'#suivi_leaser">';

        if(! empty($fk_fin_dossier)) { // La simulation a été financée, lien direct vers le dossier
            $suivi_leaser = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$fk_fin_dossier, 1).'">';
            $suivi_leaser .= get_picto('money');
            $suivi_leaser .= '</a>';
        }
        else if($statut == 'OK') $suivi_leaser .= get_picto('super_ok');
        else if($statut == 'WAIT_SELLER') $suivi_leaser .= get_picto('wait_seller');
        else if($statut == 'WAIT_LEASER') $suivi_leaser .= get_picto('wait_leaser');
        else if($nb_ok > 0) $suivi_leaser .= get_picto('ok');
        else if($nb_refus > 0) $suivi_leaser .= get_picto('refus');
        else if($nb_wait > 0) $suivi_leaser .= get_picto('wait');
        else if($nb_err > 0) $suivi_leaser .= get_picto('err');
        else $suivi_leaser .= '';
        $suivi_leaser .= '</a>';
    }

    $suivi_leaser .= ' <span style="color: #00AA00;">'.$nb_ok.'</span>';
    $suivi_leaser .= ' <span style="color: #FF0000;">'.$nb_refus.'</span>';
    $suivi_leaser .= ' <span>'.($nb_ok + $nb_refus + $nb_wait + $nb_err).'</span>';

    return $suivi_leaser;
}

function _fiche(&$ATMdb, TSimulation &$simulation, $mode) {
    global $db, $langs, $user, $conf, $action;

    $result = restrictedArea($user, 'financement', $simulation->getID(), 'fin_simulation&societe', '', 'fk_soc', 'rowid');

    // Si simulation déjà préco ou demande faite, le "montant_accord" est renseigné, le vendeur ne peux modifier que certains champs
    if($mode == 'edit') {
        if($simulation->modifiable === 0 && empty($user->rights->financement->admin->write)) {
            $mode = 'view';
        }
        if(! empty($simulation->montant_accord) && empty($user->rights->financement->admin->write)
            && $simulation->modifiable == 2) {
            $mode = 'edit_montant';
        }
    }

    if($simulation->getId() == 0) {
        $simulation->duree = __get('duree', $simulation->duree, 'integer');
    }

    $conformite = new Conformite;
    $conformite->fetchBy('fk_simulation', $simulation->rowid);

    $extrajs = array('/financement/js/financement.js', '/financement/js/dossier.js');
    llxHeader('', $langs->trans("Simulation"), '', '', '', '', $extrajs);
    print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';
    if($action == 'confirm_selectionner') {
        $form = new Form($db);
        $fk_suivi = GETPOST('id_suivi', 'int');
        print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$simulation->rowid.'&id_suivi='.$fk_suivi, $langs->trans('SelectThisLeaser'), $langs->trans('ConfirmSelectThisLeaser'), 'selectionner', '', '', 2);
    }

    $head = simulation_prepare_head($simulation, $conformite);
    dol_fiche_head($head, 'card', $langs->trans("Simulation"), -2, 'simul@financement');

    $affaire = new TFin_affaire;
    $financement = new TFin_financement;
    $grille = new TFin_grille_leaser();
    $html = new Form($db);
    $form = new TFormCore($_SERVER['PHP_SELF'].'#calculateur', 'formSimulation', 'POST'); //,FALSE,'onsubmit="return soumettreUneSeuleFois(this);"'
    $form->Set_typeaff($mode);

    $fk_simu_cristal = GETPOST('fk_simu_cristal');
    $fk_projet_cristal = GETPOST('fk_projet_cristal');

    $ent = empty($simulation->entity) ? $conf->entity : $simulation->entity;

    echo $form->hidden('id', $simulation->getId());
    echo $form->hidden('action', 'save');
    echo $form->hidden('fk_soc', $simulation->fk_soc);
    echo $form->hidden('entity', $ent);
    echo $form->hidden('idLeaser', FIN_LEASER_DEFAULT);
    echo $form->hidden('mode', $mode);
    echo $form->hidden('fk_simu_cristal', empty($fk_simu_cristal) ? $simulation->fk_simu_cristal : $fk_simu_cristal);
    echo $form->hidden('fk_projet_cristal', empty($fk_projet_cristal) ? $simulation->fk_projet_cristal : $fk_projet_cristal);

    $TBS = new TTemplateTBS();
    $ATMdb = new TPDOdb;

    dol_include_once('/core/class/html.formfile.class.php');
    $formfile = new FormFile($db);
    $filename = dol_sanitizeFileName($simulation->getRef());
    $filedir = $simulation->getFilePath();

    $TDuree = (array)$grille->get_duree($ATMdb, FIN_LEASER_DEFAULT, $simulation->fk_type_contrat, $simulation->opt_periodicite, $simulation->entity);
    $can_preco = ($user->rights->financement->allsimul->simul_preco && $simulation->fk_soc > 0) ? 1 : 0;

    // 2017.01.04 MKO : simulation modifiable par un admin ou si pas de préco ou demande sur un leaser catégorie Cession
    $can_modify = 0;
    if(! empty($user->rights->financement->admin->write)) $can_modify = 1;
    if($simulation->modifiable > 0) $can_modify = 1;

    // Chargement des groupes configurés dans multi entité
    $TGroupEntity = unserialize($conf->global->MULTICOMPANY_USER_GROUP_ENTITY);
    $TGroupEntities = array();

    if(! empty($TGroupEntity)) {
        foreach($TGroupEntity as $tab) {
            $g = new UserGroup($db);
            if(! in_array($tab['group_id'], array_keys($TGroupEntities))) {
                $g->fetch($tab['group_id']);
                if($g->id > 0) $TGroupEntities[$tab['group_id']] = "'".$g->name."'";
            }
        }
    }

    if($user->rights->financement->admin->write && ($mode == "add" || $mode == "new" || $mode == "edit")) {
        $formdolibarr = new Form($db);
        $rachat_autres = "texte";
        $TUserInclude = array();

        $sql = "SELECT u.rowid 
				FROM ".MAIN_DB_PREFIX."user as u 
					LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user as ugu ON (ugu.fk_user = u.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."usergroup as ug ON (ug.rowid = ugu.fk_usergroup)
				WHERE ug.nom IN (".implode(',', $TGroupEntities).") ";

        $TUserInclude = TRequeteCore::_get_id_by_sql($ATMdb, $sql);

        $link_user = $formdolibarr->select_dolusers($simulation->fk_user_author, 'fk_user_author', 1, '', 0, $TUserInclude, '', getEntity('fin_simulation', 1));

        $TUserInclude = TRequeteCore::_get_id_by_sql($ATMdb, "SELECT u.rowid 
															FROM ".MAIN_DB_PREFIX."user as u 
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup_user as ugu ON (ugu.fk_user = u.rowid)
																LEFT JOIN ".MAIN_DB_PREFIX."usergroup as ug ON (ug.rowid = ugu.fk_usergroup)
															WHERE ug.nom = 'GSL_DOLIBARR_FINANCEMENT_ADMIN'");

        $link_user_suivi = $formdolibarr->select_dolusers($simulation->fk_user_suivi, 'fk_user_suivi', 1, '', 0, $TUserInclude, '', $conf->entity);
    }
    else {
        $rachat_autres = "texteRO";
        $link_user = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$simulation->fk_user_author.'">'.img_picto('', 'object_user.png', '', 0).' '.$simulation->user->login.'</a>';
        $link_user_suivi = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.$simulation->fk_user_suivi.'">'.img_picto('', 'object_user.png', '', 0).' '.$simulation->user_suivi->login.'</a>';
    }

    $e = new DaoMulticompany($db);
    $e->getEntities();
    $TEntities = array();
    foreach($e->entities as $obj_entity) $TEntities[$obj_entity->id] = $obj_entity->label;

    $entity = empty($simulation->entity) ? getEntity('fin_dossier', false) : $simulation->entity;

    $TEntityName = TFinancementTools::build_array_entities();
    if(TFinancementTools::user_courant_est_admin_financement() && empty($conf->global->FINANCEMENT_DISABLE_SELECT_ENTITY)) {
        $entity_field = $form->combo('', 'entity_partenaire', $TEntityName, $entity);   // select entities
    }
    else {
        $entity_field = $TEntityName[$entity].$form->hidden('entity_partenaire', $entity);  // NAME<input type="hidden" .../>
    }

    $id_dossier = $simulation->fk_fin_dossier;
    if(empty($id_dossier)) $link_dossier = $simulation->numero_accord;
    else $link_dossier = '<a href="'.dol_buildpath('/financement/dossier.php?id='.$id_dossier, 2).'" >'.$simulation->numero_accord.'</a>';

    $TOptCalageLabel = array('' => '', '0M' => '0 mois', '1M' => '1 mois', '2M' => '2 mois', '3M' => '3 mois', '4M' => '4 mois', '5M' => '5 mois');

    /**
     * Calcul à la volé pour connaitre le coef en fonction de la périodicité
     */
    $tempCoeff = $simulation->coeff;

    if($simulation->opt_periodicite == 'MOIS') $coeff = $tempCoeff / 3;
    else if($simulation->opt_periodicite == 'SEMESTRE') $coeff = $tempCoeff * 2;
    else if($simulation->opt_periodicite == 'ANNEE') $coeff = $tempCoeff * 4;
    else $coeff = $tempCoeff; // TRIMESTRE

    if($simulation->montant_decompte_copies_sup < 0) $simulation->montant_decompte_copies_sup = 0;

    $accordIcon = (! empty($simulation->accord)) ? get_picto($simulation->TStatutIcons[$simulation->accord]) : '';

    // Retrait copie uniquement à afficher pour Cpro impression
    $display_retrait_copie = 0;
    if(empty($simulation->entity) && $conf->entity == 1 || $simulation->entity == 1) {
        $display_retrait_copie = 1;
    }

    // Récupération des dossiers en cours pour sélection si adjonction
    $selectDossierAdjonction = TFin_dossier::getListeDossierClient($ATMdb, $simulation->fk_soc, $simulation->societe->idprof1);

    // Le label doit aussi changer dans le simulateur
    if(in_array($simulation->entity, array(18, 25)) || empty($simulation->entity) && in_array($conf->entity, array(18, 25))) {
        $dateLabel = $langs->trans('DateDemarrageCustom');
    }
    else $dateLabel = $langs->trans('DateDemarrage');

    $cristalLink = '';
    if(! empty($simulation->fk_simu_cristal)) {
        $cristalLink .= '<a href="http://cpro.cristal/simulation/'.$simulation->fk_simu_cristal.'/calcul">'.$simulation->fk_projet_cristal.'</a>';
    }
    else if(! empty($simulation->fk_projet_cristal)) {
        $cristalLink .= '<a href="http://cpro.cristal/projet/'.$simulation->fk_projet_cristal.'">'.$simulation->fk_projet_cristal.'</a>';
    }

    $simuArray = array(
        'titre_simul' => load_fiche_titre($langs->trans("CustomerInfo"), '', 'object_company.png'),
        'titre_calcul' => load_fiche_titre($langs->trans("Simulator"), '', 'object_simul.png@financement'),
        'titre_dossier' => load_fiche_titre($langs->trans("DossierList"), '', 'object_financementico.png@financement'),
        'id' => $simulation->rowid,
        'entity' => $entity_field,
        'ref' => $simulation->reference,
        'cristal_project' => $simulation->fk_projet_cristal,
        'cristal_link' => $cristalLink,
        'doc' => ($simulation->getId() > 0) ? $formfile->getDocumentsLink('financement', $filename, $filedir, '\.pdf$') : '',
        'fk_soc' => $simulation->fk_soc,
        'fk_type_contrat' => $form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat).(! empty($simulation->modifs['fk_type_contrat']) ? ' (Ancienne valeur : '.$affaire->TContrat[$simulation->modifs['fk_type_contrat']].')' : ''),
        'opt_administration' => $form->checkbox1('', 'opt_administration', 1, $simulation->opt_administration),
        'opt_adjonction' => $form->checkbox1('', 'opt_adjonction', 1, $simulation->opt_adjonction),
        'fk_fin_dossier_adjonction' => empty($selectDossierAdjonction) ? '' : $form->combo('', 'fk_fin_dossier_adjonction', $selectDossierAdjonction, $simulation->fk_fin_dossier_adjonction, 1, '', '', 'flat', '', 'false', 1),
        'adjonction_ok' => ! empty($selectDossierAdjonction) ? 1 : 0,
        'opt_periodicite' => $form->combo('', 'opt_periodicite', $financement->TPeriodicite, $simulation->opt_periodicite).(! empty($simulation->modifs['opt_periodicite']) ? ' (Ancienne valeur : '.$financement->TPeriodicite[$simulation->modifs['opt_periodicite']].')' : ''),
        'opt_mode_reglement' => $form->combo('', 'opt_mode_reglement', $financement->TReglement, $simulation->opt_mode_reglement).(! empty($simulation->modifs['opt_mode_reglement']) ? ' (Ancienne valeur : '.$financement->TReglement[$simulation->modifs['opt_mode_reglement']].')' : ''),
        'opt_calage_label' => $form->combo('', 'opt_calage_label', $TOptCalageLabel, $simulation->opt_calage, 0, '', TFinancementTools::user_courant_est_admin_financement() ? '' : 'disabled'),
        'opt_calage' => $form->hidden('opt_calage', $simulation->opt_calage),
        'opt_terme' => $form->combo('', 'opt_terme', $financement->TTerme, $simulation->opt_terme).(! empty($simulation->modifs['opt_terme']) ? ' (Ancienne valeur : '.$financement->TTerme[$simulation->modifs['opt_terme']].')' : ''),
        'date_demarrage' => $form->calendrier('', 'date_demarrage', $simulation->get_date('date_demarrage'), 12),
        'date_demarrage_label' => $dateLabel,
        'montant' => $form->texte('', 'montant', $simulation->montant, 10).(! empty($simulation->modifs['montant']) ? ' (Ancienne valeur : '.$simulation->modifs['montant'].')' : ''),
        'montant_rachete' => $form->texteRO('', 'montant_rachete', $simulation->montant_rachete, 10),
        'montant_decompte_copies_sup' => $form->texteRO('', 'montant_decompte_copies_sup', $simulation->montant_decompte_copies_sup, 10),
        'display_retraitcopie' => $display_retrait_copie,
        'montant_rachat_final' => $form->texteRO('', 'montant_rachat_final', $simulation->montant_rachat_final, 10),
        'montant_rachete_concurrence' => $form->texte('', 'montant_rachete_concurrence', $simulation->montant_rachete_concurrence, 10),
        'duree' => $form->combo('', 'duree', $TDuree, $simulation->duree).(! empty($simulation->modifs['duree']) ? ' (Ancienne valeur : '.$TDuree[$simulation->modifs['duree']].')' : ''),
        'echeance' => $form->texte('', 'echeance', $simulation->echeance, 10).(! empty($simulation->modifs['echeance']) ? ' (Ancienne valeur : '.$simulation->modifs['echeance'].')' : ''),
        'vr' => price($simulation->vr),
        'coeff' => $form->texteRO('', 'coeff', $coeff, 6).(! empty($simulation->modifs['coeff']) ? ' (Ancienne valeur : '.$simulation->modifs['coeff'].')' : ''),
        'coeff_final' => ($can_preco ? $form->texte('', 'coeff_final', $simulation->coeff_final, 6) : $simulation->coeff_final).(! empty($simulation->modifs['coeff_final']) ? ' (Ancienne valeur : '.$simulation->modifs['coeff_final'].')' : ''),
        'montant_presta_trim' => $form->texte('', 'montant_presta_trim', $simulation->montant_presta_trim, 10).(! empty($simulation->modifs['montant_presta_trim']) ? ' (Ancienne valeur : '.$simulation->modifs['montant_presta_trim'].')' : ''),
        'cout_financement' => $simulation->cout_financement,
        'accord' => $accordIcon.'<br />'.($user->rights->financement->allsimul->simul_preco ? $form->combo('', 'accord', $simulation->TStatut, $simulation->accord) : $simulation->TStatut[$simulation->accord]).'<br>',
        'can_resend_accord' => $simulation->accord,
        'date_validite' => $simulation->accord == 'OK' ? 'Validité : '.$simulation->get_date('date_validite') : '',
        'commentaire' => $form->zonetexte('', 'commentaire', $mode == 'edit' ? $simulation->commentaire : nl2br($simulation->commentaire), 50, 3),
        'accord_confirme' => $simulation->accord_confirme,
        'total_financement' => $simulation->montant_total_finance,
        'type_materiel' => $form->texte('', 'type_materiel', $simulation->type_materiel, 50).(! empty($simulation->modifs['type_materiel']) ? ' (Ancienne valeur : '.$simulation->modifs['type_materiel'].')' : ''),
        'numero_accord' => ($can_preco && GETPOST('action') == 'edit') ? $form->texte('', 'numero_accord', $simulation->numero_accord, 20) : $link_dossier,
        'attente' => $simulation->get_attente($ATMdb, ($action == 'calcul' ? 1 : 0)),
        'attente_style' => (empty($simulation->attente_style)) ? 'none' : $simulation->attente_style,
        'no_case_to_settle' => $form->checkbox1('', 'opt_no_case_to_settle', 1, $simulation->opt_no_case_to_settle),
        'accord_val' => $simulation->accord,
        'can_preco' => $can_preco,
        'can_modify' => $can_modify,
        'user' => $link_user,
        'user_suivi' => $link_user_suivi,
        'date' => $simulation->date_simul,
        'bt_calcul' => $form->btsubmit('Calculer', 'calculate'),
        'bt_cancel' => $form->btsubmit('Annuler', 'cancel'),
        'bt_save' => $form->btsubmit('Enregistrer simulation', 'validate_simul'),
        'display_preco' => $can_preco,
        'type_financement' => $can_preco ? $form->combo('', 'type_financement', array_merge(array('' => ''), $affaire->TTypeFinancement), $simulation->type_financement) : $simulation->type_financement,
        'leaser' => ($mode == 'edit' && $can_preco) ? $html->select_company($simulation->fk_leaser, 'fk_leaser', 'fournisseur=1', 1, 0, 1) : (($simulation->fk_leaser > 0) ? $simulation->leaser->getNomUrl(1) : ''),
        'pct_vr' => ($mode == 'edit') ? '<input name="pct_vr" type="number" value="'.$simulation->pct_vr.'" min="0" max="100" '.(TFinancementTools::user_courant_est_admin_financement() ? '' : 'readonly').' />' : $simulation->pct_vr,
        'mt_vr' => $form->texte('', 'mt_vr', price2num($simulation->mt_vr), 10),
        'info_vr' => $html->textwithpicto('', $langs->transnoentities('simulation_info_vr'), 1, 'info', '', 0, 3)
    );

    if($mode == 'edit_montant') {
        $mode = 'edit';
        $form->Set_typeaff($mode);

        $simuArray['montant'] = $form->texte('', 'montant', $simulation->montant, 10).(! empty($simulation->modifs['montant']) ? ' (Ancienne valeur : '.$simulation->modifs['montant'].')' : '');
        $simuArray['montant_rachete'] = $form->texteRO('', 'montant_rachete', $simulation->montant_rachete, 10);
        $simuArray['montant_decompte_copies_sup'] = $form->texteRO('', 'montant_decompte_copies_sup', $simulation->montant_decompte_copies_sup, 10);
        $simuArray['montant_rachat_final'] = $form->texteRO('', 'montant_rachat_final', $simulation->montant_rachat_final, 10);
        $simuArray['montant_rachete_concurrence'] = $form->texte('', 'montant_rachete_concurrence', $simulation->montant_rachete_concurrence, 10);
        $simuArray['echeance'] = $form->texte('', 'echeance', $simulation->echeance, 10).(! empty($simulation->modifs['echeance']) ? ' (Ancienne valeur : '.$simulation->modifs['echeance'].')' : '');
        $simuArray['montant_presta_trim'] = $form->texte('', 'montant_presta_trim', $simulation->montant_presta_trim, 10).(! empty($simulation->modifs['montant_presta_trim']) ? ' (Ancienne valeur : '.$simulation->modifs['montant_presta_trim'].')' : '');
        $simuArray['type_materiel'] = $form->texte('', 'type_materiel', $simulation->type_materiel, 50).(! empty($simulation->modifs['type_materiel']) ? ' (Ancienne valeur : '.$simulation->modifs['type_materiel'].')' : '');
        $simuArray['opt_periodicite'] = $form->combo('', 'opt_periodicite', $financement->TPeriodicite, $simulation->opt_periodicite).(! empty($simulation->modifs['opt_periodicite']) ? ' (Ancienne valeur : '.$financement->TPeriodicite[$simulation->modifs['opt_periodicite']].')' : '');
        $simuArray['duree'] = $form->combo('', 'duree', $TDuree, $simulation->duree).(! empty($simulation->modifs['duree']) ? ' (Ancienne valeur : '.$TDuree[$simulation->modifs['duree']].')' : '');
        $simuArray['fk_type_contrat'] = $form->combo('', 'fk_type_contrat', array_merge(array(''), $affaire->TContrat), $simulation->fk_type_contrat).(! empty($simulation->modifs['fk_type_contrat']) ? ' (Ancienne valeur : '.$affaire->TContrat[$simulation->modifs['fk_type_contrat']].')' : '');
        $simuArray['opt_mode_reglement'] = $form->combo('', 'opt_mode_reglement', $financement->TReglement, $simulation->opt_mode_reglement).(! empty($simulation->modifs['opt_mode_reglement']) ? ' (Ancienne valeur : '.$financement->TReglement[$simulation->modifs['opt_mode_reglement']].')' : '');
        $simuArray['opt_terme'] = $form->combo('', 'opt_terme', $financement->TTerme, $simulation->opt_terme).(! empty($simulation->modifs['opt_terme']) ? ' (Ancienne valeur : '.$financement->TTerme[$simulation->modifs['opt_terme']].')' : '');
        $simuArray['coeff'] = $form->texteRO('', 'coeff', $coeff, 6).(! empty($simulation->modifs['coeff']) ? ' (Ancienne valeur : '.$simulation->modifs['coeff'].')' : '');

        if(in_array($conf->entity, array(13, 14))) { // BCMP, PERRET ont droit de modifier le calage
            $simuArray['date_demarrage'] = $form->calendrier('', 'date_demarrage', $simulation->get_date('date_demarrage'), 12);
            $simuArray['opt_calage_label'] = $form->combo('', 'opt_calage_label', $TOptCalageLabel, $simulation->opt_calage, 0, '', TFinancementTools::user_courant_est_admin_financement() ? '' : 'disabled');
            $simuArray['opt_calage'] = $form->hidden('opt_calage', $simulation->opt_calage);
        }
    }

    if(in_array($conf->entity, array(20, 21, 22, 23, 24))) { // Pas de calage pour les entité 20 à 24
        $mode = 'view';
        $form->Set_typeaff($mode);
        $simuArray['date_demarrage'] = $form->calendrier('', 'date_demarrage', $simulation->get_date('date_demarrage'), 12);
        $simuArray['opt_calage_label'] = $form->combo('', 'opt_calage_label', $TOptCalageLabel, $simulation->opt_calage, 0, '', TFinancementTools::user_courant_est_admin_financement() ? '' : 'disabled');
        $simuArray['opt_calage'] = $form->hidden('opt_calage', $simulation->opt_calage);
        $mode = 'edit';
        $form->Set_typeaff($mode);
    }

    if(TFinancementTools::user_courant_est_admin_financement()) {
        $simuArray['accord'] .= '<br />';
        foreach($simulation->TStatutIcons as $k => $icon) {
            if($k !== $simulation->accord) $simuArray['accord'] .= '<a href="'.$_SERVER['PHP_SELF'].'?id='.$simulation->id.'&action=changeAccord&accord='.$k.'">'.get_picto($icon, 'Changer vers '.$simulation->TStatut[$k]).'</a>&nbsp;&nbsp;';
        }
    }
    // Recherche par SIREN
    $search_by_siren = true;
    if(! empty($simulation->societe->array_options['options_no_regroup_fin_siren'])) {
        $search_by_siren = false;
    }

    $siret = ($simulation->accord == 'OK' && ! empty($simulation->thirdparty_idprof2_siret)) ? $simulation->thirdparty_idprof2_siret : $simulation->societe->idprof2;
    $siren = substr($siret, 0, 9);
    $siretlink = '<a target="_blank" href="https://portail.infolegale.fr/identity/'.$siren.'">'.$siret.'</a>';

    print $TBS->render('./tpl/simulation.tpl.php'
        , array()
        , array(
            'simulation' => $simuArray
            , 'client' => array(
                'societe' => '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$simulation->fk_soc.'">'.img_picto('', 'object_company.png', '', 0).' '.(! empty($simulation->thirdparty_name) ? $simulation->thirdparty_name : $simulation->societe->nom).'</a>'
                , 'autres_simul' => '<a href="'.DOL_URL_ROOT.'/custom/financement/simulation/simulation.php?socid='.$simulation->fk_soc.'">(autres simulations)</a>'
                , 'adresse' => ($simulation->accord == 'OK' && ! empty($simulation->thirdparty_address)) ? $simulation->thirdparty_address : $simulation->societe->address
                , 'cpville' => (($simulation->accord == 'OK' && ! empty($simulation->thirdparty_zip)) ? $simulation->thirdparty_zip : $simulation->societe->zip).' / '.(($simulation->accord == 'OK' && ! empty($simulation->thirdparty_town)) ? $simulation->thirdparty_town : $simulation->societe->town)
                , 'siret' => $siretlink
                , 'naf' => ($simulation->accord == 'OK' && ! empty($simulation->thirdparty_idprof3_naf)) ? $simulation->thirdparty_idprof3_naf : $simulation->societe->idprof3
                , 'code_client' => ($simulation->accord == 'OK' && ! empty($simulation->thirdparty_code_client)) ? $simulation->thirdparty_code_client : $simulation->societe->code_client
                , 'display_score' => $user->rights->financement->score->read ? 1 : 0
                , 'score_date' => empty($simulation->societe) ? '' : $simulation->societe->score->get_date('date_score')
                , 'score' => empty($simulation->societe) ? '' : $simulation->societe->score->score
                , 'encours_cpro' => empty($simulation->societe) ? 0 : $simulation->societe->encours_cpro
                , 'encours_conseille' => empty($simulation->societe) ? '' : $simulation->societe->score->encours_conseille

                , 'contact_externe' => empty($simulation->societe) ? '' : $simulation->societe->score->get_nom_externe()

                , 'liste_dossier' => _liste_dossier($ATMdb, $simulation, $mode, $search_by_siren)

                , 'nom' => $simulation->societe->nom
                , 'siren' => (($simulation->societe->idprof1) ? $simulation->societe->idprof1 : $simulation->societe->idprof2)
            )
            , 'view' => array(
                'mode' => $mode
                , 'type' => ($simulation->fk_soc > 0) ? 'simul' : 'calcul'
                , 'calcul' => empty($simulation->montant_total_finance) ? 0 : 1
                , 'pictoMail' => img_picto('', 'stcomm0.png', '', 0)
            )

            , 'user' => $simulation->user

        ),
        array(),
        array('charset' => 'utf-8')
    );
    // End of page

    if($user->rights->financement->allsimul->suivi_leaser) {
        _fiche_suivi($ATMdb, $simulation, $mode);
    }

    $refus_moins_6mois = $simulation->hasOtherSimulationRefused($ATMdb);
    if($refus_moins_6mois) {
        setEventMessage('Ce client a eu une demande de fi refusée il y a moins de 6 mois', 'errors');
    }

    $simu_moins_30jours = $simulation->hasOtherSimulation($ATMdb);
    if($simu_moins_30jours) {
        setEventMessage('Ce client a déjà une demande de fi de moins de 30 jours', 'warnings');
    }

    global $mesg, $error;
    dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
}

function _fiche_suivi(&$ATMdb, TSimulation &$simulation, $mode) {
    global $conf, $db, $langs;

    $form = new TFormCore($_SERVER['PHP_SELF'].'#suivi_leaser', 'form_suivi_simulation', 'POST');
    $form->Set_typeaff('edit');

    echo $form->hidden('action', 'save_suivi');
    echo $form->hidden('id', $simulation->getId());
    $TLignes = $simulation->get_suivi_simulation($ATMdb, $form);
    $TLigneHistorized = $simulation->get_suivi_simulation($ATMdb, $form, true);

    $TBS = new TTemplateTBS;
    print $TBS->render('./tpl/simulation_suivi.tpl.php',
        array(
            'ligne' => $TLignes,
            'TLigneHistorized' => $TLigneHistorized
        ),
        array(
            'view' => array(
                'mode' => $mode,
                'type' => ($simulation->fk_soc > 0) ? 'simul' : 'calcul',
                'titre' => load_fiche_titre($langs->trans("SimulationSuivi"), '', 'object_simul.png@financement'),
                'titre_history' => load_fiche_titre($langs->trans("SimulationSuiviHistory"), '', 'object_simul.png@financement')
            ),
            'formDolibarr' => $formDolibarr    // FIXME: Undefined variable $formDolibarr
        )
    );

    $form->end_form();
}

function _liste_dossier(&$ATMdb, TSimulation &$simulation, $mode, $search_by_siren = true) {
    global $langs, $conf, $db, $bc, $user, $serialNumber;
    $r = new TListviewTBS('dossier_list', './tpl/simulation.dossier.tpl.php');

    $sql = "SELECT a.rowid as 'IDAff', a.reference as 'N° affaire', e.rowid as 'entityDossier', a.contrat as 'Type contrat'";
    $sql .= " , d.rowid as 'IDDoss'";
    $sql .= " FROM ".MAIN_DB_PREFIX."fin_affaire a ";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON da.fk_fin_affaire = a.rowid";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON d.rowid = da.fk_fin_dossier";
    $sql .= ' INNER JOIN '.MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX.'entity e ON (e.rowid = d.entity) ';
    $sql .= ' WHERE a.entity IN('.getEntity('fin_dossier', true).')';
    $sql .= " AND dflea.reference <> '' AND dflea.reference IS NOT NULL";
    $sql .= " AND (a.fk_soc = ".$simulation->fk_soc;
    if(! empty($simulation->societe->idprof1) && $search_by_siren) {
        $sql .= " OR a.fk_soc IN
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
    $sql .= " )";
    $sql .= " ORDER BY e.rowid ASC";

    $TDossier = array();
    $form = new TFormCore;
    $form->Set_typeaff($mode);
    $ATMdb->Execute($sql);
    $ATMdb2 = new TPDOdb;
    $var = true;

    // 2017.04.14 MKO : on ne vérifie plus si un dossier est déjà utilisé dans une autre simul
    $TDossierUsed = array();

    while($ATMdb->Get_line()) {
        $searchedBySerialNumber = false;

        $idDoss = $ATMdb->Get_field('IDDoss');
        $affaire = new TFin_affaire;
        $dossier = new TFin_Dossier;
        $dossier->load($ATMdb2, $idDoss);
        $leaser = new Societe($db);
        $leaser->fetch($dossier->financementLeaser->fk_soc);

        // Chargement des équipements
        if(! empty($dossier->TLien[0])) {
            dol_include_once('/asset/class/asset.class.php');
            $dossier->TLien[0]->affaire->loadEquipement($ATMdb2);
            $TSerial = array();

            foreach($dossier->TLien[0]->affaire->TAsset as $linkAsset) {
                $serial = $linkAsset->asset->serial_number;
                if(! empty($serialNumber) && $serialNumber == $serial) $searchedBySerialNumber = true; // Champ définis dynamiquement signifiant qu'on a cherché ce matricule via la recherche
                $TSerial[] = $serial;
                if(count($TSerial) >= 3) {
                    $TSerial[] = '...';
                    break;
                }
            }
        }

        if($dossier->nature_financement == 'INTERNE') {
            $fin = &$dossier->financement;
        }
        else {
            $fin = &$dossier->financementLeaser;
        }
        $dossierRachete = '';
        foreach($simulation->DossierRachete as $dr) {
            if($dr->fk_dossier == $idDoss) {
                $dossierRachete = $dr;
                break;
            }
        }
        if($fin->date_solde > 0 && $fin->date_solde < time() && empty($dossierRachete->choice)) continue;
        if(empty($dossier->financementLeaser->reference)) continue;

        // On vérifie si le solde du dossier doit être affiché ou non
        $display_solde = $dossier->get_display_solde();

        if($dossier->nature_financement == 'INTERNE') {
            if($simulation->rowid > 0) {
                $soldeRM1 = (! empty($dossierRachete->solde_vendeur_m1)) ? $dossierRachete->solde_vendeur_m1 : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financement->numero_prochaine_echeance - 2), 2); //SRCPRO
                $soldeR = (! empty($dossierRachete->solde_vendeur)) ? $dossierRachete->solde_vendeur : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financement->numero_prochaine_echeance - 1), 2); //SRCPRO
                $soldeR1 = (! empty($dossierRachete->solde_vendeur_p1)) ? $dossierRachete->solde_vendeur_p1 : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financement->numero_prochaine_echeance), 2); //SRCPRO
            }
            else {  // Correction du simulateur qui ne gardait pas les dossiers cochés après avoir cliqué sur "Calculer"
                $soldeRM1 = (! empty($simulation->dossiers_rachetes_m1[$idDoss]['montant'])) ? $simulation->dossiers_rachetes_m1[$idDoss]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financement->numero_prochaine_echeance - 2), 2); //SRCPRO
                $soldeR = (! empty($simulation->dossiers_rachetes[$idDoss]['montant'])) ? $simulation->dossiers_rachetes[$idDoss]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financement->numero_prochaine_echeance - 1), 2); //SRCPRO
                $soldeR1 = (! empty($simulation->dossiers_rachetes_p1[$idDoss]['montant'])) ? $simulation->dossiers_rachetes_p1[$idDoss]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financement->numero_prochaine_echeance), 2);
            }
            $soldeperso = round($dossier->getSolde($ATMdb2, 'perso'), 2);
        }
        else {
            if($simulation->id > 0) {
                $soldeRM1 = (! empty($dossierRachete->solde_vendeur_m1)) ? $dossierRachete->solde_vendeur_m1 : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance - 2), 2);
                $soldeR = (! empty($dossierRachete->solde_vendeur)) ? $dossierRachete->solde_vendeur : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance - 1), 2);
                $soldeR1 = (! empty($dossierRachete->solde_vendeur_p1)) ? $dossierRachete->solde_vendeur_p1 : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance), 2);
            }
            else {
                $soldeRM1 = (! empty($simulation->dossiers_rachetes_m1[$idDoss]['montant'])) ? $simulation->dossiers_rachetes_m1[$idDoss]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance - 2), 2);
                $soldeR = (! empty($simulation->dossiers_rachetes[$idDoss]['montant'])) ? $simulation->dossiers_rachetes[$idDoss]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance - 1), 2);
                $soldeR1 = (! empty($simulation->dossiers_rachetes_p1[$idDoss]['montant'])) ? $simulation->dossiers_rachetes_p1[$idDoss]['montant'] : round($dossier->getSolde($ATMdb2, 'SRCPRO', $dossier->financementLeaser->numero_prochaine_echeance), 2);
            }
            $soldeperso = round($dossier->getSolde($ATMdb2, 'perso'), 2);
        }

        if($display_solde < 0) {
            $display_solde = 0;
            $soldeR = 0;
            $soldeR1 = 0;
            $soldeperso = 0;
        }

        $soldeperso = $dossier->calculSoldePerso($ATMdb2);

        // Obligé de mettre les 2 tests car les dossiers rachetés ne sont pas encore créés quand on clique sur "Calculer" dans le simulateur
        $checkedrm1 = ! isset($_REQUEST['calculate']) && $dossierRachete->choice == 'prev' || isset($_REQUEST['calculate']) && ! empty($simulation->dossiers_rachetes_m1[$idDoss]['checked']);
        $checkbox_moreRM1 = 'solde="'.$soldeRM1.'" style="display: none;"';
        $checkbox_moreRM1 .= (in_array($idDoss, $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';

        // Obligé de mettre les 2 tests car les dossiers rachetés ne sont pas encore créés quand on clique sur "Calculer" dans le simulateur
        $checkedr = ! isset($_REQUEST['calculate']) && $dossierRachete->choice == 'curr' || isset($_REQUEST['calculate']) && ! empty($simulation->dossiers_rachetes[$idDoss]['checked']);
        $checkbox_moreR = 'solde="'.$soldeR.'" style="display: none;"';
        $checkbox_moreR .= (in_array($idDoss, $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';

        // Obligé de mettre les 2 tests car les dossiers rachetés ne sont pas encore créés quand on clique sur "Calculer" dans le simulateur
        $checkedr1 = ! isset($_REQUEST['calculate']) && $dossierRachete->choice == 'next' || isset($_REQUEST['calculate']) && ! empty($simulation->dossiers_rachetes_p1[$idDoss]['checked']);
        $checkbox_moreR1 = 'solde="'.$soldeR1.'" style="display: none;"';
        $checkbox_moreR1 .= (in_array($idDoss, $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';

        $checkbox_moreperso = 'solde="'.$soldeperso.'" style="display: none;"';
        $checkbox_moreperso .= (in_array($idDoss, $TDossierUsed)) ? ' readonly="readonly" disabled="disabled" title="Dossier déjà utilisé dans une autre simulation pour ce client" ' : '';

        $date_echeance_prochaine = ($dossierRachete->date_prochaine_echeance) ? $dossierRachete->date_prochaine_echeance : $fin->date_prochaine_echeance;
        $date_echeance_prochaine_fin = strtotime('-1day', $dossier->_add_month($fin->getiPeriode(), $date_echeance_prochaine));
        $date_echeance_en_cours = $dossier->_add_month(-1 * $fin->getiPeriode(), $date_echeance_prochaine);
        $date_echeance_en_cours_fin = strtotime('-1day', $dossier->_add_month($fin->getiPeriode(), $date_echeance_en_cours));
        $date_echeance_precedente = $dossier->_add_month(-1 * $fin->getiPeriode(), $date_echeance_en_cours);
        $date_echeance_precedente_fin = strtotime('-1day', $dossier->_add_month($fin->getiPeriode(), $date_echeance_precedente));

        $TEntityName = TFinancementTools::build_array_entities();
        $numcontrat_entity_leaser = ($dossierRachete->num_contrat) ? $dossierRachete->num_contrat : $fin->reference;
        $numcontrat_entity_leaser = '<a href="'.dol_buildpath('/financement/dossier.php', 1).'?id='.$idDoss.'">'.$numcontrat_entity_leaser.'</a> / '.$TEntityName[$ATMdb->Get_field('entityDossier')];
        $numcontrat_entity_leaser .= '<br>'.$leaser->getNomUrl(0);

        $rowClass = '';
        if($searchedBySerialNumber) $rowClass .= 'class="highlight"';

        $row = array(
            'id_affaire' => $ATMdb->Get_field('IDAff'),
            'num_affaire' => $ATMdb->Get_field('N° affaire'),
            'entityDossier' => $ATMdb->Get_field('entityDossier'),
            'id_dossier' => $dossier->getId(),
            'num_contrat' => ($dossierRachete->num_contrat) ? $dossierRachete->num_contrat : $fin->reference,
            'type_contrat' => ($dossierRachete->type_contrat) ? $affaire->TContrat[$dossierRachete->type_contrat] : $affaire->TContrat[$ATMdb->Get_field('Type contrat')],
            'duree' => ($dossierRachete->duree) ? $dossierRachete->duree : $fin->duree.' '.substr($fin->periodicite, 0, 1),
            'echeance' => ($dossierRachete->echeance) ? $dossierRachete->echeance : $fin->echeance,
            'loyer_actualise' => ($dossier->nature_financement == 'INTERNE') ? ($dossierRachete->loyer_actualise) ? $dossierRachete->loyer_actualise : $fin->loyer_actualise : '',
            'debut' => ($dossierRachete->date_debut) ? $dossierRachete->date_debut : $fin->date_debut,
            'fin' => ($dossierRachete->date_fin) ? $dossierRachete->date_fin : $fin->date_fin,
            'prochaine_echeance' => $date_echeance_prochaine,
            'avancement' => ($dossierRachete->numero_prochaine_echeance) ? $dossierRachete->numero_prochaine_echeance : $fin->numero_prochaine_echeance.'/'.$fin->duree,
            'terme' => ($dossierRachete->terme) ? $dossierRachete->terme : $fin->TTerme[$fin->terme],
            'reloc' => ($dossierRachete->reloc) ? $dossierRachete->reloc : $fin->reloc,
            'solde_rm1' => $soldeRM1,
            'date_echeance_precedente' => date('d/m/y', $date_echeance_precedente),
            'date_echeance_precedente_fin' => date('d/m/y', $date_echeance_precedente_fin),
            'hidden_date_deb_echeance_prev' => $form->hidden('dossiers_rachetes_m1['.$idDoss.'][date_deb_echeance]', date('Y-m-d', $date_echeance_precedente)),
            'hidden_date_fin_echeance_prev' => $form->hidden('dossiers_rachetes_m1['.$idDoss.'][date_fin_echeance]', date('Y-m-d', $date_echeance_precedente_fin)),
            'solde_r' => $soldeR,
            'date_echeance_en_cours' => date('d/m/y', $date_echeance_en_cours),
            'date_echeance_en_cours_fin' => date('d/m/y', $date_echeance_en_cours_fin),
            'hidden_date_deb_echeance_curr' => $form->hidden('dossiers_rachetes['.$idDoss.'][date_deb_echeance]', date('Y-m-d', $date_echeance_en_cours)),
            'hidden_date_fin_echeance_curr' => $form->hidden('dossiers_rachetes['.$idDoss.'][date_fin_echeance]', date('Y-m-d', $date_echeance_en_cours_fin)),
            'solde_r1' => $soldeR1,
            'date_echeance_prochaine' => date('d/m/y', $date_echeance_prochaine),
            'date_echeance_prochaine_fin' => date('d/m/y', $date_echeance_prochaine_fin),
            'hidden_date_deb_echeance_next' => $form->hidden('dossiers_rachetes_p1['.$idDoss.'][date_deb_echeance]', date('Y-m-d', $date_echeance_prochaine)),
            'hidden_date_fin_echeance_next' => $form->hidden('dossiers_rachetes_p1['.$idDoss.'][date_fin_echeance]', date('Y-m-d', $date_echeance_prochaine_fin)),
            'soldeperso' => $soldeperso,
            'display_solde' => $display_solde,
            'fk_user' => $ATMdb->Get_field('fk_user'),
            'user' => $ATMdb->Get_field('Utilisateur'),
            'leaser' => $leaser->getNomUrl(0),
            'choice_solde' => ($simulation->contrat == $ATMdb->Get_field('Type contrat')) ? 'solde_r' : 'solde_nr',
            'checkboxrm1' => ($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_m1['.$idDoss.'][checked]', $idDoss, $checkedrm1, $checkbox_moreRM1) : '',
            'checkboxr' => ($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes['.$idDoss.'][checked]', $idDoss, $checkedr, $checkbox_moreR) : '',
            'checkboxr1' => ($mode == 'edit') ? $form->checkbox1('', 'dossiers_rachetes_p1['.$idDoss.'][checked]', $idDoss, $checkedr1, $checkbox_moreR1) : '',
            'montantrm1' => ($mode == 'edit') ? $form->hidden('dossiers_rachetes_m1['.$idDoss.'][montant]', $soldeRM1, $checkbox_moreRM1) : '',
            'montantr' => ($mode == 'edit') ? $form->hidden('dossiers_rachetes['.$idDoss.'][montant]', $soldeR, $checkbox_moreR) : '',
            'montantr1' => ($mode == 'edit') ? $form->hidden('dossiers_rachetes_p1['.$idDoss.'][montant]', $soldeR1, $checkbox_moreR1) : '',
            'checkboxperso' => ($mode == 'edit') ? $form->hidden('dossiers_rachetes_perso['.$idDoss.']', $idDoss, $checkbox_moreperso) : '',
            'checkedrm1' => $checkedrm1,
            'checkedr' => $checkedr,
            'checkedr1' => $checkedr1,
            'maintenance' => ($dossierRachete->maintenance) ? $dossierRachete->maintenance : $fin->montant_prestation,
            'assurance' => ($dossierRachete->assurance) ? $dossierRachete->assurance : $fin->assurance,
            'assurance_actualise' => ($dossierRachete->assurance_actualise) ? $dossierRachete->assurance_actualise : $fin->assurance_actualise,
            'montant' => ($dossierRachete->montant) ? $dossierRachete->montant : $fin->montant,
            'class' => $rowClass,
            'numcontrat_entity_leaser' => $numcontrat_entity_leaser,
            'serial' => implode(', ', $TSerial)
        );

        if($row['type_contrat'] == 'Intégral') {
            $row['type_contrat'] = '<a href="'.dol_buildpath('/financement/dossier_integrale.php', 1).'?id='.$idDoss.'">Intégral</a>';
        }

        $TDossier[$dossier->getId()] = $row;

        $var = ! $var;
    }

    $THide = array('IDAff', 'IDDoss', 'fk_user', 'Type contrat');

    TFinancementTools::add_css();

    // Retrait copie uniquement à afficher pour Cpro impression
    $display_retrait_copie = 0;
    if(empty($simulation->entity) && $conf->entity == 1 || $simulation->entity == 1) {
        $display_retrait_copie = 1;
    }

    return $r->renderArray($ATMdb, $TDossier, array(
        'limit' => array(
            'page' => (isset($_REQUEST['page']) ? $_REQUEST['page'] : 0),
            'nbLine' => '150'
        ),
        'orderBy' => array(
            'num_affaire' => 'DESC'
        ),
        'link' => array(
            'num_affaire' => '<a href="'.dol_buildpath('/financement/affaire.php', 1).'?id=@id_affaire@">@val@</a>',
            'num_contrat' => '<a href="'.dol_buildpath('/financement/dossier.php', 1).'?id=@id_dossier@">@val@</a>',
            'user' => '<a href="'.DOL_URL_ROOT.'/user/card.php?id=@fk_user@">'.img_picto('', 'object_user.png', '', 0).' @val@</a>'
        ),
        'hide' => $THide,
        'type' => array('Début' => 'date', 'Fin' => 'date'),
        'liste' => array(
            'titre' => 'Liste des imports',
            'image' => img_picto('', 'import32.png@financement', '', 0),
            'picto_precedent' => img_picto('', 'back.png', '', 0),
            'picto_suivant' => img_picto('', 'next.png', '', 0),
            'noheader' => 0,
            'messageNothing' => "Il n'y a aucun dossier à afficher",
            'order_down' => img_picto('', '1downarrow.png', '', 0),
            'order_up' => img_picto('', '1uparrow.png', '', 0),
            'display_montant' => (! empty($user->rights->financement->admin->write)) ? 1 : 0,
            'display_retraitcopie' => $display_retrait_copie
        )
    ));
}

function _has_valid_simulations(&$ATMdb, $socid) {
    global $db;

    $simu = new TSimulation();
    $TSimulations = $simu->load_by_soc($ATMdb, $db, $socid);

    foreach($TSimulations as $simulation) {
        if($simulation->date_validite > dol_now()) {
            return true;
        }
    }
    return false;
}

function _simu_edit_link($simulId, $date) {
    if(! function_exists('get_picto')) dol_include_once('/financement/lib/financement.lib.php');

    if(strtotime($date) > dol_now()) {
        $return = '<a href="?id='.$simulId.'&action=edit">'.get_picto('edit').'</a>';
    }
    else {
        $return = '';
    }
    return $return;
}

