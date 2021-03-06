<?php
require('config.php');
require('./class/simulation.class.php');
require('./class/grille.class.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/score.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/custom/report/class/dashboard.class.php");

$langs->load('financement@financement');
$ATMdb = new TPDOdb;

$mesg = '';
$error=false;

llxHeader('','Pilotage');

$type_annee = (GETPOST('type_annee')) ? GETPOST('type_annee') : 'fiscale';

if($type_annee == 'fiscale'){
	//Calcule de l'année fiscale
	if(date('m') < $conf->global->SOCIETE_FISCAL_MONTH_START){
		$date_debut = (date('Y')-1)."-0".$conf->global->SOCIETE_FISCAL_MONTH_START."-01";
		$date_fin = date('Y-m-t',strtotime("+11 month",strtotime($date_debut)));
	}
	else{
		$date_debut = date('Y')."-0".$conf->global->SOCIETE_FISCAL_MONTH_START."-01";
		$date_fin = date('Y-m-t',strtotime("+11 month",strtotime($date_debut)));
	}
}
else{ //Année civile
	$date_debut = date('Y')."-01-01";
	$date_fin = date('Y-m-t',strtotime("+11 month",strtotime($date_debut)));
}

/*echo $date_debut.'<br>';
echo $date_fin.'<br>';*/

?>

<style type="text/css">
	.titre_colonne{
		font-weight: bold;
		text-align: center;
	}
	td{
		text-align: center;
	}
	.titre{
		text-align: left;
		margin-left: 5px;
		margin-bottom: 5px;
	}
	.justifie{
		text-align: right;
		min-width: 50px;
	}
</style>

<script type="text/javascript">
	$(document).ready(function(){

	<?php
	
	$TParam = array('@date_debut@'=>$date_debut,'@date_fin@'=>$date_fin);
	
	$PDOdb=new TPDOdb;
	$dash=new TReport_dashboard;
	$dash->initByCode($PDOdb, 'PRODUCTIONFOURNISSEUR',$TParam);
	
	?>$('#chart_prod_fournisseur').html('<div id="chart_prod_fournisseur" style="height:<?php echo $dash->hauteur; ?>px; margin-bottom:20px;"></div>');<?php
	
	$dash->get('chart_prod_fournisseur', true," €");
	
	$dash=new TReport_dashboard;
	$dash->initByCode($PDOdb, 'PRODUCTIONLEASER',$TParam);
	
	?>$('#chart_prod_leaser').html('<div id="chart_prod_leaser" style="height:<?php echo $dash->hauteur; ?>px; margin-bottom:20px;"></div>');<?php
	
	$dash->get('chart_prod_leaser', true," €");


	$PDOdb->close();
	
	?>
});
</script>

<table cellpadding="0" cellspacing="0" style="white-space: nowrap;">
	<tr>
		<td><div class="titre" style="text-align: center;font-size: 22px;">Pilotage de la cellule Financement</div></td>
	</tr>
	
	<tr>
		<td colspan="2" style="text-align: left">
			<?php
			$form = new TFormCore('#','switch_type_annee');
			
			$TSelect = array('fiscale'=>'Fiscale','civile'=>'Civile');
			print $form->combo('Année en cours :', 'type_annee', $TSelect, $type_annee);
			
			print $form->btsubmit('Afficher', 'bt_switch');
			$form->end();
			?>
		</td>
	</tr>
	
	
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeNbAffaireParTypeContrat($ATMdb,$date_debut,$date_fin); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeNbAffaireParTypeContratParMois($ATMdb,$date_debut,$date_fin); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeCAFactureMaterielParCategorie($ATMdb,"fournisseur",$date_debut,$date_fin); ?>
		<td width="50%">
			<!--- eChart -->
			<div id="chart_prod_fournisseur" style="position:relative;margin-left: 50px;width: 800px;"></div>
		</td>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeCAFactureMaterielParCategorie($ATMdb,"leaser",$date_debut,$date_fin); ?>
		<td width="50%">
			<!--- eChart -->
			<div id="chart_prod_leaser" style="position:relative;margin-left: 50px;width: 800px;"></div>
		</td>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeSommeCRDLeaserParCategoriesFournisseur($ATMdb,$date_debut,$date_fin); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeRelationCommerciales($ATMdb,$date_debut,$date_fin); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeAdministrationDolibarr($ATMdb,$date_debut,$date_fin); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeRentabilite($ATMdb,$date_debut,$date_fin); ?>
	</tr>
	<tr><td height="15"></td></tr>
</table>
<?php

llxFooter();
	
function _listeNbAffaireParTypeContrat(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	//Année N-1
	$sql ="SELECT CONCAT(CONCAT(contrat,' - '), nature_financement) as contrat, count(*) as 'nb', MONTH(date_affaire) as 'm', YEAR(date_affaire) as 'y'
		   FROM ".MAIN_DB_PREFIX."fin_affaire
		   WHERE date_affaire >= '".date("Y-m", strtotime("-1 year", strtotime($date_debut)))."-01'
		   	 AND date_affaire <= '".date("Y-m-t", strtotime("-1 year",strtotime($date_fin)))."'
		   AND contrat IS NOT NULL AND contrat != ''
		   AND reference NOT LIKE 'EXT%'
		   GROUP BY contrat, `y`, `m`
		   ORDER BY 1";

	$ATMdb->Execute($sql);
	$TRes = array();
	$Total1 = 0;
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('contrat')][0] += $ATMdb->Get_field('nb');
		$Total1 += $ATMdb->Get_field('nb');
	}
	
	//Année N
	$sql ="SELECT CONCAT(CONCAT(contrat,' - '), nature_financement) as contrat, count(*) as 'nb', MONTH(date_affaire) as 'm', YEAR(date_affaire) as 'y'
		   FROM ".MAIN_DB_PREFIX."fin_affaire
		   WHERE date_affaire >= '".$date_debut."'
		   AND contrat IS NOT NULL AND contrat != ''
		   AND reference NOT LIKE 'EXT%'
		   GROUP BY contrat, `y`, `m`
		   ORDER BY 1";
	$ATMdb->Execute($sql);
	$Total2 = 0;
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('contrat')][1] += $ATMdb->Get_field('nb');
		$Total2 += $ATMdb->Get_field('nb');
	}
	
	?>	   
		<!-- Premier tableau -->
		<td>
			<div class="titre">
				Nombre d'affaires / type
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<td colspan="2" class="titre_colonne">Nombre</td>
					<td colspan="2" class="titre_colonne">% sur quantité totale</td>
				</tr>
				<tr class="liste_titre">
					<td></td>
					<td class="titre_colonne"><?php echo (GETPOST('type_annee') == 'civile') ? date("Y", strtotime("-1 year", strtotime($date_debut))) : date("Y", strtotime("-1 year", strtotime($date_debut))).'/'.date("Y",strtotime($date_debut));?></td>
					<td class="titre_colonne"><?php echo (GETPOST('type_annee') == 'civile') ? date("Y", strtotime($date_debut)) : date("Y", strtotime($date_debut)).'/'.date("Y",strtotime($date_fin));?></td>
					<td class="titre_colonne"><?php echo (GETPOST('type_annee') == 'civile') ? date("Y", strtotime("-1 year", strtotime($date_debut))) : date("Y", strtotime("-1 year", strtotime($date_debut))).'/'.date("Y",strtotime($date_debut));?></td>
					<td class="titre_colonne"><?php echo (GETPOST('type_annee') == 'civile') ? date("Y", strtotime($date_debut)) : date("Y", strtotime($date_debut)).'/'.date("Y",strtotime($date_fin));?></td>
				</tr>
				<?php
				foreach($TRes as $contrat=>$TNb){
					?>
					<tr>
						<td><?php echo $contrat; ?></td>
						<td class="justifie"><?php echo $TNb[0]; ?></td>
						<td class="justifie"><?php echo $TNb[1]; ?></td>
						<td><?php echo round(($TNb[0]*100)/$Total1); ?> %</td>
						<td><?php echo round(($TNb[1]*100)/$Total2); ?> %</td>
					</tr>
					<?php	
				}
				?>
			</table>
		</td>
	<?php
}

