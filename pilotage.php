<?php
require('config.php');
require('./class/simulation.class.php');
require('./class/grille.class.php');
require('./class/affaire.class.php');
require('./class/dossier.class.php');
require('./class/score.class.php');

require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");

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
</style>

<table width="50%"cellpadding="0" cellspacing="0">
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
	</tr>
	<tr><td height="15"></td></tr>
	<tr>
		<?php _listeCAFactureMaterielParCategorie($ATMdb,"leaser"); ?>
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
						<td><?=$TNb[0];?></td>
						<td><?=$TNb[1];?></td>
						<td><?=round(($TNb[0]*100)/$Total1);?></td>
						<td><?=round(($TNb[1]*100)/$Total2);?></td>
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
					foreach($TRes[$TNb] as $cle=>$nb){
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
						<td><?=$nb;?></td>
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
		$sql .= "AND c.label IN ('Mandatee','Adossee','Cession') ";
	elseif($type=="leaser")
		$sql .= "AND c.label != 'Mandatee' AND c.label != 'Adossee' AND c.label != 'Cession' ";
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
		$sql .= "AND c.label IN ('Mandatee','Adossee','Cession')";
	elseif($type=="leaser")
		$sql .= "AND c.label != 'Mandatee' AND c.label != 'Adossee' AND c.label != 'Cession'";
	$sql .= "GROUP BY c.rowid, s.rowid";
	
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
								<td><?=number_format($TNb[1],2,',',' ')?> €</td>
								<td><?=$TNb[0]?></td>
								<td><?=number_format($TNb[3],2,',',' ')?> €</td>
								<td><?=$TNb[2]?></td>
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
							<td style="font-weight: bold;"><?=number_format($totalCA1,2,',',' ')?> €</td>
							<td style="font-weight: bold;"><?=$totalNb1?></td>
							<td style="font-weight: bold;"><?=number_format($totalCA2,2,',',' ')?> €</td>
							<td style="font-weight: bold;"><?=$totalNb2?></td>
						</tr>
						<?php
					}
					elseif($type=="leaser"){
						?>
						<tr>
							<td><?=$categorie;?></td>
							<td><?=number_format($totalCA1,2,',',' ')?> €</td>
							<td><?=$totalNb1?></td>
							<td><?=number_format($totalCA2,2,',',' ')?> €</td>
							<td><?=$totalNb2?></td>
						</tr>
						<?php
					}
				}
				?>
				<tr>
					<td style="font-weight: bold;">TOTAL</td>
					<td style="font-weight: bold;"><?=number_format($TotalCA1,2,',',' ')?> €</td>
					<td style="font-weight: bold;"><?=$TotalNb1?></td>
					<td style="font-weight: bold;"><?=number_format($TotalCA2,2,',',' ')?> €</td>
					<td style="font-weight: bold;"><?=$TotalNb2?></td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeSommeCRDLeaserParCategoriesFournisseur(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql = "SELECT fdf.rowid as rowid, s.nom
			FROM ".MAIN_DB_PREFIX."fin_dossier as fd
				LEFT JOIN ".MAIN_DB_PREFIX."fin_dossier_financement as fdf ON (fdf.fk_fin_dossier = fd.rowid)
				LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON (s.rowid = fdf.fk_soc)
			WHERE fdf.type = 'LEASER'
				AND fd.nature_financement = 'INTERNE'
				AND fd.date_solde = '0000-00-00 00:00:00'";
	
	$TIdDossierFinancement = TRequeteCore::_get_id_by_sql($ATMdb,$sql,'rowid');
	$TRes = array();
	foreach($TIdDossierFinancement as $idDossierFin){
		$dossierFin = new TFin_financement;
		$dossierFin->load($ATMdb, $idDossierFin);
		
		//$TRes
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
					<td></td>
				</tr>
				<tr>
					<td>Acecom</td>
					<td></td>
					<td></td>
					<td>10 000 000 €</td>
					<td>10 000 000 €</td>
					<td>20 %</td>
				</tr>
				<tr>
					<td>BNP</td>
					<td>450 000 €</td>
					<td>2 000 000 €</td>
					<td>10 350 000 €</td>
					<td>12 800 000 €</td>
					<td>26 %</td>
				</tr>
				<tr>
					<td>Total</td>
					<td>18 750 000 €</td>
					<td>9 000 000</td>
					<td>21 350 000 €</td>
					<td>49 100 000 €</td>
				</tr>
				<tr>
					<td></td>
					<td>38 %</td>
					<td>18 %</td>
					<td>43 %</td>
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
	
	$sql ="SELECT a.contrat, (SELECT COUNT(rowid) FROM ".MAIN_DB_PREFIX."fin_affaire WHERE date_affaire LIKE '".date('Y')."%' AND contrat = a.contrat)
		   FROM ".MAIN_DB_PREFIX."fin_affaire as a";
		   
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
					<td>0</td>
					<td>10</td>
				</tr>
				<tr>
					<td>NB dossiers externes non rattachés</td>
					<td>50</td>
					<td>10%</td>
				</tr>
				<tr>
					<td>Stat  appels?</td>
					<td>à mesurer puis à définir</td>
					<td></td>
				</tr>
			</table>
		</td>
	<?php
}

function _listeRentabilite(&$ATMdb) {
	global $langs, $db, $conf, $user;
	
	$sql ="SELECT a.contrat, (SELECT COUNT(rowid) FROM ".MAIN_DB_PREFIX."fin_affaire WHERE date_affaire LIKE '".date('Y')."%' AND contrat = a.contrat)
		   FROM ".MAIN_DB_PREFIX."fin_affaire as a";
		   
	?>
		<!-- 8ème tableau -->
		<td>
			<div class="titre">
				Rentabilités
			</div>
			<table class="border" width="100%">
				<tr class="liste_titre">
					<td></td>
					<td class="titre_colonne">Adossée</td>
					<td class="titre_colonne">Mandatée</td>
					<td class="titre_colonne">Cession</td>
				</tr>
				<tr>
					<td>Rentabilité attendue (écart de taux)</td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td>Rentabilité encaissée (non clôturés)</td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td>Rentabilité des dossiers clôturés</td>
					<td></td>
					<td></td>
				</tr>
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