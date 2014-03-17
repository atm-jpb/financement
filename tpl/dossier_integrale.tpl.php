<table class="border" width="100%">
	<tr class="liste_titre">
		<td colspan="4">Informations dossier</td>
	</tr>
	<tr>
		<td width="20%">Nom du tiers</td>
		<td width="30%">[client.societe; strconv=no] [client.autres_simul; strconv=no]</a></td>
		<td width="20%">Contact</td>
		<td>[client.contact_externe]</td>
	</tr>
	<tr>
		<td>Adresse</td>
		<td>[client.adresse]</td>
		<td>Code Artis</td>
		<td>[client.code_client]</td>
	</tr>
	<tr>
		<td>CP / Ville</td>
		<td>[client.cpville]</td>
		<td>SIRET</td>
		<td>[client.siret]</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
		<td>Code NAF</td>
		<td>[client.naf]</td>
	</tr>
	<tr class="liste_titre">
		<td colspan="4">Suivi intégrale</td>
	</tr>
	<tr>
		<td colspan="4">
			<table class="border" width="100%">
			<tr class="liste_titre">
				<td>Période</td>
				<td>Vol noir engag&eacute;</td>
				<td>Vol noir r&eacute;alis&eacute;</td>
				<td>Vol noir factur&eacute;</td>
				<td>Coût unitaire noir</td>
				<td>Vol couleur engag&eacute;</td>
				<td>Vol couleur r&eacute;alis&eacute;</td>
				<td>Vol couleur factur&eacute;</td>
				<td>Coût unitaire couleur</td>
				<td>FAS</td>
				<td>FASS</td>
				<td>Frais de dossier</td>
				<td>Bris de machine</td>
				<td>FTC</td>
				<td>Total HT engag&eacute;</td>
				<td>Total HT r&eacute;alis&eacute;</td>
				<td>%</td>
			</tr>
			<tr>
				<td>[integrale.periode;block=tr]</td>
				<td>[integrale.vol_noir_engage]</td>
				<td>[integrale.vol_noir_realise]</td>
				<td>[integrale.vol_noir_facture]</td>
				<td>[integrale.cout_unit_noir]</td>
				<td>[integrale.vol_coul_engage]</td>
				<td>[integrale.vol_coul_realise]</td>
				<td>[integrale.vol_coul_facture]</td>
				<td>[integrale.cout_unit_coul]</td>
				<td>[integrale.fas]</td>
				<td>[integrale.fass]</td>
				<td>[integrale.frais_dossier]</td>
				<td>[integrale.frais_bris_machine]</td>
				<td>[integrale.frais_facturation]</td>
				<td>[integrale.total_ht_engage]</td>
				<td>[integrale.total_ht_realise]</td>
				<td>[integrale.ecart]</td>
			</tr>
		</td>
	</tr>
</table>