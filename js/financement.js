$(document).ready(function() {
	$('select[name="opt_periodicite"]').bind('change', get_periode);
	$('select[name="fk_type_contrat"]').bind('change', get_grille);
	$('input[name^="opt_"]').bind('click', get_grille);
	$('select[name^="opt_"]').bind('change', get_grille);

	if($('select[name="fk_type_contrat"]').length > 0) {
		get_grille();
	}
});

var get_grille = function() {
	var fin_options = new Array();
	var fin_options = $('input[name^="opt_"]:checked, select[name^="opt_"]').map(function() {
		if($(this).val() == 1) return $(this).attr('name');
		return $(this).val();
	}).get();
	
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

var calcul_financement = function() {
	var fin_options = new Array();
	var fin_options = $('input[name^="opt_"]:checked, select[name^="opt_"]').map(function(){
		return $(this).val();
	}).get();
	
	var data = {
		mode : 'calcul',
		outjson : 1,
		idLeaser : $('input[name="idLeaser"]').val(),
		fk_type_contrat : $('select[name="fk_type_contrat"]').val(),
		opt_periodicite : $('select[name="opt_periodicite"]').val(),
		options : fin_options,
		montant : $('input[name="montant"]').val(),
		duree : $('select[name="duree"]').val(),
		echeance : $('input[name="echeance"]').val(),
		vr : $('input[name="vr"]').val()
	};
	
	if(data.idTypeContrat != '') {
		$.post(
			'ajaxsimulateur.php',
			data,
			function(res) {
				alert(res);
			},
			'json'
		);
	}
};
