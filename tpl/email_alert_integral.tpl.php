Bonjour [dataMail.username],
<br><br>
Tu trouveras ci-dessous, le(s) client(s) int&eacute;gral ayant une surfacturation au-del&agrave; de [conf.global.FINANCEMENT_INTEGRALE_ECART_ALERTE_EMAIL] % :
<br><br>
<table width="100%">
	<tr>
		<th>Client</th>
		<th>Contrat Artis</th>
		<th>Facture</th>
		<th>Date facture</th>
		<th>Date période</th>
		<th>Montant<br>engagement</th>
		<th>Montant<br>factur&eacute;</th>
		<th>&Eacute;cart</th>
		<th>Copieur</th>
		<th>Traceur</th>
		<th>Solution</th>
	</tr>
	<tr>
		<td align="center">[content.client;block=tr]</td>
		<td align="center">[content.contrat;strconv=no]</td>
		<td align="center">[content.facture]</td>
		<td align="center">[content.date_facture]</td>
		<td align="center">[content.date_periode]</td>
		<td align="center">[content.montant_engage;frm=0 000,00] &euro;</td>
		<td align="center">[content.montant_facture;frm=0 000,00] &euro;</td>
		<td align="center">[content.ecart;frm=0] %</td>
		<td align="center">[content.1-Copieur]</td>
		<td align="center">[content.2-Traceur]</td>
		<td align="center">[content.3-Solution]</td>
	</tr>
</table>
<br><br>
Bonne journ&eacute;e.
<br><br>
Le service financement.