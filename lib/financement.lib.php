<?php

class TFinancementTools {
	
	static function user_courant_est_admin_financement() {
		
		global $db, $user;
		
		dol_include_once('/user/class/usergroup.class.php');
		
		// On vérifie si l'utilisateur fait partie du groupe admin financement
		$g = new UserGroup($db);
		$g->fetch('', 'GSL_DOLIBARR_FINANCEMENT_ADMIN');
		if($g->id>0) {

			// On ne peut pas utiliser la fonction listgroupforuser parce qu'elle cherche les groupes dans lesquels se trouve l'utilisateur, mais uniquement les groupes qui sont dans l'entité courante
			$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'usergroup_user WHERE fk_user = '.$user->id.' AND fk_usergroup = '.$g->id);
			$res = $db->fetch_object($resql);
			
			if($res->rowid > 0) return true;
			else return false;
			
		}
		
		return false;		
	}
	
	function check_user_rights(&$object) {
		
		global $user, $conf,$db;
		
		dol_include_once('/core/lib/security.lib.php');

		if(!TFinancementTools::user_courant_est_admin_financement() && GETPOST('action') != 'new' && $object->entity != getEntity()) accessforbidden();
		
	}
	
	static function build_array_entities() {
		
		global $db;
		
		$obj_entity = new DaoMulticompany($db);
		$obj_entity->getEntities();
		
		$TEntityName = self::get_entity_translation();
		
		$TEntities = array();
		foreach($obj_entity->entities as $ent) {
			if(!empty($TEntityName[$ent->label]))
				$TEntities[$ent->id] = $TEntityName[$ent->label];
			else {
				$TEntities[$ent->id] = $ent->label;
			}
		}
		
		return $TEntities;
	}
	
	static function get_entity_translation($entity_id=false) {
		global $db, $conf;
		
		$TEntityAlternativeName = $conf->global->FINANCEMENT_TAB_ENTITY_ALTERNATIVE_NAME;
		// Constante de la forme : 1,Impression,C'PRO;2,Informatique,C'PRO info;3,Télécom,C'PRO Télécom
		if(empty($TEntityAlternativeName)) return $entity_id;
		$TEntityAlternativeName = explode(';', $TEntityAlternativeName);
		
		$TEntityName = array();
		
		foreach ($TEntityAlternativeName as $TData) {
			$tab_temp = explode(',', $TData);
			//var_dump($tab_temp);exit;
			if(empty($entity_id)) $TEntityName[$tab_temp[1]] = $tab_temp[2];
			else $TEntityName[$tab_temp[0]] = $tab_temp[2];
		}

		if(!empty($entity_id)) return $TEntityName[$entity_id];
		
		return $TEntityName;
		
	}
	
	static function add_css() {
		
		?>
			<style type="text/css">
				td[field="Montant"] {white-space:nowrap;}
				td[field="reference"] {text-align:center;}
				td[field="duree"] {text-align:center;}
				td[field="date_simul"] {text-align:center;}
				td[field="login"] {text-align:center;}
				td[field="accord"] {text-align:center;}
				td[field="type_financement"] {text-align:center;}
				
				td[field="refDosCli"] {text-align:center;}
				td[field="entity_id"] {text-align:center;}
				td[field="refDosLea"] {text-align:center;}
				td[field="Affaire"] {text-align:center;}
				td[field="nature_financement"] {text-align:center;}
				td[field="Montant"] {text-align:center;}
				td[field="duree"] {text-align:center;}
				td[field="echeance"] {text-align:center;}
				td[field="Prochaine"] {text-align:center;}
				td[field="date_debut"] {text-align:center;}
				td[field="Fin"] {text-align:center;}
				td[field="fact_materiel"] {text-align:center;}
				
				td[id="num_contrat"] {text-align:center;}
				td[id="entity_dossier"] {text-align:center;}
				td[id="leaser"] {text-align:center;}
				td[id="type_contrat"] {text-align:center;}
				td[id="Montant"] {text-align:center;}
				td[id="duree"] {text-align:center;}
				td[id="echeance"] {text-align:center;}
				td[id="debut_fin"] {text-align:center;}
				td[id="prochaine_echeance"] {text-align:center;}
				td[id="assurance"] {text-align:center;}
				td[id="maintenance"] {text-align:center;}
				td[id="solde_r"] {text-align:center;}
				td[id="solde_nr"] {text-align:center;}
				td[id="solde_r1"] {text-align:center;}
				td[id="solde_nr1"] {text-align:center;}
				td[id="solde_perso"] {text-align:center;}
				td[id="numcontrat_entity_leaser"] {text-align:center;}
				
				tr.liste_titre input{width:60%}
				
			</style>
		<?php
		
	}
	
}