function _listeNbAffaireParTypeContratParMois(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql ="SELECT contrat, count(*) as 'nb', MONTH(date_affaire) as 'm'
		   FROM ".MAIN_DB_PREFIX."fin_affaire
		   WHERE date_affaire >= '".$date_debut."'
		   AND date_affaire <= '".$date_fin."'
		   AND contrat IS NOT NULL AND contrat != ''
		   AND reference NOT LIKE 'EXT%'
		   GROUP BY contrat, `m`
		   ORDER BY 1, YEAR(date_affaire), MONTH(date_affaire)";
	
	$ATMdb->Execute($sql);
	$TRes = array();
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('contrat')][$ATMdb->Get_field('m')] = $ATMdb->Get_field('nb');
	}
	
	foreach($TRes as $cle=>$Tnb){
		foreach($Tnb as $month=>$nb){
			$TCle[$month] = "on";
		}
	}
	
	?>
		<!-- Deuxième tableau -->
		<td>
			<div class="titre">
				Nombre d'affaires / mois
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<?php
					$TNb = key($TRes);
					setlocale(LC_TIME, "fr_FR");
					foreach($TCle as $cle=>$val){
					?>
					<td class="titre_colonne"><?php echo ucfirst($langs->trans(strftime('%B',strtotime("2013-".$cle."-01")))); ?></td>
					<?php
					}
					?>
				</tr>
				<?php
				foreach($TRes as $contrat=>$TNb){
					?>
				<tr>
					<td><?php echo $contrat; ?></td>
					<?php
					foreach($TNb as $cle=>$nb){
						?>
						<td class="justifie"><?php echo $nb; ?></td>
						<?php
					}
					?>
				</tr>
					<?php
				}
				?>
			</table>
		</td>
	<?php
}

