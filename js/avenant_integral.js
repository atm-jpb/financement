$(document).ready(function() {
	// Pas de submit sur Entrée
	$('#formAvenantIntegrale').on('keypress', function(e) {
		if ( e.which == 13 ) return false;
	});
	
	$('#nouvel_engagement_noir, #nouvel_engagement_couleur').on('input', function() {
		var type = $(this).attr('engagement_type');
		update_cout_loyer('change_engagement',type);
	});
	
	if($('#nouvelle_repartition_couleur').val() > 0) {
		$('#cursor').slider({
			range: "max",
			min: 15,
			max: 85,
			value: $('#nouvelle_repartition_couleur').val(),
			change: function( event, ui ) {
				$('#nouvelle_repartition_couleur').val( ui.value );
				$('#repartition_coul').html( ui.value );
				update_cout_loyer('change_couleur_percent');
			}
	});
	}
	
	$('#nouveau_fas').on('change', function() {
		var val = parseFloat($(this).val());
		var max = parseFloat($(this).attr('max'));
		var min = parseFloat($(this).attr('min'));
		if(val >= min && val <= max) {
			update_cout_loyer('change_fas');
		} else if(val < min) {
			$(this).val(min).change();
		} else {
			$(this).val(max).change();
		}
	});
	
	update_cout_loyer('change_fas');
});

function update_cout_loyer(action,type) {
	$.ajax({
		type: 'post',
		url: './ajax/avenant_integral.php',
		data: {
			'action': action,
			'type' : type,
			'percent': $('#nouvelle_repartition_couleur').val(),
			'fas': $('#nouveau_fas').val(),
			'id_integrale': $('#formAvenantIntegrale > #id_integrale').val(),
			'cout_unit_noir': $('input[name="nouveau_cout_unitaire_noir"]').val(),
			'cout_unit_coul': $('input[name="nouveau_cout_unitaire_coul"]').val(),
			'engagement_noir': $('input[name="nouvel_engagement_noir"]').val(),
			'engagement_coul': $('input[name="nouvel_engagement_couleur"]').val()
		},
		dataType: 'json',
		success: function(data) {
			for (i in data.couts_noir) data.couts_noir[i] = _format_float(data.couts_noir[i],5);
			for (i in data.couts_coul) data.couts_coul[i] = _format_float(data.couts_coul[i],5);
			for (i in data) data[i] = _format_float(data[i],2);
			data.couts_noir.nouveau_cout_total = _format_float(data.couts_noir.nouveau_cout_total,2);
			data.couts_coul.nouveau_cout_total = _format_float(data.couts_coul.nouveau_cout_total,2);
			$('input[name="nouveau_cout_unitaire_noir"]').val(data.couts_noir.cout_unitaire);
			$('input[name="nouveau_cout_unitaire_coul"]').val(data.couts_coul.cout_unitaire);
			$('input[name="nouveau_cout_unit_noir_tech"]').val(data.couts_noir.nouveau_cout_unitaire_tech);
			$('input[name="nouveau_cout_unit_coul_tech"]').val(data.couts_coul.nouveau_cout_unitaire_tech);
			$('input[name="nouveau_cout_unit_noir_mach"]').val(data.couts_noir.nouveau_cout_unitaire_mach);
			$('input[name="nouveau_cout_unit_coul_mach"]').val(data.couts_coul.nouveau_cout_unitaire_mach);
			$('input[name="nouveau_cout_unit_noir_loyer"]').val(data.couts_noir.nouveau_cout_unitaire_loyer);
			$('input[name="nouveau_cout_unit_coul_loyer"]').val(data.couts_coul.nouveau_cout_unitaire_loyer);
			$('input[name="montant_total_noir"]').val(data.couts_noir.nouveau_cout_total);
			$('input[name="montant_total_coul"]').val(data.couts_coul.nouveau_cout_total);
			$('input[name="total_global"]').val(data.total_global);
			$('input[name="total_hors_frais"]').val(data.total_hors_frais);
			$('#nouveau_fas').attr('max', data.fas_max);
			
			$('input[name="fass"]').val(_format_float($('input[name="fass"]').val(),2));
			$('input[name="ftc"]').val(_format_float($('input[name="ftc"]').val(),2));
		}
	});
}

function _format_float(val,dec) {
	if(isNaN(val)) return val;
	return parseFloat(val).toFixed(dec);
}
