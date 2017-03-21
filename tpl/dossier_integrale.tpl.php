<div class="tabs">
	<a class="tabTitle"><img border="0" title="" alt="" src="./img/object_financeico.png"> Dossier</a>
	<a href="dossier.php?id=[dossier.rowid]" class="tab">Fiche</a>
	<a href="dossier_integrale.php?id=[dossier.rowid]" class="tab" id="active">Suivi int&eacute;grale</a>
	<a href="[dossier.url_therefore][fin.reference]" class="tab" target="_blank">Therefore</a>
</div>
	
<div class="tabBar">

	<table class="border" width="100%">
		<tr class="liste_titre">
			<td colspan="4">Informations dossier</td>
		</tr>
		<tr>
			<td width="20%">Nom du tiers</td>
			<td width="30%">[client.nom; strconv=no]</a></td>
		</tr>
		<tr>
			<td>N&deg; contrat C'Pro</td>
			<td>[fin.reference]</td>
		</tr>
		<tr>
			<td>Date de début</td>
			<td>[fin.date_debut; frm=dd/mm/yy]</td>
		</tr>
		<tr>
			<td>Terme</td>
			<td>[fin._affterme]</td>
		</tr>
		<tr>
			<td>Facturation</td>
			<td>[fin._affperiodicite]</td>
		</tr>
		<tr>
			<td>Durée</td>
			<td>[fin.duree]</td>
		</tr>
		<tr>
			<td>Type de régul</td>
			<td>[dossier.type_regul]</td>
		</tr>
	</table>
	<br>
	<table class="border" width="100%">
		<tr class="liste_titre">
			<th class="liste_titre" align="center">P&eacute;riode</th>
			<th class="liste_titre" align="center">Facture</th>
			<th class="liste_titre" align="center">Date</th>
			<th class="liste_titre" align="center">Vol noir<br>engag&eacute;</th>
			[onshow;block=begin;when [error]=='0']<th class="liste_titre" align="center">Vol noir<br>r&eacute;alis&eacute;</th>[onshow;block=end]
			[onshow;block=begin;when [error]=='0']<th class="liste_titre" align="center">Vol noir<br>factur&eacute;</th>[onshow;block=end]
			<th class="liste_titre" align="center">Coût unitaire<br>noir</th>
			<th class="liste_titre" align="center">Vol couleur<br>engag&eacute;</th>
			[onshow;block=begin;when [error]=='0']<th class="liste_titre" align="center">Vol couleur<br>r&eacute;alis&eacute;</th>[onshow;block=end]
			[onshow;block=begin;when [error]=='0']<th class="liste_titre" align="center">Vol couleur<br>factur&eacute;</th>[onshow;block=end]
			<th class="liste_titre" align="center">Coût unitaire<br>couleur</th>
			<th class="liste_titre" align="center">FAS</th>
			<th class="liste_titre" align="center">FASS</th>
			<th class="liste_titre" align="center">Frais de<br>dossier</th>
			<th class="liste_titre" align="center">Bris de<br>machine</th>
			<th class="liste_titre" align="center">FTC</th>
			<th class="liste_titre" align="center">Total HT<br>engag&eacute;</th>
			<!--<th class="liste_titre" align="center">Total HT r&eacute;alis&eacute;</th>-->
			[onshow;block=begin;when [error]=='0']<th class="liste_titre" align="center">Total HT<br>factur&eacute;</th>[onshow;block=end]
			<th class="liste_titre" align="center">%</th>
			<th class="liste_titre" align="center">Propositions<br>associées</th>
		</tr>
		<tr class="impair">
			<td align="center">[integrale.date_periode;block=tr;noerr]</td>
			<td align="center">[integrale.facnumber;block=tr;noerr;strconv=no]</td>
			<td align="center">[integrale.date_facture;block=tr;noerr;strconv=no]</td>
			<td align="center">[integrale.vol_noir_engage;noerr;strconv=no]</td>
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_noir_realise;noerr;strconv=no]</td>[onshow;block=end]
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_noir_facture;noerr;strconv=no]</td>[onshow;block=end]
			<td align="right" nowrap="nowrap">[integrale.cout_unit_noir;frm=0,00000;noerr;strconv=no]</td>
			<td align="center">[integrale.vol_coul_engage;noerr;strconv=no]</td>
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_coul_realise;noerr;strconv=no]</td>[onshow;block=end]
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_coul_facture;noerr;strconv=no]</td>[onshow;block=end]
			<td align="right" nowrap="nowrap">[integrale.cout_unit_coul;frm=0,00000;noerr;strconv=no]</td>
			<td align="right" nowrap="nowrap">[integrale.fas;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.fass;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.frais_dossier;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.frais_bris_machine;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.frais_facturation;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.total_ht_engage;frm=0 000,00;noerr] &euro;</td>
			<!--<td align="right" nowrap="nowrap">[integrale.total_ht_realise;frm=0 000,00;noerr] &euro;</td>-->
			[onshow;block=begin;when [error]=='0']<td align="right" nowrap="nowrap">[integrale.total_ht_facture;frm=0 000,00;noerr] &euro;</td>[onshow;block=end]
			<td align="center" nowrap="nowrap">[integrale.ecart;frm=0 000,00;noerr] %</td>
			<td align="center" nowrap="nowrap">[integrale.propal;frm=0 000,00;noerr]</td>
		</tr>
		<tr class="pair">
			<td align="center">[integrale.date_periode;block=tr;noerr]</td>
			<td align="center">[integrale.facnumber;block=tr;noerr;strconv=no]</td>
			<td align="center">[integrale.date_facture;block=tr;noerr;strconv=no]</td>
			<td align="center">[integrale.vol_noir_engage;noerr;strconv=no]</td>
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_noir_realise;noerr;strconv=no]</td>[onshow;block=end]
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_noir_facture;noerr;strconv=no]</td>[onshow;block=end]
			<td align="right" nowrap="nowrap">[integrale.cout_unit_noir;frm=0,00000;noerr;strconv=no]</td>
			<td align="center">[integrale.vol_coul_engage;noerr;strconv=no]</td>
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_coul_realise;noerr;strconv=no]</td>[onshow;block=end]
			[onshow;block=begin;when [error]=='0']<td align="center">[integrale.vol_coul_facture;noerr;strconv=no]</td>[onshow;block=end]
			<td align="right" nowrap="nowrap">[integrale.cout_unit_coul;frm=0,00000;noerr;strconv=no]</td>
			<td align="right" nowrap="nowrap">[integrale.fas;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.fass;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.frais_dossier;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.frais_bris_machine;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.frais_facturation;frm=0 000,00;noerr] &euro;</td>
			<td align="right" nowrap="nowrap">[integrale.total_ht_engage;frm=0 000,00;noerr] &euro;</td>
			<!--<td align="right" nowrap="nowrap">[integrale.total_ht_realise;frm=0 000,00;noerr] &euro;</td>-->
			[onshow;block=begin;when [error]=='0']<td align="right" nowrap="nowrap">[integrale.total_ht_facture;frm=0 000,00;noerr] &euro;</td>[onshow;block=end]
			<td align="center" nowrap="nowrap">[integrale.ecart;frm=0 000,00;noerr] %</td>
			<td align="center" nowrap="nowrap">[integrale.propal;frm=0 000,00;noerr]</td>
		</tr>
	</table>
	[onshow;block=begin;when [error]=='1']
	<br>
	<center>[errormsg]</center>
	[onshow;block=end]
</div>