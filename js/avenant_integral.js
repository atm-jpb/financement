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
			update_form_data(ui.value);
		}
	});
});

function update_form_data(couleur_percent) {
	$.ajax({
		type: 'post',
		url: './ajax/avenant_integral.php',
		data: {
			'action': 'change_couleur_percent',
			'couleur_percent': couleur_percent
		},
		dataType: 'json',
		success: function(data) {
			alert(data.cout_u);
			$('input[name="nouveau_cout_unitaire_noir"]').val(data.cout_u);
		}
	});
}
