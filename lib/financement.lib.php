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
	
	static function build_array_entities() {
		
		global $mc;
		
		$mc->dao->getEntities();
		
		$TEntities = array();
		foreach($mc->dao->entities as $ent) {
			$TEntities[$ent->id] = $ent->label;
		}
		
		return $TEntities;
	}
	
	static function add_css() {
		
		?>
			<style type="text/css">
				td[field="montant_total_finance"] {white-space:nowrap;}
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
				td[field="montant_total_finance"] {text-align:center;}
				td[field="duree"] {text-align:center;}
				td[field="echeance"] {text-align:center;}
				td[field="Prochaine"] {text-align:center;}
				td[field="date_debut"] {text-align:center;}
				td[field="Fin"] {text-align:center;}
				td[field="fact_materiel"] {text-align:center;}
				td[field="attente"] {text-align:right;}
				td[field="suivi"] {text-align:center;}
				td[field="loupe"] {text-align:center;}
				
				td[id="num_contrat"] {text-align:center;}
				td[id="entity_dossier"] {text-align:center;}
				td[id="leaser"] {text-align:center;}
				td[id="type_contrat"] {text-align:center;}
				td[id="montant_total_finance"] {text-align:center;}
				td[id="duree"] {text-align:center;}
				td[id="echeance"] {text-align:center;}
				td[id="debut_fin"] {text-align:center;}
				td[id="prochaine_echeance"] {text-align:center;}
				td[id="assurance"] {text-align:center;}
				td[id="maintenance"] {text-align:center;}
				td[id="solde_rm1"] {text-align:center;}
				td[id="solde_nrm1"] {text-align:center;}
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
	
	
	static function getCategorieId()
	{
		global $db,$conf;
		
		$TRes = array();
		
		$sql = 'SELECT cat_id, label FROM '.MAIN_DB_PREFIX.'c_financement_categorie_bien WHERE active = 1 AND entity IN (0, '.$conf->entity.') ORDER BY label, cat_id';
		$resql = $db->query($sql);
		
		if ($resql)
		{
			//$TRes[] = '';
			while ($row = $db->fetch_object($resql))
			{
				$TRes[$row->cat_id] = $row->label;
			}
		}
		
		return $TRes;
	}
	
	static function getNatureId()
	{
		global $db,$conf;
		
		$TRes = array();
		
		$sql = 'SELECT nat_id, label FROM '.MAIN_DB_PREFIX.'c_financement_nature_bien WHERE active = 1 AND entity IN (0, '.$conf->entity.') ORDER BY label, nat_id';
		$resql = $db->query($sql);
		
		if ($resql)
		{
			//$TRes[] = '';
			while ($row = $db->fetch_object($resql))
			{
				$TRes[$row->nat_id] = $row->label;
			}
		}
		
		return $TRes;
	}
	
	static function getCategorieLabel($fk_categorie)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_categorie_bien WHERE cat_id = '.(int) $fk_categorie;
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			return $row->label;
		}
		
		return '';
	}

	static function getNatureLabel($fk_nature)
	{
		global $db;
		
		$sql = 'SELECT label FROM '.MAIN_DB_PREFIX.'c_financement_nature_bien WHERE nat_id = '.(int) $fk_nature;
		$resql = $db->query($sql);
		
		if ($resql && ($row = $db->fetch_object($resql)))
		{
			return $row->label;
		}
		
		return '';
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
	global $conf;
	
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
	
	$sqlfields = 'a.reference as refaffaire, a.rowid as fk_affaire, a.fk_soc as fk_client,d.fk_statut_renta_neg_ano,d.fk_statut_dossier,';
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
	//$sqlwhere.= " AND d.entity IN (".getEntity('fin_dossier', true).")";
	$sqlwhere.= " AND d.entity = ".$conf->entity." ";
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
		$sql.= " AND d.renta_anomalie = 0";
		$sql.= $sqlwhere;
		
		$PDOdb->Execute($sql);
		$TRes = $PDOdb->Get_All();
		
		foreach($TRes as $res) {
			$rowid = $res->rowid;
			$renta_neg = false;
			
			// On ne vérifie la règle que si demandé, sinon le visa fait foi pour savoir si le dossier est à vérifier ou non
			if(!empty($visaauto)) {
				$dossier->load($PDOdb, $rowid,false,true);

				// On ramène l'échéance leaser sur la même périodicité que le dossier client
				$equi_periodicite = $dossier->financementLeaser->getiPeriode() / $dossier->financement->getiPeriode();
				$echeanceClient = $dossier->financement->echeance;
				$echeanceLeaser = ($dossier->financementLeaser->echeance / $equi_periodicite);

				// Si règle 1 vérifiée, on prend le dossier, sinon, on coche la case visa pour ne pas le récupérer la prochaine fois
				if($echeanceClient < $echeanceLeaser) {
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
		$sql.= " AND d.renta_anomalie = 0";
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
				
				// On ramène l'échéance leaser sur la même périodicité que le dossier client
				$equi_periodicite = $dossier->financementLeaser->getiPeriode() / $dossier->financement->getiPeriode();
				$echeanceLeaser = ($total_echeances / $equi_periodicite);
				
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
					if($montant_facture < $echeanceLeaser && !$intercalaireOK && $dossier->fk_statut_renta_neg_ano != '17') {
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
		$sql.= " AND d.renta_anomalie = 0";
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
		$sql.= " AND d.renta_anomalie = 0";
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
		$sql.= " AND d.renta_anomalie = 0";
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
			
			// Calcul du nombre de période écoulées entre le début du dossier et le 01/01/2016,
			// date de début d'intégration des factures dans LeaseBoard
			$nb_periode_sans_fact = 0;
			
			$nbmonth = $TPeriodeType[$res->periodicite];
			
			$d1 = new DateTime($res->date_debut);
			$d2 = new DateTime('2016-01-01');
			$diff = $d1->diff($d2);

			$nb_periode_sans_fact = $diff->y * 12 / $nbmonth + ceil($diff->m / $nbmonth) + ceil($diff->d / 31);
			if($diff->invert == 1) $nb_periode_sans_fact = 0;
			
			// Intercalaire ?
			$intercalaire = ($res->loyer_intercalaire > 0) ? 1 : 0;
			
			// Si intercalaire et dossier démarrant avant le 01/01/2016
			/*$datedeb = strtotime($res->date_debut);
			$datelimit = strtotime('2016-01-01');
			if(!empty($intercalaire) && $datedeb < $datelimit) $nb_periode_sans_fact++;
			while($datedeb < $datelimit) {
				$datedeb = strtotime('+ '.$nbmonth.' month', $datedeb);
				$nb_periode_sans_fact++;
			}*/
			
			// Si échu, décalage
			$echu = ($res->terme == 0) ? 1 : 0;
			// Si l'échéance n'est pas calée sur le civil, la facture étant faite en fin de mois, on enleve 1 si pas d'intercalaire
			$decal = (date('d',strtotime($res->date_debut)) == '01' || $intercalaire) ? 0 : 1;
			
			/*echo 'ECH FACT : '.$res->nb_echeances_facturees;
			echo ' + PERIODE NON FACT : '.$nb_periode_sans_fact;
			echo ' = <b>'. ($res->nb_echeances_facturees + $nb_periode_sans_fact) . '</b><br>-----<br>';
			echo 'ECH PASSEES : '.($res->numero_prochaine_echeance - 1);
			echo ' + INTERCALAIRE : '.$intercalaire;
			echo ' - ECHU : '.$echu;
			echo ' - DECALAGE : '.$decal;
			echo ' = <b>'. ($res->numero_prochaine_echeance - 1 + $intercalaire - $echu - $decal). '</b>';*/
			if(($res->nb_echeances_facturees + $nb_periode_sans_fact) < ($res->numero_prochaine_echeance - 1 + $intercalaire - $echu - $decal)) {
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


/**
 * Return array of tabs to used on pages for simulation cards.
 *
 * @param 	TSimulation	$object		Object simulation shown
 * @return 	array				    Array of tabs
 */
function simulation_prepare_head(TSimulation $object)
{
    global $db, $langs, $conf, $user;
    $h = 0;
    $head = array();

    $id = $object->getId();

    $url = dol_buildpath('/financement/simulation/simulation.php', 2);
    if(empty($id)) $url .= '?action=new';
    else $url .= '?id='.$id;
    $url .= '&mainmenu=financement';

    $head[$h][0] = $url;
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'card';
    $h++;

    if ($user->rights->financement->admin && ! empty($id))
    {
		$nbNote = 0;
        if(!empty($object->note_private)) $nbNote++;
		if(!empty($object->note_public)) $nbNote++;

        $head[$h][0] = dol_buildpath('/financement/simulation/note.php', 2).'?id='.$id.'&mainmenu=financement';
        $head[$h][1] = get_picto('snowplow').'&nbsp;'.$langs->trans("NoteLabel");
		if ($nbNote > 0) $head[$h][1].= ' <span class="badge">'.$nbNote.'</span>';

        $cssFlipStyle = '-moz-transform: scaleX(-1);';
        $cssFlipStyle.= ' -o-transform: scaleX(-1);';
        $cssFlipStyle.= ' -webkit-transform: scaleX(-1);';
        $cssFlipStyle.= ' transform: scaleX(-1);';
        $cssFlipStyle.= ' filter: FlipH;';
        $cssFlipStyle.= " -ms-filter: 'FlipH';";
		$head[$h][1].= '&nbsp;'.get_picto('snowplow', '', '', $cssFlipStyle);
        $head[$h][2] = 'note';
        $h++;
    }

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'simulation');

    complete_head_from_modules($conf,$langs,$object,$head,$h,'simulation','remove');

    return $head;
}

/**
 * Return array of tabs to used on pages for dossier cards.
 *
 * @param 	TFin_dossier	$object		Object dossier shown
 * @return 	array				        Array of tabs
 */
function dossier_prepare_head(TFin_dossier $object)
{
    global $db, $langs, $conf, $user;
    $h = 0;
    $head = array();

    $id = $object->getId();

    $head[$h][0] = dol_buildpath('/financement/dossier.php', 2).'?id='.$id.'&mainmenu=financement';;
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'card';
    $h++;

    $head[$h][0] = dol_buildpath('/financement/dossier_integrale.php', 2).'?id='.$id.'&mainmenu=financement';
    $head[$h][1] = $langs->trans("SuiviIntegral");
    $head[$h][2] = 'integrale';
    $h++;

    $head[$h][0] = FIN_THEREFORE_DOSSIER_URL.$object->financement->referance;
    $head[$h][1] = $langs->trans("Therefore");
    $head[$h][2] = 'therefore';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'dossier');

    complete_head_from_modules($conf,$langs,$object,$head,$h,'dossier','remove');

    return $head;
}

function switchEntity($target) {
    global $db, $conf, $mysoc;

    if($conf->entity != $target) {
        // Récupération configuration de l'entité de la simulation
        $confentity = &$conf;
        $confentity->entity = $target;
        $confentity->setValues($db);

        $mysocentity = &$mysoc;
        $mysocentity->setMysoc($confentity);
    }
}

function get_picto($name, $title = '', $color = '', &$style = '') {
    $img = '';
    $lo_title = '';
    if(! empty($title)) $lo_title = ' title="'.$title.'"';
    $iconSize = 'font-size: 21px;';

    $lo_name = strtolower($name);
    switch($lo_name) {
        case 'ko':
        case 'refus':
            $img .= '<i class="fas fa-times-circle" style="color: #b90000; ' .$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'wait':
            $img .= '<i class="fas fa-clock" style="color: #22b8cf; '.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'err':
            $img .= '<i class="fas fa-exclamation-triangle" style="color: #ffd507; ' .$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'super_ok':
            $img .= '<i class="fas fa-check-circle" style="color: green; '.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'wait_seller':
            $img .= '<i class="fas fa-briefcase" style="'.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'wait_leaser':
            $img .= '<i class="fas fa-piggy-bank" style="'.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'ok':
            $img .= '<i class="fas fa-check-circle" style="color: grey; ' .$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'edit':
            $img .= '<i class="fas fa-edit" style="color: darkorange; '.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'money':
            $img .= '<i class="fas fa-coins" style="'.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'ss':
        case 'sans_suite':
            $img .= '<i class="fas fa-minus-circle" style="'.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'phone':
            $img .= '<i class="fas fa-phone" style="'.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'webservice':
            $img .= '<i class="fas fa-satellite-dish" style="color: green; '.$iconSize.'"'.$lo_title.'></i>';
            break;
        case 'save':    // This will use the unicode value of 'fas fa-save' only to keep the input
            $img .= '<input type="submit" class="fa fa-input" style="'.$iconSize.' border: none;" value="&#xf0c7" title="Enregistrer" />';
            break;
        case 'manual':
            $style = 'style="'.$iconSize;
            if(! empty($color)) $style .= ' color: '.$color.';';
            $style .= ' vertical-align: top;"';

            $img .= '<i class="fas fa-bell" '.$style.$lo_title.'></i>';
            break;
        case 'fish':
            $iconSize = 'font-size: 14px;';
            $style .= ' '.$iconSize;
            $img .= '<i class="fas fa-fish" style="'.$style.'"'.$lo_title.'></i>';
            break;
        case 'snowplow':
            $iconSize = 'font-size: 14px;';
            $style .= ' '.$iconSize;
            $img .= '<i class="fas fa-snowplow" style="'.$style.'"'.$lo_title.'></i>';
            break;
        default:
            return '';
    }

    return $img;
}