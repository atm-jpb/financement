<?php

/* Copyright (C) 2012	  Maxime Kohlhaas		<maxime.kohlhaas@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**		\file		reports/tpl/simulateur.tpl.php
 *		\ingroup	financement
 *		\brief		Outil de simulation
 */

?>
<table width="100%"cellpadding="0" cellspacing="0">
<tr>
<td valign="top" width="50%" style="padding-right: 5px;">
	<div id="simulateur" style="width: 100%;">
	
	<?php print_fiche_titre($langs->trans("Simulator"),'','simul32.png@financement'); ?>
	<br />
	<form action="<?php echo $_SERVER['PHP_SELF'] ?>" method="post">
		<input type="hidden" name="mode" value="<?php echo $mode ?>" />
	
		<table class="liste" width="100%">
			<tr class="liste_titre">
				<td><?php echo $langs->trans('CustomerSearch') ?></td>
				<td><input type="text" name="search_customer" value="<?php echo $search_customer ?>" class="flat" /></td>
				<td><input type="submit" name="search" value="<?php echo $langs->trans('Search') ?>" class="button" /></td>
			</tr>
		</table>
	</form>
	
	<?php if(!empty($customer_list)) { ?>
	
	<table class="liste" width="100%">
	
	<?php
	$var = false;
	$companystatic=new Societe($db);
	while($obj = $db->fetch_object($customer_list)) {
		$var = !$var;
		$companystatic->id=$obj->rowid;
		$companystatic->nom=$obj->nom;
		$companystatic->client=$obj->client;
	?>
		
		<tr class="<?php echo $var ? 'pair' : 'impair' ?>">
			<td><?php echo $companystatic->getNomUrl(1,'customer') ?></td>
			<td><?php echo $obj->siren ?></td>
			<td><?php echo $obj->cp ?></td>
			<td><?php echo $obj->ville ?></td>
			<td><a href="<?php echo $_SERVER['PHP_SELF'] ?>?mode=simul&socid=<?php echo $obj->rowid ?>"><?php echo $langs->trans('Select') ?></a></td>
		</tr>
		
	<?php } ?>
	
	</table>
			
	<?php } ?>
	
	<?php if(!empty($customer)) { ?>
	<br />
	<?php print_fiche_titre($langs->trans('CustomerInfos'), '', '') ?>
	<table class="border" width="100%">
		<tr>
			<td width="25%"><?php echo $langs->trans('ThirdPartyName') ?></td>
			<td width="25%"><?php echo $customer->getNomUrl(1,'customer') ?></td>
			<td width="25%"><?php echo $langs->trans('CustomerScore') ?></td>
			<td width="25%" align="center"><?php echo $customer->score ?> / 100</td>
		</tr>
		<tr>
			<td><?php echo $langs->transcountry('ProfId1',$customer->country_code); ?></td>
			<td><?php echo $customer->idprof1 ?></td>
			<td><?php echo $langs->trans('CustomerEncoursMax') ?></td>
			<td align="right"><?php echo $customer->encours_max . ' ' . $langs->trans("Currency".$conf->currency) ?></td>
		</tr>
		<tr>
			<td><?php echo $langs->trans('Zip').' / '.$langs->trans("Town") ?></td>
			<td><?php echo $customer->cp ?> / <?php echo $customer->ville ?></td>
			<td><?php echo $langs->trans('CustomerEncoursCPRO') ?></td>
			<td align="right"><?php echo $customer->encours_cpro . ' ' . $langs->trans("Currency".$conf->currency) ?></td>
		</tr>
	</table>
	<br />
	<?php print_fiche_titre($langs->trans('CustomerDossierFinancement'), '', '') ?>
		<?php if(!empty($dossier_list)) { ?>
	<table class="liste" width="100%">
		<tr class="liste_titre">
			<td><?php echo $langs->trans('DossierRef') ?></td>
			<td><?php echo $langs->trans('DossierMontant') ?></td>
			<td><?php echo $langs->trans('DateStart') ?></td>
			<td><?php echo $langs->trans('DateEnd') ?></td>
		</tr>
			<?php
			while($obj = $db->fetch_object($dossier_list)) {
				$var = !$var;
			?>
		<tr class="<?php echo $var ? 'pair' : 'impair' ?>">
			<td><a href="dossier.php?id=<?php echo $obj->rowid ?>"><?php echo $obj->ref ?></a></td>
			<td><?php echo $obj->montant ?></td>
			<td><?php echo $obj->datedeb ?></td>
			<td><?php echo $obj->datefin ?></td>
		</tr>
		
			<?php } ?>
	</table>
		<?php } else { ?>
	<table class="liste" width="100%">
		<tr class="liste_titre">
			<td><?php echo $langs->trans('NoDossierFound') ?></td>
		</tr>
	</table>
		<?php } ?>
	<?php } ?>
	
	</div>
</td>
<td valign="top" style="padding-left: 5px;">
<?php include 'tpl/calculateur.tpl.php' ?>
</td>
</tr>
</table>