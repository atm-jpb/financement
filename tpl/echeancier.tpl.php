<table class="echeancier border" width="100%">

	<tr class="liste_titre">
		<td>P</td>
		<td>Dates des<br />Loyers</td>
		<td align="right">Capital <br />restant dû HT</td>
		<td align="right">Amortissmt<br />Capital HT</td>
		<td align="right">Part<br />Intérêts</td>
		<td align="right">Assurance</td>
		<td align="right">Loyers<br />HT</td>
		[onshow; block=begin; when [autre.nature_financement]=='INTERNE']
		<td align="right">Facture<br />HT</td>
		[onshow;block=end]
	</tr>

	<tr class="pair">
		[onshow; block=begin; when [autre.loyer_intercalaire]!=0]
		<td>&nbsp;</td>
		<td>[autre.date_debut]</td>
		[onshow;block=end]
		
		[onshow; block=begin; when [autre.loyer_intercalaire]==0]
		<td colspan="2">&nbsp;</td>
		[onshow;block=end]
		
		<td align="right"><strong>[autre.capitalInit; frm=0 000,00] &euro;</strong></td>
		
		[onshow; block=begin; when [autre.loyer_intercalaire]!=0]
		
		<td colspan="3" align="right">Loyer intercalaire</td>
		<td align="right">[autre.loyer_intercalaire; frm=0 000,00] &euro;</td>
		
		[onshow; block=begin; when [autre.nature_financement]=='INTERNE']
		[onshow; block=begin; when [autre.loyer_intercalaire_facture_total_ht]!='']
		<td align="right" style="background-color: [autre.loyer_intercalaire_facture_bg];"><a href="[autre.loyer_intercalaire_facture_link]">[autre.loyer_intercalaire_facture_total_ht; frm=0 000,00] &euro;</a></td>
		[onshow;block=end]
		[onshow; block=begin; when [autre.loyer_intercalaire_facture_total_ht]=='']
		<td align="center" style="background-color: [autre.loyer_intercalaire_facture_bg];">-</td>
		[onshow;block=end]
		[onshow;block=end]
		
		[onshow;block=end]
		[onshow; block=begin; when [autre.loyer_intercalaire]==0]
		<td colspan="5">&nbsp;</td>
		[onshow;block=end]
	</tr>
	
	<tr class="impair classfortooltip" title="[ligne.soldes; strconv=no]">
		<td>[ligne.#; block=tr]</td>
		<td>[ligne.date]</td>
		<td align="right"><strong>[ligne.capital; frm=0 000,00] &euro;</strong></td>
		<td align="right">[ligne.amortissement; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.interet; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.assurance; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.loyerHT; frm=0 000,00] &euro;</td>
		
		[onshow; block=begin; when [autre.nature_financement]=='INTERNE']
			[onshow; block=begin; when [ligne.facture_total_ht]!='']
				[onshow; block=begin; when [ligne.facture_total_ht]!='+']
					<td align="right" style="padding:0;background-color: [ligne.facture_bg];">
						[onshow;block=begin; when [ligne.facture_multiple]='0']
							<a href="[ligne.facture_link]">[ligne.facture_total_ht; frm=0 000,00] &euro;</a>
						[onshow;block=end]
						[onshow;block=begin; when [ligne.facture_multiple]='1']
							<b>Total : [ligne.facture_total_ht; frm=0 000,00] &euro;</b>
							[ligne.facture_link;strconv=no]
						[onshow;block=end]
					</td>
				[onshow;block=end]
			[onshow;block=end]
			
			[onshow; block=begin; when [ligne.facture_total_ht]=='+']
			<td align="center" style="background-color: [ligne.facture_bg];"><a href="[ligne.facture_link]">+</a></td>
			[onshow;block=end]
			
			[onshow; block=begin; when [ligne.facture_total_ht]=='']
			<td align="center" style="background-color: [ligne.facture_bg];">-</td>
			[onshow;block=end]
		[onshow;block=end]
	</tr>
	<tr class="pair classfortooltip" title="[ligne.soldes; strconv=no]">
		<td>[ligne.#; block=tr]</td>
		<td>[ligne.date]</td>
		<td align="right"><strong>[ligne.capital; frm=0 000,00] &euro;</strong></td>
		<td align="right">[ligne.amortissement; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.interet; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.assurance; frm=0 000,00] &euro;</td>
		<td align="right">[ligne.loyerHT; frm=0 000,00] &euro;</td>
		
		[onshow; block=begin; when [autre.nature_financement]=='INTERNE']
			[onshow; block=begin; when [ligne.facture_total_ht]!='']
				[onshow; block=begin; when [ligne.facture_total_ht]!='+']
					<td align="right" style="background-color: [ligne.facture_bg];">
						[onshow;block=begin; when [ligne.facture_multiple]='0']
							<a href="[ligne.facture_link]">[ligne.facture_total_ht; frm=0 000,00] &euro;</a>
						[onshow;block=end]
						[onshow;block=begin; when [ligne.facture_multiple]='1']
							[ligne.facture_link;strconv=no]
						[onshow;block=end]
					</td>
				[onshow;block=end]
			[onshow;block=end]
			
			[onshow; block=begin; when [ligne.facture_total_ht]=='+']
			<td align="center" style="background-color: [ligne.facture_bg];"><a href="[ligne.facture_link]">+</a></td>
			[onshow;block=end]
	
			[onshow; block=begin; when [ligne.facture_total_ht]=='']
			<td align="center" style="background-color: [ligne.facture_bg];">-</td>
			[onshow;block=end]
		[onshow;block=end]
	</tr>

	<tr class="ligne">
		<td align="right" colspan="6">Val. Résiduelle</td>
		<td align="right">[autre.reste; frm=0 000,00] &euro;</td>
		
		[onshow; block=begin; when [autre.nature_financement]=='INTERNE']
		<td>&nbsp;</td>
		[onshow;block=end]
	</tr>

	<tr class="liste_titre">
		<td align="center" colspan="3">Totaux</td>
		<td align="right">[autre.total_capital_amortit; frm=0 000,00] &euro;</td>
		<td align="right">[autre.total_part_interet; frm=0 000,00] &euro;</td>
		<td align="right">[autre.total_assurance; frm=0 000,00] &euro;</td>
		<td align="right">[autre.total_loyer; frm=0 000,00] &euro;</td>
		
		[onshow; block=begin; when [autre.nature_financement]=='INTERNE']
		<td align="right">[autre.total_facture; frm=0 000,00] &euro;</td>
		[onshow;block=end]
	</tr>
	
</table>
