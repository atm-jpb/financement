$(document).ready(function() {
	$('select[name="periodicite"]').bind('change', get_periode);
	$('select[name="type_contrat"]').bind('change', get_grille);
	$('input[name^="opt_"]').bind('click', get_grille);
});

var get_grille = function() {
	var fin_options = new Array();
	if($('input[name="opt_administration"]:checked').length > 0) fin_options.push('opt_administration');
	if($('input[name="opt_creditbail"]:checked').length > 0) fin_options.push('opt_creditbail');
	if($('input[name="opt_terme_echu"]:checked').length > 0) fin_options.push('opt_terme_echu');
	if($('select[name="periodicite"]').val() == 'M') fin_options.push('opt_mensuel');
	
	var data = {
		mode : 'grille',
		outjson : 1,
		idSoc : 2,
		idTypeContrat : $('select[name="type_contrat"]').val(),
		periodicite : $('select[name="periodicite"]').val(),
		options : fin_options
	};
	$.post(
		'ajaxgrille.php',
		data,
		function(resHTML) {
			$('#grille').remove();
			var grille = $('<div />').append(resHTML);
			grille.attr('id', 'grille');
			$('div.fiche').append(grille);
		},
		'json'
	);
};

var get_periode = function() {
	var data = {
		mode : 'duree',
		outjson : 1,
		periodicite : $('select[name="periodicite"]').val()
	};
	$.post(
		'ajaxgrille.php',
		data,
		function(resHTML) {
			$('select[name="duree"]').replaceWith(resHTML);
			get_grille();
		},
		'json'
	);
};