/********************************************************************************************************************
 * Liste des dossiers qui doivent être contrôlés car il y a risque de rentabilité négative
 * 1 - loyer client < loyer leaser (Case à cocher sur le dossier de financement)
 * 2 - facture client < loyer leaser (Case à cocher sur la facture client)
 * 3 - facture client < loyer client (Case à cocher sur la facture client)
 * 4 - facture client impayée
 * 5 - échéance non facturée
 * 6 - dossier coché "anomalie"
 ********************************************************************************************************************/
function get_liste_dossier_renta_negative(&$PDOdb,$id_dossier = 0,$visaauto = false, $TRule = array()) {
	$dossier=new TFin_Dossier;
	$TDossiersError = array(
		'all'=>array(),
		'err1'=>array(),
		'err2'=>array(),
		'err3'=>array(),
		'err4'=>array(),
		'err5'=>array(),
		'err6'=>array()
	);
	
	$sqlfields = 'a.reference as refaffaire, a.rowid as fk_affaire, a.fk_soc as fk_client,';
	$sqlfields.= 'dfcli.reference as refdoscli, dfcli.duree, dfcli.periodicite, dfcli.montant, dfcli.echeance, dfcli.date_debut, dfcli.date_fin, dfcli.date_prochaine_echeance, ';
	$sqlfields.= 'd.renta_previsionnelle, d.renta_attendue, d.renta_reelle, d.marge_previsionnelle, d.marge_attendue, d.marge_reelle, ';
	$sqlfields.= 'dflea.reference as refdoslea, dflea.fk_soc as fk_leaser, scli.nom as nomcli, slea.nom as nomlea';
	$sqljoin = " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type = 'CLIENT')";
	$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type = 'LEASER')";
	$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."societe slea ON (slea.rowid = dflea.fk_soc)";
	$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire da ON (d.rowid = da.fk_fin_dossier) ";
	$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (da.fk_fin_affaire = a.rowid) ";
	$sqljoin.= " LEFT JOIN ".MAIN_DB_PREFIX."societe scli ON (scli.rowid = a.fk_soc)";
	$sqlwhere = " AND d.nature_financement = 'INTERNE'";
	$sqlwhere.= " AND d.montant_solde = 0";
	$sqlwhere.= " AND d.date_solde < '1970-00-00 00:00:00' ";
	$sqlwhere.= " AND d.entity IN (".getEntity('fin_dossier', TFinancementTools::user_courant_est_admin_financement()).")";
	$sqlwhere.= " AND d.reference NOT LIKE '%old%' ";
	$sqlwhere.= " AND d.reference NOT LIKE '%adj%' ";
	$sqlwhere.= " AND dfcli.date_fin > NOW() ";
	$sqlwhere.= " AND dfcli.date_debut < NOW() ";
	$sqlwhere.= " AND dflea.echeance > 0 ";
	if(!empty($id_dossier)) $sqlwhere.= " AND d.rowid = ".$id_dossier;
	//$sqlwhere.= " LIMIT 1 ";
	
	
	/***********************************************************************************************************************************************************
	 * 1 - Récupération de tous les dossiers dont "Visa renta négative" est à non, ce qui signifie que la règle 1 est à contrôler
	 ***********************************************************************************************************************************************************/
	if(!empty($TRule['rule1'])) {
		$sql = "SELECT d.rowid";
		$sql.= ", $sqlfields";
		$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
		$sql.= $sqljoin;
		$sql.= " WHERE d.visa_renta = 0";
		$sql.= $sqlwhere;
		
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = false;
			
			// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
			if(!empty($visaauto)) {
				$dossier->load($PDOdb, $rowid,false,true);
				
				// Si règle 1 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
				if($dossier->financement->echeance < $dossier->financementLeaser->echeance) {
					$renta_neg = true;
				} else {
					echo 'Dossier '.$dossier->financement->reference.' respecte la règle 1, case "Visa renta négative" cochée automatiquement.<br>';
					$dossier->visa_renta = 1;
					$dossier->save($PDOdb);
				}
			}
			
			if($renta_neg || empty($visaauto)) {
				if(!in_array($rowid, $TDossiersError['all'])) {
					$TDossiersError['all'][] = $rowid;
					$TDossiersError['data'][$rowid] = $res;
				}
				if(!in_array($rowid, $TDossiersError['err1'])) $TDossiersError['err1'][] = $rowid;
			}
		}
	}
	//pre($TDossiersError,true);
	//exit;
	
	/***********************************************************************************************************************************************************
	 * 2 - Récupération de tous les dossiers dont "Visa renta facture < loyer leaser" est à non
	 * ce qui signifie que la règle 2 est à contrôler
	 ***********************************************************************************************************************************************************/
	if(!empty($TRule['rule2'])) {
		$sql = "SELECT DISTINCT d.rowid";
		$sql.= ", $sqlfields";
		$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fext ON (fext.fk_object = f.rowid)";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
		$sql.= $sqljoin;
		$sql.= " WHERE (fext.visa_renta_loyer_leaser = 0 OR fext.visa_renta_loyer_leaser IS NULL)";
		$sql.= $sqlwhere;
		
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = false;
			
			// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
			if(!empty($visaauto)) {
				$dossier->load($PDOdb, $rowid, false, true);
				$dossier->load_facture($PDOdb,true);
				
				// On fait la somme des échéances des dossiers leaser associés à cette référence dossier (prise en compte des adjonctions)
				$sql = "SELECT SUM(dflea.echeance) as total_echeances";
				$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dfcli ON (dfcli.fk_fin_dossier = d.rowid AND dfcli.type='CLIENT')";
				$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement dflea ON (dflea.fk_fin_dossier = d.rowid AND dflea.type='LEASER')";
				$sql.= " WHERE dfcli.reference LIKE '".$dossier->financement->reference."%'";
				$sql.= " AND dfcli.montant_solde = 0";
				$sql.= " AND dfcli.date_solde < '1970-00-00 00:00:00'";
				$sql.= " AND dfcli.reference NOT LIKE '%old%'";
				$TRes = $PDOdb->ExecuteAsArray($sql);
				$total_echeances = $TRes[0]->total_echeances;
				
				// Attention on vérifie les factures et regroupements de factures
				$montant_facture = 0;
				foreach($dossier->TFacture as $p => $d) {
					// Récupération du montant facturé au client pour comparer aux loyers. Si plusieurs factures, on fait la somme
					if(is_array($d)) {
						foreach ($d as $i => $f) {
							$montant_facture += $f->total_ht;
						}
					} else {
						$montant_facture = $d->total_ht;
					}
					
					// Si on est sur la facture intercalaire, on compare avec le loyer intercalaire prévu
					$intercalaireOK = false;
					if($p == -1 && $montant_facture >= round($dossier->financement->loyer_intercalaire,2)) {
						$intercalaireOK = true;
					}
					
					// Comparaison au loyer leaser
					// Si règle 2 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
					if($montant_facture < $total_echeances && !$intercalaireOK) {
						$renta_neg = true;
					} else {
						echo 'Dossier '.$dossier->financement->reference.', période '.($p+1).' respecte la règle 2, case "Visa renta facture < loyer leaser" cochée automatiquement.<br>';
						if(is_array($d)) {
							foreach ($d as $i => $f) {
								$f->array_options['options_visa_renta_loyer_leaser'] = 1;
								$f->insertExtraFields();
								//echo $f->ref.'<br>';
							}
						} else {
							$d->array_options['options_visa_renta_loyer_leaser'] = 1;
							$d->insertExtraFields();
							//echo $d->ref.'<br>';
						}
					}
					//echo 'Dossier '.$dossier->financement->reference.', '.$montant_facture.' < '.$total_echeances.'<br>';
				}
			}
		
			if($renta_neg || empty($visaauto)) {
				if(!in_array($rowid, $TDossiersError['all'])) {
					$TDossiersError['all'][] = $rowid;
					$TDossiersError['data'][$rowid] = $res;
				}
				if(!in_array($rowid, $TDossiersError['err2'])) $TDossiersError['err2'][] = $rowid;
			}
		}
	}
	/***********************************************************************************************************************************************************
	 * 3 - Récupération de tous les dossiers dont "Visa renta facture < loyer client" est à non
	 * ce qui signifie que la règle 3 est à contrôler
	 ***********************************************************************************************************************************************************/
	if(!empty($TRule['rule3'])) {
		$sql = "SELECT DISTINCT d.rowid";
		$sql.= ", $sqlfields";
		$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fext ON (fext.fk_object = f.rowid)";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
		$sql.= $sqljoin;
		$sql.= " WHERE (fext.visa_renta_loyer_client = 0 OR fext.visa_renta_loyer_client IS NULL)";
		$sql.= $sqlwhere;
		
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = false;
			
			// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
			if(!empty($visaauto)) {
				$dossier->load($PDOdb, $rowid, false, true);
				$dossier->load_facture($PDOdb,true);
				
				// On fait la somme des échéances des dossiers client associés à cette référence dossier (prise en compte des adjonctions)
				$sql = "SELECT SUM(dfcli.echeance) as total_echeances, COUNT(dfcli.reference) as nb_dossiers";
				$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier_financement dfcli";
				$sql.= " WHERE dfcli.reference LIKE '".$dossier->financement->reference."%'";
				$sql.= " AND dfcli.type='CLIENT'";
				$sql.= " AND dfcli.montant_solde = 0";
				$sql.= " AND dfcli.date_solde < '1970-00-00 00:00:00'";
				$sql.= " AND dfcli.reference NOT LIKE '%old%'";
				$TRes = $PDOdb->ExecuteAsArray($sql);
				$total_echeances = $TRes[0]->total_echeances;
				
				// Attention on vérifie les factures et regroupements de factures
				$montant_facture = 0;
				foreach($dossier->TFacture as $p => $d) {
					// Récupération du montant facturé au client pour comparer aux loyers. Si plusieurs factures, on fait la somme
					if(is_array($d)) {
						foreach ($d as $i => $f) {
							$montant_facture += $f->total_ht;
						}
					} else {
						$montant_facture = $d->total_ht;
					}
					
					// Si on est sur la facture intercalaire, on compare avec le loyer intercalaire prévu
					$intercalaireOK = false;
					if($p == -1 && $montant_facture >= round($dossier->financement->loyer_intercalaire,2)) {
						$intercalaireOK = true;
					}
					
					// Comparaison au loyer client
					// Si règle 3 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
					if($montant_facture < $total_echeances && !$intercalaireOK) {
						$renta_neg = true;
					} else {
						echo 'Dossier '.$dossier->financement->reference.', période '.($p+1).' respecte la règle 3, case "Visa renta facture < loyer client" cochée automatiquement.<br>';
						if(is_array($d)) {
							foreach ($d as $i => $f) {
								$f->array_options['options_visa_renta_loyer_client'] = 1;
								$f->insertExtraFields();
								//echo $f->ref.'<br>';
							}
						} else {
							$d->array_options['options_visa_renta_loyer_client'] = 1;
							$d->insertExtraFields();
							//echo $d->ref.'<br>';
						}
					}
				}
			}
		
			if($renta_neg || empty($visaauto)) {
				if(!in_array($rowid, $TDossiersError['all'])) {
					$TDossiersError['all'][] = $rowid;
					$TDossiersError['data'][$rowid] = $res;
				}
				if(!in_array($rowid, $TDossiersError['err3'])) $TDossiersError['err3'][] = $rowid;
			}
		}
	}
	
	/***********************************************************************************************************************************************************
	 * 4 - Récupération de tous les dossiers dont au moins une facture client est impayée et en retard
	 * ce qui signifie que la règle 4 est à contrôler
	 ***********************************************************************************************************************************************************/
	if(!empty($TRule['rule4'])) {
		$sql = "SELECT DISTINCT d.rowid";
		$sql.= ", $sqlfields";
		$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields fext ON (fext.fk_object = f.rowid)";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d ON (ee.fk_source = d.rowid AND ee.sourcetype = 'dossier')";
		$sql.= $sqljoin;
		$sql.= " WHERE f.paye = 0";
		$sql.= " AND f.date_lim_reglement <= '".date('Y-m-d')."'";
		$sql.= " AND SUBSTR(f.ref_client, -4) >= 2015";
		$sql.= $sqlwhere;
		
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = false;
			
			// TODO : voir si besoin d'un visa sur la règle concernant les factures impayées, sachant qu'elle passent en payées en automatique via import quotidien
		
			if($renta_neg || empty($visaauto)) {
				if(!in_array($rowid, $TDossiersError['all'])) {
					$TDossiersError['all'][] = $rowid;
					$TDossiersError['data'][$rowid] = $res;
				}
				if(!in_array($rowid, $TDossiersError['err4'])) $TDossiersError['err4'][] = $rowid;
			}
		}
	}
	
	/***********************************************************************************************************************************************************
	 * 5 - Récupération de tous les dossiers pour lesquels il manque une facture
	 * ce qui signifie que la règle 5 est à contrôler
	 ***********************************************************************************************************************************************************/
	if(!empty($TRule['rule5'])) {
		$sql = "SELECT d.rowid, dfcli.numero_prochaine_echeance, dfcli.loyer_intercalaire, dfcli.terme";
			$sql.= ", ( SELECT COUNT(DISTINCT f.ref_client)";
			$sql.= " FROM ".MAIN_DB_PREFIX."facture f";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture a ON (f.rowid = a.fk_facture_source AND a.type = 2)";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee ON (ee.fk_target = f.rowid AND ee.targettype = 'facture')";
			$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier d2 ON (ee.fk_source = d2.rowid AND ee.sourcetype = 'dossier')";
			$sql.= " WHERE d.rowid = d2.rowid";
			$sql.= " AND f.type = 0";
			$sql.= " AND (f.total + IFNULL(a.total,0)) != 0";
			$sql.= " AND SUBSTR(f.ref_client,-4) >= 2016";
			$sql.= ") as nb_echeances_facturees";
		$sql.= ", $sqlfields";
		$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
		$sql.= $sqljoin;
		$sql.= " WHERE d.reference NOT LIKE '%adj%'";
		$sql.= $sqlwhere;
		//echo $sql;
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		$TPeriodeType = array(
			'MOIS' => 1
			,'TRIMESTRE' => 3
			,'SEMESTRE' => 6
			,'ANNEE' => 12
		);
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = false;
			
			// Calcul du nombre de période écoulées entre le début du dossier et le 01/01/2014, date de début d'intégration des factures dans LeaseBoard
			$nb_periode_sans_fact = 0;
			$datedeb = strtotime($res->date_debut);
			$datelimit = strtotime('2016-01-01');
			$nbmonth = $TPeriodeType[$res->periodicite];
			
			while($datedeb < $datelimit) {
				$datedeb = strtotime('+ '.$nbmonth.' month', $datedeb);
				$nb_periode_sans_fact++;
			}
			
			$intercalaire = ($res->loyer_intercalaire > 0) ? 1 : 0;
			$echu = ($res->terme == 0) ? 1 : 0;
			//echo $res->nb_echeances_facturees.' + '.$nb_periode_sans_fact.' != '.$res->numero_prochaine_echeance.' + '.$intercalaire.' - '.$echu;
			if(($res->nb_echeances_facturees + $nb_periode_sans_fact) < ($res->numero_prochaine_echeance - 1 + $intercalaire - $echu)) {
				$renta_neg = true;
			}
			
			// TODO : voir si besoin d'un visa sur la règle concernant les factures manquantes, sachant qu'elles sont créées en automatique via import quotidien
		
			if($renta_neg) {
				if(!in_array($rowid, $TDossiersError['all'])) {
					$TDossiersError['all'][] = $rowid;
					$TDossiersError['data'][$rowid] = $res;
				}
				if(!in_array($rowid, $TDossiersError['err5'])) $TDossiersError['err5'][] = $rowid;
			}
		}
	}
	
	/***********************************************************************************************************************************************************
	 * 6 - Récupération de tous les dossiers dont la case anomalie est cochée
	 ***********************************************************************************************************************************************************/
	if(!empty($TRule['rule6'])) {
		$sql = "SELECT d.rowid";
		$sql.= ", $sqlfields";
		$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier d";
		$sql.= $sqljoin;
		$sql.= " WHERE d.renta_anomalie = 1";
		$sql.= $sqlwhere;
		
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = true;
			
			if($renta_neg) {
				if(!in_array($rowid, $TDossiersError['all'])) {
					$TDossiersError['all'][] = $rowid;
					$TDossiersError['data'][$rowid] = $res;
				}
				if(!in_array($rowid, $TDossiersError['err6'])) $TDossiersError['err6'][] = $rowid;
			}
		}
	}
	
	//pre($TDossiersError,true);
	//exit;
	
	return $TDossiersError;
}
