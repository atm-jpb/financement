<?php
	require('config.php');
	require('./class/affaire.class.php');
	require('./class/dossier.class.php');
	require('./class/grille.class.php');
	require('./lib/financement.lib.php');

	require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
	require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

	dol_include_once('/assetatm/class/asset.class.php');
	dol_include_once('/product/class/product.class.php');
	dol_include_once('/compta/facture/class/facture.class.php');

	$langs->load('financement@financement');

	if (!$user->rights->financement->affaire->read)	{ accessforbidden(); }


	$affaire=new TFin_Affaire;
	$ATMdb = new TPDOdb;
	$tbs = new TTemplateTBS;

	$mesg = '';
	$error=false;

	if(isset($_REQUEST['action'])) {
		switch($_REQUEST['action']) {
			case 'add':
			case 'new':

				$affaire->set_values($_REQUEST);

				//$affaire->save($ATMdb);
				_fiche($ATMdb, $affaire,'edit');

				break;
			case 'edit'	:

				$affaire->load($ATMdb, $_REQUEST['id']);

				_fiche($ATMdb, $affaire,'edit');
				break;

			case 'save':
				$affaire->load($ATMdb, $_REQUEST['id']);
				$affaire->set_values($_REQUEST);
				$affaire->fk_soc = isset($_REQUEST['socid']) ? $_REQUEST['socid'] : $_REQUEST['fk_soc'];
				//$ATMdb->db->debug=true;
				//print_r($_REQUEST);

				$affaire->save($ATMdb);
				$affaire->load($ATMdb, $_REQUEST['id']);

				// Création de la facture matériel
				$facRef = GETPOST('facRef');
				$facDate = dol_mktime(0, 0, 0, GETPOST('facDatemonth'), GETPOST('facDateday'), GETPOST('facDateyear'));
				$facSerialNumber = GETPOST('facSerialNumber');
                $facRefMat = GETPOST('facRefMat');
				$facLabel = GETPOST('facLabel');
				$facLeaser = GETPOST('facLeaser');
				if(! empty($facRef) && ! empty($facDate) && ! empty($facSerialNumber) && ! empty($facRefMat) && ! empty($facLabel) && ! empty($affaire->montant) && ! empty($facLeaser)) {
				    $f = new Facture($db);
				    $f->date = date('Y-m-d', $facDate);
				    $f->socid = $facLeaser;

				    // Vérification de la référence facture
                    if(isSimilarInvoiceRefExists($facRef, $affaire->entity)) {
                        setEventMessage($langs->trans('EquipmentInvoiceRefAlreadyInUse', $facRef), 'errors');
                    }
                    else {
                        $old_entity = $conf->entity;
                        switchEntity($affaire->entity); // Nécessaire car la création prend pour entité la $conf->entity
                        $res = $f->create($user);
                        switchEntity($old_entity);

                        if($res > 0) {
                            $p = new Product($db);
                            $res = $p->fetch('', $facRefMat);
                            if($res == 0) {
                                $p->ref = $facRefMat;
                                $p->label = $facLabel;
                                $res = $p->create($user);
                            }

                            $taux_tva = 20;
                            $f->addline($facSerialNumber, $affaire->montant, 1, $taux_tva, 0, 0, $p->id);

                            $f->ref = $facRef;
                            $f->statut = 0;
                            $resUpdate = $f->validate($user);   // ça update aussi la ref donc c'est ok
                            if($resUpdate < 0) {
                                setEventMessage($langs->trans('EquipmentInvoiceRefAlreadyInUse', $facRef), 'warnings');
                            }

                            if($res > 0) {
                                $f->add_object_linked('affaire', $affaire->rowid);

                                $asset = new TAsset;
                                $asset->serial_number = $facSerialNumber;
                                $asset->fk_product = $p->id;

                                $asset->add_link($f->id, 'facture');
                                $asset->add_link($affaire->rowid, 'affaire');
                                $asset->save($ATMdb);

                                setEventMessage($langs->trans('EquipmentInvoiceCreated'));
                            }
                            else {
                                setEventMessage($langs->trans($p->error, $facRefMat), 'errors');
                            }
                        }
                        else {
                            setEventMessage($langs->trans('EquipmentInvoiceCreationError', $f->error), 'errors');
                        }
                    }
                }

				header('Location: '.$_SERVER['PHP_SELF'].'?id='.$affaire->rowid);
				exit;

				break;


			case 'delete':
				$affaire->load($ATMdb, $_REQUEST['id']);
				//$ATMdb->db->debug=true;
				$affaire->delete($ATMdb);

				?>
				<script language="javascript">
					document.location.href="?delete_ok=1";
				</script>
				<?php


				break;
			case 'add_dossier':
			//$ATMdb->db->debug=true;
				$affaire->load($ATMdb, $_REQUEST['id']);
				$affaire->set_values($_REQUEST);

				if(!$affaire->addDossier($ATMdb, null, $_REQUEST['dossier_to_add'])) {
					$mesg = '<div class="error">Impossible d\'ajouter ce dossier à l\'affaire. </div>';
					$error=true;

				}
				else {
					$mesg = '<div class="ok">Dossier ajouté à l\'affaire</div>';
				}
				//exit($mesg);
				$affaire->save($ATMdb);

				_fiche($ATMdb, $affaire,'edit');

				break;

			case 'delete_dossier':
				//$ATMdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				$affaire->load($ATMdb, $_REQUEST['id']);


				if($affaire->deleteDossier($ATMdb, $_REQUEST['id_dossier'])) {
					$mesg = '<div class="ok">Dossier retiré de l\'affaire</div>';
				}

				$affaire->save($ATMdb);

				_fiche($ATMdb, $affaire,'edit');

				break;

			case 'add_facture_mat':
			//$ATMdb->db->debug=true;
				$affaire->load($ATMdb, $_REQUEST['id']);
				$affaire->set_values($_REQUEST);

				//echo $_REQUEST['facture_mat_to_add'];exit;

				if(!$affaire->addFactureMat($ATMdb,$_REQUEST['facture_mat_to_add'])) {
					$mesg = '<div class="error">Impossible de lier cette facture matériel à l\'affaire. </div>';
					$error=true;

				}
				else {
					$mesg = '<div class="ok">Facture matériel liée à l\'affaire</div>';
				}
				//exit($mesg);
				$affaire->save($ATMdb);

				_fiche($ATMdb, $affaire,'edit');

				break;

			case 'delete_facture_mat':
				//$ATMdb->db->debug=true;
				//$affaire->set_values($_REQUEST);
				/*$affaire->load($ATMdb, $_REQUEST['id']);


				if($affaire->deleteDossier($ATMdb, $_REQUEST['id_dossier'])) {
					$mesg = '<div class="ok">Dossier retiré de l\'affaire</div>';
				}

				$affaire->save($ATMdb);*/

				_fiche($ATMdb, $affaire,'edit');

				break;
		}

	}
	elseif(isset($_REQUEST['id'])) {
		$affaire->load($ATMdb, $_REQUEST['id']);

		_fiche($ATMdb, $affaire, 'view');

	}
	else {
		/*
		 * Liste
		 */
		 _liste($ATMdb, $affaire);
	}



	llxFooter();

