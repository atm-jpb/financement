<table class="border" align="center" width="50%">
	
	<tr class="liste_titre">
		<th class="liste_titre" align="center" width="33%"></th>
		<th class="liste_titre" align="center">Noir</th>
		<th class="liste_titre" align="center">Couleur</th>
	</tr>
	
	<tr class="impair" align="center">
		<td>Engagé (qté)</td>
		<td>[noir.engage]</td>
		<td>[couleur.engage]</td>
	</tr>
	
	<tr class="pair" align="center">
		<td>Coût unitaire</td>
		<td>[noir.cout_unitaire]</td>
		<td>[couleur.cout_unitaire]</td>
	</tr>
	
	<tr class="impair showOnlyAdmin" align="center" style="display:none;">
		<td>- Tech</td>
		<td>[noir.cout_unit_tech]</td>
		<td>[couleur.cout_unit_tech]</td>
	</tr>
	
	<tr class="impair showOnlyAdmin" align="center" style="display:none;">
		<td>- Mach</td>
		<td>[noir.cout_unit_mach]</td>
		<td>[couleur.cout_unit_mach]</td>
	</tr>
	
	<tr class="impair showOnlyAdmin" align="center" style="display:none;">
		<td>- Loyer</td>
		<td>[noir.cout_unit_loyer]</td>
		<td>[couleur.cout_unit_loyer]</td>
	</tr>
	
	<tr class="pair" align="center">
		<td>Nouvel engagement (qté)</td>
		<td>[noir.nouvel_engagement;strconv=no]</td>
		<td>[couleur.nouvel_engagement;strconv=no]</td>
	</tr>
	
	<tr class="impair" align="center">
		<td>Coût unitaire</td>
		<td>[noir.nouveau_cout_unitaire;strconv=no]</td>
		<td>[couleur.nouveau_cout_unitaire;strconv=no]</td>
	</tr>
	
	<tr class="pair showOnlyAdmin" align="center" style="display:none;">
		<td>- Tech</td>
		<td>[noir.nouveau_cout_unit_tech;strconv=no]</td>
		<td>[couleur.nouveau_cout_unit_tech;strconv=no]</td>
	</tr>
	
	<tr class="pair showOnlyAdmin" align="center" style="display:none;">
		<td>- Mach</td>
		<td>[noir.nouveau_cout_unit_mach;strconv=no]</td>
		<td>[couleur.nouveau_cout_unit_mach;strconv=no]</td>
	</tr>
	
	<tr class="pair showOnlyAdmin" align="center" style="display:none;">
		<td>- Loyer</td>
		<td>[noir.nouveau_cout_unit_loyer;strconv=no]</td>
		<td>[couleur.nouveau_cout_unit_loyer;strconv=no]</td>
	</tr>
	
	<tr class="pair" align="center">
		<td>Total (Nouvel engagement * P.U)</td>
		<td>[noir.montant_total;strconv=no]</td>
		<td>[couleur.montant_total;strconv=no]</td>
	</tr>
	
	<tr class="impair" align="center">
		<td>% couleur</td>
		<td colspan="2" style="padding: 10px;">
			<span id="repartition_coul">[couleur.repartition;strconv=no]</span> %
			<div id="cursor"></div>
			<div style="display:none;">[couleur.repartition_input;strconv=no]</div>
		</td>
	</tr>
	
	<tr class="pair" align="center">
		<td>FAS</td>
		<td colspan="2">[global.FAS;strconv=no]</td>
	</tr>
	
	<tr class="impair" align="center">
		<td>FASS</td>
		<td colspan="2">[global.FASS;strconv=no]</td>
	</tr>
	
	<tr class="pair" align="center">
		<td>Total hors frais</td>
		<td colspan="2">[global.total_hors_frais;strconv=no]</td>
	</tr>
	
	<tr class="impair" align="center">
		<td>Frais bris machine</td>
		<td colspan="2">[global.frais_bris_machine;strconv=no]</td>
	</tr>
	
	<tr class="pair" align="center">
		<td>FTC</td>
		<td colspan="2">[global.frais_facturation;strconv=no]</td>
	</tr>
	
	<tr class="impair" align="center">
		<td>Total</td>
		<td colspan="2">[global.total_global;strconv=no]</td>
	</tr>
	
</table>

[onshow;block=begin;when [rights.voir_couts_unitaires]=='1']
	
	<script type="text/javascript">
		
		$(document).ready(function() {
			$(".showOnlyAdmin").show();
		});
		
	</script>
	
[onshow;block=end]

<script type="text/javascript">
	
	$(document).ready(function() {
		
		var rep_left = $("#nouvelle_repartition_noir");
		var rep_right = $("#nouvelle_repartition_couleur");
		
		rep_left.change(function() {
			if(!$.isNumeric(rep_left.val())) {
				rep_left.val(50);
			} else if(rep_left.val() > 85){
				rep_left.val(85);
			} else if(rep_left.val() < 15) {
				rep_left.val(15);
			}
			rep_right.val(100 - rep_left.val());
		});
		rep_right.change(function() {
			if(!$.isNumeric(rep_right.val())) {
				rep_right.val(50);
			} else if(rep_right.val() > 85){
				rep_right.val(85);
			} else if(rep_right.val() < 15) {
				rep_right.val(15);
			}
			rep_left.val(100 - rep_right.val());
		});
		
	});
</script>