function _listeCAFactureMaterielParCategorie(&$ATMdb,$type,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	//Requête pour facture année N-1
	$sql = "SELECT c.label as 'categorie',s.nom as 'societe', COUNT(f.rowid) as 'nb', SUM(f.total) as 'montant'
			FROM ".MAIN_DB_PREFIX."facture as f
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (f.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire as fa ON (fa.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)	
			WHERE s.fournisseur = 1 AND s.client = 1
			 AND f.datef >= '".date("Y-m", strtotime("-1 year", strtotime($date_debut)))."-01'
		   	 AND f.datef <= '".date("Y-m-t", strtotime("-1 year",strtotime($date_fin)))."' ";
	if($type=="fournisseur")
		$sql .= "AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement') ";
	elseif($type=="leaser")
		$sql .= "AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Leaser') ";
	$sql .= "GROUP BY c.rowid, s.rowid";
	
	$ATMdb->Execute($sql);
	$TRes = array();
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')] = array($ATMdb->Get_field('nb'),$ATMdb->Get_field('montant'));
	}
	
	//Requête pour facture année N
	$sql = "SELECT c.label as 'categorie',s.nom as 'societe', COUNT(f.rowid) as 'nb', SUM(f.total) as 'montant'
			FROM ".MAIN_DB_PREFIX."facture as f
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (f.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire as fa ON (fa.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)	
			WHERE s.fournisseur = 1 AND s.client = 1
			 AND f.datef >= '".$date_debut."'";
	if($type=="fournisseur")
		$sql .= "AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement') ";
	elseif($type=="leaser")
		$sql .= "AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Leaser') ";
	$sql .= "GROUP BY c.rowid, s.rowid";
	
	//echo $sql.'<br>';
	
	//Merging des deux tableaux de résultat
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		if(empty($TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')])) {
			$TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')] = array(0,0);
		}
		$TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')] = array_merge(
							(array)$TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')]
							,array($ATMdb->Get_field('nb'),$ATMdb->Get_field('montant'))
						);
	}
	
	?>
		<!-- 3ème tableau -->
		<td>
			<div class="titre">
				Production de l'exercice / Catégorie <?php echo ucfirst($type); ?>
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<td class="titre_colonne" colspan="2"><?php echo (GETPOST('type_annee') == 'civile') ? date("Y", strtotime("-1 year", strtotime($date_debut))) : date("Y", strtotime("-1 year", strtotime($date_debut))).'/'.date("Y",strtotime($date_debut));?></td>
					<td class="titre_colonne" colspan="2"><?php echo (GETPOST('type_annee') == 'civile') ? date("Y", strtotime($date_debut)) : date("Y", strtotime($date_debut)).'/'.date("Y",strtotime($date_fin));?></td>
				</tr>
				<tr class="liste_titre">
					<td class="titre_colonne">au <?php echo date('d/m/Y');?></td>
					<td class="titre_colonne">CA</td>
					<td class="titre_colonne">NB Factures</td>
					<td class="titre_colonne">CA</td>
					<td class="titre_colonne">NB Factures</td>
				</tr>
				<?php
				$TotalCA1 = $TotalCA2 = $TotalNb1 =  $TotalNb2 = 0;
				foreach($TRes as $categorie=>$TSoc){
					$totalNb1 = $totalCA1 = $totalNb2 = $totalCA2 = 0;
					foreach($TSoc as $societe=>$TNb){
						if($type == "fournisseur"){
							?>
							<tr>
								<td><?php echo $societe; ?></td>
								<td class="justifie"><?php echo number_format($TNb[1],2,',',' '); ?> €</td>
								<td class="justifie"><?php echo $TNb[0]; ?></td>
								<td class="justifie"><?php echo number_format($TNb[3],2,',',' '); ?> €</td>
								<td class="justifie"><?php echo $TNb[2]; ?></td>
							</tr>
							<?php
						}
						$totalCA1 += $TNb[1];
						$totalNb1 += $TNb[0];
						$totalCA2 += $TNb[3];
						$totalNb2 += $TNb[2];
					}
					
					$TotalCA1 += $totalCA1;
					$TotalNb1 += $totalNb1;
					$TotalCA2 += $totalCA2;
					$TotalNb2 += $totalNb2;
					
					if($type=="fournisseur"){
						?>
						<tr>
							<td style="font-weight: bold;">Sous Total <?php echo $categorie; ?></td>
							<td style="font-weight: bold;" class="justifie"><?php echo number_format($totalCA1,2,',',' '); ?> €</td>
							<td style="font-weight: bold;" class="justifie"><?php echo $totalNb1; ?></td>
							<td style="font-weight: bold;" class="justifie"><?php echo number_format($totalCA2,2,',',' '); ?> €</td>
							<td style="font-weight: bold;" class="justifie"><?php echo $totalNb2; ?></td>
						</tr>
						<?php
					}
					elseif($type=="leaser"){
						?>
						<tr>
							<td><?php echo $categorie; ?></td>
							<td class="justifie"><?php echo number_format($totalCA1,2,',',' '); ?> €</td>
							<td class="justifie"><?php echo $totalNb1; ?></td>
							<td class="justifie"><?php echo number_format($totalCA2,2,',',' '); ?> €</td>
							<td class="justifie"><?php echo $totalNb2; ?></td>
						</tr>
						<?php
					}
				}
				?>
				<tr>
					<td style="font-weight: bold;">TOTAL</td>
					<td style="font-weight: bold;" class="justifie"><?php echo number_format($TotalCA1,2,',',' '); ?> €</td>
					<td style="font-weight: bold;" class="justifie"><?php echo $TotalNb1; ?></td>
					<td style="font-weight: bold;" class="justifie"><?php echo number_format($TotalCA2,2,',',' '); ?> €</td>
					<td style="font-weight: bold;" class="justifie"><?php echo $TotalNb2; ?></td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeSommeCRDLeaserParCategoriesFournisseur(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT fdf.rowid as id_dossier, c1.label as cat1, c2.label as cat2";
	$sql.= " FROM ".MAIN_DB_PREFIX."fin_dossier as fd";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as fdf ON (fdf.fk_fin_dossier = fd.rowid)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf1 ON (cf1.fk_societe = fdf.fk_soc)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf2 ON (cf2.fk_societe = fdf.fk_soc)";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie as c1 ON (c1.rowid = cf1.fk_categorie AND c1.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Leaser'))";
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."categorie as c2 ON (c2.rowid = cf2.fk_categorie AND c2.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement'))";
	$sql.= " WHERE fdf.type = 'LEASER'";
	$sql.= " AND c1.rowid IS NOT NULL";
	$sql.= " AND c2.rowid IS NOT NULL";
	$sql.= " AND fdf.date_solde < '1970-00-00 00:00:00'";
	$sql.= " ORDER BY c1.label, c2.label";
	
	$ATMdb->Execute($sql);
	$TRestemp = array();
	while($ATMdb->Get_line()){
		$TRestemp[$ATMdb->Get_field('cat1')][$ATMdb->Get_field('cat2')][] = $ATMdb->Get_field('id_dossier');
	}
	
	$TRes = $TTotal = array();
	foreach($TRestemp as $cat1=>$subArray){
		
		foreach($subArray as $cat2=>$TIdDossier){
			if(empty($TRes[$cat1][$cat2])) $TRes[$cat1][$cat2] = 0;
			
			foreach($TIdDossier as $iddossier){
			
				$dossierFin = new TFin_financement;
				$dossierFin->load($ATMdb, $iddossier);
				
				$va = $dossierFin->valeur_actuelle();
				if(is_nan($va) || empty($dossierFin->montant) || $dossierFin->taux > 100 || $dossierFin->taux < 0) {
					//echo '<br>'.$dossierFin->reference.';'.$va.';'.$dossierFin->taux;
				} else {
					$TRes[$cat1][$cat2] += $va;
				}
			}
		}
	}
	
	$TTotaux = array();
	foreach($TRes as $leaser=>$TCategories){
		$totalLeaser = 0;
		foreach($TCategories as $categorie=>$montant){
			$TTotaux[$categorie] += $montant;
			$totalLeaser += $montant;
		}
		
		$TTotaux[$leaser] += $totalLeaser;
		$TTotaux['total'] += $totalLeaser;
	}
	
	?>
		<!-- 4ème tableau -->
		<td>
			<div class="titre">
				En-cours
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td class="titre_colonne">au <?php echo date('d/m/Y'); ?></td>
					<td class="titre_colonne">Cession</td>
					<td class="titre_colonne">Mandatée</td>
					<td class="titre_colonne">Adossée</td>
					<td class="titre_colonne">Total</td>
					<td class="titre_colonne">%</td>
				</tr>
				<?php
				foreach($TRes as $categorie=>$TCategorieLeaser){
					$sommeCession += $TCategorieLeaser['Cession'];
					$sommeMandatee += $TCategorieLeaser['Mandatee'];
					$sommeAdossee += $TCategorieLeaser['Adossee'];
					?>
					<tr>
						<td><?php echo $categorie; ?></td>
						<td class="justifie"><?php echo number_format($TCategorieLeaser['Cession'],2,',',' '); ?> €</td>
						<td class="justifie"><?php echo number_format($TCategorieLeaser['Mandatee'],2,',',' '); ?> €</td>
						<td class="justifie"><?php echo number_format($TCategorieLeaser['Adossee'],2,',',' '); ?> €</td>
						<td class="justifie"><?php echo number_format($TCategorieLeaser['Adossee'] + $TCategorieLeaser['Mandatee'] + $TCategorieLeaser['Cession'],2,',',' '); ?> €</td>
						<td><?php echo number_format(($TCategorieLeaser['Adossee'] + $TCategorieLeaser['Mandatee'] + $TCategorieLeaser['Cession']) * 100 / $TTotaux['total'],2,',',''); ?> %</td>
					</tr>
					<?php
				}
				?>
				<tr style="font-weight: bold;">
					<td>TOTAL</td>
					<td class="justifie"><?php echo number_format($sommeCession,2,',',' '); ?></td>
					<td class="justifie"><?php echo number_format($sommeMandatee,2,',',' '); ?></td>
					<td class="justifie"><?php echo number_format($sommeAdossee,2,',',' '); ?></td>
					<td class="justifie"><?php echo number_format($sommeCession + $sommeMandatee + $sommeAdossee,2,',',' '); ?></td>
				</tr>
				<tr>
					<td>%</td>
					<td><?php echo number_format(($sommeCession * 100) / $TTotaux['total'],2,',',' '); ?> %</td>
					<td><?php echo number_format(($sommeMandatee * 100) / $TTotaux['total'],2,',',' '); ?> %</td>
					<td><?php echo number_format(($sommeAdossee * 100) / $TTotaux['total'],2,',',' '); ?> %</td>
					<td></td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeRelationCommerciales(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT count(*) as 'nbAuto', MONTH(date_simul) as 'm', (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."fin_simulation WHERE MONTH(date_simul) = `m`)  as 'nbTotal'
			FROM ".MAIN_DB_PREFIX."fin_simulation 
			WHERE date_simul = date_accord
				AND date_simul >= '".$date_debut."'
			GROUP BY `m`";
	
	$ATMdb->Execute($sql);
	$TRes = array();
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('m')] = array(
						"nbAuto"=>$ATMdb->Get_field('nbAuto')
						,"nbTotal"=>$ATMdb->Get_field('nbTotal')
						);
	}
	
	$sql = "SELECT AVG(DATEDIFF(date_accord,date_cre)) as 'delais', MONTH(date_simul) as 'm'
			FROM ".MAIN_DB_PREFIX."fin_simulation 
			WHERE date_simul != date_accord GROUP BY `m`";
	
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('m')] = array_merge(
					(array)$TRes[$ATMdb->Get_field('m')]
					,array("delais"=>$ATMdb->Get_field('delais'))
					);
	}
	?>
		<!-- 6ème tableau -->
		<td>
			<div class="titre">
				Relations Commerciales
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<?php
					setlocale(LC_TIME, "fr_FR");
					foreach($TRes as $cle=>$Tval){
					?>
					<td class="titre_colonne"><?php echo ucfirst($langs->trans(strftime('%B',strtotime("2013-".$cle."-01")))); ?></td>
					<?php
					}
					?>
				</tr>
				<tr>
					<td>% accords automatiques</td>
					<?php
					foreach($TRes as $tres){
						?>
						<td><?php echo ($tres["nbAuto"]) ? round(($tres["nbAuto"] * 100) / $tres["nbTotal"]) : "0"; ?> %</td>
						<?php 
					}
					?>
				</tr>
				<tr>
					<td>Délais accords non automatiques</td>
					<?php
					foreach($TRes as $tres){
						?>
						<td><?php echo round($tres["delais"]); ?> jours</td>
						<?php 
					}
					?>
				</tr>
			</table>
		</td>
	<?php
}

