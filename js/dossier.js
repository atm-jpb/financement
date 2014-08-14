var delete_elem = function(id_elem, type) {
	if(type == 'dossier') {
		var q = 'Êtes-vous sur de vouloir supprimer ce dossier ?';
	} else if(type == 'affaire') {
		var q = 'Êtes-vous sur de vouloir supprimer cette affaire ?';
	} else if(type == 'simulation') {
		var q = 'Êtes-vous sur de vouloir supprimer cette simulation ?';
	}
	if(confirm(q)) {
		document.location.href='?action=delete&id='+id_elem;
	}
};