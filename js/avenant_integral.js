$(document).ready(function() {
	$('#nouvel_engagement_noir, #nouvel_engagement_couleur').on('input', function() {
		var type = $(this).attr('engagement_type');
		update_cout_loyer('change_engagement',type);
	});
	
	$('#cursor').slider({
		range: "max",
		min: 0,
		max: 100,
		value: $('#nouvelle_repartition_couleur').val(),
		change: function( event, ui ) {
			$('#nouvelle_repartition_couleur').val( ui.value );
			$('#nouvelle_repartition_noir').val( 100 - ui.value );
			update_cout_loyer('change_couleur_percent');
		}
	});
	
	$('#nouveau_fas').on('blur', function() {
		update_cout_loyer('change_fas');
	});
});

function update_percent_couleur() {
	$.ajax({
		type: 'post',
		url: './ajax/avenant_integral.php',
		data: {
			'action': 'get_percent_couleur',
			'cout_unit_noir_loyer': $('input[name="nouveau_cout_unit_noir_loyer"]').val(),
			'cout_unit_coul_loyer': $('input[name="nouveau_cout_unit_coul_loyer"]').val(),
			'engagement_noir': $('input[name="nouvel_engagement_noir"]').val(),
			'engagement_coul': $('input[name="nouvel_engagement_couleur"]').val()
		},
		dataType: 'json',
		success: function(data) {
			/*$('#cursor').slider({
				value: data.percent
			});*/
			$('#nouvelle_repartition_couleur').val( data.percent );
			$('#nouvelle_repartition_noir').val( 100 - data.percent );
		}
	});
}

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
		}
	});
}