function _listeAdministrationDolibarr(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT COUNT(d.rowid) as 'nb'
			FROM ((((".MAIN_DB_PREFIX."fin_dossier d LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_affaire l ON (d.rowid=l.fk_fin_dossier)) 
				LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_affaire a ON (l.fk_fin_affaire=a.rowid)) 
				LEFT OUTER JOIN ".MAIN_DB_PREFIX."fin_dossier_financement f ON (d.rowid=f.fk_fin_dossier )) 
				LEFT OUTER JOIN ".MAIN_DB_PREFIX."societe s ON (a.fk_soc=s.rowid)) 
			WHERE a.entity=1 
				AND a.nature_financement = 'INTERNE' 
				AND (f.type = 'LEASER' 
				AND (f.reference IS NULL OR f.reference = '' OR f.duree = 0 OR f.echeance = 0)) 
				AND DATEDIFF(CURDATE(),f.date_cre) > 30";
	//echo $sql.'<br>';
	$ATMdb->Execute($sql);
	$ATMdb->Get_line();
	$NbDossier = $ATMdb->Get_field('nb');
	?>
		<!-- 7ème tableau -->
		<td>
			<div class="titre">
				Administration dolibarr
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<td class="titre_colonne">Objectif</td>
					<td class="titre_colonne">Constat</td>
				</tr>
				<tr>
					<td>Nb dossier internes > 1 mois incomplets</td>
					<td class="justifie">30</td>
					<td class="justifie"><?php echo $NbDossier; ?></td>
				</tr>
				<!-- <tr>
					<td>NB dossiers externes non rattachés</td>
					<td>50</td>
					<td>10%</td>
				</tr>
				<tr>
					<td>Stat  appels?</td>
					<td>à mesurer puis à définir</td>
					<td></td>
				</tr> -->
			</table>
		</td>
	<?php
}

