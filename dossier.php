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
	dol_include_once('/multicompany/class/dao_multicompany.class.php');
	
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
    		}
		else{
			$dossier->load($PDOdb, $Tid[0]);
       			 _fiche($PDOdb,$dossier, 'view');
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
				//$PDOdb->db->debug=true;
				
				$dossier->load($PDOdb, $id);
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
				
				$dossier->load($PDOdb, $id);
				//$dossier->generate_factures_leaser(false, true);
				//$dossier->save($PDOdb);
				
				$dossier->load_factureFournisseur($PDOdb);
				_fiche($PDOdb,$dossier,'view');
				
				break;
				
			case 'delete':
				//$PDOdb->db->debug=true;
				$dossier->load($PDOdb, $id);
				$dossier->delete($PDOdb);
				
				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";					
				</script>
				<?php
				unset($dossier);
				
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
				
				$affaire = new TFin_affaire;
				
				$TAffaires = $affaire->getAffairesForXML($PDOdb,GETPOST('fk_leaser'));
				$dirName = $affaire->genLixxbailXML($PDOdb, $TAffaires);
				
				header("Location: ".dol_buildpath("/document.php?modulepart=financement&entity=".$conf->entity."&file=XML/Lixxbail/".$dirName.".xml",2));
				
				break;
			
			case 'generateXMLandupload':
				
				//TODO a mettre dans des variables donfigurable, voir dans la BDD pour les futurs envoi leaser
				/*$host = "test.b2b.eurofactor.com";
				$user = "cpro";
				$directory = "";*/
				
				$affaire = new TFin_affaire;
				
				$TAffaires = $affaire->getAffairesForXML($PDOdb,GETPOST('fk_leaser'));
				$filename = $affaire->genLixxbailXML($PDOdb, $TAffaires,true);
				$dirname = DOL_DATA_ROOT.'/financement/XML/Lixxbail/'.$filename.'.xml';
				
				if($conf->entity > 1)
					$dirname = DOL_DATA_ROOT.'/'.$conf->entity.'/financement/XML/Lixxbail/'.$filename.'.xml';
				else
					$dirname = DOL_DATA_ROOT.'/financement/XML/Lixxbail/'.$filename.'.xml';

				//$affaire->uploadXMLOnLeaserServer($host,$user,$directory,$dirname,$filename.'.xml');
//echo $dirname;exit;
				if(BASE_TEST) {
					exec('sh bash/lixxbailxml_test.sh '.$dirname);
				} else {
					exec('sh bash/lixxbailxml.sh '.$dirname);
				}
				
				?>
				<script language="javascript">
					document.location.href="?fk_leaser=<?php echo $fk_leaser; ?>&envoiXML=ok";					
				</script>
				<?php
				
				break;
				
			case 'setnottransfer':
				
				$affaire = new TFin_affaire;
				$TAffaires = $affaire->getAffairesForXML($PDOdb,GETPOST('fk_leaser'));
				$affaire->resetAllDossiersInXML($PDOdb,$TAffaires);
				
				?>
				<script language="javascript">
					document.location.href="?fk_leaser=<?php echo $fk_leaser; ?>";
				</script>
				<?php 
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
				$fact->facnumber = 'AV'.$origine->ref_supplier;
				$fact->ref_supplier = 'AV'.$origine->ref_supplier;
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
				
				$Techeance = explode('/', $fact->ref_supplier);
				$echeance = array_pop($Techeance);

				//MAJ dates période facture
				$date_debut_periode = $dossier->getDateDebutPeriode($echeance-1,'LEASER');
				$date_fin_periode = $dossier->getDateFinPeriode($echeance-1);

				$db->query("UPDATE ".MAIN_DB_PREFIX."facture_fourn SET date_debut_periode = '".date('Y-m-d',strtotime($date_debut_periode))."' , date_fin_periode = '".date('Y-m-d',strtotime($date_fin_periode))."' WHERE rowid = ".$fact->id);
				
				$urlback = dol_buildpath('/fourn/facture/card.php?facid='.$fact->id, 1);
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
				$fact = $dossier->create_facture_client(false, true, $echeance);
				
				if($fact->id){
					$dossier->financement->setProchaineEcheanceClient($PDOdb, $dossier);
					$dossier->save($PDOdb);
					
					$urlback = dol_buildpath('/compta/facture.php?facid='.$fact->id, 1);
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
			
			case 'exportListeDossier' :
				_liste_renta_negative($PDOdb, $dossier);
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
		else if(isset($_REQUEST['liste_renta_negative'])) _liste_renta_negative($PDOdb,$dossier);
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
	$sql ="SELECT d.rowid as 'ID', fc.reference as refDosCli, e.rowid as entity_id, fl.reference as refDosLea, a.rowid as 'ID affaire', a.reference as 'Affaire', ";
	$sql.="a.nature_financement, a.fk_soc, c.nom as nomCli, l.nom as nomLea, ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.duree ELSE fl.duree END as 'duree', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.montant ELSE fl.montant END as 'Montant', ";
	$sql.="CASE WHEN a.nature_financement = 'INTERNE' THEN fc.echeance ELSE fl.echeance END as 'echeance', ";
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
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])) $sql.=" WHERE a.entity IN(".((strpos(getEntity(),'1') !== FALSE || strpos(getEntity(),'4')!== FALSE) ? "1,4" : getEntity() ).")";
	else $sql.=" WHERE a.entity IN(".getEntity('fin_dossier', TFinancementTools::user_courant_est_admin_financement()).")";
	
	//Filtrage sur leaser et uniquement dossier avec "Bon pour transfert" = 1 (Oui)
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = __val($_REQUEST['fk_leaser'],'','integer');

		$sql .= " AND l.rowid = ".$fk_leaser." AND fl.transfert = 1 AND a.type_financement = 'MANDATEE'";
	}
	
	if(GETPOST('searchdossier')){
		$sql .= " AND ( fc.reference LIKE '%".GETPOST('searchdossier')."%' OR fl.reference LIKE '%".GETPOST('searchdossier')."%')";
//	echo $sql;
	}

	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
	$aff = new TFin_affaire;
	
	$TEntityName = TFinancementTools::build_array_entities();
	TFinancementTools::add_css();

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
			//,'fact_materiel'=>'<a href="'.DOL_URL_ROOT.'/compta/facture.php?facid=@fk_fact_materiel@">'.img_object('', 'bill').' @val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$aff->TNatureFinancement
		)
		,'hide'=>array('fk_soc','ID','ID affaire','fk_fact_materiel')
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
			,'duree'=>'Durée'
			,'echeance'=>'Echéance'
			,'entity_id'=>'Partenaire'
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
			,'entity_id'=>array('recherche'=>$TEntityName, 'table'=>'e', 'field'=>'rowid')
			,'nomCli'=>array('recherche'=>true, 'table'=>'c', 'field'=>'nom')
			,'nomLea'=>array('recherche'=>true, 'table'=>'l', 'field'=>'nom')
			,'nature_financement'=>array('recherche'=>$aff->TNatureFinancement,'table'=>'a')
			//,'date_debut'=>array('recherche'=>'calendars', 'table'=>'f')
		)
		,'eval'=>array(
			'fact_materiel'=>'_get_facture_mat(@ID affaire@);'
			,'entity_id' => 'TFinancementTools::get_entity_translation(@entity_id@)'
		)
		,'position'=>array(
			'text-align'=>array(
				'refDosCli'=>'center'
				,'entity_id'=>'center'
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
				<a href="?action=generateXML&fk_leaser=<?php echo $fk_leaser; ?>" class="butAction">Générer le XML Lixxbail</a>
				<a href="?action=generateXMLandupload&fk_leaser=<?php echo $fk_leaser; ?>" onclick="confirm('Etes-vous certain de vouloir générer puis uploader le fichier XML?')" class="butAction">Générer le XML Lixxbail et envoyer au Leaser</a>
				<a href="?action=setnottransfer&fk_leaser=<?php echo $fk_leaser; ?>" onclick="confirm('Etes-vous certain de vouloir rendre non transférable les dossiers?')" class="butAction">Rendre tous les Dossiers non transférable</a>
		</div>
		<?php
	}
	
	//Cas action export CSV de la liste des futurs affaire transféré en XML
	$action = GETPOST('action');
	if($action === 'exportXML'){
		_getExportXML($sql);
	}
	
	llxFooter();
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
			ORDER BY f.facnumber ASC";

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

	$sql = "SELECT ff.rowid, ff.date_debut_periode
			FROM ".MAIN_DB_PREFIX."element_element as ee
				LEFT JOIN ".MAIN_DB_PREFIX."facture_fourn as ff ON (ff.rowid = ee.fk_target)
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
function _liste_renta_negative(&$PDOdb, &$dossier) {
	global $conf, $db, $langs;
	
	$display_all = false;
	if (GETPOST('display_all') || GETPOST('action') == 'exportListeDossier') $display_all = true;
	
	llxHeader('','Dossiers');
	
	$TErrorStatus=array(
		'error_1' => "Echéance Client <br>< Echéance Leaser",
		'error_2' => "Echéance client <br>non facturée",
		'error_3' => "Facture Client <br>< Facture leaser",
		'error_4' => "Facture Client <br>impayée",
		'error_5' => "Facture Client <br>< Loyer client"
	);
	
	$TTemplateTBS = new TTemplateTBS;
	
	$r = new TListviewTBS('list_'.$dossier->get_table());
	
	// Liste des dossiers, ne pas prendre en compte les dossier soldés et/ou avec "old" dans référence -> Doc drive "Retours suite réunion du 29.01.2016"
	$sql = 'SELECT d.rowid AS iddossier, a.rowid AS idaffaire, a.reference, a.nature_financement, a.fk_soc
			FROM '.MAIN_DB_PREFIX.'fin_dossier d
				LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_affaire da ON (da.fk_fin_dossier = d.rowid)
				INNER JOIN '.MAIN_DB_PREFIX.'fin_affaire a ON (a.rowid = da.fk_fin_affaire)
				LEFT JOIN '.MAIN_DB_PREFIX.'fin_dossier_financement df ON (d.rowid = df.fk_fin_dossier AND df.type = "LEASER")
				
			WHERE df.date_solde < "1970-00-00 00:00:00"
			AND d.montant_solde = "0.00" 
			AND d.date_solde < "1970-00-00 00:00:00"
			AND a.entity IN('.getEntity('fin_dossier', TFinancementTools::user_courant_est_admin_financement()).')
			AND d.reference NOT LIKE "%old%"
			ORDER BY d.rowid
			';
	
	$PDOdb->Execute($sql);	
	
	$TListTBS = GETPOST('TListTBS');
	$page = 1;
	if (!empty($TListTBS['list_llx_fin_dossier']['page'])) $page = $TListTBS['list_llx_fin_dossier']['page'];
	
	while ($PDOdb->Get_line()) {
		$res['iddossier'] = $PDOdb->Get_field('iddossier');
		$res['idaffaire'] = $PDOdb->Get_field('idaffaire');
		$res['reference'] = $PDOdb->Get_field('reference');
		$res['nature_financement'] = $PDOdb->Get_field('nature_financement');
		$res['fk_soc'] = $PDOdb->Get_field('fk_soc');
		
		$Tres[] = $res;
	}
	
	if(!empty($Tres)) 
	{
		$i=0;
		$maxDisplay = 30 * $page;
		foreach($Tres as $res)
		{
			$renta_negative = false;
			$TError = array();
			
			$dossier_temp = new TFin_dossier;
			$dossier_temp->load($PDOdb, $res['iddossier'], false); // Laisser le 3eme param à false, on recherchera les infos que si on doit l'afficher
			
			if($dossier_temp->financement->echeance < $dossier_temp->financementLeaser->echeance){
				$renta_negative = true;
				$TError[$res['iddossier']]['error_1'] = "EcheanceClientEcheanceLeaser";
				
				if ($dossier_temp->visa_renta) continue; // Doc drive "Retours suite réunion du 29.01.2016" (Ajouter filtre pour ne pas afficher les dossier où “Visa renta échéance” est à “Oui” ET “Échéance client < échéance leaser”)
			}
				
			//if($dossier_temp->rowid == 434){ pre($TFacturesFourn,true); pre($TFactures,true); }
			$decalage_echeance_client = 0;
			if ($dossier_temp->financement->terme != $dossier_temp->financementLeaser->terme)
			{
				// Doc drive "Retours suite réunion du 29.01.2016" (Si le terme n’est pas le même des 2 côtés, comparer les période P pour “à échoir” et P-1 pour le échu)
				// 0 => Echu (fin de periode)
				// 1 => A Echoir (debut de periode)
				if ($dossier_temp->financement->terme == 0) $decalage_echeance_client = -1;
				if ($dossier_temp->financementLeaser->terme == 0) $decalage_echeance_client = 1;
			}
			
			$TFactures = _load_facture($PDOdb, $dossier_temp);
			$TFacturesFourn = _load_factureFournisseur($PDOdb,$dossier_temp);
			
			foreach ($TFacturesFourn as $echeance => $TfactureFourn) 
			{
				$date_periode_client = $dossier_temp->getDateDebutPeriode($echeance);
				if (strtotime($date_periode_client) < strtotime('2015-04-01')) continue; // Doc drive "Retours suite réunion du 29.01.2016"
				
				$sql = "SELECT date_fin_periode FROM ".MAIN_DB_PREFIX."facture_fourn WHERE rowid = ".$TfactureFourn['rowid'];
				$PDOdb->Execute($sql);
			
				if($PDOdb->Get_line() && strpos($PDOdb->Get_field('date_fin_periode'), '/')){
					$date_fin_periode = explode('/',$PDOdb->Get_field('date_fin_periode'));
					$date_fin_periode = $date_fin_periode[2]."-".$date_fin_periode[1]."-".$date_fin_periode[0];
				}
				else{
					$date_fin_periode = $PDOdb->Get_field('date_fin_periode');
				}
				
				// Si echeance courrante == à la dernière et que le leaser est à echu alors on n'applique pas de décalage vers la P+1 du client
				if ($dossier_temp->financementLeaser->duree == $echeance+1 && $dossier_temp->financementLeaser->terme == 0) $decalage_echeance_client = 0; // Ceci a une incidence uniquement si les termes client/leaser sont différents
				
				$echeance_client = $echeance + $decalage_echeance_client;
				
				if(empty($TFactures[$echeance_client]['rowid']) && strtotime($date_fin_periode) > strtotime('2014-04-01')){
					//echo "1<br>";
					$TError[$res['iddossier']]['error_2'] = "NoFactureOnEcheance";
					$renta_negative = true;break;
				}
			}
			
			foreach($TFactures as $echeanceClient => $Tfacture){
				
				$date_fact_client  = explode("/",$Tfacture['ref_client']);
				$date_fact_client = $date_fact_client[2]."-".$date_fact_client[1]."-".$date_fact_client[0];
				
				if($echeanceClient == -1 || strtotime($date_fact_client) > strtotime('2014-04-01')) continue;
				
				//Renta négative si une facture échéance client < facture échéance leaser (dossierfinleaser->echeance)
				if($Tfacture['total_ht'] < $dossier_temp->financementLeaser->echeance && $Tfacture['ref_client']){
					$TError[$res['iddossier']]['error_3'] = "FactureClientFactureLeaser";
					$renta_negative = true;
				}
				
				//Renta négative si une facture échéance client STATUS NON PAYE
				if($Tfacture['paye'] == 0){
					$TError[$res['iddossier']]['error_4'] = "FactureClientUnpaid";
					$renta_negative = true;break;
				}
				
				//Renta négative si une facture échéance client < facture échéance leaser (dossierfinleaser->echeance)
				//echo $Tfacture['total_ht'] .' : '. $dossier_temp->financement->echeance.'<br>';
				if($Tfacture['total_ht'] < $dossier_temp->financement->echeance && $Tfacture['ref_client']){
					$TError[$res['iddossier']]['error_5'] = "FactureClientLoyerClient";
					$renta_negative = true;
				}
			}
			
			//if($dossier_temp->rowid == 1315){ pre($TError,true);exit; }
			
			if($renta_negative){
				
				$error_1 = $error_2 = $error_3 = $error_4 = $error_5 = "Non";
	
				foreach ($TError as $iddossier => $TLabel) {
					foreach($TLabel as $key => $label){
						${$key} = 'Oui';
					}
				}
				
				$nomCli = _getNomCli($dossier_temp->financement->fk_soc);
				$nomLea= _getNomCli($dossier_temp->financementLeaser->fk_soc);
				
				$affiche = true;
				if($dossier_temp->visa_renta && $error_1 == 'Oui') $affiche = false;
				if($dossier_temp->visa_renta_ndossier && ($error_2 == 'Oui' || $error_3 == 'Oui')) $affiche = false; 
				
				if($affiche){
					// On doit l'afficher alors on load les infos manquantes
					$dossier_temp->load_affaire($PDOdb);
					$dossier_temp->load_facture($PDOdb);
					$dossier_temp->load_factureFournisseur($PDOdb);
					$dossier_temp->calculSolde();
					$dossier_temp->calculRenta($PDOdb);
					
					$TLines[] = array(
						'ID' => $res['iddossier'],//*
						'refDosCli' => $dossier_temp->financement->reference,
						'refDosLea' => $dossier_temp->financementLeaser->reference,
						'ID affaire' => $res['idaffaire'], //*
						'Affaire' => $res['reference'],
						'nature_financement' =>$res['nature_financement'],
						'fk_soc' => $res['fk_soc'],//*
						'nomCli' => $nomCli,
						'nomLea' => $nomLea,
						'status_1' => $error_1,
						'status_2' => $error_2,
						'status_3' => $error_3,
						'status_4' => $error_4,
						'status_5' => $error_5,
						'Durée' => $dossier_temp->financement->duree,
						'Montant' => $dossier_temp->financement->montant,
						'Echéance' => $dossier_temp->financement->echeance,
						'Prochaine' => $dossier_temp->financement->get_date('date_prochaine_echeance'),
						'date_debut' => $dossier_temp->financement->get_date('date_debut'),
						'Fin' => $dossier_temp->financement->get_date('date_fin'),
						'fact_materiel' => '',
						'visa_renta'=>$dossier_temp->Tvisa[$dossier_temp->visa_renta] // Doc drive "Retours suite réunion du 29.01.2016" 
						,'renta_previsionnelle'=>number_format($dossier_temp->renta_previsionnelle,2, ',', ' ').' € / '.number_format($dossier_temp->marge_previsionnelle,2).' %'
						,'renta_attendue'=>number_format($dossier_temp->renta_attendue,2, ',', ' ').' € / '.number_format($dossier_temp->marge_attendue, 2).' %'
						,'renta_reelle'=>number_format($dossier_temp->renta_reelle,2, ',', ' ').' € / '.number_format($dossier_temp->marge_reelle,2).' %'
					);
					
					$i++;
					if (!$display_all && $i > $maxDisplay) break;
				}
			}
		}

	}

	//pre($TLines,true);
	
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formDossier', 'GET');
	echo $form->hidden('liste_renta_negative', '1');
	$aff = new TFin_affaire;
	$dos = new TFin_dossier;
	
	//pre($TLines,true);exit;
	//echo $sql;
	print $r->renderArray($PDOdb, $TLines, array(
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
			//,'fact_materiel'=>'<a href="'.DOL_URL_ROOT.'/compta/facture.php?facid=@fk_fact_materiel@">'.img_object('', 'bill').' @val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$aff->TNatureFinancement
			,'visa_renta'=>$dos->Tvisa
		)
		,'hide'=>array('fk_soc','ID','ID affaire','fk_fact_materiel')
		,'type'=>array()//'date_debut'=>'date','Fin'=>'date','Prochaine'=>'date', 'Montant'=>'money', 'Echéance'=>'money')
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
			,'status_1'=>$TErrorStatus['error_1']
			,'status_2'=>$TErrorStatus['error_2']
			,'status_3'=>$TErrorStatus['error_3']
			,'status_4'=>$TErrorStatus['error_4']
			,'status_5'=>$TErrorStatus['error_5']
			,'fact_materiel'=>'Facture matériel'
			,'visa_renta'=>'Visa <br />Rentabilité'
			,'renta_previsionnelle'=>'Rentabilité <br />Prévisionnelle'
			,'renta_attendue'=>'Rentabilité <br />Attendue'
			,'renta_reelle'=>'Rentabilité <br />Réelle'
		)
		,'orderBy'=> array('ID'=>'DESC','fc.reference'=>'ASC')
		/*,'search'=>array(
			'refDosCli'=>array('recherche'=>true, 'table'=>'fc', 'field'=>'reference')
			,'refDosLea'=>array('recherche'=>true, 'table'=>'fl', 'field'=>'reference')
			,'nomCli'=>array('recherche'=>true, 'table'=>'c', 'field'=>'nom')
			,'nomLea'=>array('recherche'=>true, 'table'=>'l', 'field'=>'nom')
			,'nature_financement'=>array('recherche'=>$aff->TNatureFinancement,'table'=>'a')
			//,'visa_renta'=>array('recherche'=>$dos->Tvisa,'table'=>'d', 'field' => 'visa_renta')
			//,'date_debut'=>array('recherche'=>'calendars', 'table'=>'f')
		)*/
		,'eval'=>array('fact_materiel'=>'_get_facture_mat(@ID affaire@);')
		
	));
	$form->end();
	
	if(isset($_REQUEST['fk_leaser']) && !empty($_REQUEST['fk_leaser'])){
		$fk_leaser = GETPOST('fk_leaser');
		?>
		<div class="tabsAction">
				<a href="?action=exportXML&fk_leaser=<?php echo $fk_leaser; ?>" class="butAction">Exporter</a>
				<a href="?action=generateXML" class="butAction">Générer le XML Lixxbail</a>
				<a href="?action=generateXMLandupload&fk_leaser=<?php echo $fk_leaser; ?>" onclick="confirm('Etes-vous certain de vouloir générer puis uploader le fichier XML?')" class="butAction">Générer le XML Lixxbail et envoyer au Leaser</a>
				<a href="?action=setnottransfer&fk_leaser=<?php echo $fk_leaser; ?>" onclick="confirm('Etes-vous certain de vouloir rendre non transférable les dossiers?')" class="butAction">Rendre tous les Dossiers non transférable</a>
		</div>
		<?php
	}
	else{
		?>
		<div class="tabsAction">
				<a href="?liste_renta_negative=1&action=exportListeDossier" class="butAction">Exporter</a><?php print img_warning($langs->trans('CareAboutExportMayBeTakeLongTime', count($Tres))); ?>
		</div>
		<?php
	}

	//Cas action export CSV de la liste des futurs affaire transféré en XML
	$action = GETPOST('action');
	if($action === 'exportListeDossier'){
		_getExport($TLines);
	}
	
	llxFooter();
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
	
	$filepath = $url.$filename;
	$file = fopen($filepath,'w');

	//Ajout première ligne libelle
	$TLabel = array('Contrat','Contrat Leaser','Affaire','Nature','Client','Leaser','Duree','Montant','Echeance','Prochaine','Debut','Fin','Facture Materiel');
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
	
	$sql = "SELECT f.rowid, f.facnumber
			FROM ".MAIN_DB_PREFIX."element_element as ee
				LEFT JOIN ".MAIN_DB_PREFIX."facture as f ON (ee.fk_target = f.rowid)
			WHERE ee.fk_target=f.rowid AND ee.sourcetype = 'affaire' AND ee.targettype = 'facture' AND ee.fk_source = ".$fk_source."";

	$PDOdb->Execute($sql);
	
	$link = '';
	while($PDOdb->Get_line()){
		if($withlink){
			$link .= '<a href="'.DOL_URL_ROOT.'/compta/facture.php?facid='.$PDOdb->Get_field('rowid').'">'.img_object('', 'bill').' '.$PDOdb->Get_field('facnumber').'</a><br>';
		}
		else{
			$link .= $PDOdb->Get_field('facnumber')." ";
		}
	}
	
	$PDOdb->close();
	
	return $link;
}

