<?php
/*
 * Ce script permet de supprimer les doublons engendrés par la MEP des dossiers rachetés
 */
require_once '../config.php';
dol_include_once('/financement/class/dossierRachete.class.php');

$limit = GETPOST('limit', 'int');
$force_rollback = GETPOST('force_rollback', 'int');

$sql = 'SELECT GROUP_CONCAT(rowid) as TRowid';
$sql.= ' FROM '.MAIN_DB_PREFIX.DossierRachete::$tablename;
$sql.= ' GROUP BY date_cre, date_maj,fk_dossier, fk_leaser, fk_simulation, ref_simulation, num_contrat, num_contrat_leaser, retrait_copie_sup, decompte_copies_sup,';
$sql.= ' date_debut_periode_leaser, date_fin_periode_leaser, solde_banque_a_periode_identique, type_contrat, duree, echeance, loyer_actualise, date_debut, date_fin,';
$sql.= ' date_prochaine_echeance, numero_prochaine_echeance, terme, reloc, maintenance, assurance, assurance_actualise, montant, date_debut_periode_client_m1,';
$sql.= ' date_fin_periode_client_m1, solde_vendeur_m1, solde_banque_m1, solde_banque_nr_m1, date_debut_periode_client, date_fin_periode_client, solde_vendeur, solde_banque,';
$sql.= ' solde_banque_nr, date_debut_periode_client_p1, date_fin_periode_client_p1, solde_vendeur_p1, solde_banque_p1, solde_banque_nr_p1, choice, type_solde';
$sql.= ' HAVING count(*) > 1';
if(! empty($limit)) $sql.= ' LIMIT '.$limit;

$resql = $db->query($sql);
if(! $resql) {
	dol_print_error($db);
	exit;
}

$nb_commit = $nb_rollback = 0;

while($obj = $db->fetch_object($resql)) {
	$TRowid = explode(',', $obj->TRowid);
    $error = 0;

    $db->begin();
    
    // On laisse volontairement le premier enregistrement
    for($i = 1 ; $i < count($TRowid) ; $i++) {
        $sql_delete = 'DELETE FROM '.MAIN_DB_PREFIX.DossierRachete::$tablename.' WHERE rowid = '.$TRowid[$i].';';
        $res_delete = $db->query($sql_delete);
        if(! $res_delete || ! empty($force_rollback)) $error++;
    }

    if(empty($error)) {
        $db->commit();
        $nb_commit++;
    }
    else {
        $db->rollback();
        $nb_rollback++;
    }
}
$db->free($resql);
?>
<span>Nb Commit : <?php echo $nb_commit; ?></span><br />
<span>Nb Rollback : <?php echo $nb_rollback; ?></span>
