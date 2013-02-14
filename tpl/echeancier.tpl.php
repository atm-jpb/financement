<table class="echeancier border" width="100%">

	<tr class="entete liste_titre">
		<td>P</td>
		<td>Dates des<br />Loyers</td>
		<td align="right">Capital <br />restant dû HT</td>
		<td align="right">Amortissmt<br />Capital HT</td>
		<td align="right">Part<br />Intérêts</td>
		<td align="right">Assurance</td>
		<td align="right">Loyers<br />HT</td>
		<td align="right">Loyers<br />TTC</td>
	</tr>

	<tr class="ligne">
		<td colspan="2">&nbsp;</td>
		<td align="right"><strong>[autre.capitalInit; frm='0,00 €']</strong></td>
		<td colspan="5">&nbsp;</td>
	</tr>

	
	<tr class="impair">
		<td>[ligne.#; block=tr]</td>
		<td>[ligne.date]</td>
		<td align="right"><strong>[ligne.capital; frm='0,00 €']</strong></td>
		<td align="right">[ligne.amortissement; frm='0,00 €']</td>
		<td align="right">[ligne.interet; frm='0,00 €']</td>
		<td align="right">[ligne.assurance; frm='0,00 €']</td>
		<td align="right">[ligne.loyerHT; frm='0,00 €']</td>
		<td align="right">[ligne.loyer; frm='0,00 €']</td>
	</tr>
	<tr class="pair">
		<td>[ligne.#; block=tr]</td>
		<td>[ligne.date]</td>
		<td align="right"><strong>[ligne.capital; frm='0,00 €']</strong></td>
		<td align="right">[ligne.amortissement; frm='0,00 €']</td>
		<td align="right">[ligne.interet; frm='0,00 €']</td>
		<td align="right">[ligne.assurance; frm='0,00 €']</td>
		<td align="right">[ligne.loyerHT; frm='0,00 €']</td>
		<td align="right">[ligne.loyer; frm='0,00 €']</td>
	</tr>
	
	<tr class="ligne">
		<td colspan="5">&nbsp;</td>
		<td>Val. Résiduelle</td>
		<td align="right">[autre.reste; frm='0,00 €']</td>
		<td align="right">[autre.resteTTC; frm='0,00 €']</td>
	</tr>
	
</table>
