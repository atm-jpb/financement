<table id="[liste.id]" class="liste" width="100%">
	<tr class="liste_titre">
		<td class="liste_titre">[entete.val;block=td]</td>
		<td align="center" class="liste_titre">&nbsp;</td>
	</tr>
	<tr class="impair">
		<!-- [champs.$;block=tr;sub1] -->
		<td>[champs_sub1.val;block=td; strconv=no]</td>
		<td align="center"><input type="checkbox" name="doss_[champs_sub1.rowid]" montant="[champs_sub1.montant]" /></td>
	</tr>
	<tr class="pair">
		<!-- [champs.$;block=tr;sub1] -->
		<td>[champs_sub1.val;block=td; strconv=no]</td>
		<td align="center"><input type="checkbox" name="doss_[champs_sub1.rowid]" montant="[champs_sub1.montant]" /></td>
	</tr>
</table>
<p align="center">
	[liste.messageNothing] [onshow; block=p; strconv=no; when [liste.totalNB]==0]
</p>
	