function _listeRentabilite(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT SUM(fd.renta_previsionnelle) as renta_previsionnelle, 
				   SUM(fd.renta_attendue) as renta_attendue, 
				   SUM(renta_reelle) as renta_relle_nc,
				   c.label
			FROM ".MAIN_DB_PREFIX."fin_dossier as fd
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as fdf ON (fd.rowid = fdf.fk_fin_dossier)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = fdf.fk_soc)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)
			WHERE fd.nature_financement = 'INTERNE'
				AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement')
				AND fd.date_solde < '1970-00-00 00:00:00'
			GROUP BY c.rowid";
	
	$ATMdb->Execute($sql);
	
	$TRes = array();
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('label')] = array(
						"Rentabilité Prévisionnelle"=>$ATMdb->Get_field('renta_previsionnelle')
						,"Rentabilité Attendue"=>$ATMdb->Get_field('renta_attendue')
						,"Rentabilité encaissée (non clôturés)"=>$ATMdb->Get_field('renta_relle_nc')
						);
	}
	
	$sql = "SELECT SUM(renta_reelle) as renta_relle,
				   c.label
			FROM ".MAIN_DB_PREFIX."fin_dossier as fd
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as fdf ON (fd.rowid = fdf.fk_fin_dossier)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = fdf.fk_soc)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)
			WHERE fd.nature_financement = 'INTERNE'
				AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement')
				AND fd.date_solde > '1970-00-00 00:00:00'
			GROUP BY c.rowid";
	
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		$TRestemp[$ATMdb->Get_field('label')] = array_merge((array)$TRes[$ATMdb->Get_field('label')],array("Rentabilité réelle (dossiers clôturés)"=>$ATMdb->Get_field('renta_relle')));
	}
	$TRes = array();
	foreach($TRestemp as $categorie=>$TRenta){
		foreach($TRenta as $cle=>$renta){
			$TRes[$cle][$categorie] = $renta;
		}	
	}
	
	?>
		<!-- 8ème tableau -->
		<td>
			<div class="titre">
				Rentabilités
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<td class="titre_colonne">Mandatée</td>
					<td class="titre_colonne">Adossée</td>
					<td class="titre_colonne">Cession</td>
				</tr>
				<?php
				foreach($TRes as $renta=>$TCategorie){
					?>
					<tr>
						<td><?php echo $renta; ?></td>
						<?php
						foreach($TCategorie as $montant){
							?>
							<td class="justifie"><?php echo number_format($montant,2,',',' '); ?> €</td>
							<?php
						}
						?>
					</tr>
					<?php
				}
				?>
				<!-- <tr>
					<td colspan="3">Alerte sur les dossiers clôturés en rentavilité négative (ou < à K %)</td>
				</tr> -->
			</table>
		</td>
	<?php
}

