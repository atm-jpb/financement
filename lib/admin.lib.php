<?php

/**
 *  \brief      	Define head array for tabs of ndfp setup pages
 *  \return			Array of head
 */
function financement_admin_prepare_head()
{
	global $langs, $conf, $user;
	$h = 0;
	$head = array();
	
	$head[$h][0] = dol_buildpath('/financement/admin/config.php', 1);
	$head[$h][1] = $langs->trans("GlobalAdmin");
	$head[$h][2] = 'config';
	$h++;
	
	$head[$h][0] = dol_buildpath('/financement/admin/grille.php', 1);
	$head[$h][1] = $langs->trans("Grille");
	$head[$h][2] = 'grille';
	$h++;
	
	$head[$h][0] = dol_buildpath('/financement/admin/other.php', 1);
	$head[$h][1] = $langs->trans("Other");
	$head[$h][2] = 'other';
	$h++;

    return $head;
}

?>