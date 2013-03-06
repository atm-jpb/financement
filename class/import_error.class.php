<?php

class TImportError extends TObjetStd {
	function __construct() {
		parent::set_table(MAIN_DB_PREFIX.'fin_import_error');
		parent::add_champs('fk_import','type=entier;');
		parent::add_champs('num_line','type=entier;');
		parent::add_champs('content_line,error_msg,error_data,sql_executed,type_erreur,sql_errno,sql_error','type=chaine;');
		parent::start();
		parent::_init_vars();
	}
}

?>
