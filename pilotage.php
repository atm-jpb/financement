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
	}
</style>

<script type="text/javascript">
	$(document).ready(function(){

	<?php
	
	$PDOdb=new TPDOdb;
	$dash=new TReport_dashboard;
	$dash->initByCode($PDOdb, 'PRODUCTIONFOURNISSEUR');
	
	?>$('#chart_prod_fournisseur').html('<div id="chart_prod_fournisseur" style="height:<?=$dash->hauteur?>px; margin-bottom:20px;"></div>');<?
	
	$dash->get('chart_prod_fournisseur', true);
	
	$dash=new TReport_dashboard;
	$dash->initByCode($PDOdb, 'PRODUCTIONLEASER');
	
	?>$('#chart_prod_leaser').html('<div id="chart_prod_leaser" style="height:<?=$dash->hauteur?>px; margin-bottom:20px;"></div>');<?
	
	$dash->get('chart_prod_leaser', true);


	$PDOdb->close();
	
	?>
});
</script>

<table cellpadding="0" cellspacing="0" style="white-space: nowrap;">
	<tr>
		<td><div class="titre" style="text-align: center;font-size: 22px;">Pilotage de la cellule Financement</div></td>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeNbAffaireParTypeContrat($ATMdb); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeNbAffaireParTypeContratParMois($ATMdb); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeCAFactureMaterielParCategorie($ATMdb,"fournisseur"); ?>
		<td width="50%">
			<!--- eChart -->
			<div id="chart_prod_fournisseur" style="position:relative;margin-left: 50px;width: 800px;"></div>
		</td>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeCAFactureMaterielParCategorie($ATMdb,"leaser"); ?>
		<td width="50%">
			<!--- eChart -->
			<div id="chart_prod_leaser" style="position:relative;margin-left: 50px;width: 800px;"></div>
		</td>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeSommeCRDLeaserParCategoriesFournisseur($ATMdb); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeRelationCommerciales($ATMdb); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeAdministrationDolibarr($ATMdb); ?>
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeRentabilite($ATMdb); ?>
	</tr>
	<tr><td height="15"></td></tr>
</table>
<?php

llxFooter();
	
function _listeNbAffaireParTypeContrat(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql ="SELECT contrat, count(*) as 'nb', MONTH(date_affaire) as 'm', YEAR(date_affaire) as 'y'
		   FROM ".MAIN_DB_PREFIX."fin_affaire
		   WHERE date_affaire >= '".((date('Y')-1)."-0".$conf->global->SOCIETE_FISCAL_MONTH_START)."%'
		   	 AND date_affaire <= '".date('Y-0'.$conf->global->SOCIETE_FISCAL_MONTH_START)."%'
		   AND contrat IS NOT NULL AND contrat != ''
		   GROUP BY contrat, `y`, `m`";
	
	$ATMdb->Execute($sql);
	$TRes = array();
	$Total1 = 0;
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('contrat')][0] += $ATMdb->Get_field('nb');
		$Total1 += $ATMdb->Get_field('nb');
	}
	
	$sql ="SELECT contrat, count(*) as 'nb', MONTH(date_affaire) as 'm', YEAR(date_affaire) as 'y'
		   FROM ".MAIN_DB_PREFIX."fin_affaire
		   WHERE date_affaire >= '".(date('Y-0'.$conf->global->SOCIETE_FISCAL_MONTH_START))."%'
		   AND contrat IS NOT NULL AND contrat != ''
		   GROUP BY contrat, `y`, `m`";
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
					<td class="titre_colonne"><?=date('Y')-1;?>/<?=date('Y');?></td>
					<td class="titre_colonne"><?=date('Y');?>/<?=date('Y')+1;?></td>
					<td class="titre_colonne"><?=date('Y')-1;?>/<?=date('Y');?></td>
					<td class="titre_colonne"><?=date('Y');?>/<?=date('Y')+1;?></td>
				</tr>
				<?php
				foreach($TRes as $contrat=>$TNb){
					?>
					<tr>
						<td><?=$contrat;?></td>
						<td class="justifie"><?=$TNb[0];?></td>
						<td class="justifie"><?=$TNb[1];?></td>
						<td><?=round(($TNb[0]*100)/$Total1);?> %</td>
						<td><?=round(($TNb[1]*100)/$Total2);?> %</td>
					</tr>
					<?php	
				}
				?>
			</table>
		</td>
	<?php
}