function _fiche(&$PDOdb, &$dossier, $mode) {
	global $user,$db,$conf;
	
	TFinancementTools::check_user_rights($dossier);
	
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
	
	$id_simu = _getIDSimuByReferenceDossierLeaser($financementLeaser->reference);
	if(!empty($id_simu)) $link_simu = '<a href="'.dol_buildpath('/financement/simulation.php?id='.$id_simu, 2).'" >'.$financementLeaser->reference.'</a>';
	
	$TFinancementLeaser=array(
			'id'=>$financementLeaser->getId()
			,'reference'=>(empty($link_simu) || GETPOST('action') == 'edit') ? $formRestricted->texte('', 'leaser[reference]', $financementLeaser->reference, 20,255,'','','à saisir') : $link_simu
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
			
			,'detail_fact' => dol_buildpath('/fourn/facture/list.php?search_ref_supplier='.$financementLeaser->reference,2)
			
			
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
	
	$dossier_for_integral = new TFin_dossier;
	$dossier_for_integral->load($PDOdb, $dossier->getId());
	$dossier_for_integral->load_facture($PDOdb,true);
	//$dossier_for_integral->format_facture_integrale($PDOdb);
	//pre($dossier_for_integral->TFacture,true);
	$sommeRealise = $sommeNoir = $sommeCouleur = $sommeCopieSupCouleur = $sommeCopieSupNoir = 0;
	//list($sommeRealise,$sommeNoir,$sommeCouleur) = $dossier_for_integral->getSommesIntegrale($PDOdb);
	list($sommeCopieSupNoir,$sommeCopieSupCouleur) = $dossier_for_integral->getSommesIntegrale($PDOdb,true);
	
	$decompteCopieSupNoir = $sommeCopieSupNoir * $dossier_for_integral->quote_part_noir;
	$decompteCopieSupCouleur = $sommeCopieSupCouleur * $dossier_for_integral->quote_part_couleur;
	
	$soldepersointegrale = $decompteCopieSupCouleur + $decompteCopieSupNoir;

	$soldepersointegrale = ($soldepersointegrale * (FINANCEMENT_PERCENT_RETRIB_COPIES_SUP/100)); //On ne prend que 80% conformément  la règle de gestion

	//echo $soldepersointegrale;
	//echo $sommeRealise." ".$sommeNoir." ".$sommeCouleur;
	//pre($dossier->financement,true);exit;
	//echo $dossier->getSolde($PDOdb, 'SRNRSAME');exit;
	
	//Calcul du Solde Renouvelant et Non Renouvelant CPRO 
	/*$dossier->financement->capital_restant = $dossier->financement->montant;
	$dossier->financement->total_loyer = $dossier->financement->montant;
	for($i=0; $i<$dossier->financement->numero_prochaine_echeance;$i++){
		$capital_amortit = $dossier->financement->amortissement_echeance( $i+1 ,$dossier->financement->capital_restant);
		$part_interet = $dossier->financement->echeance - $capital_amortit;
		$dossier->financement->capital_restant-=$capital_amortit;
		
		$dossier->financement->total_loyer -= $dossier->financement->echeance;
	}*/

	$e = new DaoMulticompany($db);
	$e->getEntities();
	$TEntities = array();
	foreach($e->entities as $obj_entity) $TEntities[$obj_entity->id] = $obj_entity->label;
	
	$entity = empty($dossier->entity) ? getEntity('fin_dossier') : $dossier->entity;
	
	if(TFinancementTools::user_courant_est_admin_financement() && empty($conf->global->FINANCEMENT_DISABLE_SELECT_ENTITY)){
		$entity_field = $form->combo('', 'entity', TFinancementTools::build_array_entities(), $entity);
	} else {
		$entity_field = TFinancementTools::get_entity_translation($entity).$form->hidden('entity', $entity);
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

/* TODO remove
				,'soldeRCPRO'=>($dossier->nature_financement == 'INTERNE') ? $dossier->getSolde($PDOdb, 'SRNRSAME',$dossier->_get_num_echeance_from_date(time()) +1) : $dossier->getSolde($PDOdb, 'SRCPRO')//SRCPRO
				,'soldeNRCPRO'=>($dossier->nature_financement == 'INTERNE') ? $dossier->getSolde($PDOdb, 'SRNRSAME',$dossier->_get_num_echeance_from_date(time()) +1) : $dossier->getSolde($PDOdb, 'SNRCPRO')//SNRCPRO
*/
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
				,'commentaire_visa'=>$form->zonetexte('', 'commentaire_visa', $dossier->commentaire_visa,100,5,'')
				,'quote_part_noir' => $form->texte('', 'quote_part_noir', $dossier_for_integral->quote_part_noir, 10)
				,'quote_part_couleur' => $form->texte('', 'quote_part_couleur', $dossier_for_integral->quote_part_couleur, 10)
				,'somme_sup_noir' => $sommeCopieSupNoir
				,'somme_sup_coul' => $sommeCopieSupCouleur
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
	
	llxFooter();
	
}



function _getIDSimuByReferenceDossierLeaser($num_dossier_leaser) {
	
	global $db;
	
	$num_dossier_leaser = trim($num_dossier_leaser);
	if(empty($num_dossier_leaser)) return 0;
	
	$sql = 'SELECT rowid
			FROM '.MAIN_DB_PREFIX.'fin_simulation
			WHERE numero_accord = "'.$num_dossier_leaser.'"';
	
	$resql = $db->query($sql);
	$res = $db->fetch_object($resql);
	
	return $res->rowid;
	
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
