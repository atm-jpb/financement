$(document).ready(function() {
	$('select[name="type_contrat"]').bind('change', function() {
		$.getJSON(
			'ajaxgrille.php',
			{ idTypeContrat : $(this).val(), idSoc : 2, outjson : 1 },
			function(resHTML) {
				if(resHTML != 'KO') {
					$('div.fiche').append(resHTML);
				}
			}
		);
	});
});