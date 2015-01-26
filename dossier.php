<?php
	ini_set("display_errors", 1);
	error_reporting(E_ALL);
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	require('./class/grille.class.php');
	
	dol_include_once("/core/lib/company.lib.php");
	dol_include_once('/asset/class/asset.class.php');
	
	$langs->load('financement@financement');
	
	if (!$user->rights->financement->affaire->read)	{ accessforbidden(); }
	
	$dossier=new TFin_Dossier;
	$PDOdb=new TPDOdb;
	$tbs = new TTemplateTBS;
	
	$mesg = '';
	$error=false;
	
	$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');
	
	if(GETPOST('envoiXML')){
		setEventMessage('La génération et l\'envoi du fichier XML s\'est effectué avec succès');
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
			
				$dossier->load($PDOdb, $_REQUEST['id']);
				
				_fiche($PDOdb,$dossier,'edit');
				break;
				
			case 'save':
				//$PDOdb->db->debug=true;
				
				$dossier->load($PDOdb, $_REQUEST['id']);
				$dossier->set_values($_REQUEST);
				$dossier->set_date('dateperso', $_REQUEST['dateperso']);
				//pre($dossier);exit;
				
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
					//print 'nature_financement:'.$dossier->nature_financement;exit;
					_fiche($PDOdb,$dossier,'view');
						
					
				}
				
				
				break;
			
			case 'regenerate-facture-leaser':
				//$PDOdb->db->debug=true;
				
				$dossier->load($PDOdb, $_REQUEST['id']);
				//$dossier->generate_factures_leaser(false, true);
				//$dossier->save($PDOdb);
				
				$dossier->load_factureFournisseur($PDOdb);
				_fiche($PDOdb,$dossier,'view');
				
				break;
				
			case 'delete':
				//$PDOdb->db->debug=true;
				$dossier->load($PDOdb, $_REQUEST['id']);
				$dossier->delete($PDOdb);
				
				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";					
				</script>
				<?
				unset($dossier);
				
				break;
				
			case 'add_affaire':
			//$PDOdb->db->debug=true;
				$dossier->load($PDOdb, $_REQUEST['id']);
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
				$dossier->load($PDOdb, $_REQUEST['id']);
				
			
				if($dossier->deleteAffaire($PDOdb, $_REQUEST['id_affaire'])) {
					$mesg = '<div class="ok">Affaire retirée du dossier</div>';	
				}	
				
				$dossier->save($PDOdb);
				
				_fiche($PDOdb,$dossier,'edit');
				
				break;
			case 'generateXML':
			
				$affaire = new TFin_affaire;
				
				$TAffaires = $affaire->getAffairesForXML($PDOdb);
				$dirName = $affaire->genLixxbailXML($PDOdb, $TAffaires);
				
				header("Location: ".dol_buildpath("/document.php?modulepart=financement&entity=1&file=XML/Lixxbail/".$dirName.".xml",2));
				
				break;
			
			case 'generateXMLandupload':
				
				//TODO a mettre dans des variables donfigurable, voir dans la BDD pour les futurs envoi leaser
				$host = "test.b2b.eurofactor.com";
				$user = "cpro";
				$directory = "";
				
				$affaire = new TFin_affaire;
				
				$TAffaires = $affaire->getAffairesForXML($PDOdb);
				$filename = $affaire->genLixxbailXML($PDOdb, $TAffaires);
				$dirname = DOL_DATA_ROOT.'/financement/XML/Lixxbail/'.$filename.'.xml';
				
				//$affaire->uploadXMLOnLeaserServer($host,$user,$directory,$dirname,$filename.'.xml');
				if(BASE_TEST) {
					exec('sh bash/lixxbailxml_test.sh '.$dirname);
				} else {
					exec('sh bash/lixxbailxml.sh '.$dirname);
				}
				
				?>
				<script language="javascript">
					document.location.href="?fk_leaser=<?php echo $fk_leaser; ?>&envoiXML=ok";					
				</script>
				<?
				
				break;
				
			case 'setnottransfer':
				
				$affaire = new TFin_affaire;
				$TAffaires = $affaire->getAffairesForXML($PDOdb);
				$affaire->resetAllDossiersInXML($PDOdb,$TAffaires);
				
				break;
				
			case 'create_avoir':
				dol_include_once('/fourn/class/fournisseur.facture.class.php');
				dol_include_once('/product/class/product.class.php');
				
				$idFactureFourn = GETPOST('id_facture_fournisseur');
				$idDossier = GETPOST('id_dossier');
				$origine = new FactureFournisseur($db);
				$origine->fetch($idFactureFourn);
				
				$fact = new FactureFournisseur($db);
				$idClone = $fact->createFromClone($idFactureFourn);
				$fact->fetch($idClone);
				
				$fact->type = 2;
				$fact->fk_facture_source = $origine->id;
				$fact->facnumber = 'AV'.$origine->facnumber;
				$fact->ref_supplier = 'AV'.$origine->facnumber;
				$fact->update($user);
				foreach($fact->lines as $line) {
					$line->pu_ht *= -1;
					$fact->updateline($line->rowid, $line->libelle, $line->pu_ht, $line->tva_tx,0,0,$line->qty,$line->fk_product);
				}
				
				$fact->validate($user);
				
				// Ajout lien dossier
				$fact->add_object_linked('dossier', $idDossier);
				
				// Maj échéance dossier
				$dossier = new TFin_dossier();
				$dossier->load($PDOdb, $idDossier);
				$dossier->financementLeaser->setEcheance(-1, false);
				
				$urlback = dol_buildpath('/fourn/facture/fiche.php?facid='.$fact->id, 1);
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
				$dossier->financementLeaser->setEcheanceExterne();
				$dossier->save($PDOdb);
				
				$urlback = dol_buildpath('/fourn/facture/fiche.php?facid='.$fact->id, 1);
				header("Location: ".$urlback);
				exit;
				
				break;
		}
		
	}
	elseif(isset($_REQUEST['id'])) {
		$dossier->load($PDOdb, $_REQUEST['id']);
		_fiche($PDOdb,$dossier, 'view');
	}
	else {
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
	
	//Affichage de l'en-tête société si fk_leaser
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');

		$societe = new Societe($db);
		$societe->fetch($fk_leaser);
		$head = societe_prepare_head($societe);
		
		print dol_get_fiche_head($head, 'transfert', $langs->trans("ThirdParty"),0,'company');
	}
	
	$r = new TSSRenderControler($dossier);
	$sql ="SELECT d.rowid as 'ID', fc.reference as refDosCli, fl.reference as refDosLea, a.rowid as 'ID affaire', a.reference as 'Affaire', ";
	$sql.="a.nature_financement, a.fk_soc, c.nom as nomCli, l.nom as nomLea, ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.duree ELSE fl.duree END as 'Durée', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.montant ELSE fl.montant END as 'Montant', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.echeance ELSE fl.echeance END as 'Echéance', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_prochaine_echeance ELSE fl.date_prochaine_echeance END as 'Prochaine', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_debut ELSE fl.date_debut END as 'date_debut', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.date_fin ELSE fl.date_fin END as 'Fin', ";
	$sql.="f.rowid as fk_fact_materiel, f.facnumber as fact_materiel ";
	$sql.="FROM ((((((((@table@ d ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (d.rowid=da.fk_fin_dossier)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (da.fk_fin_affaire=a.rowid)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_financement fc ON (d.rowid=fc.fk_fin_dossier AND fc.type='CLIENT')) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_financement fl ON (d.rowid=fl.fk_fin_dossier AND fl.type='LEASER')) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."societe c ON (a.fk_soc=c.rowid)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."societe l ON (fl.fk_soc=l.rowid)) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_source=a.rowid AND ee.sourcetype = 'affaire' AND ee.targettype = 'facture')) ";
	$sql.="LEFT OUTER JOIN ".MAIN_DB_PREFIX."facture f ON (f.rowid=ee.fk_target)) ";

	$sql.="WHERE a.entity=".$conf->entity;
	
	//Filtrage sur leaser et uniquement dossier avec "Bon pour transfert" = 1 (Oui)
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');

		$sql .= " AND l.rowid = ".$fk_leaser." AND fl.transfert = 1";
	}
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
	$aff = new TFin_affaire;
	
	$r->liste($PDOdb, $sql, array(
		'limit'=>array(
			'page'=>(isset($_REQUEST['page']) ? $_REQUEST['page'] : 1)
			,'nbLine'=>'30'
		)
		,'link'=>array(
			'nomCli'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'nomLea'=>'<a href="'.DOL_URL_ROOT.'/societe/soc.php?socid=@fk_soc@">'.img_object('', 'company').' @val@</a>'
			,'refDosCli'=>'<a href="?id=@ID@">@val@</a>'
			,'refDosLea'=>'<a href="?id=@ID@">@val@</a>'
			,'Affaire'=>'<a href="'.DOL_URL_ROOT.'/custom/financement/affaire.php?id=@ID affaire@">@val@</a>'
			,'fact_materiel'=>'<a href="'.DOL_URL_ROOT.'/compta/facture.php?facid=@fk_fact_materiel@">'.img_object('', 'bill').' @val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$aff->TNatureFinancement
		)
		,'hide'=>array('fk_soc','ID','ID affaire')
		,'type'=>array('date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant'=>'money', 'Echéance'=>'money')
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
			,'nomCli'=>'Client'
			,'nomLea'=>'Leaser'
			,'nature_financement'=>'Nature'
			,'date_debut'=>'Début'
			,'fact_materiel'=>'Facture matériel'
		)
		,'orderBy'=> array('ID'=>'DESC','fc.reference'=>'ASC')
		,'search'=>array(
			'refDosCli'=>array('recherche'=>true, 'table'=>'fc', 'field'=>'reference')
			,'refDosLea'=>array('recherche'=>true, 'table'=>'fl', 'field'=>'reference')
			,'nomCli'=>array('recherche'=>true, 'table'=>'c', 'field'=>'nom')
			,'nomLea'=>array('recherche'=>true, 'table'=>'l', 'field'=>'nom')
			,'nature_financement'=>array('recherche'=>$aff->TNatureFinancement,'table'=>'a')
			//,'date_debut'=>array('recherche'=>'calendars', 'table'=>'f')
		)
		
	));
	$form->end();
	
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		?>
		<div class="tabsAction">
				<a href="?action=generateXML" class="butAction">Générer le XML Lixxbail</a>
				<a href="?action=generateXMLandupload&fk_leaser=<?php echo $fk_leaser; ?>" onclick="confirm('Etes-vous certain de vouloir générer puis uploader le fichier XML?')" class="butAction">Générer le XML Lixxbail et envoyer au Leaser</a>
				<a href="?action=setnottransfer" onclick="confirm('Etes-vous certain de vouloir rendre non transférable les dossiers?')" class="butAction">Rendre tous les Dossiers non transférable</a>
		</div>
		<?php
	}
	
	llxFooter();
}

