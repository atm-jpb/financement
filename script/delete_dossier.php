<?php

require_once('../config.php');
dol_include_once('/financement/class/dossier.class.php');
dol_include_once('/financement/class/affaire.class.php');
dol_include_once('/financement/class/grille.class.php');

$limit = GETPOST('limit', 'int');

$sql = 'SELECT rowid, fk_fin_dossier';
$sql.= ' FROM '.MAIN_DB_PREFIX.'fin_dossier_financement';
$sql.= " WHERE reference LIKE 'DELETE_%'";
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$PDOdb = new TPDOdb;
$nb = $db->num_rows($resql);

while($obj = $db->fetch_object($resql)) {
    $dossier = new TFin_dossier;
    $dossier->load($PDOdb, $obj->fk_fin_dossier);

    $affaire = new TFin_affaire;
    $affaire->load($PDOdb, $dossier->TLien[0]->fk_fin_affaire);
    $affaire->delete($PDOdb);

    $dossier->delete($PDOdb, true, false, false);
}
$db->free($resql);
?>
<span>Nb to be deleted : <?php echo $nb; ?>
