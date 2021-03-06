<?php
	set_time_limit(0);
	require('config.php');
	
	if (GETPOST('DEBUG'))
	{
		ini_set("display_errors", 1);
		error_reporting(E_ALL);
	}
	
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	require('./class/dossier_integrale.class.php');
	require('./class/grille.class.php');
	require('./lib/financement.lib.php');
	
	dol_include_once("/core/lib/company.lib.php");
	dol_include_once('/asset/class/asset.class.php');
	dol_include_once('/financement/class/dossierRachete.class.php');
	dol_include_once('/multicompany/class/dao_multicompany.class.php');
	
	$langs->load('financement@financement');
	
	if (!$user->rights->financement->affaire->read)	{ accessforbidden(); }

	$confirm = GETPOST('confirm');

	$dossier=new TFin_Dossier;
	$PDOdb=new TPDOdb;
	$tbs = new TTemplateTBS;
	$form = new Form($db);
	
	$mesg = '';
	$error=false;
	
	$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');
	
	if(GETPOST('envoiXML')){
		setEventMessage('La génération et l\'envoi du fichier XML s\'est effectué avec succès');
	}
	
	$id = GETPOST('id');
	if(!$id && GETPOST('searchdossier')){
        $sql = "SELECT d.rowid
                FROM ".MAIN_DB_PREFIX."fin_dossier as d
                LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as df ON (df.fk_fin_dossier = d.rowid)
                WHERE df.reference LIKE '%".GETPOST('searchdossier')."%'
                ORDER BY rowid DESC";
        $Tid = TRequeteCore::_get_id_by_sql($PDOdb, $sql);
        if(!empty($Tid)){
    		if(count($Tid) > 1){
    			_liste($PDOdb, $dossier);
    		} else{
				header('Location: '.$SERVER['PHP_SELF'].'?id='.$Tid[0]);
			}
        }
	}
	
	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'add':
			case 'new':
				
				$dossier->set_values($_REQUEST);
				if(isset($_REQUEST['fk_fin_affaire'])) {
					if($dossier->addAffaire($PDOdb, $_REQUEST['fk_fin_affaire'])) {
						$dossier->financement->montant = $_REQUEST['montant'];
						if($_REQUEST['nature_financement']=='EXTERNE') {
							unset($dossier->financement);
						}
						$dossier->save($PDOdb);
					}
					else {
						$mesg = '<div class="error">Impossible d\'ajouter créer un dossier dans cette affaire. </div>';
						$error=true;
						
						_liste($PDOdb, $dossier);
					}
				}
				else {
					$dossier->save($PDOdb);
				}
	
				
				_fiche($PDOdb,$dossier,'edit');
				
				break;	
			case 'edit'	:
			
				$dossier->load($PDOdb, $id);
				
				_fiche($PDOdb,$dossier,'edit');
				break;
				
			case 'save':
				$dossier->load($PDOdb, $id);
				$dossier->set_values($_REQUEST);
				$dossier->set_date('dateperso', $_REQUEST['dateperso']);
				$dossier->demat = intval(isset($_REQUEST['demat']));
				
				if(isset($dossier->financement))$dossier->financement->set_values($_REQUEST);

				if(isset($_REQUEST['leaser'])){
					$dossier->financementLeaser->set_values($_REQUEST['leaser']);
				}
				
				if ((!isset($dossier->type_financement_affaire['ADOSSEE'] ) && !isset($dossier->type_financement_affaire['MANDATEE'] ) )
					&& ($dossier->financementLeaser->okPourFacturation=='OUI' || $dossier->financementLeaser->okPourFacturation=='AUTO')){
					
					$dossier->financementLeaser->okPourFacturation='NON';
					
					setEventMessage("Ce dossier ne peut pas être à bon pour facturation 'Oui' ou 'Toujours' car son affaire n'est ni mandatée, ni adossée.", 'errors');
						
					_fiche($PDOdb,$dossier,'edit');	
				}
                else {
                    $dossier->save($PDOdb);
                    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$dossier->rowid);
                    exit;
                }

				break;
			case 'regenerate-facture-leaser':
				//$PDOdb->db->debug=true;
				
				$dossier->load($PDOdb, $id);
				//$dossier->generate_factures_leaser(false, true);
				//$dossier->save($PDOdb);
				
				$dossier->load_factureFournisseur($PDOdb);
				_fiche($PDOdb,$dossier,'view');
				
				break;
				
			case 'delete':
				//$PDOdb->db->debug=true;
				$dossier->load($PDOdb, $id);
				if(DossierRachete::isDossierSelected($dossier->rowid)) {
				    setEventMessage($langs->trans('ThisDossierIsSelected'), 'errors');
				    header('Location: '.$_SERVER['PHP_SELF'].'?id='.$dossier->rowid);
				    exit;
                }
				else {
                    $dossier->delete($PDOdb);
                    ?>
                    <script language="javascript">
                        document.location.href = "?delete_ok=1";
                    </script>
                    <?php
                    unset($dossier);
                }
				
				break;
				
			case 'add_affaire':
			//$PDOdb->db->debug=true;
				$dossier->load($PDOdb, $id);
				$dossier->set_values($_REQUEST);
			
				if(!$dossier->addAffaire($PDOdb, null, $_REQUEST['affaire_to_add'])) {
					$mesg = '<div class="error">Impossible d\'ajouter cette affaire au dossier. </div>';
					$error=true;
					
				}	
				else {
					$mesg = '<div class="ok">Affaire ajoutée au dossier</div>';
				}
				//exit($mesg);
				$dossier->save($PDOdb);
				
				_fiche($PDOdb,$dossier,'edit');
				
				break;
				
			case 'delete_affaire':
				//$PDOdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				$dossier->load($PDOdb, $id);
				
			
				if($dossier->deleteAffaire($PDOdb, $_REQUEST['id_affaire'])) {
					$mesg = '<div class="ok">Affaire retirée du dossier</div>';	
				}	
				
				$dossier->save($PDOdb);
				
				_fiche($PDOdb,$dossier,'edit');
				
				break;
			case 'exportXML':
			
				_liste($PDOdb, $dossier);
				
				break;
			case 'generateXML':
				dol_include_once('/financement/class/dossier_transfert_xml.class.php');

                $fk_leaser = GETPOST('fk_leaser');

                $dt = TFinDossierTransfertXML::create($fk_leaser);
				$filePath = $dt->transfertXML($PDOdb);
				
				header("Location: ".dol_buildpath("/document.php?modulepart=financement&entity=".$conf->entity."&file=".$filePath,2));
				
				break;

            case 'confirm_generateXMLandupload':
                _liste($PDOdb, $dossier);

                $question = 'Etes-vous certain de vouloir générer puis uploader le fichier XML ?';
                print $form->formconfirm($_SERVER['PHP_SELF'].'?fk_leaser='.$fk_leaser, 'Dossiers', $question, 'generateXMLandupload', '', 'no', 1);
                break;
			case 'generateXMLandupload':
				if($confirm != 'yes') {
				    header('Location: '.$_SERVER['PHP_SELF'].'?fk_leaser='.$fk_leaser);
				    exit;
                }
				dol_include_once('/financement/class/dossier_transfert_xml.class.php');
                $dtx = TFinDossierTransfertXML::create($fk_leaser, true);
				$filePath = $dtx->transfertXML($PDOdb);
				
				?>
				<script language="javascript">
					document.location.href="?fk_leaser=<?php echo $fk_leaser; ?>&envoiXML=ok";					
				</script>
				<?php
				
				break;
            case 'confirm_setnottransfer':
                _liste($PDOdb, $dossier);

                $question = 'Etes-vous certain de vouloir rendre non transférable les dossiers ?';
                print $form->formconfirm($_SERVER['PHP_SELF'].'?fk_leaser='.$fk_leaser, 'Dossiers', $question, 'setnottransfer', '', 'no', 1);
                break;
				
			case 'setnottransfer':
                if($confirm != 'yes') {
                    header('Location: '.$_SERVER['PHP_SELF'].'?fk_leaser='.$fk_leaser);
                    exit;
                }
				
				dol_include_once('/financement/class/dossier_transfert_xml.class.php');
				$dtx = TFinDossierTransfertXML::create($fk_leaser, true);
				$dtx->resetAllDossiersInXML($PDOdb);
				
				?>
				<script language="javascript">
					document.location.href="?fk_leaser=<?php echo $fk_leaser; ?>";
				</script>
				<?php 
				break;
				
			case 'create_avoir':
                $idDossier = GETPOST('id_dossier');
                $idFactureFourn = GETPOST('id_facture_fournisseur');

                $dossier->load($PDOdb, $idDossier);
                $idAvoir = $dossier->financementLeaser->createAvoirLeaserFromFacture($idFactureFourn);

				$urlback = dol_buildpath('/fourn/facture/card.php?facid='.$idAvoir, 1);
				header("Location: ".$urlback);
				exit;
				
				break;
			case 'new_facture_leaser':
				dol_include_once('/fourn/class/fournisseur.facture.class.php');
				dol_include_once('/product/class/product.class.php');
				
				$idDossier = GETPOST('id_dossier');
				$echeance = GETPOST('echeance');
				
				// Maj échéance dossier
				$dossier = new TFin_dossier();
				$dossier->load($PDOdb, $idDossier);
				$fact = $dossier->create_facture_leaser(false, true, $echeance, time());
				
				if($fact->id){
					$dossier->financementLeaser->setEcheanceExterne();
					$dossier->save($PDOdb);
					
					$urlback = dol_buildpath('/fourn/facture/card.php?facid='.$fact->id, 1);
					header("Location: ".$urlback);
					exit;
				}
				else{
					setEventMessage('Création facture impossible, dossier incomplet','errors');
					$urlback = dol_buildpath('/financement/dossier.php?id='.$dossier->rowid, 1);
					header("Location: ".$urlback);
					exit;
				}
				
				break;
			
			case 'new_facture_client':
				dol_include_once('/compta/facture/class/facture.class.php');
				dol_include_once('/product/class/product.class.php');
				
				$idDossier = GETPOST('id_dossier');
				$echeance = GETPOST('echeance');
				
				// Maj échéance dossier
				$dossier = new TFin_dossier();
				$dossier->load($PDOdb, $idDossier);
				$fact = $dossier->create_facture_client(true, true, $echeance);
				
				if($fact->id){
					$dossier->financement->setProchaineEcheanceClient($PDOdb, $dossier);
					$dossier->save($PDOdb);
					
					$urlback = dol_buildpath('/compta/facture/card.php?facid='.$fact->id, 1);
					header("Location: ".$urlback);
					exit;
				}
				else{
					setEventMessage('Création facture impossible, dossier incomplet','errors');
					$urlback = dol_buildpath('/financement/dossier.php?id='.$dossier->rowid, 1);
					header("Location: ".$urlback);
					exit;
				}

				break;
            case 'modifAccord':
                dol_include_once('/financement/class/simulation.class.php');
                dol_include_once('/financement/class/service_financement.class.php');
                $dossier->load($PDOdb, $id);

                $fk_simu = GETPOST('fk_simu', 'int');
                $simu = new TSimulation;
                if(! empty($fk_simu)) {
                    $simu->load($PDOdb, $fk_simu);  // C'est quand même mieux de load par l'id de la simul
                }

                if(! empty($simu->rowid)) {
                    // On duplique la ligne de suivi CMCIC
                    $newSuivi = new TSimulationSuivi;
                    $TRes = $newSuivi->loadAllBy($PDOdb, array('fk_simulation' => $simu->rowid, 'fk_leaser' => $dossier->financementLeaser->fk_soc));
                    $suivi = array_shift($TRes);

                    $TFieldToKeep = array(
                        'entity',
                        'fk_simulation',
                        'fk_leaser',
                        'fk_user_author',
                        'numero_accord_leaser',
                        'b2b_nodef',
                        'b2b_noweb',
                        'leaseRequestID'
                    );
                    foreach($TFieldToKeep as $field) $newSuivi->$field = $suivi->$field;
                    $newSuivi->statut = 'WAIT';
                    $newSuivi->statut_demande = 2; // Statut spécifique aux modifs

                    $newSuivi->save($PDOdb);
                    $newSuivi->loadLeaser();
                    $simu->montant = $dossier->financementLeaser->montant;

                    $service = new ServiceFinancement($simu, $newSuivi);

                    // La méthode se charge de tester si la conf du module autorise l'appel au webservice (renverra true sinon active)
                    $res = $service->call();
                    $newSuivi->date_demande = time();
                    $newSuivi->save($PDOdb);
                }

                header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id);
                exit;

                break;
		}
		
	}
	elseif($id) {
		$dossier->load($PDOdb, $id);
		_fiche($PDOdb,$dossier, 'view');
	}
	elseif (empty($Tid)) {
		/*
		 * Liste
		 */
		if(isset($_REQUEST['liste_incomplet'])) _liste_dossiers_incomplets($PDOdb, $dossier);
		else _liste($PDOdb, $dossier);
	}
	
	
	
	llxFooter();
	
