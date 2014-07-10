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
	</table>
	<br>
	<table class="border" width="100%">
		<tr class="liste_titre">
			<td align="center">Période</td>
			<td align="center">Facture</td>
			<td align="center">Vol noir engag&eacute;</td>
			<td align="center">Vol noir r&eacute;alis&eacute;</td>
			<td align="center">Vol noir factur&eacute;</td>
			<td align="center">Coût unitaire noir</td>
			<td align="center">Vol couleur engag&eacute;</td>
			<td align="center">Vol couleur r&eacute;alis&eacute;</td>
			<td align="center">Vol couleur factur&eacute;</td>
			<td align="center">Coût unitaire couleur</td>
			<td align="center">FAS</td>
			<td align="center">FASS</td>
			<td align="center">Frais de dossier</td>
			<td align="center">Bris de machine</td>
			<td align="center">FTC</td>
			<td align="center">Total HT engag&eacute;</td>
			<!--<td align="center">Total HT r&eacute;alis&eacute;</td>-->
			<td align="center">Total HT factur&eacute;</td>
			<td align="center">%</td>
		</tr>
		<tr>
			<td align="center">[integrale.periode;block=tr;noerr]</td>
			<td align="center">[integrale.facnumber;block=tr;noerr]</td>
			<td align="center">[integrale.vol_noir_engage;noerr]</td>
			<td align="center">[integrale.vol_noir_realise;noerr]</td>
			<td align="center">[integrale.vol_noir_facture;noerr]</td>
			<td align="right">[integrale.cout_unit_noir;frm=0 000,00000;noerr] &euro;</td>
			<td align="center">[integrale.vol_coul_engage;noerr]</td>
			<td align="center">[integrale.vol_coul_realise;noerr]</td>
			<td align="center">[integrale.vol_coul_facture;noerr]</td>
			<td align="right">[integrale.cout_unit_coul;frm=0 000,00000;noerr] &euro;</td>
			<td align="right">[integrale.fas;frm=0 000,00;noerr] &euro;</td>
			<td align="right">[integrale.fass;frm=0 000,00;noerr] &euro;</td>
			<td align="right">[integrale.frais_dossier;frm=0 000,00;noerr] &euro;</td>
			<td align="right">[integrale.frais_bris_machine;frm=0 000,00;noerr] &euro;</td>
			<td align="right">[integrale.frais_facturation;frm=0 000,00;noerr] &euro;</td>
			<td align="right">[integrale.total_ht_engage;frm=0 000,00;noerr] &euro;</td>
			<!--<td align="right">[integrale.total_ht_realise;frm=0 000,00;noerr] &euro;</td>-->
			<td align="right">[integrale.total_ht_facture;frm=0 000,00;noerr] &euro;</td>
			<td align="center">[integrale.ecart;frm=0 000,00;noerr] %</td>
		</tr>
	</table>

</div>