function _listeBalance(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql ="SELECT a.contrat, (SELECT COUNT(rowid) FROM ".MAIN_DB_PREFIX."fin_affaire WHERE date_affaire LIKE '".date('Y')."%' AND contrat = a.contrat)
		   FROM ".MAIN_DB_PREFIX."fin_affaire as a";
		   
	?>
		<!-- 9ème tableau -->
		<td>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td>Balance</td>
					<td>Attentes</td>
					<td>Constats</td>
				</tr>
				<tr>
					<td>< x K € inférieur à 40j</td>
					<td>50K €</td>
					<td>53K €</td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeDossiersIncomplets(&$ATMdb,$date_debut,$date_fin) {
	global $langs, $db, $conf, $user;
	
	$sql ="SELECT a.contrat, (SELECT COUNT(rowid) FROM ".MAIN_DB_PREFIX."fin_affaire WHERE date_affaire LIKE '".date('Y')."%' AND contrat = a.contrat)
		   FROM ".MAIN_DB_PREFIX."fin_affaire as a";
		   
	?>
		<!-- 10ème tableau -->
		<td>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td>Dossiers incomplets</td>
					<td>NB</td>
					<td>% NB</td>
				</tr>
				<tr>
					<td>NB pièces manquantes</td>
					<td></td>
					<td></td>
				</tr>
			</table>
		</td>
	<?php
}