function _liste(&$ATMdb, &$affaire) {
	global $langs,$conf, $db;

	llxHeader('','Affaires');

	$errone = GETPOST('errone');

	$r = new TSSRenderControler($affaire);
	$sql="SELECT a.rowid as 'ID', a.reference, e.label as entity_label, a.montant as 'Montant', a.fk_soc, s.nom
	, a.nature_financement, a.type_financement, a.contrat, a.date_affaire
		FROM @table@ a LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc=s.rowid)
		LEFT JOIN ".MAIN_DB_PREFIX."entity e ON (a.entity = e.rowid)
		WHERE a.entity IN(".getEntity('fin_dossier', true).")";
	//echo $sql; exit;

	if($errone){
		$sql="SELECT a.rowid as 'ID', a.reference,
                          ROUND(ABS(SUM(df.montant) - SUM(a.montant)), 2) as 'Ecart', e.label as entity_label, a.montant as 'Montant Affaire', SUM(df.montant) as 'Montant Financé', df.fk_fin_dossier, a.fk_soc, s.nom , a.nature_financement, a.type_financement, a.contrat, a.date_affaire 
			  FROM llx_fin_affaire a 
			  	LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc=s.rowid) 
			  	LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (da.fk_fin_affaire = a.rowid) 
			  	LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (d.rowid = da.fk_fin_dossier) 
			  	LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement df ON (df.fk_fin_dossier = d.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."entity e ON (a.entity = e.rowid) 
			  WHERE a.entity IN(".getEntity('fin_dossier', true).")
			  	AND df.type = 'LEASER' ";
//			  	AND df.montant != a.montant ";
//	$sql.="		  	AND ABS(df.montant - a.montant) > 0.01";
	}

	$THide = array('fk_soc', 'ID', 'fk_fin_dossier');

	if(isset($_REQUEST['socid'])) {
		$sql.= ' AND (a.fk_soc='.$_REQUEST['socid'].' OR  a.fk_soc IN (
				SELECT ss.rowid FROM '.MAIN_DB_PREFIX.'societe as ss WHERE ss.siren = (
					SELECT siren from '.MAIN_DB_PREFIX.'societe WHERE rowid = '.$_REQUEST['socid'].'
				) 
				AND siren != "")
			)';
		$societe = new Societe($db);
		$societe->fetch($_REQUEST['socid']);
		$head = societe_prepare_head($societe);

		$THide[] = 'Société';

		// Affichage résumé client
		$formDoli = new Form($db);

		$TBS=new TTemplateTBS();

		print $TBS->render('./tpl/client_entete.tpl.php'
			,array(

			)
			,array(
				'client'=>array(
					'dolibarr_societe_head'=>dol_get_fiche_head(societe_prepare_head($societe), 'affaire', $langs->trans("ThirdParty"),0,'company')
					,'showrefnav'=>$formDoli->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom')
					,'idprof1'=>$societe->idprof1
					,'adresse'=>$societe->address
					,'cpville'=>$societe->zip.($societe->zip && $societe->town ? " / ":"").$societe->town
					,'pays'=>picto_from_langcode($societe->country_code).' '.$societe->country
					,'code_client'=>$societe->code_client
				)
				,'view'=>array(
					'mode'=>'view'
				)
			)
		);
	}

	if($errone) $sql .= " GROUP BY a.rowid
			      HAVING ROUND(ABS(SUM(df.montant) - SUM(a.montant)), 2) > 0.01";
 	//echo $sql;
	$form=new TFormCore($_SERVER['PHP_SELF'], 'formAffaire', 'GET');

	$TEntityName = TFinancementTools::build_array_entities();

	$r->liste($ATMdb, $sql, array(
		'limit'=>array(
			'page'=>1
			,'nbLine'=>'30'
			,'global'=>'1000'
		)
		,'link'=>array(
			'nom'=>'<a href="'.DOL_URL_ROOT.'/societe/card.php?socid=@fk_soc@">'.img_picto('','object_company.png', '', 0).' @val@</a>'
			,'reference'=>'<a href="?id=@ID@">@val@</a>'
		)
		,'translate'=>array(
			'nature_financement'=>$affaire->TNatureFinancement
			,'type_financement'=>$affaire->TTypeFinancement
			,'contrat'=>$affaire->TContrat
		)
		,'hide'=>$THide
		,'type'=>array('date_affaire'=>'date', 'Montant'=>'money')
		,'liste'=>array(
			'titre'=>'Liste des affaires'
			,'image'=>img_picto('','title.png', '', 0)
			,'picto_precedent'=>img_picto('','previous.png', '', 0)
			,'picto_suivant'=>img_picto('','next.png', '', 0)
			,'noheader'=> (int)isset($_REQUEST['socid'])
			,'messageNothing'=>"Il n'y a aucune affaire à afficher"
			,'order_down'=>img_picto('','1downarrow.png', '', 0)
			,'order_up'=>img_picto('','1uparrow.png', '', 0)
			,'picto_search'=>img_picto('','search.png', '', 0)
		)
		,'title'=>array(
			'reference'=>'Numéro d\'affaire'
			,'nom'=>'Société'
			,'entity_label'=>'Partenaire'
			,'nature_financement'=>'Nature'
			,'type_financement'=> 'Type'
			,'contrat'=> 'Type de contrat'
			,'date_affaire'=>'Date de l\'affaire'
		)
		,'orderBy'=>array('date_affaire'=>'DESC','reference'=>'ASC')
		,'search'=>array(
			'reference'=>true
			,'nom'=>array('recherche'=>true,'table'=>'s')
			,'nature_financement'=>$affaire->TNatureFinancement
			,'entity_label'=>array('recherche'=>$TEntityName, 'table'=>'e', 'field'=>'rowid')
			,'type_financement'=>$affaire->TTypeFinancement
			,'contrat'=>$affaire->TContrat
			,'date_affaire'=>'calendar'
		),'operator'=>array(
			'entity_label' => '='
		)
		,'eval'=>array(

		)
		,'position'=>array(
			'text-align'=>array(
				'reference'=>'center'
				,'entity_label'=>'center'
				,'nature_financement'=>'center'
				,'type_financement'=>'center'
				,'nom'=>'center'
				,'contrat'=>'center'
				,'date_affaire'=>'center'
			)
		)
	));

	$form->end();

	if(isset($_REQUEST['socid'])) {
		?><div class="tabsAction"><a href="?action=new&fk_soc=<?php echo $_REQUEST['socid']; ?>" class="butAction">Créer une affaire</a></div><?php
	}

	llxFooter();
}