function _liste(&$PDOdb, &$dossier) {
	global $conf, $db, $langs;
	
	llxHeader('','Dossiers');
    print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';
	
	//Affichage de l'en-tête société si fk_leaser
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');

		$societe = new Societe($db);
		$societe->fetch($fk_leaser);
		$head = societe_prepare_head($societe);
		
		print dol_get_fiche_head($head, 'transfert', $langs->trans("ThirdParty"),0,'company');
	}
	
	$r = new TSSRenderControler($dossier);
	$sql ="SELECT d.rowid as 'ID', fc.reference as refDosCli, e.label as entity_label, fl.reference as refDosLea, a.rowid as 'ID affaire', a.reference as 'Affaire', ";
	$sql.="a.nature_financement, a.fk_soc, c.nom as nomCli, l.nom as nomLea, COALESCE(fc.relocOK, 'OUI') as relocClientOK, COALESCE(fl.relocOK, 'OUI') as relocLeaserOK, COALESCE(fl.intercalaireOK, 'OUI') as intercalaireLeaserOK, ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.duree ELSE fl.duree END as 'duree', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.montant ELSE fl.montant END as 'Montant', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.echeance ELSE fl.echeance END as 'echeance', ";
	$sql.="fl.duree as 'dureeLeaser', ";
	$sql.="fl.montant as 'montantLeaser', ";
	$sql.="fl.echeance as 'echeanceLeaser', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_prochaine_echeance ELSE fl.date_prochaine_echeance END as 'Prochaine', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_debut ELSE fl.date_debut END as 'date_debut', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_fin ELSE fl.date_fin END as 'Fin' ";
	$sql.=", '' as fact_materiel ";
	$sql.="FROM ((((((((@table@ d ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (d.rowid=da.fk_fin_dossier)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (da.fk_fin_affaire=a.rowid)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_financement fc ON (d.rowid=fc.fk_fin_dossier AND fc.type='CLIENT')) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_financement fl ON (d.rowid=fl.fk_fin_dossier AND fl.type='LEASER')) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."societe c ON (a.fk_soc=c.rowid)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."societe l ON (fl.fk_soc=l.rowid)))) ";
	$sql.="LEFT JOIN ".MAIN_DB_PREFIX.'entity e ON (e.rowid = d.entity) ';
	//$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_source=a.rowid AND ee.sourcetype = 'affaire' AND ee.targettype = 'facture')) ";
	//$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."facture f ON (f.rowid=ee.fk_target)) ";
	
	//$sql.=" WHERE a.entity=".$conf->entity;
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])) $sql.=" WHERE a.entity = ".$conf->entity;
	else $sql.=" WHERE a.entity IN(".getEntity('fin_dossier', true).")";
	
	//Filtrage sur leaser et uniquement dossier avec "Bon pour transfert" = 1 (Oui)
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');

		$sql .= " AND l.rowid = ".$fk_leaser." AND fl.transfert = 1 AND a.type_financement = 'MANDATEE'";
	}
	
	if(GETPOST('searchdossier')){
		$sql .= " AND ( fc.reference LIKE '%".GETPOST('searchdossier')."%' OR fl.reference LIKE '%".GETPOST('searchdossier')."%')";
//	echo $sql;
	}

	if(GETPOST('reloc')) {
		$sql.= " AND (fc.reloc = 'OUI' OR fl.reloc = 'OUI')";
	}

	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');

	if(GETPOST('reloc')) echo $form->hidden('reloc', 1);

	$aff = new TFin_affaire;
	
	$TEntityName = TFinancementTools::build_array_entities();
	TFinancementTools::add_css();

	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'30'
			,'global'=>'1000'
		)
		,'link'=>array(
			'nomCli'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'nomLea'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'refDosCli'=>'<a href="?id=@ID@">@val@</a>'
//			,'refDosLea'=>'<a href="?id=@ID@">@val@</a>'
			,'Affaire'=>'<a href="'.DOL_URL_ROOT.'/custom/financement/affaire.php?id=@ID affaire@">@val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$aff->TNatureFinancement
			, 'relocClientOK'=>$dossier->financement->TRelocOK
			, 'relocLeaserOK'=>$dossier->financementLeaser->TRelocOK
			, 'intercalaireLeaserOK'=>$dossier->financementLeaser->TIntercalaireOK
		)
		,'hide'=>array('fk_soc','ID','ID affaire','fk_fact_materiel', 'relocLeaserOK', 'relocClientOK', 'intercalaireLeaserOK')
		,'type'=>array('date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant'=>'money', 'echeance'=>'money', 'montantLeaser'=>'money', 'echeanceLeaser'=>'money')
		,'liste'=>array(
			'titre'=>"Liste des dossiers"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'noheader'=>FALSE
			,'messageNothing'=>"Il n'y a aucun dossier"
			,'picto_search'=>img_picto('','search.png', '', 0)
			)
		,'title'=>array(
			'refDosCli'=>'Contrat'
			,'refDosLea'=>'Contrat Leaser'
			,'duree'=>'Durée'
			,'echeance'=>'Echéance'
			,'dureeLeaser'=>'Durée leaser'
			,'montantLeaser'=>'Montant leaser'
			,'echeanceLeaser'=>'Echéance leaser'
			,'entity_label'=>'Partenaire'
			,'nomCli'=>'Client'
			,'nomLea'=>'Leaser'
			,'nature_financement'=>'Nature'
			,'date_debut'=>'Début'
			,'fact_materiel'=>'Facture matériel'
			,'relocClientOK'=>'Relocation client OK ?'
			,'relocLeaserOK'=>'Relocation leaser OK ?'
			,'intercalaireLeaserOK'=>'Loyer intercalaire leaser OK ?'
		)
		,'orderBy'=> array('ID'=>'DESC','fc.reference'=>'ASC')
		,'search'=>array(
			'refDosCli'=>array('recherche'=>true, 'table'=>'fc', 'field'=>'reference')
			,'refDosLea'=>array('recherche'=>true, 'table'=>'fl', 'field'=>'reference')
			,'entity_label'=>array('recherche'=>$TEntityName, 'table'=>'e', 'field'=>'rowid')
			,'nomCli'=>array('recherche'=>true, 'table'=>'c', 'field'=>'nom')
			,'nomLea'=>array('recherche'=>true, 'table'=>'l', 'field'=>'nom')
			,'nature_financement'=>array('recherche'=>$aff->TNatureFinancement,'table'=>'a')
			,'relocClientOK'=>array('recherche'=> $dossier->financement->TRelocOK, 'table' => 'fc', 'field' => 'relocOK')
			,'relocLeaserOK'=>array('recherche'=> $dossier->financementLeaser->TRelocOK, 'table' => 'fl', 'field' => 'relocOK')
			,'intercalaireLeaserOK'=>array('recherche'=>$dossier->financementLeaser->TIntercalaireOK, 'table' => 'fl', 'field' => 'intercalaireOK')
			//,'date_debut'=>array('recherche'=>'calendars', 'table'=>'f')
		),'operator'=>array(
			'entity_label' => '='
		)
		,'eval'=>array(
			'fact_materiel'=>'_get_facture_mat(@ID affaire@);'
            ,'refDosLea'=>"_print_modif_icon(@ID@);"
		)
		,'position'=>array(
			'text-align'=>array(
				'refDosCli'=>'center'
				,'entity_label'=>'center'
				,'refDosLea'=>'center'
				,'Affaire'=>'center'
				,'nature_financement'=>'center'
				,'nomCli'=>'center'
				,'nomLea'=>'center'
				,'duree'=>'center'
				,'Montant'=>'center'
				,'echeance'=>'center'
				,'date'=>'center'
				,'Fin'=>'center'
				,'fact_materiel'=>'center'
			)
		)
		
	));
	$form->end();
	
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = GETPOST('fk_leaser');
		?>
		<div class="tabsAction">
				<a href="?action=exportXML&fk_leaser=<?php echo $fk_leaser; ?>" class="butAction">Exporter</a>
				<a href="?action=generateXML&fk_leaser=<?php echo $fk_leaser; ?>" class="butAction">Télécharger le XML</a>
				<a href="?action=confirm_generateXMLandupload&fk_leaser=<?php echo $fk_leaser; ?>" class="butAction">Envoyer le XML</a>
				<a href="?action=confirm_setnottransfer&fk_leaser=<?php echo $fk_leaser; ?>" class="butAction">Rendre les dossiers non transférables</a>
		</div>
		<?php
	}
	
	//Cas action export CSV de la liste des futurs affaire transféré en XML
	$action = GETPOST('action');
	if($action === 'exportXML'){
		_getExportXML($sql);
	}
}