function _fiche(&$PDOdb, &$dossier, $mode) {
	global $user,$db;
	
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
	
	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
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
	
	$formRestricted->Set_typeaff( $mode_aff_fLeaser );

	$TFinancementLeaser=array(
			'id'=>$financementLeaser->getId()
			,'reference'=>$formRestricted->texte('', 'leaser[reference]', $financementLeaser->reference, 20,255,'','','à saisir')
			,'montant'=>$formRestricted->texte('', 'leaser[montant]', $financementLeaser->montant, 10,255,'','','à saisir')
			,'taux'=> $financementLeaser->taux
			
			,'assurance'=>$formRestricted->texte('', 'leaser[assurance]', $financementLeaser->assurance, 10,255,'','','à saisir')
			,'loyer_intercalaire'=>$formRestricted->texte('', 'leaser[loyer_intercalaire]', $financementLeaser->loyer_intercalaire, 10,255,'','','à saisir')
			,'echeance'=>$formRestricted->texte('', 'leaser[echeance]', $financementLeaser->echeance, 10,255,'','','à saisir')
			,'reste'=>$formRestricted->texte('', 'leaser[reste]', $financementLeaser->reste, 10,255,'','','à saisir')
			,'montant_prestation'=>$formRestricted->texte('', 'leaser[montant_prestation]', $financementLeaser->montant_prestation, 10,255,'','','à saisir')
			,'frais_dossier'=>$formRestricted->texte('', 'leaser[frais_dossier]', $financementLeaser->frais_dossier, 10,255,'','','à saisir')
			,'montant_solde'=>$form->texte('', 'leaser[montant_solde]', $financementLeaser->montant_solde, 10,255,'','','0')
			,'dossier_termine'=>($financementLeaser->montant_solde > 0) ? 1 : 0
							
				
			,'numero_prochaine_echeance'=>$financementLeaser->numero_prochaine_echeance 
			,'duree'=>$formRestricted->texte('', 'leaser[duree]', $financementLeaser->duree, 5,255,'','','à saisir')
								
			,'periodicite'=>$formRestricted->combo('', 'leaser[periodicite]', $financementLeaser->TPeriodicite , $financementLeaser->periodicite)
			,'terme'=>$formRestricted->combo('', 'leaser[terme]', $financementLeaser->TTerme , $financementLeaser->terme)
			,'reglement'=>$formRestricted->combo('', 'leaser[reglement]', $financementLeaser->TReglement , $financementLeaser->reglement)
			,'incident_paiement'=>$formRestricted->combo('', 'leaser[incident_paiement]', $financementLeaser->TIncidentPaiement , $financementLeaser->incident_paiement)
			,'reloc'=>$formRestricted->combo('', 'leaser[reloc]', $financementLeaser->TReloc, $financementLeaser->reloc)
			
			,'date_debut'=>$formRestricted->calendrier('', 'leaser[date_debut]', $financementLeaser->get_date('date_debut'),10)
			,'date_fin'=>$financementLeaser->get_date('date_fin') //$form->calendrier('', 'date_fin', $financement->get_date('date_fin'),10)
			,'date_prochaine_echeance'=>($financementLeaser->date_prochaine_echeance>0) ? $financementLeaser->get_date('date_prochaine_echeance') : ''
			,'date_solde'=>$form->calendrier('', 'leaser[date_solde]', $financementLeaser->get_date('date_solde'),10)
						
			,'leaser'=>($mode_aff_fLeaser=='edit') ? $html->select_company($leaser->id,'leaser[fk_soc]','fournisseur=1',0, 0,1) : $leaser->getNomUrl(1)
			
			,'okPourFacturation'=>$formRestricted->combo('', 'leaser[okPourFacturation]', $financementLeaser->TOkPourFacturation , $financementLeaser->okPourFacturation)
			,'transfert'=>$formRestricted->combo('', 'leaser[transfert]', $financementLeaser->TTransfert , $financementLeaser->transfert)
			,'xml_infos_transfert' => (!empty($affaire) && !empty($affaire->xml_fic_transfert)) ? ' - '.$affaire->xml_fic_transfert. ' - '.$affaire->get_date('xml_date_transfert') : ''
			
			//,'reinit'=>'<a href="'.$_SERVER['PHP_SELF'].'?action=regenerate-facture-leaser&id='.$dossier->getId().'">Lancer</a>'
			
			,'echeancier'=>$dossier->echeancier($PDOdb,'LEASER')
			
			,'detail_fact' => dol_buildpath('/fourn/facture/index.php?search_ref_supplier='.$financementLeaser->reference,2)
			
			
	);
	//print $financement->get_date('date_solde','d/m/Y',true);
	if(isset($financement)) {
		$TFinancement = array(
			'montant'=>$formRestricted->texte('', 'montant', $financement->montant, 10,255,'','','à saisir') 
			,'reference'=>$formRestricted->texte('', 'reference', $financement->reference, 20,255,'','','à saisir')/*$dossier->getId().'/'.$financement->getId()*/
			
			,'taux'=> $financement->taux //$form->texte('', 'taux', $financement->taux, 5,255,'','','à saisir')
			
			,'assurance'=>$formRestricted->texte('', 'assurance', $financement->assurance, 10,255,'','','à saisir')
			,'assurance_actualise' => $financement->assurance_actualise
			,'loyer_intercalaire'=>$formRestricted->texte('', 'loyer_intercalaire', $financement->loyer_intercalaire, 10,255,'','','à saisir')
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
			
			,'date_debut'=>$formRestricted->calendrier('', 'date_debut', $financement->get_date('date_debut'),10)
			,'date_fin'=>$financement->get_date('date_fin') //$form->calendrier('', 'date_fin', $financement->get_date('date_fin'),10)
			,'date_prochaine_echeance'=>($financement->date_prochaine_echeance>0) ? $financement->get_date('date_prochaine_echeance') : ''
			,'date_solde'=>$form->calendrier('', 'date_solde', $financement->get_date('date_solde'),10)
						
			,'penalite_reprise'=>$formRestricted->texte('', 'penalite_reprise', $financement->penalite_reprise, 10,255,'','','à saisir') 
			,'taux_commission'=>$formRestricted->texte('', 'taux_commission', $financement->taux_commission, 5,255,'','') 
	
			,'echeancier'=>$dossier->echeancier($PDOdb)
			
			,'detail_fact' => ''
			
			,'client'=>$TAffaire[0]['client']
		);
	}
	else {
		$TFinancement= array('id'=>0,
				'reference'=>''
				,'montant'=>0
				,'taux'=> 0
				,'loyer_intercalaire'=> 0
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
				,'penalite_reprise'=>0
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
	
	//pre($TAffaire,true);exit;
	
	print $TBS->render('./tpl/dossier.tpl.php'
		,array(
			'affaire'=>$TAffaire
		)
		,array(
			'dossier'=>array(
				'id'=>$dossier->rowid
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
				,'soldeRBANK'=>$dossier->getSolde($PDOdb, 'SRBANK')
				,'soldeNRBANK'=>$dossier->getSolde($PDOdb, 'SNRBANK')
				,'soldeRCPRO'=>$dossier->getSolde($PDOdb, 'SRCPRO')
				,'soldeNRCPRO'=>$dossier->getSolde($PDOdb, 'SNRCPRO')
				,'soldeperso'=>$soldeperso
				,'dateperso'=>$dateperso
				,'url_therefore'=>FIN_THEREFORE_DOSSIER_URL
				,'affaire1'=>$TAffaire[0]
			)
			,'financement'=>$TFinancement
			,'financementLeaser'=>$TFinancementLeaser
			
			,'view'=>array(
				'mode'=>$mode
				,'otherAffaire'=>$otherAffaire
				,'userRight'=>((int)$user->rights->financement->affaire->write)
			)
			
		)
	);
	
	echo $form->end_form();
	// End of page
	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	
	llxFooter();
	
}


/*
 * LISTE SPECIFIQUE
 */
function _liste_dossiers_incomplets(&$PDOdb, &$dossier) {
	global $conf;
	
	llxHeader('','Dossiers incomplets');
	
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
	
	llxFooter();
}
