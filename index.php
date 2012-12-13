<?php
/* Copyright (C) 2012      Maxime Kohlhaas        <maxime@atm-consulting.fr>
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
 *	\file       financement/index.php
 *	\ingroup    financement
 *	\brief      Home page of financement module
 */


$res=@include("../main.inc.php");					// For root directory
if (! $res) $res=@include("../../main.inc.php");	// For "custom" directory
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formother.class.php");

if (!($user->rights->financement->allsimul->calcul || $user->rights->financement->allsimul->simul ||
		$user->rights->financement->allsimul->simul_list || $user->rights->financement->mysimul->simul_list))
{
	accessforbidden();
}

$langs->load('financement@financement');

$search_ref=GETPOST('search_ref','alpha');
$search_client=GETPOST('search_client','alpha');
$search_leaser=GETPOST('search_leaser','alpha');
$year=GETPOST("year");
$month=GETPOST("month");
$search_montant_ht=GETPOST('search_montant_ht','alpha');

llxHeader();

$form = new Form($db);
$formother = new FormOther($db);

// Récupération de la liste des derniers dossiers
$now=dol_now();

$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if (! $sortfield) $sortfield='d.datedeb';
if (! $sortorder) $sortorder='DESC';
$limit = $conf->liste_limit;

$sql = "SELECT s.nom as nom_client, s.rowid as id_client, s2.nom as nom_leaser, s2.rowid as id_leaser, ";
$sql.= "d.rowid as dossierid, d.montant, d.num_contrat, d.fk_statut, d.fk_user_author, d.datedeb as ddeb, d.datefin as dfin,";
if (! $user->rights->societe->client->voir && ! $socid) $sql .= " sc.fk_soc, sc.fk_user,";
$sql.= " u.login";
$sql.= " FROM ".MAIN_DB_PREFIX."societe s, ".MAIN_DB_PREFIX."societe s2, ".MAIN_DB_PREFIX."fin_dossier d";
if (! $user->rights->societe->client->voir && ! $socid) $sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux sc";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON d.fk_user_author = u.rowid";
$sql.= " WHERE d.fk_soc_client = s.rowid";
$sql.= " AND d.fk_soc_leaser = s2.rowid";
$sql.= " AND d.entity = ".$conf->entity;

if (! $user->rights->societe->client->voir && ! $socid) //restriction
{
	$sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
}
if ($search_ref)
{
	$sql.= " AND d.ref LIKE '%".$db->escape(trim($search_ref))."%'";
}
if ($search_societe)
{
	$sql.= " AND s.nom LIKE '%".$db->escape(trim($search_societe))."%'";
}
if ($search_leaser)
{
	$sql.= " AND s2.nom LIKE '%".$db->escape(trim($search_leaser))."%'";
}
if ($search_montant_ht)
{
	$sql.= " AND d.montant='".$db->escape(trim($search_montant_ht))."'";
}
if ($socid) $sql.= ' AND s.rowid = '.$socid;
if ($month > 0)
{
	if ($year > 0)
	$sql.= " AND date_format(d.datedeb, '%Y-%m') = '".$year."-".$month."'";
	else
	$sql.= " AND date_format(d.datedeb, '%m') = '".$month."'";
}
if ($year > 0)
{
	$sql.= " AND date_format(d.datedeb, '%Y') = '".$year."'";
}

$sql.= ' ORDER BY '.$sortfield.' '.$sortorder.', d.num_contrat DESC';
$sql.= $db->plimit($limit + 1,$offset);
$result=$db->query($sql);