function _load_facture(&$PDOdb,&$dossier_temp) {
	
	$TFacture = array();
	
	$sql = "SELECT f.rowid, f.ref_client, f.total, f.paye
			FROM ".MAIN_DB_PREFIX."element_element ee
				LEFT JOIN ".MAIN_DB_PREFIX."facture f ON (f.rowid = ee.fk_target)
			WHERE ee.sourcetype='dossier'
				AND ee.targettype='facture'
				AND ee.fk_source=".$dossier_temp->getId()."
				AND f.type = 0 
				AND (f.ref_client != '' OR f.ref_client IS NOT NULL)
			ORDER BY f.ref ASC";

	$PDOdb->Execute($sql);

	while($PDOdb->Get_line()) {
		$date_echeance = explode('/',$PDOdb->Get_field('ref_client'));
		$date_echeance = strtotime($date_echeance[2].'-'.$date_echeance[1].'-'.$date_echeance[0]);
		$echeance = $dossier_temp->_get_num_echeance_from_date($date_echeance);

		$TFacture[$echeance]['rowid'] = $PDOdb->Get_field('rowid');
		$TFacture[$echeance]['ref_client'] = $PDOdb->Get_field('ref_client');
		$TFacture[$echeance]['total_ht'] += $PDOdb->Get_field('total');
		$TFacture[$echeance]['paye'] = $PDOdb->Get_field('paye');
	}

	return $TFacture;
}