function _fiche(&$ATMdb, &$affaire, $mode) {
    global $db, $user, $conf, $langs;

    $result = restrictedArea($user, 'financement', $affaire->getID(), 'fin_affaire&societe', 'affaire', 'fk_soc', 'rowid');

	if(empty($affaire->societe) || empty($affaire->societe->id)) {
		$affaire->societe = new Societe($db);
		$affaire->societe->fetch($affaire->fk_soc);
	}

	/*
	 * Liste des dossiers rattachés à cette affaire
	 */
	$TDossier=array();
	foreach($affaire->TLien as &$lien) {
		$dossier = &$lien->dossier;
		$dossier->load_financement($ATMdb);
		$ref = '';
		if($dossier->nature_financement == 'INTERNE') {
			$ref.= empty($dossier->financement->reference) ? '(vide)' : $dossier->financement->reference;
			$ref.= ' / ';
		}
		$ref.= empty($dossier->financementLeaser->reference) ? '(vide)' : $dossier->financementLeaser->reference;
		$TDossier[]=array(
			'id'=>$dossier->getId()
			,'reference'=>$ref
			,'date_debut'=>$dossier->get_date('date_debut')
			,'date_fin'=>$dossier->get_date('date_fin')
			,'montant'=>$dossier->montant
			,'taux'=>$dossier->taux
			,'incident_paiement'=>$dossier->TIncidentPaiement[$dossier->incident_paiement]
			,'duree'=>$dossier->duree
			,'echeance'=>$dossier->echeance
			,'modif_dossier'=>'<a href="dossier.php?id='.$dossier->getId().'&action=edit">'.img_edit().'</a>'
		);
	}

	$TAsset=array();
	foreach($affaire->TAsset as $link) {

		$row = $link->asset->get_values();

		// Lien produit
		if(!empty($link->asset->fk_product)) {
			$product = new Product($db);
			$product->fetch($link->asset->fk_product);

			$row['produit'] = $product->getNomUrl(true).' '.$product->label;
		}
		else {
		    // Bout de code permettant de créer à la main un matériel
		    $row['produit'] = '<i class="fas fa-plus-circle" style="cursor: pointer;" title="'.$langs->trans('CreateMateriel').'"></i>';
            $row['produit'] .= "<script type=\"text/javascript\">
                                    $(document).ready(function() {
                                        function createMateriel() {
                                            let libelleProduit = $('#libelleProduit').val();
                                            let serialNumber = $('#serialNumber').val();
                                            let refProduit = $('#refProduit').val();
                                            let marque = $('#marque').val();
                                            let affaireEntity = $('#affaireEntity').val();

                                            $.ajax({
                                                url: '".dol_buildpath('/financement/script/interface.php', 1)."',
                                                data: {
                                                    json: 1,
                                                    action: 'createMateriel',
                                                    libelleProduit: libelleProduit,
                                                    serialNumber: serialNumber,
                                                    refProduit: refProduit,
                                                    marque: marque,
                                                    affaireEntity: affaireEntity
                                                },
                                                dataType: 'json',
                                                type: 'POST',
                                                async: false
                                            });
                                        }

                                        $('i.fa-plus-circle').on('click', function() {
                                            $('#serialNumber').val($(this).parents('tr').find('td.asset a').text());

                                            $('div#addMateriel').dialog({
                                                modal: true,
                                                minWidth: 400,
                                                minHeight: 100,
                                                buttons: [{
                                                    text: '".$langs->trans('Ok')."',
                                                    click: function() {
                                                        createMateriel();
                                                        $(this).dialog('close');

                                                        location.reload();
                                                    }
                                                }, { text: '".$langs->trans('Cancel')."', click: function () { $(this).dialog('close'); }}
                                                ]
                                            });
                                        });
                                    });
                                </script>";
        }

		// Lien facture
		$row['facture'] = '';

		$TIdFacture = TRequeteCore::get_id_from_what_you_want($ATMdb,MAIN_DB_PREFIX.'assetatm_link',array('fk_asset'=>$link->asset->getId(), 'type_document'=>'facture'),'fk_document');
		if(!empty($TIdFacture[0])) {
			$facture = new Facture($db);
			$facture->fetch($TIdFacture[0]);

			$row['facture'] = $facture->getNomUrl(1);
		}

		$TAsset[]=$row;
	}

	/*
	 * Pour autocomplete ajout dossier
	 */
	$otherDossier=$otherFactureMat='';
	if($mode=='edit') {
		$ATMdb=new TPDOdb;
		//$Tab = TRequeteCore::get_id_from_what_you_want($ATMdb,'llx_fin_dossier', " solde>0 AND reference!='' " ,'reference');

		$sql = "SELECT DISTINCT(f.reference) as reference 
		FROM ".MAIN_DB_PREFIX."fin_dossier_financement f INNER JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (f.fk_fin_dossier=d.rowid)
		WHERE d.solde>0 AND f.reference!=''";
	//	print $sql;
		$Tab = TRequeteCore::_get_id_by_sql($ATMdb, $sql,'reference');

		$otherDossier = '["'. implode('","', $Tab). '"]';

		$sql = "SELECT DISTINCT(f.ref) as reference
				FROM ".MAIN_DB_PREFIX."facture f
					LEFT JOIN ".MAIN_DB_PREFIX."facturedet as fd ON (fd.fk_facture = f.rowid)
				WHERE LOCATE('Matricule',fd.description) > 0";
	//	print $sql;
		$Tab = TRequeteCore::_get_id_by_sql($ATMdb, $sql,'reference');

		$otherFactureMat = '["'. implode('","', $Tab). '"]';

		$sql = "SELECT rowid, nom 
				FROM ".MAIN_DB_PREFIX."societe s
				WHERE entity IN (".getEntity('societe', 1).")";
	//	print $sql;
		$Tab = TRequeteCore::_get_id_by_sql($ATMdb, $sql,'nom', 'rowid');

		foreach ($Tab as $key => $value) {
			$data = '{ value: "'.$key.'", label: "'.htmlspecialchars($value).'"}';
			$TSoc[] = $data;
		}
		$otherSoc = '['. implode(',', $TSoc). ']';

		$ATMdb->close();
	}

	$extrajs = array('/financement/js/dossier.js');
	llxHeader('','Affaires','','','','',$extrajs);

	$form=new TFormCore($_SERVER['PHP_SELF'],'formAff','POST');
	$form->Set_typeaff($mode);
	$doliform = new Form($db);
	echo $form->hidden('id', $affaire->getId());
	echo $form->hidden('action', 'save');
	echo $form->hidden('fk_soc', $affaire->fk_soc);

	$formRestricted=new TFormCore;
	if($mode=='edit' && ( (!empty($affaire->TLien[0]->dossier->financementLeaser->okPourFacturation) && $affaire->TLien[0]->dossier->financementLeaser->okPourFacturation!='AUTO')
		 //|| count($affaire->TLien[0]->dossier->TFactureFournisseur)==0
		 || $user->rights->financement->admin->write )  ) $mode_aff_fLeaser = 'edit';
	else $mode_aff_fLeaser='view';
	//$mode_aff_fLeaser = $mode;
	$formRestricted->Set_typeaff( $mode_aff_fLeaser );

	//require('./tpl/affaire.tpl.php');
	$TBS=new TTemplateTBS();

	$e = new DaoMulticompany($db);
	$e->getEntities();
	$TEntities = array();
	foreach($e->entities as $obj_entity) $TEntities[$obj_entity->id] = $obj_entity->label;

	$entity = empty($affaire->entity) ? getEntity('fin_dossier', false) : $affaire->entity;

	$TEntityName = TFinancementTools::build_array_entities();
	if(TFinancementTools::user_courant_est_admin_financement() && empty($conf->global->FINANCEMENT_DISABLE_SELECT_ENTITY)){
		$entity_field = $form->combo('', 'entity', $TEntityName, $entity);
	} else {
		$entity_field = $TEntityName[$entity].$form->hidden('entity', $entity);
	}

    $facRef = $facDate = $facSerialNumber = $facRefMat = $facLabel = $facLeaser = '';
	if($mode == 'edit') {
	    $facRef = '<input type="text" name="facRef" />';
	    $facDate = $doliform->select_date('', 'facDate', 0, 0, 0, '', 1, 0, 1);
	    $facSerialNumber = '<input type="text" name="facSerialNumber" />';
        $facRefMat = '<input type="text" name="facRefMat" />';
	    $facLabel = '<input type="text" name="facLabel" />';

        $selected = '';
        if(! empty($affaire->TLien[0])) $selected = $affaire->TLien[0]->dossier->financementLeaser->fk_soc;
        $facLeaser = $doliform->select_company($selected, 'facLeaser', 'fournisseur = 1', 1);
    }

	print $TBS->render('./tpl/affaire.tpl.php'
		,array(
			'dossier'=>$TDossier,
            'asset'=>$TAsset
		)
		,array(
			'affaire'=>array(
				'id'=>$affaire->rowid,
                'ref'=>$affaire->reference,
                'entity'=>$entity_field,
                'fk_entity' => $affaire->entity,
                'reference'=>$formRestricted->texte('', 'reference', $affaire->reference, 100,255,'','','à saisir'),
                'nature_financement'=>$formRestricted->combo('', 'nature_financement', $affaire->TNatureFinancement , $affaire->nature_financement),
                'type_financement'=>$formRestricted->combo('', 'type_financement', $affaire->TTypeFinancement , $affaire->type_financement),
                'contrat'=>$formRestricted->combo('', 'contrat', $affaire->TContrat , $affaire->contrat),
                'type_materiel'=>$formRestricted->combo('', '', $affaire->TTypeMateriel , $affaire->type_materiel),
                'date_affaire'=>$formRestricted->calendrier('', 'date_affaire', $affaire->date_affaire,10),
                'montant'=>$formRestricted->texte('', 'montant', $affaire->montant, 20,255,'','','à saisir'),
                'montant_ok'=>$affaire->somme_dossiers, // somme des dossiers rattachés
                'solde'=>$affaire->solde, // montant à financer - somme des dossiers
                'date_maj'=>$affaire->get_date('date_maj','d/m/Y à H:i:s'),
                'date_cre'=>$affaire->get_date('date_cre','d/m/Y'),
                'socid'=>$affaire->societe->id,
                'societe'=>$mode == "edit" ? $affaire->societe->nom : $affaire->societe->getNomUrl(1),
                'montant_val'=>$affaire->montant,
                'force_update'=>$formRestricted->checkbox1('', 'force_update', 1),
                'nature_financement_val'=>$affaire->nature_financement,
                'addDossierButton'=>(($affaire->nature_financement!='') ? 1 : 0),
                'url_therefore'=>FIN_THEREFORE_AFFAIRE_URL
			),
            'view'=>array(
				'mode'=>$mode,
                'otherDossier'=>$otherDossier,
                'otherFactureMat'=>$otherFactureMat,
                'otherSoc'=>$otherSoc,
                'userRight'=>((int)$user->rights->financement->affaire->write),
                'financement_verouille'=>($affaire->TLien[0]->dossier->financementLeaser->okPourFacturation === 'AUTO' && $user->rights->financement->admin->write) ? 'verrouille' : '',
                'creer_affaire' => ($affaire->nature_financement && $affaire->montant && $affaire->type_financement && $affaire->contrat) ? 'ok' : 'ko'
			),
            'fac' => array(
                'reference' => $facRef,
                'date' => $facDate,
                'num_serie' => $facSerialNumber,
                'refMat' => $facRefMat,
                'label' => $facLabel,
                'leaser' => $facLeaser
            )
		)
	);

	echo $form->end_form();
	// End of page

	global $mesg, $error;
	dol_htmloutput_mesg($mesg, '', ($error ? 'error' : 'ok'));
	llxFooter();
}