if ($result)
{
	//$objectstatic=new DossierFinancement($db);
	$userstatic=new User($db);
	$societestatic=new Societe($db);

	$num = $db->num_rows($result);

 	if ($socid)
	{
		$soc = new Societe($db);
		$soc->fetch($socid);
	}

	$param='&amp;socid='.$socid;
	if ($month) $param.='&amp;month='.$month;
	if ($year) $param.='&amp;year='.$year;
	print_barre_liste($langs->trans('ListOfDossierFinancement').' '.($socid?'- '.$soc->nom:''), $page,'index.php',$param,$sortfield,$sortorder,'',$num,0,'financement32.png@financement');

	$i = 0;
	print '<table class="liste" width="100%">';
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans('Ref'),$_SERVER["PHP_SELF"],'d.ref','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Customer'),$_SERVER["PHP_SELF"],'s.nom','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Leaser'),$_SERVER["PHP_SELF"],'s2.nom','',$param,'',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('DateStart'),$_SERVER["PHP_SELF"],'d.datedeb','',$param, 'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('DateEnd'),$_SERVER["PHP_SELF"],'d.datefin','',$param, 'align="center"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Amount'),$_SERVER["PHP_SELF"],'d.montant','',$param, 'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Author'),$_SERVER["PHP_SELF"],'u.login','',$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($langs->trans('Status'),$_SERVER["PHP_SELF"],'d.fk_statut','',$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre('');
	print "</tr>\n";
	// Lignes des champs de filtre
	print '<form method="get" action="'.$_SERVER["PHP_SELF"].'">';

	print '<tr class="liste_titre">';
	print '<td class="liste_titre">';
	print '<input class="flat" size="10" type="text" name="search_ref" value="'.$search_ref.'">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="16" name="search_client" value="'.$search_client.'">';
	print '</td>';
	print '<td class="liste_titre" align="left">';
	print '<input class="flat" type="text" size="16" name="search_leaser" value="'.$search_leaser.'">';
	print '</td>';
	print '<td class="liste_titre" colspan="1" align="center">';
	print $langs->trans('Month').': <input class="flat" type="text" size="1" maxlength="2" name="month" value="'.$month.'">';
	print '&nbsp;'.$langs->trans('Year').': ';
	$syear = $year;
	$formother->select_year($syear,'year',1, 20, 5);
	print '</td>';
	print '<td class="liste_titre" colspan="1">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
	print '<input class="flat" type="text" size="10" name="search_montant_ht" value="'.$search_montant_ht.'">';
	print '</td>';
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="right">';
	//$formpropal->select_propal_statut($viewstatut,1);
	print '</td>';
	print '<td class="liste_titre" align="right"><input class="liste_titre" type="image" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
	print '</td>';
	print "</tr>\n";
	print '</form>';

	$var=true;

	while ($i < min($num,$limit))
	{
		$obj = $db->fetch_object($result);
		$now = time();
		$var=!$var;
		print '<tr '.$bc[$var].'>';
		print '<td nowrap="nowrap">';

		$objectstatic->id=$obj->dossierid;
		$objectstatic->num_contrat=$obj->num_contrat;

		print '<table class="nobordernopadding"><tr class="nocellnopadd">';
		print '<td class="nobordernopadding" nowrap="nowrap">';
		print $objectstatic->num_contrat;
		print '</td></tr></table>';

		if ($obj->client == 1)
		{
			$url = DOL_URL_ROOT.'/comm/fiche.php?socid='.$obj->rowid;
		}
		else
		{
			$url = DOL_URL_ROOT.'/comm/prospect/fiche.php?socid='.$obj->rowid;
		}

		// Client
		$societestatic->id=$obj->id_client;
		$societestatic->nom=$obj->nom_client;
		$societestatic->client=1;
		print '<td>';
		print $societestatic->getNomUrl(1,'customer');
		print '</td>';
		
		// Leaser
		$societestatic->id=$obj->id_leaser;
		$societestatic->nom=$obj->nom_leaser;
		$societestatic->fournisseur=1;
		print '<td>';
		print $societestatic->getNomUrl(1,'customer');
		print '</td>';

		// Date propale
		print '<td align="center">';
		$y = dol_print_date($db->jdate($obj->dp),'%Y');
		$m = dol_print_date($db->jdate($obj->dp),'%m');
		$mt= dol_print_date($db->jdate($obj->dp),'%b');
		$d = dol_print_date($db->jdate($obj->dp),'%d');
		print $d."\n";
		print ' <a href="'.$_SERVER["PHP_SELF"].'?year='.$y.'&amp;month='.$m.'">';
		print $mt."</a>\n";
		print ' <a href="'.$_SERVER["PHP_SELF"].'?year='.$y.'">';
		print $y."</a></td>\n";

		// Date fin validite
		if ($obj->dfv)
		{
			print '<td align="center">'.dol_print_date($db->jdate($obj->dfv),'day');
			print '</td>';
		}
		else
		{
			print '<td>&nbsp;</td>';
		}

		print '<td align="right">'.price($obj->total_ht)."</td>\n";

		$userstatic->id=$obj->fk_user_author;
		$userstatic->login=$obj->login;
		print '<td align="center">';
		if ($userstatic->id) print $userstatic->getLoginUrl(1);
		else print '&nbsp;';
		print "</td>\n";

		print '<td align="right">'.$objectstatic->LibStatut($obj->fk_statut,5)."</td>\n";

		print '<td>&nbsp;</td>';

		print "</tr>\n";

		$total = $total + $obj->total_ht;
		$subtotal = $subtotal + $obj->total_ht;

		$i++;
	}
	print '</table>';
	$db->free($result);
}
else
{
	dol_print_error($db);
}

llxFooter('');

$db->close();

?>