function _load_factureFournisseur(&$PDOdb,&$dossier_temp){
	global $db;
	
	$TFactureFourn = array();

	$sql = "SELECT ff.rowid, fext.date_debut_periode
			FROM ".MAIN_DB_PREFIX."element_element as ee
				LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as ff ON (ff.rowid = ee.fk_target)
				LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn_extrafields as fext ON (ff.rowid = fext.fk_object)
			WHERE ee.sourcetype='dossier'
				AND ee.targettype='invoice_supplier'
				AND ee.fk_source=".$dossier_temp->getId();
	
	$PDOdb->Execute($sql);

	while($PDOdb->Get_line()) {
			
		$date_echeance = $PDOdb->Get_field('date_debut_periode');
		$echeance = $dossier_temp->_get_num_echeance_from_date($date_echeance);	

		$TFactureFourn[$echeance]['rowid'] = $PDOdb->Get_field('rowid');

	}
	
	return $TFactureFourn;
}
function _getNomCli($fk_soc) {
	global $TSocieteCache,$db,$user,$conf,$langs;
	
		if(empty( $TSocieteCache))  $TSocieteCache=array();
	
		if(!empty($TSocieteCache[$fk_soc])) return $TSocieteCache[$fk_soc]->nom;
	
				 $TSocieteCache[$fk_soc] = new Societe($db);
				 $TSocieteCache[$fk_soc]->fetch($fk_soc);
				return  $TSocieteCache[$fk_soc]->nom;
}

function _getExport(&$TLines){
	
	$filename = 'export_liste_dossier.csv';
	$filepath = DOL_DATA_ROOT.'/financement/'.$filename;
	$file = fopen($filepath,'w');
	
	//Ajout première ligne libelle
	$TLabel = array('Contrat','Contrat Leaser','Affaire','Nature','Client','Leaser'
					,'Echéance Client < Echéance Leaser','Echéance client non facturée ','Facture Client < Facture leaser ','Facture Client impayée ', 'Facture Client < Loyer client'
					,'Duree','Montant','Echeance','Prochaine','Debut','Fin','Facture Materiel', 'Visa rentabilité échéance'
					,'Rentabilité Prévisionnelle', 'Rentabilité Attendue', 'Rentabilité Réelle');
					
	fputcsv($file, $TLabel,';','"');
	
	foreach($TLines as $line){
		
		//On renseigne la facture mat car on l'a avec un eval() dans la liste
		$line['fact_materiel'] = _get_facture_mat($line['ID affaire'],false);
		
		unset($line['ID']);
		unset($line['ID affaire']);
		unset($line['fk_soc']);
		
		fputcsv($file, $line,';','"');
	}
	
	fclose($file);
	
	?>
	<script language="javascript">
		document.location.href="<?php echo dol_buildpath("/document.php?modulepart=financement&entity=1&file=".$filename,2); ?>";					
	</script>
	<?php
	
	$PDOdb->close();
}	


function _getExportXML($sql){
	global $conf;
	
	$PDOdb = new TPDOdb;;
	
	$sql = str_replace('@table@','llx_fin_dossier',$sql);
	
	//On met l'order by ajouter par le render()
	$sql .= " ORDER BY ID DESC, fc.reference ASC";
	
	$PDOdb->Execute($sql);
	$TTRes = $PDOdb->Get_All(PDO::FETCH_ASSOC);
	
	$filename = 'export_XML.csv';
	
	if($conf->entity > 1)
		$url = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/XML/Lixxbail/';
	else
		$url = DOL_DATA_ROOT.'/financement/XML/Lixxbail/';
	
	dol_mkdir($url);
	
	$filepath = $url.$filename;
	$file = fopen($filepath,'w');

	//Ajout première ligne libelle
	$TLabel = array('Contrat','Partenaire','Contrat Leaser','Affaire','Nature','Client','Leaser','Duree','Montant Client','Echeance Client','Duree Leaser', 'Montant Leaser','Echeance Leaser','Prochaine','Debut','Fin','Facture Materiel');
	fputcsv($file, $TLabel,';','"');
	
	foreach($TTRes as $TRes){

		//On renseigne la facture mat car on l'a avec un eval() dans la liste
		$TRes['fact_materiel'] = _get_facture_mat($TRes['ID affaire'],false);
		
		//Suppression des colonnes inutiles
		unset($TRes['ID']);
		unset($TRes['ID affaire']);
		unset($TRes['fk_soc']);
		
		fputcsv($file, $TRes,';','"');
	}
	
	fclose($file);
	
	?>
	<script language="javascript">
		document.location.href="<?php echo dol_buildpath("/document.php?modulepart=financement&entity=".$conf->entity."&file=XML/Lixxbail/".$filename,2); ?>";					
	</script>
	<?php
	
	$PDOdb->close();
}	

