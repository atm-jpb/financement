<table class="echeancier border" width="100%">

	<tr class="liste_titre">
		<td>P</td>
		<td>Dates des<br />Loyers</td>
		<td align="right">Capital <br />restant dû HT</td>
		<td align="right">Amortissmt<br />Capital HT</td>
		<td align="right">Part<br />Intérêts</td>
		<td align="right">Assurance</td>
		<td align="right">Loyers<br />HT</td>
	</tr>

	<tr class="pair">
		<td colspan="2">&nbsp;</td>
		<td align="right"><strong>[autre.capitalInit; frm=0 000,00] &euro;</strong></td>
		<td colspan="5">&nbsp;</td>
	</tr>
	
	<tr class="impair">
		<td>[ligne.#; block=tr]</td>
		<td>[ligne.date]</td>
		<td align="right"><strong>[ligne.capital; frm=0 000,00] &euro;</strong></td>
		<td align="right">[ligne.amortissement; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.interet; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.assurance; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.loyerHT; frm=0 000,00] &euro;</td>
	</tr>
	<tr class="pair">
		<td>[ligne.#; block=tr]</td>
		<td>[ligne.date]</td>
		<td align="right"><strong>[ligne.capital; frm=0 000,00] &euro;</strong></td>
		<td align="right">[ligne.amortissement; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.interet; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.assurance; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.loyerHT; frm=0 000,00] &euro;</td>
	</tr>

	<tr class="liste_titre">
		<td align="center" colspan="3">Totaux</td>
		<td align="right">[autre.total_capital_amortit; frm=0 000,00] &euro;</td>
		<td align="right">[autre.total_part_interet; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.assurance; frm=0 000,00] &euro;</td>
		<td align="right">[autre.total_loyer; frm=0 000,00] &euro;</td>
	</tr>
	
</table>
