$(document).ready(function() {
	$('select[name="opt_periodicite"]').bind('change', get_periode);
	$('select[name="type_contrat"]').bind('change', get_grille);
	$('input[name^="opt_"]').bind('click', get_grille);
	$('select[name^="opt_"]').bind('change', get_grille);
	
	get_grille();
});

var get_grille = function() {
	var fin_options = new Array();
	var fin_options = $('input[name^="opt_"]checked, select[name^="opt_"]').map(function(){
		return $(this).val();
	}).get();
	
	var data = {
		mode : 'grille',
		outjson : 1,
		idLeaser : 2,
		idTypeContrat : $('select[name="type_contrat"]').val(),
		periodicite : $('select[name="opt_periodicite"]').val(),
		options : fin_options
	};
	
	if(data.idTypeContrat != '') {
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
		periodicite : $('select[name="opt_periodicite"]').val()
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