function _get_facture_mat($fk_source,$withlink=true){
	
	$PDOdb = new TPDOdb;
	
	$sql = "SELECT f.rowid, f.ref
			FROM ".MAIN_DB_PREFIX."element_element as ee
				LEFT JOIN ".MAIN_DB_PREFIX."facture as f ON (ee.fk_target = f.rowid)
			WHERE ee.fk_target=f.rowid AND ee.sourcetype = 'affaire' AND ee.targettype = 'facture' AND ee.fk_source = ".$fk_source."";

	$PDOdb->Execute($sql);
	
	$link = '';
	while($PDOdb->Get_line()){
		if($withlink){
			$link .= '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$PDOdb->Get_field('rowid').'">'.img_object('', 'bill').' '.$PDOdb->Get_field('ref').'</a><br>';
		}
		else{
			$link .= $PDOdb->Get_field('ref')." ";
		}
	}
	
	$PDOdb->close();
	
	return $link;
}

function _fiche(&$PDOdb, TFin_dossier &$dossier, $mode) {
	global $user,$db,$conf, $langs;

    $result = restrictedArea($user, 'financement', $dossier->getID(), 'fin_dossier&societe', 'alldossier', 'fk_soc', 'rowid');
	
	$html=new Form($db);
	/*
	 * Liste des affaires rattachés à ce dossier
	 */ 
	$TAffaire=array();
	foreach($dossier->TLien as &$lien) {
		$affaire = &$lien->affaire;
		$client = new Societe($db);
		$client->fetch($affaire->fk_soc);
		
		$TAffaire[]=array(
			'id'=>$affaire->getId()
			,'reference'=>$affaire->reference
			,'date_affaire'=>$affaire->get_date('date_affaire')
			,'montant'=>$affaire->montant
			,'nature_financement'=>$affaire->TNatureFinancement[$affaire->nature_financement]
			,'type_financement'=>$affaire->TTypeFinancement[$affaire->type_financement]
			,'contrat'=>$affaire->TContrat[$affaire->contrat]
			,'client'=>$client->getNomUrl(1)
		);
		
		if($affaire->nature_financement=='INTERNE' && !isset($dossier->financement) ) {
			$dossier->financement = new TFin_financement;
			$dossier->financement->fk_fin_dossier = $dossier->getId();
			$dossier->financement->type='CLIENT';
			$dossier->financement->save($PDOdb);
		}
	}
	
	/*
	 * Pour autocomplete ajout dossier
	 */
	$otherAffaire='';
	if($mode=='edit') {
		
		$Tab = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."fin_affaire", "solde>0" ,'reference');
		$otherAffaire = '["'. implode('","', $Tab). '"]';
		
	}
	
	$extrajs = array('/financement/js/dossier.js');
	llxHeader('','Dossier','','','','',$extrajs);
    print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';

    $head = dossier_prepare_head($dossier);
    $img_path = dol_buildpath('/financement/img/object_financeico.png', 2);
    dol_fiche_head($head, 'card', $langs->trans("Dossier"),1, $img_path, 1);
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	print '<div class="tabBar">';
	$form->Set_typeaff($mode);
	
	echo $form->hidden('id', $dossier->rowid);
	echo $form->hidden('action', 'save');
	
	//require('./tpl/affaire.tpl.php');
	$TBS=new TTemplateTBS();
	$TBS->TBS->noErr=true;
	
	$financement=&$dossier->financement;
	$financementLeaser=&$dossier->financementLeaser;

	$leaser=new Societe($db);
	if($financementLeaser->fk_soc>0)$leaser->fetch($financementLeaser->fk_soc);
	else { $leaser->nom="Non défini"; }

	$formRestricted=new TFormCore;
	
	if($mode=='edit' && ( $financementLeaser->okPourFacturation!='AUTO' || count($dossier->TFactureFournisseur)==0 || $user->rights->financement->admin->write )  ) $mode_aff_fLeaser = 'edit';
	else $mode_aff_fLeaser='view';

	if($mode == 'view' || empty($user->rights->financement->alldossier->editReferenceAndTermLeaser)) $formRestricted->Set_typeaff( $mode_aff_fLeaser );

	$id_simu = _getIDSimuByReferenceDossierLeaser($PDOdb, $financementLeaser->fk_fin_dossier);
	if(!empty($id_simu)) {
	    $link_simu = '<a href="'.dol_buildpath('/financement/simulation/simulation.php?id='.$id_simu, 2).'" >'.$financementLeaser->reference.'</a>';
    }
	if(empty($link_simu) || GETPOST('action') == 'edit') {
        $referenceToShow = $formRestricted->texte('', 'leaser[reference]', $financementLeaser->reference, 20,255,'','','à saisir');
    }
	else $referenceToShow = $link_simu;
    if(GETPOST('action') != 'edit') $referenceToShow .= '&nbsp;'.$financementLeaser->printModifAccordCMCIC();
    $referenceToShow.= $dossier->printOtherDossierLink();   // On ajoute le lien pour les autres dossiers s'ils existent

    $numProchaineEcheance = $financementLeaser->numero_prochaine_echeance;
    if($user->id == 1 && GETPOST('action') != 'edit') {
        $numProchaineEcheance .= '<i class="fa fa-sync-alt" style="cursor: pointer; float: right; margin-right: 10px;" title="Recalculate num/date next term"></i>';
        ?>
        <script type="text/javascript">
            $(document).ready(function() {
                $('i.fa-sync-alt').on('click', function() {
                    $.ajax({
                        url: "<?php echo dol_buildpath('/financement/script/interface.php', 1); ?>",
                        data: {
                            action: 'updateNextTerm',
                            fk_dossier: <?php echo $dossier->rowid; ?>,
                            type: 'leaser'
                        },
                        dataType: 'json',
                        type: 'POST',
                        async: false
                    });
                    $(this).remove();
                });
            });
        </script>
<?php
    }
    $echeance = $formRestricted->texte('', 'leaser[echeance]', $financementLeaser->echeance, 10,255,'','','à saisir');

    if(! empty($user->rights->financement->alldossier->editReferenceAndTermLeaser)) $formRestricted->Set_typeaff( $mode_aff_fLeaser );
	
	$TFinancementLeaser=array(
			'id'=>$financementLeaser->getId()
			,'reference'=>$referenceToShow
			,'montant'=>$formRestricted->texte('', 'leaser[montant]', $financementLeaser->montant, 10,255,'','','à saisir')
			,'taux'=> $financementLeaser->taux
			
			,'assurance'=>$formRestricted->texte('', 'leaser[assurance]', $financementLeaser->assurance, 10,255,'','','à saisir')
			,'loyer_intercalaire'=>$formRestricted->texte('', 'leaser[loyer_intercalaire]', $financementLeaser->loyer_intercalaire, 10,255,'','','à saisir')
			,'intercalaireOK'=>$formRestricted->combo('', 'leaser[intercalaireOK]', $financementLeaser->TIntercalaireOK, $financementLeaser->intercalaireOK)
			,'echeance'=>$echeance
			,'reste'=>$formRestricted->texte('', 'leaser[reste]', $financementLeaser->reste, 10,255,'','','à saisir')
			,'montant_prestation'=>$formRestricted->texte('', 'leaser[montant_prestation]', $financementLeaser->montant_prestation, 10,255,'','','à saisir')
			,'frais_dossier'=>$formRestricted->texte('', 'leaser[frais_dossier]', $financementLeaser->frais_dossier, 10,255,'','','à saisir')
			,'montant_solde'=>$form->texte('', 'leaser[montant_solde]', $financementLeaser->montant_solde, 10,255,'','','0')
			,'dossier_termine'=>($financementLeaser->montant_solde > 0) ? 1 : 0
							
				
			,'numero_prochaine_echeance'=>$numProchaineEcheance
			,'duree'=>$formRestricted->texte('', 'leaser[duree]', $financementLeaser->duree, 5,255,'','','à saisir')
								
			,'periodicite'=>$formRestricted->combo('', 'leaser[periodicite]', $financementLeaser->TPeriodicite , $financementLeaser->periodicite)
			,'terme'=>$formRestricted->combo('', 'leaser[terme]', $financementLeaser->TTerme , $financementLeaser->terme)
			,'reglement'=>$formRestricted->combo('', 'leaser[reglement]', $financementLeaser->TReglement , $financementLeaser->reglement)
			,'incident_paiement'=>$formRestricted->combo('', 'leaser[incident_paiement]', $financementLeaser->TIncidentPaiement , $financementLeaser->incident_paiement)
			,'reloc'=>$formRestricted->combo('', 'leaser[reloc]', $financementLeaser->TReloc, $financementLeaser->reloc)
			,'relocOK'=>$formRestricted->combo('', 'leaser[relocOK]', $financementLeaser->TRelocOK, $financementLeaser->relocOK)
			,'encours_reloc'=> price($financementLeaser->encours_reloc)
			
			,'date_debut'=>$formRestricted->calendrier('', 'leaser[date_debut]', $financementLeaser->get_date('date_debut'),10)
			,'date_fin'=>$financementLeaser->get_date('date_fin') //$form->calendrier('', 'date_fin', $financement->get_date('date_fin'),10)
			,'date_prochaine_echeance'=>($financementLeaser->date_prochaine_echeance>0) ? $financementLeaser->get_date('date_prochaine_echeance') : ''
			,'date_solde'=>$form->calendrier('', 'leaser[date_solde]', $financementLeaser->get_date('date_solde'),10)
						
			,'leaser'=>($mode_aff_fLeaser=='edit') ? $html->select_company($leaser->id,'leaser[fk_soc]','fournisseur=1',0, 0,1) : $leaser->getNomUrl(1)
			
			,'okPourFacturation'=>$formRestricted->combo('', 'leaser[okPourFacturation]', $financementLeaser->TOkPourFacturation , $financementLeaser->okPourFacturation)
			,'transfert'=>$formRestricted->combo('', 'leaser[transfert]', $financementLeaser->TTransfert , $financementLeaser->transfert)
			,'xml_infos_transfert' => (!empty($affaire) && !empty($affaire->xml_fic_transfert)) ? ' - '.$affaire->xml_fic_transfert. ' - '.$affaire->get_date('xml_date_transfert') : ''

			,'echeancier'=>$dossier->echeancier($PDOdb,'LEASER')
			
			,'detail_fact' => dol_buildpath('/fourn/facture/list.php?search_refsupplier='.$financementLeaser->reference,1)
            ,'date_envoi'=>$formRestricted->calendrier('', 'leaser[date_envoi]', $financementLeaser->date_envoi, 10,255)
            ,'demat' => '<input type="checkbox" name="demat" '.(! empty($dossier->demat) ? 'checked="checked" ' : '').($mode == 'view' ? 'disabled="disabled" ' : '').'/>'
	);

	if(isset($financement)) {
		$TFinancement = array(
			'montant'=>$formRestricted->texte('', 'montant', $financement->montant, 10,255,'','','à saisir') 
			,'reference'=>$formRestricted->texte('', 'reference', $financement->reference, 20,255,'','','à saisir')/*$dossier->getId().'/'.$financement->getId()*/
			
			,'taux'=> $financement->taux //$form->texte('', 'taux', $financement->taux, 5,255,'','','à saisir')
			
			,'assurance'=>$formRestricted->texte('', 'assurance', $financement->assurance, 10,255,'','','à saisir')
			,'assurance_actualise' => $financement->assurance_actualise
			,'loyer_intercalaire'=>$formRestricted->texte('', 'loyer_intercalaire', $financement->loyer_intercalaire, 10,255,'','','à saisir')
			,'intercalaireOK'=>$formRestricted->combo('', 'intercalaireOK', $financement->TIntercalaireOK, $financement->intercalaireOK)
			,'echeance'=>$formRestricted->texte('', 'echeance', $financement->echeance, 10,255,'','','à saisir')
			,'loyer_actualise' => $financement->loyer_actualise
			,'reste'=>$formRestricted->texte('', 'reste', $financement->reste, 10,255,'','','à saisir')
			,'montant_prestation'=>$formRestricted->texte('', 'montant_prestation', $financement->montant_prestation, 10,255,'','','à saisir')
			,'montant_solde'=>$form->texte('', 'montant_solde', $financement->montant_solde, 10,255,'','','0')
			,'frais_dossier'=>$formRestricted->texte('', 'frais_dossier', $financement->frais_dossier, 10,255,'','','à saisir')
			,'dossier_termine'=>($financement->montant_solde > 0) ? 1 : 0
				
			,'numero_prochaine_echeance'=>$financement->numero_prochaine_echeance 
			,'duree'=>$formRestricted->texte('', 'duree', $financement->duree, 5,255,'','','à saisir')
								
			,'terme'=>$formRestricted->combo('', 'terme', $financement->TTerme , $financement->terme)
			,'periodicite'=>$formRestricted->combo('', 'periodicite', $financement->TPeriodicite , $financement->periodicite)
			,'reglement'=>$formRestricted->combo('', 'reglement', $financement->TReglement , $financement->reglement)
			,'incident_paiement'=>$formRestricted->combo('', 'incident_paiement', $financement->TIncidentPaiement , $financement->incident_paiement)
			,'reloc'=>$formRestricted->combo('', 'reloc', $financement->TReloc, $financement->reloc)
			,'relocOK'=>$formRestricted->combo('', 'relocOK', $financement->TRelocOK, $financement->relocOK)
			,'encours_reloc'=> price($financement->encours_reloc)
			
			,'date_debut'=>$formRestricted->calendrier('', 'date_debut', $financement->get_date('date_debut'),10)
			,'date_fin'=>$financement->get_date('date_fin') //$form->calendrier('', 'date_fin', $financement->get_date('date_fin'),10)
			,'date_prochaine_echeance'=>($financement->date_prochaine_echeance>0) ? $financement->get_date('date_prochaine_echeance') : ''
			,'date_solde'=>$form->calendrier('', 'date_solde', $financement->get_date('date_solde'),10)
						
			,'penalite_reprise'=>$formRestricted->texte('', 'penalite_reprise', $financement->penalite_reprise, 10,255,'','','à saisir') 
			,'taux_commission'=>$formRestricted->texte('', 'taux_commission', $financement->taux_commission, 5,255,'','') 
	
			,'echeancier'=>$dossier->echeancier($PDOdb)
			
			,'detail_fact' => ''
			
			,'client'=>$TAffaire[0]['client']
            ,'loyer_reference'=>$formRestricted->texte('', 'loyer_reference', $financement->loyer_reference, 10,255,'','','à saisir')
            ,'date_application'=>$formRestricted->calendrier('', 'date_application', $financement->date_application, 10,255)
		);
	}
	else {
		$TFinancement= array('id'=>0,
				'reference'=>''

				,'montant'=>0
				,'taux'=> 0
				,'loyer_intercalaire'=> 0
				, 'intercalaireOK' => ''
				,'echeance'=> 0
				,'reste'=> 0
				,'montant_prestation'=>0

				,'terme'=>''
				,'numero_prochaine_echeance'=> 0
				,'duree'=>0

				,'assurance'=>0

				,'periodicite'=>0
				,'reglement'=>0
				,'incident_paiement'=>0

				,'date_debut'=> 0
				,'date_fin'=>0
				,'date_prochaine_echeance'=>0
				,'echeancier'=>''
				,'taux_commission'=>0
				, 'penalite_reprise' => 0
                , 'loyer_reference' => 0
                , 'date_application' => 0
        );
	}
	$TBS->TBS->protect=false;
	$TBS->TBS->noerr=true;
	
	if($user->rights->financement->admin->write && ($mode == "add" || $mode == "new" || $mode == "edit")){
		$dateperso = $form->calendrier('', 'dateperso', $dossier->get_date('dateperso'),10);
		$soldeperso = $form->texte('', 'soldeperso', $dossier->soldeperso, 10);
	}
	else{
		$dateperso = $dossier->get_date('dateperso','d/m/Y');
		$soldeperso = $dossier->soldeperso;
	}

	$soldepersointegrale = $dossier->calculSoldePerso($PDOdb);

	$e = new DaoMulticompany($db);
	$e->getEntities();
	$TEntities = array();
	foreach($e->entities as $obj_entity) $TEntities[$obj_entity->id] = $obj_entity->label;
	
	$entity = empty($dossier->entity) ? getEntity('fin_dossier', false) : $dossier->entity;
	
	$TEntityName = TFinancementTools::build_array_entities();
	if(TFinancementTools::user_courant_est_admin_financement() && empty($conf->global->FINANCEMENT_DISABLE_SELECT_ENTITY)){
		$entity_field = $form->combo('', 'entity', $TEntityName, $entity);
	} else {
		$entity_field = $TEntityName[$entity].$form->hidden('entity', $entity);
	}


	if ($dossier->nature_financement == 'INTERNE') $current_periode = $dossier->financement->numero_prochaine_echeance - 1;
	else $current_periode = $dossier->financementLeaser->numero_prochaine_echeance - 1;


	//pre($TAffaire,true);exit;
	print $TBS->render('./tpl/dossier.tpl.php'
		,array(
			'affaire'=>$TAffaire
		)
		,array(
			'dossier'=>array(
				'id'=>$dossier->rowid
				,'entity'=>$entity_field
				,'entity_label'=>$TEntities[$dossier->entity]
				//combo($pLib,$pName,$pListe,$pDefault,$pTaille=1,$onChange='',$plus='',$class='flat',$id='',$multiple='false'){
				/*,'reference'=>$form->texte('', 'reference', $dossier->reference, 100,255,'','','à saisir')*/ 
				,'date_relocation'=>$form->calendrier('', 'date_relocation', $dossier->get_date('date_relocation'),10)
				,'commentaire'=>$form->zonetexte('', 'commentaire', $dossier->commentaire,100,5,'')
				,'display_solde'=>$form->combo('', 'display_solde', array('1' => 'Oui', '0' => 'Non'), $dossier->display_solde)
				,'date_maj'=>$dossier->get_date('date_maj','d/m/Y à H:i:s')
				,'date_cre'=>$dossier->get_date('date_cre','d/m/Y')
				,'solde'=>$dossier->solde
				,'montant_ok'=>$dossier->somme_affaire
				,'nature_financement'=>$dossier->nature_financement
				,'rentabilite_previsionnelle'=>$dossier->renta_previsionnelle
				,'rentabilite_attendue'=>$dossier->renta_attendue
				,'rentabilite_reelle'=>$dossier->renta_reelle
				,'marge_previsionnelle'=>$dossier->marge_previsionnelle
				,'marge_attendue'=>$dossier->marge_attendue
				,'marge_reelle'=>$dossier->marge_reelle
				,'soldeRBANK'=>$dossier->getSolde($PDOdb, 'SRBANK',$dossier->financementLeaser->numero_prochaine_echeance-1)
				,'soldeNRBANK'=>$dossier->getSolde($PDOdb, 'SNRBANK',$dossier->financementLeaser->numero_prochaine_echeance-1)
				,'soldeRCPRO'=>$dossier->getSolde($PDOdb, 'SRCPRO', $current_periode)//SRCPRO
				,'soldeNRCPRO'=>$dossier->getSolde($PDOdb, 'SNRCPRO', $current_periode)//SNRCPRO
				,'soldeperso'=>$soldeperso
				,'soldepersodispo'=>$form->combo('', 'soldepersodispo', array('1' => 'Oui', '2' => 'Non'), ($dossier->soldepersodispo) ? $dossier->soldepersodispo : 1)
				,'soldepersointegrale'=>$soldepersointegrale
				,'dateperso'=>$dateperso
				,'url_therefore'=>FIN_THEREFORE_DOSSIER_URL
				,'affaire1'=>$TAffaire[0]
				,'visa_renta'=>$form->combo('', 'visa_renta', array('1' => 'Oui', '0' => 'Non'), $dossier->visa_renta)
				,'visa_renta_ndossier'=>$form->combo('', 'visa_renta_ndossier', array('1' => 'Oui', '0' => 'Non'), $dossier->visa_renta_ndossier)
				,'renta_anomalie'=>$form->combo('', 'renta_anomalie', array('1' => 'Oui', '0' => 'Non'), $dossier->renta_anomalie)
				,'fk_statut_renta_neg_ano'=>$form->combo('', 'fk_statut_renta_neg_ano', $dossier->TStatutRentaNegAno, $dossier->fk_statut_renta_neg_ano)
				,'fk_statut_dossier'=>$form->combo('', 'fk_statut_dossier', $dossier->TStatutDossier, $dossier->fk_statut_dossier)
				,'commentaire_visa'=>$form->zonetexte('', 'commentaire_visa', $dossier->commentaire_visa,100,5,'')
				,'quote_part_noir' => $form->texte('', 'quote_part_noir', $dossier_for_integral->quote_part_noir, 10)
				,'quote_part_couleur' => $form->texte('', 'quote_part_couleur', $dossier_for_integral->quote_part_couleur, 10)
				,'somme_sup_noir' => $sommeCopieSupNoir
				,'somme_sup_coul' => $sommeCopieSupCouleur
				,'type_regul' => $dossier->type_regul
				,'commentaire_conformite' => $form->zonetexte('', 'commentaire_conformite', $dossier->commentaire_conformite,100,5,'')
                ,'date_reception_papier' => $formRestricted->calendrier('', 'date_reception_papier', $dossier->date_reception_papier, 10, 255)
                ,'date_paiement' => $formRestricted->calendrier('', 'date_paiement', $dossier->date_paiement, 10, 255)
                ,'date_facture_materiel' => $formRestricted->calendrier('', 'date_facture_materiel', $dossier->date_facture_materiel, 10, 255)
			)
			,'financement'=>$TFinancement
			,'financementLeaser'=>$TFinancementLeaser
			
			,'view'=>array(
				'mode'=>$mode
				,'otherAffaire'=>$otherAffaire
				,'userRight'=>((int)$user->rights->financement->affaire->write)
				,'contrat'=>$dossier->TLien[0]->affaire->contrat
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
}



function _getIDSimuByReferenceDossierLeaser(&$PDOdb, $id_dossier) {
	if(empty($id_dossier)) return 0;
	
	$TRes = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX.'fin_simulation', array('fk_fin_dossier' => $id_dossier));
	if(!empty($TRes)) return $TRes[0];
	
	return 0;
}

/*
 * LISTE SPECIFIQUE
 */
function _liste_dossiers_incomplets(&$PDOdb, &$dossier) {
	global $conf;
	
	llxHeader('','Dossiers incomplets');
    print '<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">';
	
	$r = new TSSRenderControler($dossier);
	$sql="SELECT d.rowid as 'ID', f.reference, a.rowid as 'ID affaire', a.reference as 'N° affaire', a.contrat, a.fk_soc as 'fk_soc', s.nom, 
	f.montant as 'Montant', f.duree as 'Durée', f.echeance as 'Echéance', f.date_prochaine_echeance as 'Prochaine', f.date_debut
	FROM ((((@table@ d
		LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire l ON (d.rowid=l.fk_fin_dossier))
		LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (l.fk_fin_affaire=a.rowid))
		LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_financement f ON (d.rowid=f.fk_fin_dossier ))
		LEFT OUTER JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc=s.rowid))
		
	WHERE a.entity=".$conf->entity."
	AND a.nature_financement = 'INTERNE'
	AND (f.type = 'LEASER' AND (f.reference IS NULL OR f.reference = '' OR f.duree = 0 OR f.echeance = 0))
	AND d.date_maj > '2013-06-13 00:00:00'
	GROUP BY d.rowid";
				
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
	echo $form->hidden('liste_incomplet', '1');
	$aff = new TFin_affaire;
	
	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'page'=>1
			,'nbLine'=>1000
		)
		,'link'=>array(
			'nom'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@"><img border="0" title="Afficher société: test" alt="Afficher société: test" src="'.DOL_URL_ROOT.'/theme/eldy/img/object_company.png"> @val@</a>'
			,'reference'=>'<a href="?id=@ID@">@val@</a>'
			,'N° affaire'=>'<a href="'.DOL_URL_ROOT.'/custom/financement/affaire.php?id=@ID affaire@">@val@</a>'
		)
		,'translate'=>array(
			'Incident de paiment'=>$dossier->TIncidentPaiement
			,'nature_financement'=>$aff->TNatureFinancement
			,'contrat'=>$aff->TContrat
		)
		,'hide'=>array('fk_soc','ID','ID affaire')
		,'type'=>array('date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant financé'=>'money', 'Echéance'=>'money')
		,'liste'=>array(
			'titre'=>"Liste des dossiers"
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'noheader'=>FALSE
			,'messageNothing'=>"Il n'y a aucun dossier"
			,'picto_search'=>img_picto('','search.png', '', 0)
			)
		,'title'=>array(
			'reference'=>'N° contrat'
			,'nom'=>'Société'
			,'nature_financement'=>'Nature'
			,'date_debut'=>'Début'
			,'contrat'=>'Contrat'
		)
		,'orderBy'=> array('ID'=>'DESC','f.reference'=>'ASC')
		,'search'=>array(
			'N° affaire'=>array('recherche'=>true, 'table'=>'a')
			,'nom'=>array('recherche'=>true, 'table'=>'s')
			,'contrat'=>array('recherche'=>$aff->TContrat,'table'=>'a')
			//,'date_debut'=>array('recherche'=>'calendars', 'table'=>'f')
		)
	));
	$form->end();
}

function _print_modif_icon($fk_dossier) {
    $PDOdb = new TPDOdb;
    $d = new TFin_dossier;
    $d->load($PDOdb, $fk_dossier);
    $f = &$d->financementLeaser;

    return '<a href="'.$_SERVER['PHP_SELF'].'?id='.$d->rowid.'">'.$f->reference.'</a>&nbsp;'.$f->printModifAccordCMCIC();
}
