<?php
/* Copyright (C) 2012-2013 Maxime Kohlhaas      <maxime@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   financement     Module Financement
 *  \brief      Module financement pour C'PRO
 *  \file       /financement/core/modules/modFinancement.class.php
 *  \ingroup    Financement
 *  \brief      Description and activation file for module financement
 */

require('../config.php');
dol_include_once('/financement/lib/admin.lib.php');

if (!$user->rights->financement->admin->write) accessforbidden();

llxHeader('',$langs->trans("FinancementSetup"));
print_fiche_titre($langs->trans("FinancementSetup"),'','setup32@financement');
$head = financement_admin_prepare_head(null);

dol_fiche_head($head, 'config', $langs->trans("Financement"), 0, 'financementico@financement');
dol_htmloutput_mesg($mesg);

dol_fiche_end();

$db->close();

llxFooter();