function _listeNbAffaireParTypeContratParMois(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql ="SELECT contrat, count(*) as 'nb', MONTH(date_affaire) as 'm'
		   FROM ".MAIN_DB_PREFIX."fin_affaire
		   WHERE date_affaire >= '".(date('Y-0'.$conf->global->SOCIETE_FISCAL_MONTH_START))."%'
		   AND contrat IS NOT NULL AND contrat != ''
		   GROUP BY contrat, `m`";
	
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
					<td class="titre_colonne"><?=ucfirst($langs->trans(strftime('%B',strtotime("2013-".$cle."-01")))); ?></td>
					<?php
					}
					?>
				</tr>
				<?php
				foreach($TRes as $contrat=>$TNb){
					?>
				<tr>
					<td><?=$contrat;?></td>
					<?php
					foreach($TNb as $cle=>$nb){
						?>
						<td class="justifie"><?=$nb;?></td>
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

function _listeCAFactureMaterielParCategorie(&$ATMdb,$type) {
	global $langs, $db, $conf, $user;
	
	//Requête pour facture année N-1
	$sql = "SELECT c.label as 'categorie',s.nom as 'societe', COUNT(f.rowid) as 'nb', SUM(f.total_ttc) as 'montant'
			FROM ".MAIN_DB_PREFIX."facture as f
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (f.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire as fa ON (fa.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)	
			WHERE s.fournisseur = 1 AND s.client = 1
			 AND f.datef >= '".((date('Y')-1)."-0".$conf->global->SOCIETE_FISCAL_MONTH_START)."%'
		   	 AND f.datef < '".date('Y-0'.$conf->global->SOCIETE_FISCAL_MONTH_START)."%' ";
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
	$sql = "SELECT c.label as 'categorie',s.nom as 'societe', COUNT(f.rowid) as 'nb', SUM(f.total_ttc) as 'montant'
			FROM ".MAIN_DB_PREFIX."facture as f
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (f.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."fin_affaire as fa ON (fa.fk_soc = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)	
			WHERE s.fournisseur = 1 AND s.client = 1
			 AND f.datef >= '".((date('Y'))."-0".$conf->global->SOCIETE_FISCAL_MONTH_START)."%'";
	if($type=="fournisseur")
		$sql .= "AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement') ";
	elseif($type=="leaser")
		$sql .= "AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Leaser') ";
	$sql .= "GROUP BY c.rowid, s.rowid";
	
	//echo $sql.'<br>';
	
	//Merging des deux tableaux de résultat
	$ATMdb->Execute($sql);
	while($ATMdb->Get_line()){
		$TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')] = array_merge(
							(array)$TRes[$ATMdb->Get_field('categorie')][$ATMdb->Get_field('societe')]
							,array($ATMdb->Get_field('nb'),$ATMdb->Get_field('montant'))
						);
	}
	
	?>
		<!-- 3ème tableau -->
		<td>
			<div class="titre">
				Production de l'exercice / Catégorie <?=ucfirst($type);?>
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<td colspan="2" class="titre_colonne"><?=date('Y')-1;?>/<?=date('Y');?></td>
					<td colspan="2" class="titre_colonne"><?=date('Y');?>/<?=date('Y')+1;?></td>
				</tr>
				<tr class="liste_titre">
					<td class="titre_colonne">au <?=date('d/m/Y');?></td>
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
								<td><?=$societe?></td>
								<td class="justifie"><?=number_format($TNb[1],2,',',' ')?> €</td>
								<td class="justifie"><?=$TNb[0]?></td>
								<td class="justifie"><?=number_format($TNb[3],2,',',' ')?> €</td>
								<td class="justifie"><?=$TNb[2]?></td>
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
							<td style="font-weight: bold;">Sous Total <?=$categorie;?></td>
							<td style="font-weight: bold;" class="justifie"><?=number_format($totalCA1,2,',',' ')?> €</td>
							<td style="font-weight: bold;" class="justifie"><?=$totalNb1?></td>
							<td style="font-weight: bold;" class="justifie"><?=number_format($totalCA2,2,',',' ')?> €</td>
							<td style="font-weight: bold;" class="justifie"><?=$totalNb2?></td>
						</tr>
						<?php
					}
					elseif($type=="leaser"){
						?>
						<tr>
							<td><?=$categorie;?></td>
							<td class="justifie"><?=number_format($totalCA1,2,',',' ')?> €</td>
							<td class="justifie"><?=$totalNb1?></td>
							<td class="justifie"><?=number_format($totalCA2,2,',',' ')?> €</td>
							<td class="justifie"><?=$totalNb2?></td>
						</tr>
						<?php
					}
				}
				?>
				<tr>
					<td style="font-weight: bold;">TOTAL</td>
					<td style="font-weight: bold;" class="justifie"><?=number_format($TotalCA1,2,',',' ')?> €</td>
					<td style="font-weight: bold;" class="justifie"><?=$TotalNb1?></td>
					<td style="font-weight: bold;" class="justifie"><?=number_format($TotalCA2,2,',',' ')?> €</td>
					<td style="font-weight: bold;" class="justifie"><?=$TotalNb2?></td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeSommeCRDLeaserParCategoriesFournisseur(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT fdf.rowid as rowid, s.nom, c.label
			FROM ".MAIN_DB_PREFIX."fin_dossier as fd
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as fdf ON (fdf.fk_fin_dossier = fd.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = fdf.fk_soc)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_societe = s.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."categorie as c ON (c.rowid = cf.fk_categorie)
			WHERE fdf.type = 'LEASER'
				AND fd.nature_financement = 'INTERNE'
				AND fd.date_solde = '0000-00-00 00:00:00'
				AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Leaser')
			ORDER BY c.rowid";
	
	$ATMdb->Execute($sql);
	$TRestemp = array();
	while($ATMdb->Get_line()){
		$TRestemp[$ATMdb->Get_field('label')][$ATMdb->Get_field('rowid')] = $ATMdb->Get_field('nom');
	}
	
	$TRes = $TTotal = array();
	foreach($TRestemp as $categorie=>$TidDossier){
		
		foreach($TidDossier as $iddossier=>$societe){
			$dossierFin = new TFin_financement;
			$dossierFin->load($ATMdb, $iddossier);
			
			$sql = "SELECT c.label 
					FROM ".MAIN_DB_PREFIX."categorie as c
						LEFT JOIN ".MAIN_DB_PREFIX."categorie_fournisseur as cf ON (cf.fk_categorie = c.rowid)
						LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = cf.fk_societe)
					WHERE s.nom = '".$societe."'
						AND c.fk_parent = (SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = 'Type de financement')";
			
			$ATMdb->Execute($sql);
			$ATMdb->Get_line();
			$categorieLeaser = $ATMdb->Get_field('label');
			
			$TRes[$categorie][$categorieLeaser] += $dossierFin->valeur_actuelle();
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
					<td class="titre_colonne">au <?=date('d/m/Y');?></td>
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
						<td><?=$categorie?></td>
						<td class="justifie"><?=number_format($TCategorieLeaser['Cession'],2,',',' ');?> €</td>
						<td class="justifie"><?=number_format($TCategorieLeaser['Mandatee'],2,',',' ');?> €</td>
						<td class="justifie"><?=number_format($TCategorieLeaser['Adossee'],2,',',' ');?> €</td>
						<td class="justifie"><?=number_format($TCategorieLeaser['Adossee'] + $TCategorieLeaser['Mandatee'] + $TCategorieLeaser['Cession'],2,',',' ');?> €</td>
						<td><?=number_format(($TCategorieLeaser['Adossee'] + $TCategorieLeaser['Mandatee'] + $TCategorieLeaser['Cession']) * 100 / $TTotaux['total'],2,',','');?> %</td>
					</tr>
					<?php
				}
				?>
				<tr style="font-weight: bold;">
					<td>TOTAL</td>
					<td class="justifie"><?=number_format($sommeCession,2,',',' ') ?></td>
					<td class="justifie"><?=number_format($sommeMandatee,2,',',' ') ?></td>
					<td class="justifie"><?=number_format($sommeAdossee,2,',',' ') ?></td>
					<td class="justifie"><?=number_format($sommeCession + $sommeMandatee + $sommeAdossee,2,',',' ')?></td>
				</tr>
				<tr>
					<td>%</td>
					<td><?=number_format(($sommeCession * 100) / $TTotaux['total'],2,',',' ') ?> %</td>
					<td><?=number_format(($sommeMandatee * 100) / $TTotaux['total'],2,',',' ') ?> %</td>
					<td><?=number_format(($sommeAdossee * 100) / $TTotaux['total'],2,',',' ') ?> %</td>
					<td></td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeRelationCommerciales(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT count(*) as 'nbAuto', MONTH(date_simul) as 'm', (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."fin_simulation WHERE MONTH(date_simul) = `m`)  as 'nbTotal'
			FROM ".MAIN_DB_PREFIX."fin_simulation 
			WHERE date_simul = date_accord
				AND date_simul >= '".((date('Y'))."-0".$conf->global->SOCIETE_FISCAL_MONTH_START)."%'
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
					<td class="titre_colonne"><?=ucfirst($langs->trans(strftime('%B',strtotime("2013-".$cle."-01")))); ?></td>
					<?php
					}
					?>
				</tr>
				<tr>
					<td>% accords automatiques</td>
					<?php
					foreach($TRes as $tres){
						?>
						<td><?=($tres["nbAuto"]) ? round(($tres["nbAuto"] * 100) / $tres["nbTotal"]) : "0";?> %</td>
						<?php 
					}
					?>
				</tr>
				<tr>
					<td>Délais accords non automatiques</td>
					<?php
					foreach($TRes as $tres){
						?>
						<td><?=round($tres["delais"]);?> jours</td>
						<?php 
					}
					?>
				</tr>
			</table>
		</td>
	<?php
}

function _listeAdministrationDolibarr(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT COUNT(d.rowid) as 'nb'
			FROM ((((llx_fin_dossier d LEFT OUTER JOIN llx_fin_dossier_affaire l ON (d.rowid=l.fk_fin_dossier)) 
				LEFT OUTER JOIN llx_fin_affaire a ON (l.fk_fin_affaire=a.rowid)) 
				LEFT OUTER JOIN llx_fin_dossier_financement f ON (d.rowid=f.fk_fin_dossier )) 
				LEFT OUTER JOIN llx_societe s ON (a.fk_soc=s.rowid)) 
			WHERE a.entity=1 
				AND a.nature_financement = 'INTERNE' 
				AND (f.type = 'LEASER' 
				AND (f.reference IS NULL OR f.reference = '' OR f.duree = 0 OR f.echeance = 0)) 
				AND DATEDIFF(a.date_affaire,CURDATE()) > 30";
				
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
					<td class="titre_colonne">Attentes</td>
					<td class="titre_colonne">Constats</td>
				</tr>
				<tr>
					<td>Nb dossier internes > 1 mois incomplets</td>
					<td class="justifie">0</td>
					<td class="justifie"><?=$NbDossier;?></td>
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

function _listeRentabilite(&$ATMdb) {
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
				AND fd.date_solde = '0000-00-00 00:00:00'
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
				AND fd.date_solde != '0000-00-00 00:00:00'
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
						<td><?=$renta?></td>
						<?php
						foreach($TCategorie as $montant){
							?>
							<td class="justifie"><?=number_format($montant,2,',',' ');?> €</td>
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

function _listeBalance(&$ATMdb) {
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

function _listeDossiersIncomplets(&$ATMdb) {
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