[onshow;block=begin;when [view.mode]=='edit']
[score.titre; strconv=no]

	<table class="border" width="100%">
	<tr>
		<td>Score</td>
		<td>[score.score; strconv=no] / 20</td>
	</tr>

	<tr>
		<td width="15%">Encours conseillé</td>
		<td>[score.encours_conseille; strconv=no]</td>
	</tr>

	<tr>
		<td width="15%">Date du score</td>
		<td>[score.date_score; strconv=no]</td>
	</tr>
	</table>
	
	<center><br />[score.bt_save; strconv=no]&nbsp;[score.bt_cancel; strconv=no]</center>

	<br />
</form>
[onshow;block=end]