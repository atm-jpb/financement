$(document).ready(function() {
	$('#nouvel_engagement_noir, #nouvel_engagement_couleur').on('input', function() {
		var type = $(this).attr('engagement_type');
		$.ajax({
			type: 'post',
			url: './ajax/avenant_integral.php',
			data: {
				'action': 'change_engagement',
				'id_integrale': $('#formAvenantIntegrale > #id_integrale').val(),
				'type': type,
				'engagement': $(this).val()
			},
			dataType: 'json',
			success: function(data) {
				$('input[name="nouveau_cout_unitaire_'+type+'"]').val(data.cout_unitaire);
				$('input[name="nouveau_cout_unit_'+type+'_tech"]').val(data.nouveau_cout_unitaire_tech);
				$('input[name="nouveau_cout_unit_'+type+'_mach"]').val(data.nouveau_cout_unitaire_mach);
				$('input[name="nouveau_cout_unit_'+type+'_loyer"]').val(data.nouveau_cout_unitaire_loyer);
				update_percent_couleur();
			}
		});
	});
	
	$('#cursor').slider({
		range: "max",
		min: 0,
		max: 100,
		value: $('#nouvelle_repartition_couleur').val(),
		change: function( event, ui ) {
			$('#nouvelle_repartition_couleur').val( ui.value );
			$('#nouvelle_repartition_noir').val( 100 - ui.value );
			update_cout_loyer(ui.value);
		}
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

function update_cout_loyer(couleur_percent) {
	$.ajax({
		type: 'post',
		url: './ajax/avenant_integral.php',
		data: {
			'action': 'change_couleur_percent',
			'percent': couleur_percent,
			'id_integrale': $('#formAvenantIntegrale > #id_integrale').val(),
			'cout_unit_noir': $('input[name="nouveau_cout_unitaire_noir"]').val(),
			'cout_unit_coul': $('input[name="nouveau_cout_unitaire_couleur"]').val(),
			'engagement_noir': $('input[name="nouvel_engagement_noir"]').val(),
			'engagement_coul': $('input[name="nouvel_engagement_couleur"]').val()
		},
		dataType: 'json',
		success: function(data) {
			$('input[name="nouveau_cout_unitaire_noir"]').val(data.couts_noir.cout_unitaire);
			$('input[name="nouveau_cout_unitaire_couleur"]').val(data.couts_coul.cout_unitaire);
			$('input[name="nouveau_cout_unit_noir_tech"]').val(data.couts_noir.nouveau_cout_unitaire_tech);
			$('input[name="nouveau_cout_unit_coul_tech"]').val(data.couts_coul.nouveau_cout_unitaire_tech);
			$('input[name="nouveau_cout_unit_noir_mach"]').val(data.couts_noir.nouveau_cout_unitaire_mach);
			$('input[name="nouveau_cout_unit_coul_mach"]').val(data.couts_coul.nouveau_cout_unitaire_mach);
			$('input[name="nouveau_cout_unit_noir_loyer"]').val(data.couts_noir.nouveau_cout_unitaire_loyer);
			$('input[name="nouveau_cout_unit_coul_loyer"]').val(data.couts_coul.nouveau_cout_unitaire_loyer);
		}
	});
}

