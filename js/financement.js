$(document).ready(function() {
	$('select[name="opt_periodicite"]').bind('change', get_periode);
	$('select[name="fk_type_contrat"]').bind('change', get_grille);
	$('input[name^="opt_"]').bind('click', get_grille);
	$('select[name^="opt_"]').bind('change', get_grille);

	if($('select[name="fk_type_contrat"]').length > 0) {
		get_grille();
	}
	
	//$('input[name^="dossiers_rachetes"]').bind('click', calcul_montant_rachat);
	//$('input[name^="dossiers_rachetes"]').bind('click', prevent_dbl_select);
	//$('select[name="fk_type_contrat"]').bind('change', calcul_montant_rachat);
	$('input[type="checkbox"]').parent('td.solde').bind('click', select_solde).css('cursor', 'pointer');;
	init_selected_dossier();
});

var get_grille = function() {
	var fin_options = {
		'opt_periodicite' : $('select[name="opt_periodicite"]').val()
		,'opt_mode_reglement' : $('select[name="opt_mode_reglement"]').val()
		,'opt_terme' : $('select[name="opt_terme"]').val()
		,'opt_administration' : $('input[name="opt_administration"]:checked').length > 0 ? $('input[name="opt_administration"]:checked').val() : 0
		,'opt_creditbail' : $('input[name="opt_creditbail"]:checked').length > 0 ? $('input[name="opt_creditbail"]:checked').val() : 0
		,'opt_calage' : $('select[name="opt_calage"]').val()
	};
	
	var data = {
		mode : 'grille',
		outjson : 1,
		idLeaser : $('input[name="idLeaser"]').val(),
		fk_type_contrat : $('select[name="fk_type_contrat"]').val(),
		opt_periodicite : $('select[name="opt_periodicite"]').val(),
		options : fin_options
	};
	
	if(data.fk_type_contrat != 0) {
		$.post(
			'ajaxsimulateur.php',
			data,
			function(resHTML) {
				$('#grille').remove();
				var grille = $('<div />').append(resHTML);
				grille.attr('id', 'grille');
				$('div.fiche').append(grille);
			},
			'json'
		);
	}
};

var get_periode = function() {
	var data = {
		mode : 'duree',
		outjson : 1,
		opt_periodicite : $('select[name="opt_periodicite"]').val()
	};
	$.post(
		'ajaxsimulateur.php',
		data,
		function(resHTML) {
			$('select[name="duree"]').replaceWith(resHTML);
			get_grille();
		},
		'json'
	);
};

var calcul_montant_rachat = function() {
	var montant_rachat = 0;
	var type_contrat = $('select[name="fk_type_contrat"]').val();
	var type_solde = 'solde_nr';
	$('input[name^="dossiers_rachetes"]:checked').each(function() {
		//type_solde = ($(this).attr('contrat') == type_contrat) ? 'solde_r' : 'solde_nr';
		//montant_rachat += parseFloat($(this).attr(type_solde));
		montant_rachat += parseFloat($(this).attr('solde'));
	});
	
	montant_rachat = Math.round(montant_rachat*100)/100;
	$('input[name="montant_rachete"]').val(montant_rachat);
};

var prevent_dbl_select = function() {
	var val = $(this).val();
	if($(this).attr('checked') == 'checked') {
		$('input[name^="dossiers_rachetes"][name$="\\['+val+'\\]"]').attr('disabled', true);
		$(this).attr('disabled', false);
	} else {
		$('input[name^="dossiers_rachetes"][name$="\\['+val+'\\]"]').attr('disabled', false);
	}
	/*if($('#dossiers_rachetes\\['+val+'\\]:checked').length > 0) {
		$('#dossiers_rachetes_p1\\['+val+'\\]').attr('disabled', true);
	} else {
		$('#dossiers_rachetes_p1\\['+val+'\\]').attr('disabled', false);
	}
	if($('#dossiers_rachetes_p1\\['+val+'\\]:checked').length > 0) {
		$('#dossiers_rachetes\\['+val+'\\]').attr('disabled', true);
	} else {
		$('#dossiers_rachetes\\['+val+'\\]').attr('disabled', false);
	}*/
};

var select_solde = function() {
	var cb = $(this).find('input[type="checkbox"]');
	if(cb.attr('checked') == 'checked') {
		cb.attr('checked', false);
		$(this).css('background-color', '');
	} else {
		$(this).parent('tr').find('input[type="checkbox"]').attr('checked', false);
		$(this).parent('tr').find('td.solde').css('background-color', '');
		cb.attr('checked', true);
		$(this).css('background-color', '#00FF00');
	}
	
	calcul_montant_rachat();
};

var init_selected_dossier = function() {
	$('input[type="checkbox"]:checked').parent('td').css('background-color', '#00FF00');
};
