<?php

/*$res=@include("../main.inc.php");					// For root directory
if (! $res) $res=@include("../../main.inc.php");	// For "custom" directory

dol_include_once("/report/class/report.class.php");
dol_include_once("/report/class/html.form.report.class.php");

$langs->load('report@report');

if (!$user->rights->report->read)
{
    accessforbidden();
}*/


// A REVOIR. TEST DE RAPPORT SPECIFIQUE BASE SUR L'OBJET REPORT
// PBLM AVEC LES COLONNE TRIABLES QUI NE SONT PAS DANS LE SELECT (CALCULEE)


// Ventes
$report->request = "SELECT [FIELDS]
						FROM `llx_product` p
						LEFT JOIN `llx_facturedet` fd ON p.rowid = fd.fk_product
						LEFT JOIN `llx_facture` f ON fd.fk_facture = f.rowid
						LEFT JOIN `llx_categorie_societe` cs ON f.fk_soc = cs.fk_societe
						LEFT JOIN `llx_product_fournisseur_price` pfp ON p.rowid = pfp.fk_product
						WHERE p.ref IS NOT NULL
						[WHERE]
						GROUP BY p.rowid, p.ref, p.label
						[ORDER]
						[LIMIT]";
$report->fields = "p.ref::ProductRef::text|p.label::ProductLabel::text|SUM(fd.qty)::SoldQuantity::float|";
$report->fields.= "SUM(fd.total_ht)::Revenue::float|MAX(f.datef)::LastInvoice::date|";
$report->fields.= "pfp.unitprice::PA::float|::PVM::float|::MM::float|::TauxMarge::float";
$report->order = "SoldQuantity::DESC";

if($sortfield == 'PVM' || $sortfield == 'MM' || $sortfield == 'TauxMarge') {
	$tabSort = $sortfield;
	$sortfield = '';
	$sortorder = '';
}

$fieldTab = array('headers' => array(), 'select' => array(), 'type' => array());
$sql = $reportForm->build_request($report, $selectedFilters, $fieldTab, $sortfield, $sortorder, $limit);
echo $sql;
$res = $db->query($sql);

$resultVentes = array();
while ($obj = $db->fetch_object($resql)) {
	$obj->PVM = $obj->Revenue / $obj->SoldQuantity;
	$obj->MM = $obj->PVM - $obj->PA;
	$obj->TauxMarge = ($obj->PA > 0) ? ($obj->MM / $obj->PA) * 100 : 100;
	$resultVentes[] = $obj;
}

if(!(empty($tabSort))) {
	
}

// TODO : En attente de l'utilisation des factures fournisseurs pour réaliser le comparatif ventes / achats
// Achats
/*$report->request = "SELECT [FIELDS]
						FROM `llx_product` p
						LEFT JOIN `llx_facture_fourn_det` fd ON p.rowid = fd.fk_product
						LEFT JOIN `llx_facture_fourn` f ON fd.fk_facture_fourn = f.rowid
						LEFT JOIN `llx_categorie_societe` cs ON f.fk_soc = cs.fk_societe
						WHERE p.ref IS NOT NULL
						[WHERE]
						GROUP BY p.rowid, p.ref, p.label
						[ORDER]
						[LIMIT]";
$report->fields = "p.ref::ProductRef::text|p.label::ProductLabel::text|SUM(fd.qty)::SoldQuantity::float|
					SUM(fd.total_ht)::Revenue::float|MAX(f.datef)::LastInvoice::date";

$fieldTab = array('headers' => array(), 'select' => array(), 'type' => array());
$sql = $reportForm->build_request($report, $selectedFilters, $fieldTab, $limit);

$res = $db->query($sql);

$resultAchats = array();
while ($obj = $db->fetch_object($resql)) {
	$resultAchats[] = $obj;
}*/

// Résultats
/*echo '<pre>';
print_r($resultVentes);
echo '</pre>';*/

$resultReport = $resultVentes;
/*$resultReport = array();
while ($obj = $db->fetch_object($resql)) {
	$resultReport[] = $obj;
}*/
?>